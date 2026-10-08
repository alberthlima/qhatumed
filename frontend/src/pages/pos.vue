<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import ClienteAddDialog from '@/components/system/clientes/ClienteAddDialog.vue'
import { useCashSession } from '@/composables/useCashSession'

const toast = useMaterializeToast()

// ── ESTADO DE CAJA ──
const { session: cashSession, isLoading: isLoadingSession, hasOpenSession, checkSession } = useCashSession()
const isOpenCashDialog = ref(false)
const cashRegistersList = ref([])
const isOpeningCash = ref(false)
const openCashData = ref({ cash_register_id: 1, opening_amount: 0 })

const fetchCashRegisters = async () => {
  try {
    cashRegistersList.value = await $api('/v1/cash-registers')
  } catch (e) {
    console.error('Error fetching cash registers:', e)
  }
}

const handleOpenCashFromPOS = async () => {
  if (openCashData.value.opening_amount < 0) return toast.showWarning('Monto base inválido', 'Atención')
  isOpeningCash.value = true
  try {
    await $api('/v1/cash-registers/open', {
      method: 'POST',
      body: openCashData.value
    })
    isOpenCashDialog.value = false
    toast.showSuccess('Caja aperturada con éxito', 'Éxito')
    await checkSession()
  } catch (e) {
    toast.showError(e.response?._data?.error || 'Error al abrir caja', 'Error')
  } finally {
    isOpeningCash.value = false
  }
}

const openCashDialog = async () => {
  await fetchCashRegisters()
  isOpenCashDialog.value = true
}

// ── ESTADO DEL POS ──
const cart = ref([])
const searchQuery = ref('')
const cashTendered = ref(0)
const discount = ref(0)
const saleDate = ref(new Date().toISOString().slice(0, 10))

// Datos
const products = ref([])
const customers = ref([])
const selectedCustomer = ref(null)
const isFetching = ref(false)

// Modal de Cobro / Checkout Profesional
const isCheckoutModalOpen = ref(false)

// Facturación SIAT
const isInvoice = ref(true)
const businessName = ref('CONTROL TRIBUTARIO')
const nitCi = ref('99002')
const complement = ref('')
const customerEmail = ref('')
const cardNumber = ref('')
const splitCashAmount = ref(0)
const splitCardAmount = ref(0)
const giftCardCode = ref('')
const giftCardAmount = ref(0)
const paymentMethod = ref(1) // 1: Efectivo, 2: Tarjeta, 10: Combinado, 7: QR, 27: Gift Card, 35: Pago Posterior
const documentType = ref(5)   // 5: NIT (Normado para 99002 Control Tributario)
const hasExceptionCode = ref(false) // Código de Excepción (1 = NIT no registrado/observado en padrón SIN)
const isOfflineMode = ref(false)     // Modo Contingencia Fuera de Línea (RND 102100000011)
const manualCafc = ref('')           // Código CAFC (Talonario preimpreso o contingencia)
const manualInvoiceNumber = ref('')  // N° Factura manual del talonario
const isAddClientDialog = ref(false)

// Tipos de Documento SIAT
const documentTypesList = [
  { value: 1, title: '1 - CI (Cédula de Identidad)' },
  { value: 5, title: '5 - NIT (Número de Identificación Tributaria)' },
  { value: 2, title: '2 - CEX (Cédula de Extranjero)' },
  { value: 3, title: '3 - PAS (Pasaporte)' },
  { value: 4, title: '4 - OD (Otro Documento)' }
]

// Modal de Resultado
const isResultDialog = ref(false)
const saleResult = ref(null)

// Métodos de Pago visuales (Normados SIN - Sistema Proveedor)
const paymentMethodsList = [
  { value: 1, title: 'Efectivo', icon: 'ri-money-dollar-circle-line', desc: 'Billetes / Monedas' },
  { value: 2, title: 'Tarjeta', icon: 'ri-bank-card-line', desc: 'Débito / Crédito' },
  { value: 10, title: 'Combinado', icon: 'ri-scales-3-line', desc: 'Efectivo + Tarjeta' },
  { value: 7, title: 'QR / Transfer.', icon: 'ri-qr-code-line', desc: 'Transferencia bancaria' },
  { value: 27, title: 'Gift Card', icon: 'ri-gift-line', desc: 'Tarjeta Regalo / Vale' },
  { value: 35, title: 'Pago Posterior', icon: 'ri-calendar-schedule-line', desc: 'Venta a Crédito' }
]

// Billetes rápidos para cambio en efectivo
const quickCashAmounts = [10, 20, 50, 100, 200]

// ── DATA FETCHING ──

const fetchProducts = async (search = '') => {
  try {
    isFetching.value = true
    const response = await $api(`/v1/products?per_page=100&search=${encodeURIComponent(search)}`)
    products.value = response.data || response || []
  } catch (error) {
    console.error('Error fetching products:', error)
  } finally {
    isFetching.value = false
  }
}

const fetchCustomers = async () => {
  try {
    const response = await $api('/v1/customers')
    customers.value = response.data || response || []
  } catch (error) {
    console.error('Error fetching customers:', error)
  }
}

const handleCustomerSaved = async (newCustomerData) => {
  await fetchCustomers()
  if (newCustomerData && newCustomerData.id) {
    selectedCustomer.value = newCustomerData.id
  } else if (customers.value.length > 0) {
    // Seleccionar el último cliente agregado
    const lastCustomer = customers.value[customers.value.length - 1]
    if (lastCustomer) {
      selectedCustomer.value = lastCustomer.id
    }
  }
}

// ── LÓGICA DE NEGOCIO ──

const filteredProducts = computed(() => {
  const query = (searchQuery.value || '').toLowerCase()
  return products.value.filter(p => {
    const name = (p.name || '').toLowerCase()
    const barcode = (p.barcode || '').toLowerCase()
    return name.includes(query) || barcode.includes(query)
  })
})

const subtotal = computed(() => {
  return cart.value.reduce((total, item) => {
    const itemSub = (Number(item.sale_price || 0) * item.quantity) - Number(item.discount || 0)
    return total + Math.max(0, itemSub)
  }, 0)
})

const total = computed(() => {
  return Math.max(0, subtotal.value - Number(discount.value || 0))
})

const change = computed(() => {
  if (paymentMethod.value === 10) {
    return cashTendered.value > 0 ? Math.max(0, Number((cashTendered.value - splitCashAmount.value).toFixed(2))) : 0
  }
  return cashTendered.value > 0 ? Math.max(0, Number((cashTendered.value - total.value).toFixed(2))) : 0
})

const isToday = computed(() => {
  const todayStr = new Date().toISOString().slice(0, 10)
  return saleDate.value === todayStr
})

// Vincular datos del cliente al seleccionar
watch(selectedCustomer, (newVal) => {
  if (newVal) {
    const customer = customers.value.find(c => c.id === newVal)
    if (customer) {
      businessName.value = `${customer.first_name || ''} ${customer.last_name || ''}`.trim() || customer.first_name || 'S/N'
      nitCi.value = customer.document_number || '0'
      complement.value = customer.complement || ''
      customerEmail.value = customer.email || ''
      documentType.value = customer.document_type || (String(customer.document_number || '').length >= 9 ? 5 : 1)
    }
  } else {
    businessName.value = 'CONTROL TRIBUTARIO'
    nitCi.value = '99002'
    complement.value = ''
    customerEmail.value = ''
    documentType.value = 5 // NIT normado para 99002
  }
})

const setFiscalPreset = (type) => {
  if (type === 'control') {
    documentType.value = 5
    nitCi.value = '99002'
    businessName.value = 'CONTROL TRIBUTARIO'
    complement.value = ''
    hasExceptionCode.value = false
  } else if (type === 'menores') {
    documentType.value = 5
    nitCi.value = '99003'
    businessName.value = 'VENTAS MENORES DEL DÍA'
    complement.value = ''
    hasExceptionCode.value = false
  } else if (type === 'especial') {
    documentType.value = 5
    nitCi.value = '99001'
    businessName.value = 'CASOS ESPECIALES'
    complement.value = ''
    hasExceptionCode.value = false
  } else if (type === 'ci') {
    documentType.value = 1
    nitCi.value = ''
    businessName.value = ''
    complement.value = ''
    hasExceptionCode.value = false
  }
}

// Validación y restricción de formato numérico para NIT / CI.
// Solo aplica a tipos 1 (CI) y 5 (NIT); CEX / Pasaporte / Otro documento pueden ser alfanuméricos.
watch([nitCi, documentType], ([newVal, docType]) => {
  if (!newVal) return

  if ([1, 5].includes(docType) && /[^\d]/.test(String(newVal))) {
    nitCi.value = String(newVal).replace(/\D/g, '')
    toast.showWarning(
      'La casilla NIT/CI es de formato estrictamente numérico. Se restringió la inclusión de letras o texto.',
      'Restricción de Formato (SIN)'
    )
    return
  }

  // Auto-detectar NIT si tiene 9 o más dígitos y está en CI
  if (String(newVal).length >= 9 && docType === 1) {
    documentType.value = 5 // NIT
  }
})

// Bloqueo físico en teclado de caracteres no numéricos en la casilla NIT/CI (si es CI o NIT)
const onNitCiKeypress = (e) => {
  if ([1, 5].includes(documentType.value)) {
    if (!/^\d$/.test(e.key)) {
      e.preventDefault()
    }
  }
}

// Aplicar o quitar descuento individual a un producto (normado SIN: subTotal > 0, mínimo 0.01 Bs)
const apply100Discount = (item) => {
  if (item.discount > 0) {
    item.discount = 0
    toast.showInfo(`Descuento eliminado de ${item.name}`, 'Descuento')
  } else {
    const gross = grossOf(item)
    // Norma SIN (XSD minExclusive 0.0): subTotal debe ser > 0, por lo que el descuento deja un residual de Bs. 0.01
    const maxDiscount = Number(Math.max(0, gross - 0.01).toFixed(2))
    item.discount = maxDiscount
    toast.showSuccess(`Descuento individual aplicado (Bs. ${item.discount}, residual normado Bs. 0.01) a ${item.name}`, 'Descuento 100% (SIN)')
  }
}

// Importe bruto de un ítem (cantidad x precio), tope del descuento por ítem
const grossOf = (item) => Number((Number(item.quantity || 0) * Number(item.sale_price || 0)).toFixed(2))

// Descuento por ítem manual, acotado entre 0 y el importe del ítem menos 0.01 (norma XSD subTotal > 0)
const setItemDiscount = (item, event) => {
  let val = parseFloat(event.target.value)
  if (isNaN(val) || val < 0) val = 0
  const gross = grossOf(item)
  const max = Number(Math.max(0, gross - 0.01).toFixed(2))
  if (val > max) {
    val = max
    toast.showWarning(`Por norma del SIN (XSD), el subtotal del ítem debe ser mayor a 0. Descuento ajustado a Bs. ${max.toFixed(2)} (subtotal 0.01 Bs).`, 'Tope Normativo SIN')
  }
  item.discount = Number(val.toFixed(2))
  event.target.value = item.discount || ''
}

// Si cambia la cantidad, el descuento nunca debe dejar el subtotal en 0
watch(cart, (items) => {
  items.forEach(i => {
    const max = Number(Math.max(0, grossOf(i) - 0.01).toFixed(2))
    if (Number(i.discount || 0) > max) i.discount = max
  })
}, { deep: true })

// El descuento adicional global no puede superar la suma de subtotales
watch(discount, (val) => {
  const max = Number(subtotal.value.toFixed(2))
  if (Number(val || 0) > max) discount.value = max
  if (Number(val || 0) < 0) discount.value = 0
})

const addToCart = (product) => {
  if (product.stock <= 0 || product.is_expired) return
  const existingItem = cart.value.find(item => item.id === product.id)
  if (existingItem) {
    if (existingItem.quantity < product.stock) {
      existingItem.quantity++
    } else {
      toast.showWarning(`Stock máximo alcanzado (${product.stock} unds)`, 'Stock')
    }
  } else {
    cart.value.push({ 
      ...product, 
      quantity: 1,
      discount: 0 
    })
  }
}

const updateQuantity = (item, delta) => {
  const newQty = item.quantity + delta
  if (newQty <= 0) {
    const idx = cart.value.findIndex(i => i.id === item.id)
    if (idx !== -1) cart.value.splice(idx, 1)
  } else if (newQty <= item.stock) {
    item.quantity = newQty
  } else {
    toast.showWarning(`Stock máximo alcanzado (${item.stock} unds)`, 'Stock')
  }
}

const setItemQuantity = (item, event) => {
  const val = parseInt(event.target.value, 10)
  if (isNaN(val) || val <= 0) {
    item.quantity = 1
  } else if (val > item.stock) {
    item.quantity = item.stock
    toast.showWarning(`Stock máximo alcanzado (${item.stock} unds)`, 'Stock')
  } else {
    item.quantity = val
  }
}

const removeItem = (index) => {
  cart.value.splice(index, 1)
}

const clearCart = () => {
  cart.value = []
  cashTendered.value = 0
  discount.value = 0
  cardNumber.value = ''
  splitCashAmount.value = 0
  splitCardAmount.value = 0
  giftCardCode.value = ''
  giftCardAmount.value = 0
  hasExceptionCode.value = false
  isOfflineMode.value = false
  manualCafc.value = ''
  manualInvoiceNumber.value = ''
}

const initSplitAmounts = () => {
  const half = Number((total.value / 2).toFixed(2))
  splitCashAmount.value = half
  splitCardAmount.value = Number((total.value - half).toFixed(2))
  cashTendered.value = splitCashAmount.value
}

const onSplitCashInput = (val) => {
  let cash = parseFloat(val)
  if (isNaN(cash) || cash < 0) cash = 0
  if (cash > total.value) cash = total.value
  splitCashAmount.value = Number(cash.toFixed(2))
  splitCardAmount.value = Number(Math.max(0, total.value - splitCashAmount.value).toFixed(2))
  cashTendered.value = splitCashAmount.value
}

const onSplitCardInput = (val) => {
  let card = parseFloat(val)
  if (isNaN(card) || card < 0) card = 0
  if (card > total.value) card = total.value
  splitCardAmount.value = Number(card.toFixed(2))
  splitCashAmount.value = Number(Math.max(0, total.value - splitCardAmount.value).toFixed(2))
  cashTendered.value = splitCashAmount.value
}

const isSplitBalanced = computed(() => {
  const sum = Number((Number(splitCashAmount.value || 0) + Number(splitCardAmount.value || 0)).toFixed(2))
  return Math.abs(sum - total.value) <= 0.02
})

const splitDiff = computed(() => {
  const sum = Number((Number(splitCashAmount.value || 0) + Number(splitCardAmount.value || 0)).toFixed(2))
  return Number((total.value - sum).toFixed(2))
})

watch(paymentMethod, (newVal) => {
  if (newVal === 10) {
    initSplitAmounts()
  } else if (newVal === 27) {
    giftCardAmount.value = total.value
  } else if (newVal === 1) {
    cashTendered.value = total.value
  }
})

const openCheckoutModal = () => {
  if (cart.value.length === 0) return
  if (!hasOpenSession.value) {
    openCashDialog()
    return
  }
  if (paymentMethod.value === 10) {
    initSplitAmounts()
  } else if (paymentMethod.value === 27) {
    giftCardAmount.value = total.value
  } else {
    cashTendered.value = total.value
  }
  isCheckoutModalOpen.value = true
}

const setQuickCash = (amount) => {
  const target = paymentMethod.value === 10 ? splitCashAmount.value : total.value
  if (amount === 'exact') {
    cashTendered.value = target
  } else {
    cashTendered.value = amount
  }
}

const checkout = async () => {
  if (cart.value.length === 0) return

  // 1. Validación SIAT de Tarjeta (Exigida por normativa cuando el método incluye Tarjeta: 2 = Tarjeta, 10 = Efectivo-Tarjeta)
  if ([2, 10].includes(paymentMethod.value) && isInvoice.value) {
    if (!cardNumber.value || cardNumber.value.trim().length < 4) {
      toast.showWarning('Ingrese los 16 dígitos (o al menos los últimos 4) de la tarjeta para facturación SIAT.', 'Dato Requerido')
      return
    }
  }

  // 2. Validación de Pago Combinado (Código 10)
  if (paymentMethod.value === 10) {
    if (!isSplitBalanced.value) {
      toast.showWarning(
        `La suma de Efectivo (Bs. ${splitCashAmount.value.toFixed(2)}) y Tarjeta (Bs. ${splitCardAmount.value.toFixed(2)}) debe coincidir con el total de Bs. ${total.value.toFixed(2)}.`,
        'Diferencia en Montos'
      )
      return
    }
    if (splitCashAmount.value <= 0 || splitCardAmount.value <= 0) {
      toast.showWarning('En el pago combinado, tanto el monto en Efectivo como en Tarjeta deben ser mayores a 0.', 'Montos Requeridos')
      return
    }
  }

  // 3. Validación de Gift Card (Código 27)
  if (paymentMethod.value === 27) {
    if (!giftCardCode.value || giftCardCode.value.trim().length === 0) {
      toast.showWarning('Ingrese el código o identificador de la Gift Card / Vale.', 'Dato Requerido')
      return
    }
  }

  const now = new Date()
  const timeStr = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}:${String(now.getSeconds()).padStart(2, '0')}`
  const fullSaleDate = saleDate.value ? `${saleDate.value} ${timeStr}` : `${now.toISOString().slice(0, 10)} ${timeStr}`

  let finalDocType = documentType.value
  let finalNitCi = String(nitCi.value || '').trim()
  let finalBusinessName = String(businessName.value || '').trim()

  if (isInvoice.value) {
    if (finalNitCi === '0' || finalNitCi === '' || finalBusinessName.toUpperCase() === 'CONTROL TRIBUTARIO') {
      finalDocType = 5
      finalNitCi = '99002'
      finalBusinessName = 'CONTROL TRIBUTARIO'
    }
  }

  const payload = {
    customer_id: selectedCustomer.value,
    business_name: isInvoice.value ? (finalBusinessName || 'CONTROL TRIBUTARIO') : 'VENTA LOCAL',
    nit_ci: isInvoice.value ? finalNitCi : '0',
    complement: isInvoice.value ? (complement.value || null) : null,
    customer_email: isInvoice.value ? (customerEmail.value || null) : null,
    sale_date: fullSaleDate,
    total_amount: total.value,
    cash_tendered: paymentMethod.value === 1 
      ? (cashTendered.value || total.value) 
      : (paymentMethod.value === 10 ? (cashTendered.value || splitCashAmount.value) : total.value),
    change: [1, 10].includes(paymentMethod.value) ? change.value : 0,
    payment_method_code: paymentMethod.value,
    document_type_code: isInvoice.value ? finalDocType : 1,
    card_number: [2, 10].includes(paymentMethod.value) ? cardNumber.value : null,
    card_amount: paymentMethod.value === 10 ? Number(splitCardAmount.value || 0) : (paymentMethod.value === 2 ? Number(total.value || 0) : 0),
    gift_card_amount: paymentMethod.value === 27 ? Number(giftCardAmount.value || total.value) : 0,
    gift_card_code: paymentMethod.value === 27 ? giftCardCode.value : null,
    discount_amount: Number(discount.value || 0),
    exception_code: (isInvoice.value && hasExceptionCode.value) ? 1 : null,
    is_invoice: isInvoice.value,
    is_offline: isInvoice.value && isOfflineMode.value,
    codigo_emision: (isInvoice.value && isOfflineMode.value) ? 2 : 1,
    cafc: (isInvoice.value && isOfflineMode.value && manualCafc.value) ? manualCafc.value.trim() : null,
    invoice_number: (isInvoice.value && isOfflineMode.value && manualInvoiceNumber.value) ? Number(manualInvoiceNumber.value) : null,
    items: cart.value.map(item => ({
      product_id: item.id,
      quantity: item.quantity,
      unit_price: item.sale_price,
      discount: Number(item.discount || 0),
      item_discount: Number(item.discount || 0)
    }))
  }

  try {
    isFetching.value = true
    const response = await $api('/v1/sales', {
      method: 'POST',
      body: payload
    })

    saleResult.value = response.data || response
    isCheckoutModalOpen.value = false
    isResultDialog.value = true
    
    // Limpiar carrito
    clearCart()
    saleDate.value = new Date().toISOString().slice(0, 10)
    fetchProducts(searchQuery.value)
  } catch (error) {
    const errorMsg = error.response?._data?.error || 'Error al procesar la venta'
    toast.showError(errorMsg, 'Error en POS')
  } finally {
    isFetching.value = false
  }
}

// Atajos de teclado (F12 para cobrar)
const handleGlobalKeydown = (e) => {
  if (e.key === 'F12') {
    e.preventDefault()
    if (cart.value.length > 0 && !isCheckoutModalOpen.value) {
      openCheckoutModal()
    } else if (isCheckoutModalOpen.value) {
      checkout()
    }
  }
}

onMounted(() => {
  fetchProducts()
  fetchCustomers()
  window.addEventListener('keydown', handleGlobalKeydown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleGlobalKeydown)
})

let debounceTimeout = null
watch(searchQuery, (newVal) => {
  if (debounceTimeout) clearTimeout(debounceTimeout)
  debounceTimeout = setTimeout(() => {
    fetchProducts(newVal || '')
  }, 300)
})
</script>

<template>
  <div class="pos-wrapper">
    <VRow class="pos-main-row ma-0 h-100">
      
      <!-- ======================================================= -->
      <!-- COLUMNA IZQUIERDA: CATÁLOGO DE MEDICAMENTOS (60% Desktop) -->
      <!-- ======================================================= -->
      <VCol cols="12" md="7" lg="7" xl="7" class="pos-catalog-col pa-2 pa-sm-3 d-flex flex-column">
        
        <!-- BUSCADOR SUPERIOR DIRECTO Y LIMPIO -->
        <div class="catalog-header-bar pa-2 pa-sm-3 bg-surface rounded-xl border mb-3 shadow-xs d-flex align-center gap-2">
          <VTextField
            v-model="searchQuery"
            prepend-inner-icon="ri-search-line"
            placeholder="Buscar medicamento, principio activo o escanear código de barras..."
            variant="outlined"
            density="compact"
            hide-details
            clearable
            autofocus
            class="search-pos-input flex-grow-1"
          />

          <VChip 
            v-if="hasOpenSession" 
            color="success" 
            variant="tonal" 
            size="small" 
            class="font-weight-bold d-none d-sm-flex"
          >
            <VIcon start icon="ri-safe-2-line" size="16" /> Caja Abierta
          </VChip>

          <VBtn
            v-else
            color="error"
            size="small"
            variant="flat"
            class="font-weight-bold text-white"
            @click="openCashDialog"
          >
            <VIcon start icon="ri-lock-line" size="16" /> Abrir Caja
          </VBtn>
        </div>

        <!-- GRILLA DE PRODUCTOS -->
        <div class="product-grid-container flex-grow-1 overflow-y-auto px-1">
          <VRow v-if="!isFetching && filteredProducts.length > 0" class="ma-n1">
            <VCol 
              v-for="item in filteredProducts" 
              :key="item.id" 
              cols="6" 
              sm="4" 
              md="4" 
              lg="4" 
              xl="3" 
              class="pa-1"
            >
              <div 
                class="pos-card-med"
                :class="{ 
                  'is-disabled': item.stock <= 0 || item.is_expired,
                  'is-in-cart': cart.some(c => c.id === item.id)
                }"
                @click="addToCart(item)"
              >
                <!-- Stock Flotante -->
                <div class="card-badge-top">
                  <span 
                    v-if="item.stock > 0 && !item.is_expired" 
                    class="stock-tag"
                    :class="item.stock > 10 ? 'stock-ok' : 'stock-low'"
                  >
                    Stock: {{ item.stock }}
                  </span>
                  <span v-else-if="item.stock <= 0" class="stock-tag stock-out">
                    AGOTADO
                  </span>
                  <span v-else class="stock-tag stock-expired">
                    VENCIDO
                  </span>
                </div>

                <!-- Imagen / Placeholder -->
                <div class="card-img-wrapper">
                  <VImg 
                    :src="item.image_path ? `/storage/${item.image_path}` : 'https://placehold.co/300x180/E8F5E9/009688?text='+encodeURIComponent(item.name.substring(0,6))" 
                    height="85" 
                    cover
                    class="rounded-lg"
                  />
                </div>

                <!-- Detalles -->
                <div class="card-body-wrapper">
                  <div class="card-lab-name">{{ item.laboratory?.name || 'GENÉRICO' }}</div>
                  <h4 class="card-title-name" :title="item.name">{{ item.name }}</h4>
                  
                  <div class="d-flex justify-space-between align-center mt-auto pt-1 border-t">
                    <span class="card-price-txt">Bs. {{ Number(item.sale_price || 0).toFixed(2) }}</span>
                    <button 
                      class="btn-quick-add" 
                      :disabled="item.stock <= 0 || item.is_expired"
                      title="Agregar"
                    >
                      <VIcon icon="ri-add-line" size="16" />
                    </button>
                  </div>
                </div>
              </div>
            </VCol>
          </VRow>

          <div v-else-if="!isFetching && filteredProducts.length === 0" class="empty-state-box text-center py-12">
            <VIcon icon="ri-inbox-line" size="48" class="text-disabled mb-1" />
            <div class="text-body-1 font-weight-bold text-medium-emphasis">No se encontraron medicamentos</div>
            <p class="text-caption text-disabled">Intente con otro término de búsqueda.</p>
          </div>

          <div v-else class="h-100 d-flex align-center justify-center py-10">
            <VProgressCircular indeterminate color="primary" size="48" />
          </div>
        </div>
      </VCol>

      <!-- ======================================================= -->
      <!-- COLUMNA DERECHA: CARRITO COMPLETO Y BOTÓN DE COBRO (40% Desktop) -->
      <!-- ======================================================= -->
      <VCol cols="12" md="5" lg="5" xl="5" class="pos-cart-col pa-2 pa-sm-3 d-flex flex-column h-100">
        <div class="cart-card-container h-100 d-flex flex-column bg-surface rounded-xl border shadow-sm">
          
          <!-- 1. CABECERA DEL CARRITO -->
          <div class="cart-header-top px-3 py-2 bg-primary text-white d-flex justify-space-between align-center rounded-t-xl">
            <div class="d-flex align-center">
              <VIcon icon="ri-shopping-cart-2-line" size="20" class="me-2" />
              <span class="text-subtitle-2 font-weight-bold text-white">Venta en Curso</span>
            </div>
            
            <div class="d-flex align-center gap-2">
              <VChip color="white" text-color="primary" size="small" class="font-weight-black">
                {{ cart.length }} {{ cart.length === 1 ? 'producto' : 'productos' }}
              </VChip>
              
              <IconBtn 
                v-if="cart.length > 0" 
                size="small" 
                color="white" 
                variant="text" 
                title="Vaciar carrito"
                @click="clearCart"
              >
                <VIcon icon="ri-delete-bin-line" size="18" />
              </IconBtn>
            </div>
          </div>

          <!-- 2. SELECTOR DE CLIENTE CON AUTOCOMPLETE LIVE-SEARCH Y BOTÓN CREAR -->
          <div class="client-select-bar px-3 py-2 border-b">
            <div class="d-flex align-center gap-2">
              <VAutocomplete
                v-model="selectedCustomer"
                :items="customers"
                item-title="first_name"
                item-value="id"
                placeholder="Buscar Cliente (Nombre / NIT / CI)..."
                density="compact"
                variant="outlined"
                hide-details
                clearable
                prepend-inner-icon="ri-user-search-line"
                class="flex-grow-1 customer-autocomplete-input"
              >
                <template #selection="{ item }">
                  <div class="d-flex align-center text-truncate font-weight-bold text-caption text-primary">
                    {{ item.raw.first_name || '' }} {{ item.raw.last_name || '' }}
                    <span class="text-medium-emphasis ms-1 font-weight-regular">({{ item.raw.document_number || 'S/N' }})</span>
                  </div>
                </template>

                <template #item="{ props, item }">
                  <VListItem 
                    v-bind="props" 
                    :title="`${item.raw.first_name || ''} ${item.raw.last_name || ''}`"
                    :subtitle="`NIT/CI: ${item.raw.document_number || 'S/N'} | Tel: ${item.raw.phone || 'S/N'}`"
                  />
                </template>
              </VAutocomplete>

              <VBtn 
                color="primary"
                variant="flat"
                size="small"
                class="font-weight-bold px-3"
                title="Registrar Nuevo Cliente"
                @click="isAddClientDialog = true"
              >
                <VIcon start icon="ri-user-add-line" size="16" />
                <span>Nuevo</span>
              </VBtn>
            </div>
          </div>

          <!-- 3. LISTA DE PRODUCTOS DEL CARRITO (ESPACIO MÁXIMO VERTICAL) -->
          <div class="cart-table-wrapper flex-grow-1 overflow-y-auto">
            <div v-if="cart.length === 0" class="empty-cart-state">
              <VIcon icon="ri-shopping-basket-2-line" size="52" class="text-disabled mb-2" />
              <div class="text-body-2 font-weight-bold text-medium-emphasis">El carrito está vacío</div>
              <span class="text-caption text-disabled">Haga clic en los medicamentos para añadirlos</span>
            </div>

            <!-- Tabla de Ítems Espaciosa (Permite 10-15 ítems sin ahogarse) -->
            <table v-else class="cart-items-table">
              <thead>
                <tr class="cart-th-row">
                  <th class="text-left" style="min-width: 130px;">Producto</th>
                  <th class="text-center" style="width: 95px;">Cant.</th>
                  <th class="text-right" style="width: 70px;">P. Unit</th>
                  <th class="text-right" style="width: 75px;">Total</th>
                  <th class="text-center" style="width: 28px;"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, index) in cart" :key="item.id" class="cart-tr-row">
                  <!-- Nombre y Lab -->
                  <td class="text-left">
                    <div class="item-table-name" :title="item.name">{{ item.name }}</div>
                    <div class="item-table-lab">{{ item.laboratory?.name || 'GENÉRICO' }}</div>
                    <!-- Descuento por ítem (SIAT: montoDescuento) -->
                    <div class="d-flex align-center mt-1" style="gap: 4px;">
                      <span class="text-caption text-warning font-weight-bold">Desc. Bs.</span>
                      <input
                        type="number"
                        min="0"
                        step="0.01"
                        :max="grossOf(item)"
                        :value="item.discount || ''"
                        placeholder="0.00"
                        class="discount-mini-input"
                        style="width: 64px;"
                        @change="setItemDiscount(item, $event)"
                      />
                      <button
                        type="button"
                        class="stepper-btn"
                        style="width: auto; padding: 0 5px; font-size: 10px;"
                        :title="item.discount > 0 ? 'Quitar descuento' : 'Aplicar descuento del 100%'"
                        @click="apply100Discount(item)"
                      >
                        {{ item.discount > 0 ? 'Quitar' : '100%' }}
                      </button>
                    </div>
                  </td>

                  <!-- Stepper de Cantidad -->
                  <td class="text-center">
                    <div class="stepper-box">
                      <button class="stepper-btn" @click="updateQuantity(item, -1)">
                        <VIcon icon="ri-subtract-line" size="12" />
                      </button>
                      <input 
                        type="number" 
                        :value="item.quantity" 
                        min="1" 
                        :max="item.stock" 
                        class="stepper-input" 
                        @change="setItemQuantity(item, $event)"
                      />
                      <button class="stepper-btn" @click="updateQuantity(item, 1)">
                        <VIcon icon="ri-add-line" size="12" />
                      </button>
                    </div>
                  </td>

                  <!-- Precio Unitario -->
                  <td class="text-right font-weight-medium text-caption text-medium-emphasis">
                    {{ Number(item.sale_price || 0).toFixed(2) }}
                  </td>

                  <!-- Subtotal de Línea -->
                  <td class="text-right font-weight-black text-caption text-primary">
                    Bs. {{ (item.quantity * Number(item.sale_price || 0) - Number(item.discount || 0)).toFixed(2) }}
                  </td>

                  <!-- Botón Eliminar -->
                  <td class="text-center">
                    <button class="btn-del-row" @click="removeItem(index)" title="Quitar">
                      <VIcon icon="ri-close-line" size="16" />
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- 4. TOTALES, DESCUENTO GLOBAL Y BOTÓN DE COBRO (FIJO INFERIOR) -->
          <div class="cart-bottom-checkout pa-3 border-t">
            <!-- Desglose de Totales y Descuento -->
            <div class="d-flex justify-space-between align-center mb-1">
              <span class="text-caption text-medium-emphasis">Subtotal:</span>
              <span class="text-body-2 font-weight-bold">Bs. {{ subtotal.toFixed(2) }}</span>
            </div>

            <!-- Descuento Global (SIAT) -->
            <div class="d-flex justify-space-between align-center mb-1">
              <span class="text-caption text-warning font-weight-bold d-flex align-center">
                <VIcon icon="ri-discount-percent-line" size="14" class="me-1" /> Descuento:
              </span>
              <div class="d-flex align-center" style="width: 100px;">
                <input 
                  type="number" 
                  v-model.number="discount" 
                  min="0" 
                  step="0.5" 
                  placeholder="0.00"
                  class="discount-mini-input"
                />
              </div>
            </div>

            <div class="d-flex justify-space-between align-center mb-3 pt-1 border-t">
              <span class="text-subtitle-1 font-weight-black text-high-emphasis">TOTAL A PAGAR:</span>
              <span class="text-h5 font-weight-black text-primary">Bs. {{ total.toFixed(2) }}</span>
            </div>

            <!-- Botón Grande de Cobro que Despliega el Modal de Pago -->
            <VBtn 
              block 
              color="success" 
              size="large" 
              elevation="4" 
              class="btn-open-payment font-weight-black text-white rounded-xl"
              :disabled="cart.length === 0 || !hasOpenSession" 
              @click="openCheckoutModal"
            >
              <VIcon start icon="ri-secure-payment-line" size="22" />
              <span>COBRAR BS. {{ total.toFixed(2) }} (F12)</span>
            </VBtn>
          </div>
        </div>
      </VCol>
    </VRow>

    <!-- ======================================================= -->
    <!-- MODAL DE PAGO / CHECKOUT PROFESIONAL (SCROLLABLE & FIJO) -->
    <!-- ======================================================= -->
    <VDialog v-model="isCheckoutModalOpen" max-width="600" scrollable persistent>
      <VCard class="checkout-modal-card rounded-xl overflow-hidden d-flex flex-column" style="max-height: 88vh;">
        
        <!-- 1. Cabecera del Modal (Fija) -->
        <VCardTitle class="bg-primary text-white d-flex justify-space-between align-center py-3 px-4 flex-shrink-0">
          <div class="d-flex align-center">
            <VIcon icon="ri-hand-coin-line" size="24" class="me-2" />
            <span class="text-subtitle-1 font-weight-bold text-white">Finalizar Venta y Cobro</span>
          </div>
          <VBtn icon="ri-close-line" variant="text" color="white" size="small" @click="isCheckoutModalOpen = false" />
        </VCardTitle>

        <!-- 2. Cuerpo del Modal (Con Scroll Interno Limpio) -->
        <VCardText class="pa-4 overflow-y-auto checkout-modal-body">
          <!-- Resumen de Monto Total -->
          <div class="total-banner pa-3 rounded-xl bg-primary-lighten-5 border border-primary border-opacity-25 d-flex justify-space-between align-center mb-3">
            <div>
              <span class="text-caption text-uppercase font-weight-bold text-primary">Monto Total Fiscal</span>
              <div class="text-caption text-medium-emphasis">
                {{ cart.length }} productos <span v-if="discount > 0">(Desc: -Bs. {{ Number(discount).toFixed(2) }})</span>
              </div>
            </div>
            <div class="text-h4 font-weight-black text-primary">
              Bs. {{ total.toFixed(2) }}
            </div>
          </div>

          <!-- SELECCIÓN DE MÉTODO DE PAGO (TILES VISUALES HIGH-CONTRAST) -->
          <div class="mb-3">
            <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-1">
              Método de Pago (Normado SIN)
            </div>
            <div class="modal-payment-grid d-flex gap-2">
              <div 
                v-for="pm in paymentMethodsList" 
                :key="pm.value"
                class="modal-payment-tile"
                :class="{ active: paymentMethod === pm.value }"
                @click="paymentMethod = pm.value"
              >
                <VIcon :icon="pm.icon" size="24" class="mb-1 pm-icon" />
                <span class="pm-title">{{ pm.title }}</span>
                <span class="pm-desc">{{ pm.desc }}</span>
              </div>
            </div>
          </div>

          <!-- 1. EFECTIVO (Código 1) -->
          <div v-if="paymentMethod === 1" class="cash-calc-box pa-3 rounded-xl mb-3 border">
            <VRow density="compact" class="align-center mb-2">
              <VCol cols="6">
                <VTextField 
                  v-model.number="cashTendered" 
                  type="number" 
                  label="Paga con Efectivo" 
                  variant="outlined" 
                  density="comfortable" 
                  prefix="Bs." 
                  autofocus
                  hide-details
                  class="font-weight-bold text-h6"
                />
              </VCol>
              <VCol cols="6">
                <div class="change-display-box pa-2 rounded-lg border text-center" :class="change >= 0 ? 'change-ok' : 'change-err'">
                  <div class="text-caption font-weight-bold text-uppercase">
                    {{ change >= 0 ? 'Cambio / Vuelto' : 'Falta Dinero' }}
                  </div>
                  <div class="text-h5 font-weight-black">
                    Bs. {{ Math.abs(change).toFixed(2) }}
                  </div>
                </div>
              </VCol>
            </VRow>

            <!-- Billetes Rápidos -->
            <div class="d-flex align-center gap-1 flex-wrap">
              <span class="text-caption font-weight-bold text-medium-emphasis me-1">Billetes:</span>
              <VBtn 
                size="x-small" 
                variant="outlined" 
                color="secondary" 
                class="font-weight-bold"
                @click="setQuickCash('exact')"
              >
                Exacto
              </VBtn>
              <VBtn 
                v-for="amt in quickCashAmounts" 
                :key="amt" 
                size="x-small" 
                variant="outlined" 
                color="primary" 
                class="font-weight-bold"
                @click="setQuickCash(amt)"
              >
                Bs. {{ amt }}
              </VBtn>
            </div>
          </div>

          <!-- 2. COMBINADO: EFECTIVO + TARJETA (Código 10) -->
          <div v-if="paymentMethod === 10" class="combined-calc-box pa-3 rounded-xl mb-3 border">
            <div class="d-flex align-center justify-space-between mb-2">
              <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis">
                Desglose Combinado (Normado SIN)
              </div>
              <div 
                class="text-caption px-2 py-0.5 rounded font-weight-bold"
                :class="isSplitBalanced ? 'bg-success text-white' : 'bg-warning text-white'"
              >
                {{ isSplitBalanced ? '✓ Suma Exacta: Bs. ' + total.toFixed(2) : '⚠ Diferencia: Bs. ' + Math.abs(splitDiff).toFixed(2) }}
              </div>
            </div>

            <!-- Dos Inputs: Monto Efectivo y Monto Tarjeta -->
            <VRow density="compact" class="mb-2">
              <VCol cols="6">
                <VTextField 
                  :model-value="splitCashAmount"
                  type="number" 
                  step="0.01"
                  min="0"
                  :max="total"
                  label="Monto en Efectivo" 
                  variant="outlined" 
                  density="comfortable" 
                  prefix="Bs."
                  prepend-inner-icon="ri-money-dollar-circle-line"
                  hide-details
                  class="font-weight-bold"
                  @update:model-value="onSplitCashInput"
                />
              </VCol>
              <VCol cols="6">
                <VTextField 
                  :model-value="splitCardAmount"
                  type="number" 
                  step="0.01"
                  min="0"
                  :max="total"
                  label="Monto en Tarjeta" 
                  variant="outlined" 
                  density="comfortable" 
                  prefix="Bs."
                  prepend-inner-icon="ri-bank-card-line"
                  hide-details
                  class="font-weight-bold"
                  @update:model-value="onSplitCardInput"
                />
              </VCol>
            </VRow>

            <!-- Pago del Efectivo + Vuelto sobre la parte en efectivo -->
            <VRow density="compact" class="align-center mb-2">
              <VCol cols="6">
                <VTextField 
                  v-model.number="cashTendered" 
                  type="number" 
                  label="Cliente entrega en Efectivo" 
                  variant="outlined" 
                  density="comfortable" 
                  prefix="Bs." 
                  hide-details
                  class="font-weight-bold"
                />
              </VCol>
              <VCol cols="6">
                <div class="change-display-box pa-2 rounded-lg border text-center" :class="change >= 0 ? 'change-ok' : 'change-err'">
                  <div class="text-caption font-weight-bold text-uppercase">
                    {{ change >= 0 ? 'Cambio / Vuelto' : 'Falta en Efectivo' }}
                  </div>
                  <div class="text-h6 font-weight-black">
                    Bs. {{ Math.abs(change).toFixed(2) }}
                  </div>
                </div>
              </VCol>
            </VRow>

            <!-- Billetes Rápidos para la parte de Efectivo -->
            <div class="d-flex align-center gap-1 flex-wrap mb-3">
              <span class="text-caption font-weight-bold text-medium-emphasis me-1">Billetes:</span>
              <VBtn size="x-small" variant="outlined" color="secondary" class="font-weight-bold" @click="setQuickCash('exact')">
                Exacto (Bs. {{ splitCashAmount }})
              </VBtn>
              <VBtn v-for="amt in quickCashAmounts" :key="amt" size="x-small" variant="outlined" color="primary" class="font-weight-bold" @click="setQuickCash(amt)">
                Bs. {{ amt }}
              </VBtn>
            </div>

            <!-- Número de Tarjeta (Requerido por SIAT para la parte en tarjeta) -->
            <VTextField 
              v-model="cardNumber" 
              label="Número o Últimos 4 Dígitos de Tarjeta" 
              placeholder="Ej. 4567 o 4567000000001234"
              variant="outlined" 
              density="comfortable" 
              prepend-inner-icon="ri-bank-card-line"
              hide-details
            />
            <div class="text-caption text-medium-emphasis mt-1">
              El SIAT enmascara automáticamente los dígitos centrales por seguridad bancaria.
            </div>
          </div>

          <!-- 3. TARJETA PURA (Código 2) -->
          <div v-if="paymentMethod === 2" class="card-info-box pa-3 rounded-xl mb-3 border">
            <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-2">
              Datos de Tarjeta (SIAT Crédito / Débito)
            </div>
            <VTextField 
              v-model="cardNumber" 
              label="Número o Últimos 4 Dígitos de Tarjeta" 
              placeholder="Ej. 4567 o 4567000000001234"
              variant="outlined" 
              density="comfortable" 
              prepend-inner-icon="ri-bank-card-line"
              hide-details
            />
            <div class="text-caption text-medium-emphasis mt-1">
              El SIAT enmascara automáticamente los dígitos centrales por seguridad bancaria.
            </div>
          </div>

          <!-- 4. QR / TRANSFERENCIA BANCARIA (Código 7) -->
          <div v-if="paymentMethod === 7" class="qr-info-box pa-3 rounded-xl mb-3 border">
            <div class="d-flex align-center text-primary mb-1">
              <VIcon icon="ri-qr-code-line" size="22" class="me-2" />
              <span class="text-subtitle-2 font-weight-bold">Transferencia Bancaria / QR Digital</span>
            </div>
            <div class="text-caption text-medium-emphasis">
              Asegúrese de verificar el abono o comprobante de transferencia bancaria antes de finalizar la venta.
            </div>
          </div>

          <!-- 5. GIFT CARD / VALE (Código 27) -->
          <div v-if="paymentMethod === 27" class="gift-card-box pa-3 rounded-xl mb-3 border">
            <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-2">
              Pago con Tarjeta de Regalo / Vale (Gift Card)
            </div>
            <VRow density="compact" class="mb-2">
              <VCol cols="7">
                <VTextField 
                  v-model="giftCardCode" 
                  label="Código / N° de Gift Card" 
                  placeholder="Ej. GC-2026-9481"
                  variant="outlined" 
                  density="comfortable" 
                  prepend-inner-icon="ri-gift-line"
                  hide-details
                />
              </VCol>
              <VCol cols="5">
                <VTextField 
                  v-model.number="giftCardAmount" 
                  type="number" 
                  step="0.01"
                  label="Monto Cubierto (Bs.)" 
                  variant="outlined" 
                  density="comfortable" 
                  prefix="Bs." 
                  hide-details
                />
              </VCol>
            </VRow>
            <div class="text-caption text-medium-emphasis">
              Por norma del SIN, el pago con Gift Card permite registrar la factura sin requerir pago adicional en efectivo al comprador (monto a pagar 0 Bs).
            </div>
          </div>

          <!-- 6. PAGO POSTERIOR / VENTA A CRÉDITO (Código 35) -->
          <div v-if="paymentMethod === 35" class="post-pay-box pa-3 rounded-xl mb-3 border">
            <div class="d-flex align-center text-primary mb-1">
              <VIcon icon="ri-calendar-schedule-line" size="22" class="me-2" />
              <span class="text-subtitle-2 font-weight-bold">Modalidad: Pago Posterior (Venta a Crédito)</span>
            </div>
            <div class="text-caption text-medium-emphasis">
              La factura se emite bajo la modalidad de cobro a plazo / posterior. No se genera ingreso de efectivo físico inmediato en la caja del turno.
            </div>
          </div>

          <!-- DATOS FISCALES SIAT (FACTURA VS NOTA DE VENTA) -->
          <div class="fiscal-settings-box pa-3 rounded-xl border bg-surface mb-2">
            <div class="d-flex align-center justify-space-between mb-2">
              <div class="d-flex align-center">
                <VIcon :icon="isInvoice ? 'ri-file-text-line' : 'ri-receipt-line'" size="18" class="me-1 text-primary" />
                <span class="text-subtitle-2 font-weight-bold">{{ isInvoice ? 'Emitir Factura Oficial SIAT' : 'Guardar como Recibo / Nota de Venta (No Facturado)' }}</span>
              </div>
              <VSwitch v-model="isInvoice" color="primary" density="compact" hide-details inset />
            </div>

            <!-- Switch de Contingencia / Fuera de Línea (RND 102100000011) -->
            <div v-if="isInvoice" class="mt-2 pt-2 border-t d-flex align-center justify-space-between">
              <div class="d-flex align-center">
                <VIcon icon="ri-wifi-off-line" :color="isOfflineMode ? 'error' : 'medium-emphasis'" size="16" class="me-1" />
                <span class="text-caption font-weight-medium" :class="isOfflineMode ? 'text-error font-weight-bold' : ''">
                  Modo Contingencia (Fuera de Línea)
                </span>
              </div>
              <VSwitch v-model="isOfflineMode" color="error" density="compact" hide-details inset />
            </div>

            <VAlert
              v-if="isInvoice && isOfflineMode"
              type="error"
              variant="tonal"
              density="compact"
              class="mt-2 text-caption pa-2"
              icon="ri-information-line"
            >
              <strong>Facturación Fuera de Línea activa:</strong> Se generará el CUF con Código Emisión 2 y la 3ra leyenda oficial de contingencia. La factura quedará encolada para transmisión en paquete (.tar.gz).
            </VAlert>

            <!-- Opciones de Talonario Manual con CAFC (RND 102100000011) -->
            <VRow v-if="isInvoice && isOfflineMode" density="compact" class="mt-2">
              <VCol cols="12" sm="7">
                <VTextField
                  v-model="manualCafc"
                  label="Código CAFC (Talonario Manual)"
                  placeholder="Ej: 1013E35BF1B5C"
                  variant="outlined"
                  density="compact"
                  clearable
                  prepend-inner-icon="ri-barcode-box-line"
                  hint="Opcional: Si se transcribe de talonario preimpreso"
                  persistent-hint
                />
              </VCol>
              <VCol cols="12" sm="5">
                <VTextField
                  v-model="manualInvoiceNumber"
                  label="N° Factura Manual"
                  placeholder="Ej: 154"
                  type="number"
                  variant="outlined"
                  density="compact"
                  clearable
                  prepend-inner-icon="ri-hashtag"
                  hint="N° físico del talonario"
                  persistent-hint
                />
              </VCol>
            </VRow>

            <!-- Campos Fiscales SIAT (Habilitados si es Factura, Deshabilitados si es Recibo) -->
            <div :class="{ 'opacity-50 pointer-events-none': !isInvoice }">
              <VRow density="compact" class="mt-1">
                <!-- Accesos Rápidos de Tipo de Cliente Normado -->
                <VCol cols="12">
                  <div class="d-flex gap-1 flex-wrap mb-1">
                    <VChip size="small" variant="tonal" color="info" prepend-icon="ri-shield-check-line" style="cursor: pointer;" @click="setFiscalPreset('control')">
                      Control Tributario (99002)
                    </VChip>
                    <VChip size="small" variant="tonal" color="secondary" prepend-icon="ri-shopping-basket-line" style="cursor: pointer;" @click="setFiscalPreset('menores')">
                      Ventas Menores (99003)
                    </VChip>
                    <VChip size="small" variant="tonal" color="warning" prepend-icon="ri-global-line" style="cursor: pointer;" @click="setFiscalPreset('especial')">
                      Casos Especiales (99001)
                    </VChip>
                    <VChip size="small" variant="tonal" color="primary" prepend-icon="ri-user-line" style="cursor: pointer;" @click="setFiscalPreset('ci')">
                      Con Cédula (CI)
                    </VChip>
                  </div>
                </VCol>

                <!-- Tipo de Documento -->
                <VCol cols="12" sm="6">
                  <VSelect 
                    v-model="documentType" 
                    :items="documentTypesList"
                    item-title="title"
                    item-value="value"
                    label="Tipo de Documento Identidad *"
                    variant="outlined" 
                    density="compact" 
                    :disabled="!isInvoice"
                    hide-details
                  />
                </VCol>

                <!-- NIT / CI -->
                <VCol cols="12" sm="4">
                  <VTextField 
                    v-model="nitCi" 
                    label="NIT / CI *" 
                    variant="outlined" 
                    density="compact" 
                    :disabled="!isInvoice"
                    hide-details
                    @keypress="onNitCiKeypress"
                  />
                </VCol>

                <!-- Complemento -->
                <VCol cols="12" sm="2">
                  <VTextField 
                    v-model="complement" 
                    label="Compl." 
                    placeholder="1A"
                    variant="outlined" 
                    density="compact" 
                    :disabled="!isInvoice"
                    hide-details
                  />
                </VCol>

                <!-- Razón Social -->
                <VCol cols="12" sm="6">
                  <VTextField 
                    v-model="businessName" 
                    label="Razón Social / Nombre *" 
                    variant="outlined" 
                    density="compact" 
                    :disabled="!isInvoice"
                    hide-details
                  />
                </VCol>

                <!-- Correo Cliente (Para Envío Automático) -->
                <VCol cols="12" sm="6">
                  <VTextField 
                    v-model="customerEmail" 
                    type="email"
                    label="Correo Electrónico (Para XML/PDF)" 
                    placeholder="cliente@correo.com"
                    variant="outlined" 
                    density="compact" 
                    prepend-inner-icon="ri-mail-line"
                    :disabled="!isInvoice"
                    hide-details
                  />
                </VCol>

                <!-- Código de Excepción (Art. 39 RND 102100000011) -->
                <VCol cols="12" class="pt-1">
                  <div class="d-flex align-center justify-space-between px-3 py-2 rounded-lg bg-amber-lighten-5 border border-warning border-opacity-25">
                    <div class="d-flex align-center me-2">
                      <VIcon icon="ri-shield-keyhole-line" size="20" color="warning" class="me-2 flex-shrink-0" />
                      <div>
                        <div class="text-caption font-weight-bold text-high-emphasis lh-tight">Código de Excepción (Excepción Tributaria = 1)</div>
                        <div class="text-caption text-medium-emphasis" style="font-size: 11px !important; line-height: 1.2;">
                          Activar si el NIT no figura o fue observado en el Padrón del SIN (Art. 39 RND 102100000011)
                        </div>
                      </div>
                    </div>
                    <VSwitch v-model="hasExceptionCode" color="warning" density="compact" hide-details inset :disabled="!isInvoice" />
                  </div>
                </VCol>
              </VRow>
            </div>
          </div>
        </VCardText>

        <!-- 3. Acciones del Modal (Fijas al Pie) -->
        <VCardActions class="pa-3 px-4 modal-actions-footer border-t flex-shrink-0 d-flex justify-space-between align-center">
          <VBtn variant="tonal" color="secondary" class="font-weight-bold px-4" @click="isCheckoutModalOpen = false">
            Regresar al Carrito
          </VBtn>

          <VBtn 
            color="success" 
            size="large" 
            elevation="4" 
            class="font-weight-black text-white px-6 rounded-lg"
            :loading="isFetching" 
            :disabled="isFetching || (paymentMethod === 1 && change < 0)"
            @click="checkout"
          >
            <VIcon start icon="ri-checkbox-circle-line" />
            <span>{{ isInvoice ? 'CONFIRMAR Y EMITIR FACTURA' : 'CONFIRMAR NOTA DE VENTA' }}</span>
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- MODAL CLIENTE NUEVO -->
    <ClienteAddDialog v-model:isDialogVisible="isAddClientDialog" @addCliente="handleCustomerSaved" />

    <!-- MODAL APERTURA DE CAJA -->
    <VDialog v-model="isOpenCashDialog" max-width="420">
      <VCard class="rounded-xl">
        <DialogCloseBtn variant="text" size="default" @click="isOpenCashDialog = false" />
        <VCardTitle class="pa-4 bg-primary text-white d-flex align-center font-weight-bold">
          <VIcon icon="ri-safe-2-line" class="me-2" />
          Aperturar Turno de Caja
        </VCardTitle>
        <VCardText class="pt-5 pb-3">
          <VSelect
            v-model="openCashData.cash_register_id"
            :items="cashRegistersList"
            item-title="name"
            item-value="id"
            label="Seleccionar Caja Física"
            variant="outlined"
            density="comfortable"
            class="mb-3"
          />
          <VTextField
            v-model.number="openCashData.opening_amount"
            label="Monto Inicial en Efectivo (Sencillo/Base)"
            type="number"
            min="0"
            variant="outlined"
            density="comfortable"
            prefix="Bs."
          />
        </VCardText>
        <VCardActions class="px-4 pb-4">
          <VSpacer />
          <VBtn variant="tonal" color="secondary" @click="isOpenCashDialog = false">Cancelar</VBtn>
          <VBtn color="primary" :loading="isOpeningCash" @click="handleOpenCashFromPOS" class="font-weight-bold">
            <VIcon start icon="ri-safe-line" /> Abrir Turno
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- MODAL DE RESULTADO POST-VENTA (IMPRESIÓN DIRECTA O ROLL/CARTA) -->
    <VDialog v-model="isResultDialog" max-width="500" persistent>
      <VCard v-if="saleResult" class="pa-4 text-center rounded-xl">
        <VCardTitle class="pt-2">
          <VIcon icon="ri-checkbox-circle-line" color="success" size="56" class="mb-2"/>
          <div class="text-h5 font-weight-bold">
            {{ saleResult.cuf ? '¡Factura Emitida con Éxito!' : '¡Venta Registrada con Éxito!' }}
          </div>
          <div v-if="saleResult.invoice_number" class="text-subtitle-2 text-primary font-weight-bold">
            Factura N° {{ saleResult.invoice_number }}
          </div>
          <div v-if="saleResult.codigo_emision === 2 || saleResult.estado_siat === 'OFFLINE_PENDIENTE'" class="mt-2 d-flex flex-column align-center gap-1">
            <VChip color="error" size="small" class="font-weight-bold" prepend-icon="ri-wifi-off-line">
              MODALIDAD: FUERA DE LÍNEA (CONTINGENCIA)
            </VChip>
            <VChip v-if="saleResult.cafc" color="warning" size="small" class="font-weight-bold mt-1" prepend-icon="ri-barcode-box-line">
              CAFC: {{ saleResult.cafc }}
            </VChip>
          </div>
        </VCardTitle>
        
        <VCardText class="pb-2">
          <p class="text-body-2 text-medium-emphasis mb-3">
            Seleccione el formato de impresión deseado para entregar al cliente:
          </p>
          
          <div v-if="saleResult.cuf" class="bg-primary-lighten-5 pa-3 rounded-lg border border-primary border-dashed mb-4 text-start">
            <div class="text-caption text-uppercase font-weight-bold text-primary mb-1">CÓDIGO CUF (SIAT PILOTO)</div>
            <div class="text-caption font-weight-bold text-break text-high-emphasis font-mono">{{ saleResult.cuf }}</div>
          </div>

          <VRow class="gap-2" no-gutters>
            <!-- Si tiene Factura SIAT -->
            <template v-if="saleResult.cuf">
              <VCol cols="12" sm="6" class="pa-1">
                 <VBtn 
                   :href="`/api/v1/sales/${saleResult.id}/print/thermal`" 
                   target="_blank" 
                   block 
                   color="secondary" 
                   size="large" 
                   prepend-icon="ri-receipt-line"
                   class="font-weight-bold"
                 >
                   Ticket 80mm (Rollo)
                 </VBtn>
              </VCol>
              <VCol cols="12" sm="6" class="pa-1">
                 <VBtn 
                   :href="`/api/v1/sales/${saleResult.id}/print/letter`" 
                   target="_blank" 
                   block 
                   color="primary" 
                   size="large" 
                   prepend-icon="ri-file-text-line"
                   class="font-weight-bold"
                 >
                   Factura Carta (A4)
                 </VBtn>
              </VCol>
            </template>

            <!-- Si es Recibo simple / Nota de Venta -->
            <VCol v-else cols="12" class="pa-1">
                 <VBtn 
                   :href="`/api/v1/sales/${saleResult.id}/print/thermal`" 
                   target="_blank" 
                   block 
                   color="secondary" 
                   size="large" 
                   prepend-icon="ri-receipt-line"
                   class="font-weight-bold"
                 >
                   Imprimir Recibo Térmico
                 </VBtn>
            </VCol>

            <VCol cols="12" class="pa-1 mt-2">
               <VBtn block variant="tonal" size="large" class="font-weight-bold" @click="isResultDialog = false">
                 <VIcon start icon="ri-add-line" /> Nueva Venta (Siguiente Cliente)
               </VBtn>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.pos-wrapper {
  height: calc(100vh - 75px);
  overflow: hidden;
  background-color: rgb(var(--v-theme-background));
}

.pos-main-row {
  height: 100%;
}

.pos-catalog-col {
  height: 100%;
  overflow: hidden;
}

.pos-cart-col {
  height: 100%;
  overflow: hidden;
}

.catalog-header-bar {
  background-color: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

/* Product Cards */
.pos-card-med {
  background-color: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px;
  padding: 8px;
  cursor: pointer;
  position: relative;
  transition: all 0.2s ease;
  display: flex;
  flex-direction: column;
  height: 100%;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
}

.pos-card-med:hover:not(.is-disabled) {
  transform: translateY(-2px);
  border-color: rgb(var(--v-theme-primary));
  box-shadow: 0 6px 14px rgba(102, 108, 255, 0.2);
}

.pos-card-med.is-in-cart {
  border-color: rgb(var(--v-theme-primary));
  background-color: rgba(var(--v-theme-primary), 0.12);
}

.pos-card-med.is-disabled {
  opacity: 0.5;
  cursor: not-allowed;
  filter: grayscale(0.6);
}

.card-badge-top {
  position: absolute;
  top: 12px;
  right: 12px;
  z-index: 2;
}

.stock-tag {
  font-size: 0.65rem;
  font-weight: 800;
  padding: 2px 6px;
  border-radius: 8px;
  letter-spacing: 0.3px;
  text-transform: uppercase;
}

.stock-ok { background: #dcfce7; color: #166534; }
.stock-low { background: #fef9c3; color: #854d0e; }
.stock-out { background: #fee2e2; color: #991b1b; }
.stock-expired { background: #fee2e2; color: #991b1b; }

.card-img-wrapper {
  width: 100%;
  border-radius: 8px;
  overflow: hidden;
  background: rgba(var(--v-theme-on-surface), 0.04);
}

.card-body-wrapper {
  padding-top: 6px;
  display: flex;
  flex-direction: column;
  flex-grow: 1;
}

.card-lab-name {
  font-size: 0.68rem;
  font-weight: 700;
  color: #26c6f9;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.card-title-name {
  font-size: 0.82rem;
  font-weight: 700;
  color: rgb(var(--v-theme-on-surface));
  line-height: 1.25;
  margin: 2px 0 4px 0;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  min-height: 2.5em;
}

.card-price-txt {
  font-size: 0.95rem;
  font-weight: 900;
  color: #72e128;
}

.btn-quick-add {
  width: 26px;
  height: 26px;
  border-radius: 6px;
  border: none;
  background: rgb(var(--v-theme-primary));
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-quick-add:hover:not(:disabled) {
  opacity: 0.9;
  transform: scale(1.1);
}

/* Cart Container */
.cart-card-container {
  background-color: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  overflow: hidden;
}

.client-select-bar {
  background-color: rgba(var(--v-theme-on-surface), 0.03);
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.cart-table-wrapper {
  scrollbar-width: thin;
}
.cart-table-wrapper::-webkit-scrollbar {
  width: 4px;
}
.cart-table-wrapper::-webkit-scrollbar-thumb {
  background: rgba(var(--v-theme-on-surface), 0.2);
  border-radius: 4px;
}

.empty-cart-state {
  height: 100%;
  min-height: 200px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 40px 10px;
  opacity: 0.7;
}

.cart-items-table {
  width: 100%;
  border-collapse: collapse;
}

.cart-th-row th {
  padding: 6px 8px;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  color: rgba(var(--v-theme-on-surface), 0.7);
  background-color: rgba(var(--v-theme-on-surface), 0.04);
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.cart-tr-row td {
  padding: 6px 8px;
  border-bottom: 1px solid rgba(var(--v-border-color), 0.06);
  vertical-align: middle;
}

.item-table-name {
  font-size: 0.78rem;
  font-weight: 700;
  color: rgb(var(--v-theme-on-surface));
  line-height: 1.2;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.item-table-lab {
  font-size: 0.65rem;
  font-weight: 600;
  color: #26c6f9;
}

.stepper-box {
  display: inline-flex;
  align-items: center;
  background: rgba(var(--v-theme-on-surface), 0.06);
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 16px;
  padding: 2px 4px;
  gap: 2px;
}

.stepper-btn {
  width: 18px;
  height: 18px;
  border-radius: 50%;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgb(var(--v-theme-surface));
  color: rgb(var(--v-theme-on-surface));
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.stepper-input {
  width: 26px;
  border: none;
  background: transparent;
  text-align: center;
  font-size: 0.78rem;
  font-weight: 800;
  color: rgb(var(--v-theme-on-surface));
}
.stepper-input:focus { outline: none; }

.btn-del-row {
  border: none;
  background: transparent;
  color: #ff4d49;
  cursor: pointer;
  padding: 0;
  opacity: 0.8;
}
.btn-del-row:hover { opacity: 1; }

.cart-bottom-checkout {
  background-color: rgba(var(--v-theme-on-surface), 0.03);
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.discount-mini-input {
  width: 100%;
  padding: 2px 6px;
  border: 1px dashed #fdb528;
  border-radius: 6px;
  background: rgba(253, 181, 40, 0.1);
  font-size: 0.8rem;
  font-weight: 700;
  color: #fdb528;
  text-align: right;
}
.discount-mini-input:focus {
  outline: none;
  border-color: #e4a324;
}

.btn-open-payment {
  letter-spacing: 0.5px;
  height: 46px !important;
}

/* Modal de Pago */
.checkout-modal-body::-webkit-scrollbar {
  width: 5px;
}
.checkout-modal-body::-webkit-scrollbar-thumb {
  background: rgba(var(--v-theme-on-surface), 0.2);
  border-radius: 4px;
}

.modal-payment-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}

.modal-payment-tile {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 10px 6px;
  background: rgb(var(--v-theme-surface));
  border: 2px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.2s ease;
  color: rgb(var(--v-theme-on-surface));
}

.modal-payment-tile:hover {
  border-color: rgb(var(--v-theme-primary));
}

.modal-payment-tile.active {
  background: rgba(var(--v-theme-primary), 0.12);
  border-color: rgb(var(--v-theme-primary));
  color: rgb(var(--v-theme-primary));
}

.pm-icon {
  color: inherit;
}

.pm-title {
  font-size: 0.78rem;
  font-weight: 700;
  color: inherit;
}

.pm-desc {
  font-size: 0.65rem;
  color: rgba(var(--v-theme-on-surface), 0.7);
  text-align: center;
}

.modal-payment-tile.active .pm-desc {
  color: rgb(var(--v-theme-primary));
  font-weight: 600;
}

.change-ok { background: rgba(114, 225, 40, 0.15); border-color: #72e128; color: #72e128; }
.change-err { background: rgba(255, 77, 73, 0.15); border-color: #ff4d49; color: #ff4d49; }

.pointer-events-none {
  pointer-events: none;
}

.modal-actions-footer {
  background-color: rgba(var(--v-theme-on-surface), 0.03);
  border-color: rgba(var(--v-border-color), var(--v-border-opacity));
}

.cash-calc-box,
.combined-calc-box,
.gift-card-box,
.post-pay-box,
.qr-info-box,
.fiscal-settings-box,
.card-info-box {
  background-color: rgba(var(--v-theme-on-surface), 0.03);
  border-color: rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>