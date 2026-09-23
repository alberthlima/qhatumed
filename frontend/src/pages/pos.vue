<script setup>
import { ref, computed, onMounted, watch } from 'vue'
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
const categoryFilter = ref('')
const cashTendered = ref(0)
const discount = ref(0)
const saleDate = ref(new Date().toISOString().slice(0, 10))

// Datos
const products = ref([])
const categories = ref([])
const customers = ref([])
const selectedCustomer = ref(null)
const isFetching = ref(false)

// Facturación SIAT
const isInvoice = ref(true)
const businessName = ref('CONTROL TRIBUTARIO')
const nitCi = ref('0')
const paymentMethod = ref(1) // 1: Efectivo, 2: Tarjeta, 7: QR
const documentType = ref(1)   // 1: CI
const isAddClientDialog = ref(false)
const isFiscalAccordionOpen = ref(false)

// Modal de Resultado
const isResultDialog = ref(false)
const saleResult = ref(null)

// Métodos de Pago visuales
const paymentMethodsList = [
  { value: 1, title: 'Efectivo', icon: 'ri-money-dollar-circle-line' },
  { value: 2, title: 'Tarjeta', icon: 'ri-bank-card-line' },
  { value: 7, title: 'QR / Transfer.', icon: 'ri-qr-code-line' }
]

// ── DATA FETCHING ──

const fetchProducts = async (search = '') => {
  try {
    isFetching.value = true
    const response = await $api(`/v1/products?per_page=100&search=${encodeURIComponent(search)}`)
    products.value = response.data || response || []
    
    // Extraer categorías únicas en carga inicial
    if (!search && categories.value.length === 0) {
      const cats = new Set()
      products.value.forEach(p => { if (p.category?.name) cats.add(p.category.name) })
      categories.value = Array.from(cats)
    }
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

// ── LÓGICA DE NEGOCIO ──

const filteredProducts = computed(() => {
  const query = (searchQuery.value || '').toLowerCase()
  return products.value.filter(p => {
    const name = (p.name || '').toLowerCase()
    const barcode = (p.barcode || '').toLowerCase()
    const matchesSearch = name.includes(query) || barcode.includes(query)
    const matchesCategory = categoryFilter.value ? (p.category && p.category.name === categoryFilter.value) : true
    return matchesSearch && matchesCategory
  })
})

const subtotal = computed(() => {
  return cart.value.reduce((total, item) => total + (Number(item.sale_price || 0) * item.quantity), 0)
})

const total = computed(() => {
  return Math.max(0, subtotal.value - discount.value)
})

const change = computed(() => {
  return cashTendered.value > 0 ? Math.max(0, cashTendered.value - total.value) : 0
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
      documentType.value = customer.document_type || 1
    }
  } else {
    businessName.value = 'CONTROL TRIBUTARIO'
    nitCi.value = '0'
    documentType.value = 1
  }
})

// Auto-completar cashTendered si es efectivo y total cambia
watch(total, (newTotal) => {
  if (paymentMethod.value === 1 && (cashTendered.value === 0 || cashTendered.value < newTotal)) {
    cashTendered.value = newTotal
  }
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
    cart.value.push({ ...product, quantity: 1 })
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

const removeItem = (index) => {
  cart.value.splice(index, 1)
}

const clearCart = () => {
  cart.value = []
  cashTendered.value = 0
  discount.value = 0
}

const checkout = async () => {
  if (cart.value.length === 0) return

  const payload = {
    customer_id: selectedCustomer.value,
    business_name: businessName.value || 'CONTROL TRIBUTARIO',
    nit_ci: nitCi.value || '0',
    sale_date: saleDate.value,
    total_amount: total.value,
    cash_tendered: cashTendered.value || total.value,
    change: change.value,
    payment_method_code: paymentMethod.value,
    document_type_code: documentType.value,
    is_invoice: isInvoice.value,
    items: cart.value.map(item => ({
      product_id: item.id,
      quantity: item.quantity,
      unit_price: item.sale_price,
      item_discount: 0
    }))
  }

  try {
    isFetching.value = true
    const response = await $api('/v1/sales', {
      method: 'POST',
      body: payload
    })

    saleResult.value = response.data || response
    isResultDialog.value = true
    
    // Limpiar carrito y resetear
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

onMounted(() => {
  fetchProducts()
  fetchCustomers()
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
      
      <!-- ========================================== -->
      <!-- COLUMNA IZQUIERDA / CENTRAL: CATÁLOGO Y BÚSQUEDA (68% en Desktop) -->
      <!-- ========================================== -->
      <VCol cols="12" md="7" lg="8" class="pos-catalog-col pa-3 d-flex flex-column">
        
        <!-- BARRA SUPERIOR DE BÚSQUEDA Y USUARIO -->
        <div class="catalog-header-card pa-3 bg-surface rounded-xl border mb-3 shadow-xs">
          <VRow align="center" no-gutters class="gap-2">
            <VCol class="flex-grow-1">
              <VTextField
                v-model="searchQuery"
                prepend-inner-icon="ri-search-line"
                placeholder="Buscar por medicamento, principio activo o código de barras..."
                variant="outlined"
                density="comfortable"
                hide-details
                clearable
                autofocus
                class="search-pos-input rounded-lg"
              />
            </VCol>

            <VCol cols="auto" class="d-flex align-center gap-2">
              <VChip 
                v-if="hasOpenSession" 
                color="success" 
                variant="tonal" 
                size="small" 
                class="font-weight-bold"
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
            </VCol>
          </VRow>
        </div>

        <!-- CATEGORÍAS EN PILLS / CAROUSEL -->
        <div class="categories-bar mb-3">
          <div class="d-flex align-center gap-2 overflow-x-auto py-1 px-1 categories-scroll">
            <button
              class="category-pill"
              :class="{ active: categoryFilter === '' }"
              @click="categoryFilter = ''"
            >
              <VIcon icon="ri-apps-2-line" size="18" class="me-1" />
              <span>Todos</span>
            </button>

            <button
              v-for="cat in categories"
              :key="cat"
              class="category-pill"
              :class="{ active: categoryFilter === cat }"
              @click="categoryFilter = cat"
            >
              <VIcon icon="ri-capsule-line" size="18" class="me-1" />
              <span>{{ cat }}</span>
            </button>
          </div>
        </div>

        <!-- GRILLA DE MEDICAMENTOS / PRODUCTOS -->
        <div class="product-grid-container flex-grow-1 overflow-y-auto px-1">
          <VRow v-if="!isFetching && filteredProducts.length > 0" class="ma-n1">
            <VCol 
              v-for="item in filteredProducts" 
              :key="item.id" 
              cols="12" 
              sm="6" 
              md="4" 
              lg="4" 
              xl="3" 
              class="pa-1"
            >
              <div 
                class="pos-product-card"
                :class="{ 
                  'is-disabled': item.stock <= 0 || item.is_expired,
                  'is-in-cart': cart.some(c => c.id === item.id)
                }"
                @click="addToCart(item)"
              >
                <!-- Badge de Stock Flotante -->
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

                <!-- Imagen o Thumbnail -->
                <div class="product-img-box">
                  <VImg 
                    :src="item.image_path ? `/storage/${item.image_path}` : 'https://placehold.co/300x200/E8F5E9/009688?text='+encodeURIComponent(item.name.substring(0,6))" 
                    height="100" 
                    cover
                    class="rounded-lg"
                  />
                </div>

                <!-- Info del Producto -->
                <div class="product-info-box">
                  <div class="product-lab-tag">{{ item.laboratory?.name || 'GENÉRICO' }}</div>
                  <h4 class="product-name" :title="item.name">{{ item.name }}</h4>
                  
                  <div class="d-flex justify-space-between align-center mt-2 pt-1 border-t">
                    <span class="product-price">Bs. {{ Number(item.sale_price || 0).toFixed(2) }}</span>
                    <button 
                      class="btn-add-item" 
                      :disabled="item.stock <= 0 || item.is_expired"
                      title="Agregar al carrito"
                    >
                      <VIcon icon="ri-add-line" size="18" />
                    </button>
                  </div>
                </div>
              </div>
            </VCol>
          </VRow>

          <!-- Vacío o Buscando -->
          <div v-else-if="!isFetching && filteredProducts.length === 0" class="empty-products-state">
            <VIcon icon="ri-inbox-line" size="56" class="text-disabled mb-2" />
            <div class="text-h6 font-weight-bold text-medium-emphasis">No se encontraron medicamentos</div>
            <p class="text-caption text-disabled">Intente con otro término de búsqueda o categoría.</p>
          </div>

          <!-- Spinner de Carga -->
          <div v-else class="h-100 d-flex align-center justify-center py-12">
            <VProgressCircular indeterminate color="primary" size="56" />
          </div>
        </div>
      </VCol>

      <!-- ========================================== -->
      <!-- COLUMNA DERECHA: ORDEN / CARRITO / CHECKOUT (32% en Desktop - "Bills") -->
      <!-- ========================================== -->
      <VCol cols="12" md="5" lg="4" class="pos-cart-col pa-3 d-flex flex-column h-100">
        <div class="cart-receipt-card h-100 d-flex flex-column bg-surface rounded-xl border shadow-sm">
          
          <!-- CABECERA DEL CARRITO -->
          <div class="cart-receipt-header px-4 py-3 bg-primary text-white d-flex justify-space-between align-center rounded-t-xl">
            <div class="d-flex align-center">
              <VIcon icon="ri-receipt-line" size="22" class="me-2" />
              <span class="text-subtitle-1 font-weight-bold text-white">Venta Actual</span>
            </div>
            
            <div class="d-flex align-center gap-2">
              <VChip color="white" text-color="primary" size="small" class="font-weight-black">
                {{ cart.length }} ítems
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

          <!-- AVISO DE CAJA CERRADA -->
          <div v-if="!isLoadingSession && !hasOpenSession" class="pa-3 bg-error-lighten-5 border-b text-center">
            <VIcon icon="ri-lock-line" size="28" color="error" class="mb-1" />
            <div class="text-caption font-weight-bold text-error">Caja Cerrada</div>
            <VBtn color="error" size="x-small" block class="mt-1 font-weight-bold" @click="openCashDialog">
              Aperturar Turno
            </VBtn>
          </div>

          <!-- LISTA DE ÍTEMS EN EL CARRITO -->
          <div class="cart-items-scroll flex-grow-1 overflow-y-auto pa-3">
            <div v-if="cart.length === 0" class="cart-empty-box">
              <VIcon icon="ri-shopping-cart-2-line" size="48" class="text-disabled mb-2" />
              <div class="text-body-2 font-weight-medium text-medium-emphasis">El carrito está vacío</div>
              <span class="text-caption text-disabled">Seleccione medicamentos del catálogo</span>
            </div>

            <div v-else class="cart-items-list">
              <div 
                v-for="(item, index) in cart" 
                :key="item.id" 
                class="cart-row-card mb-2 pa-2 rounded-lg border bg-surface d-flex align-center justify-space-between"
              >
                <div class="cart-item-desc flex-grow-1 pe-2">
                  <div class="cart-item-name">{{ item.name }}</div>
                  <div class="cart-item-unit-price">
                    Bs. {{ Number(item.sale_price || 0).toFixed(2) }}
                  </div>
                </div>

                <!-- Control de Cantidad (+ / -) -->
                <div class="cart-item-stepper d-flex align-center gap-1">
                  <button class="stepper-btn" @click="updateQuantity(item, -1)">
                    <VIcon icon="ri-subtract-line" size="14" />
                  </button>
                  <span class="stepper-val">{{ item.quantity }}</span>
                  <button class="stepper-btn" @click="updateQuantity(item, 1)">
                    <VIcon icon="ri-add-line" size="14" />
                  </button>
                </div>

                <!-- Subtotal y Eliminar -->
                <div class="cart-item-subtotal text-end ps-2">
                  <div class="subtotal-val">Bs. {{ (item.quantity * Number(item.sale_price || 0)).toFixed(2) }}</div>
                  <button class="btn-remove-row" @click="removeItem(index)" title="Quitar">
                    <VIcon icon="ri-close-circle-line" size="16" />
                  </button>
                </div>
              </div>
            </div>
          </div>

          <VDivider />

          <!-- SECCIÓN DE FACTURACIÓN Y CLIENTE (COMPACTA) -->
          <div class="cart-customer-section px-3 py-2 bg-grey-lighten-5">
            <!-- Toggle Factura / Recibo -->
            <div class="d-flex align-center justify-space-between mb-2">
              <div class="d-flex align-center">
                <VIcon :icon="isInvoice ? 'ri-file-text-line' : 'ri-receipt-line'" size="18" class="me-1 text-primary" />
                <span class="text-caption font-weight-bold">{{ isInvoice ? 'Factura SIAT' : 'Nota de Venta' }}</span>
              </div>
              <VSwitch 
                v-model="isInvoice" 
                color="primary" 
                density="compact" 
                hide-details 
                inset
              />
            </div>

            <!-- Cliente y Datos Fiscales -->
            <div class="d-flex align-center gap-2 mb-2">
              <VAutocomplete
                v-model="selectedCustomer"
                :items="customers"
                item-title="first_name"
                item-value="id"
                placeholder="Cliente (Opcional)"
                density="compact"
                variant="outlined"
                hide-details
                prepend-inner-icon="ri-user-line"
                class="flex-grow-1 compact-input"
              >
                <template #item="{ props, item }">
                  <VListItem 
                    v-bind="props" 
                    :title="`${item.raw.first_name || ''} ${item.raw.last_name || ''}`"
                    :subtitle="`NIT/CI: ${item.raw.document_number || 'S/N'}`"
                  />
                </template>
              </VAutocomplete>

              <VBtn 
                icon="ri-user-add-line" 
                size="small" 
                color="primary" 
                variant="tonal" 
                title="Nuevo Cliente"
                @click="isAddClientDialog = true"
              />
            </div>

            <!-- Si es Factura: Razón Social y NIT -->
            <div v-if="isInvoice" class="fiscal-inputs-box">
              <VRow no-gutters class="gap-1 mb-1">
                <VCol>
                  <VTextField 
                    v-model="businessName" 
                    placeholder="Razón Social" 
                    density="compact" 
                    variant="outlined" 
                    hide-details 
                    class="compact-input"
                  />
                </VCol>
                <VCol cols="5">
                  <VTextField 
                    v-model="nitCi" 
                    placeholder="NIT / CI" 
                    density="compact" 
                    variant="outlined" 
                    hide-details 
                    class="compact-input"
                  />
                </VCol>
              </VRow>
            </div>
          </div>

          <VDivider />

          <!-- MÉTODOS DE PAGO (TILES ESTILO FIGMA) -->
          <div class="px-3 pt-2 pb-1 bg-surface">
            <div class="text-caption font-weight-bold text-medium-emphasis mb-1">Método de Pago</div>
            <div class="payment-tiles-grid d-flex gap-2">
              <div 
                v-for="pm in paymentMethodsList" 
                :key="pm.value"
                class="payment-tile"
                :class="{ active: paymentMethod === pm.value }"
                @click="paymentMethod = pm.value"
              >
                <VIcon :icon="pm.icon" size="18" class="mb-1" />
                <span class="payment-tile-text">{{ pm.title }}</span>
              </div>
            </div>
          </div>

          <!-- TOTALES Y PAGO EN EFECTIVO -->
          <div class="cart-footer-box pa-3 bg-surface border-t">
            <div class="d-flex justify-space-between align-center mb-1">
              <span class="text-caption text-medium-emphasis">Subtotal:</span>
              <span class="text-body-2 font-weight-bold">Bs. {{ subtotal.toFixed(2) }}</span>
            </div>

            <div class="d-flex justify-space-between align-center mb-2">
              <span class="text-subtitle-1 font-weight-black text-primary">TOTAL:</span>
              <span class="text-h5 font-weight-black text-primary">Bs. {{ total.toFixed(2) }}</span>
            </div>

            <!-- Efectivo: Paga Con / Cambio -->
            <div v-if="paymentMethod === 1" class="d-flex gap-2 mb-3">
              <VTextField 
                v-model.number="cashTendered" 
                type="number" 
                label="Paga con" 
                density="compact" 
                variant="outlined" 
                hide-details 
                prefix="Bs." 
                class="compact-input"
              />
              <VTextField 
                :value="change.toFixed(2)" 
                label="Cambio" 
                readonly 
                density="compact" 
                variant="outlined" 
                hide-details 
                prefix="Bs." 
                class="compact-input text-success font-weight-bold"
              />
            </div>

            <!-- BOTÓN PROCESAR VENTA -->
            <VBtn 
              block 
              color="success" 
              size="large" 
              elevation="4" 
              class="btn-checkout font-weight-bold text-white rounded-xl"
              :disabled="cart.length === 0 || isFetching || !hasOpenSession" 
              :loading="isFetching"
              @click="checkout"
            >
              <VIcon start icon="ri-checkbox-circle-line" size="20" />
              <span>Cobrar Bs. {{ total.toFixed(2) }}</span>
            </VBtn>
          </div>
        </div>
      </VCol>
    </VRow>

    <!-- MODAL CLIENTE NUEVO -->
    <ClienteAddDialog v-model:isDialogVisible="isAddClientDialog" @addCliente="fetchCustomers" />

    <!-- MODAL APERTURA DE CAJA RÁPIDA -->
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

    <!-- MODAL DE RESULTADO DE VENTA / FACTURA -->
    <VDialog v-model="isResultDialog" max-width="480" persistent>
      <VCard v-if="saleResult" class="pa-4 text-center rounded-xl">
        <VCardTitle class="pt-2">
          <VIcon icon="ri-checkbox-circle-line" color="success" size="56" class="mb-2"/>
          <div class="text-h5 font-weight-bold">¡Venta Exitosa!</div>
        </VCardTitle>
        <VCardText class="pb-2">
          <p class="text-body-2 text-medium-emphasis mb-3">El comprobante ha sido registrado correctamente.</p>
          
          <div v-if="saleResult.cuf" class="bg-primary-lighten-5 pa-3 rounded-lg border border-primary border-dashed mb-4 text-start">
            <div class="text-caption text-uppercase font-weight-bold text-primary mb-1">CÓDIGO CUF (SIAT)</div>
            <div class="text-caption font-weight-bold text-break text-high-emphasis font-mono">{{ saleResult.cuf }}</div>
          </div>

          <VRow class="gap-2" no-gutters>
            <template v-if="saleResult.cuf">
              <VCol cols="12" sm="6" class="pa-1">
                 <VBtn :href="`/api/v1/sales/${saleResult.id}/print/thermal`" target="_blank" block color="secondary" size="large" prepend-icon="ri-receipt-line">
                   Ticket 80mm
                 </VBtn>
              </VCol>
              <VCol cols="12" sm="6" class="pa-1">
                 <VBtn :href="`/api/v1/sales/${saleResult.id}/print/letter`" target="_blank" block color="primary" size="large" prepend-icon="ri-file-text-line">
                   Factura Carta
                 </VBtn>
              </VCol>
            </template>
            <VCol v-else cols="12" class="pa-1">
                 <VBtn :href="`/api/v1/sales/${saleResult.id}/print/thermal`" target="_blank" block color="secondary" size="large" prepend-icon="ri-receipt-line">
                   Imprimir Recibo
                 </VBtn>
            </VCol>
            <VCol cols="12" class="pa-1 mt-2">
               <VBtn block variant="tonal" size="large" class="font-weight-bold" @click="isResultDialog = false">
                 Nueva Venta
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
  height: calc(100vh - 80px);
  overflow: hidden;
  background-color: #f4f6f8;
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

/* Categories Pills Bar */
.categories-scroll {
  scrollbar-width: none;
}
.categories-scroll::-webkit-scrollbar {
  display: none;
}

.category-pill {
  display: inline-flex;
  align-items: center;
  padding: 6px 14px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 600;
  color: #475569;
  white-space: nowrap;
  cursor: pointer;
  transition: all 0.2s ease;
}

.category-pill:hover {
  background: #f1f5f9;
  border-color: #cbd5e1;
}

.category-pill.active {
  background: #5c61e6;
  color: #ffffff;
  border-color: #5c61e6;
  box-shadow: 0 4px 10px rgba(92, 97, 230, 0.25);
}

/* Product Cards */
.pos-product-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 8px;
  cursor: pointer;
  position: relative;
  transition: all 0.25s ease;
  display: flex;
  flex-direction: column;
  height: 100%;
}

.pos-product-card:hover:not(.is-disabled) {
  transform: translateY(-3px);
  border-color: #5c61e6;
  box-shadow: 0 8px 18px rgba(0, 0, 0, 0.06);
}

.pos-product-card.is-in-cart {
  border-color: #5c61e6;
  background-color: #fafbff;
}

.pos-product-card.is-disabled {
  opacity: 0.55;
  cursor: not-allowed;
  filter: grayscale(0.5);
}

.card-badge-top {
  position: absolute;
  top: 14px;
  right: 14px;
  z-index: 2;
}

.stock-tag {
  font-size: 0.68rem;
  font-weight: 800;
  padding: 2px 7px;
  border-radius: 10px;
  letter-spacing: 0.3px;
  text-transform: uppercase;
}

.stock-ok { background: #dcfce7; color: #15803d; }
.stock-low { background: #fef9c3; color: #a16207; }
.stock-out { background: #fee2e2; color: #b91c1c; }
.stock-expired { background: #fee2e2; color: #b91c1c; }

.product-img-box {
  width: 100%;
  border-radius: 10px;
  overflow: hidden;
  background: #f8fafc;
}

.product-info-box {
  padding-top: 6px;
  display: flex;
  flex-direction: column;
  flex-grow: 1;
}

.product-lab-tag {
  font-size: 0.68rem;
  font-weight: 700;
  color: #0d9488; /* Emerald Teal */
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.product-name {
  font-size: 0.84rem;
  font-weight: 700;
  color: #1e293b;
  line-height: 1.25;
  margin: 2px 0 0 0;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  min-height: 2.1em;
}

.product-price {
  font-size: 0.95rem;
  font-weight: 900;
  color: #16a34a;
}

.btn-add-item {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  border: none;
  background: #5c61e6;
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-add-item:hover:not(:disabled) {
  background: #474cc7;
  transform: scale(1.1);
}

/* Cart / Bills Column (Right) */
.cart-receipt-card {
  background: #ffffff;
  overflow: hidden;
}

.cart-items-scroll::-webkit-scrollbar {
  width: 4px;
}
.cart-items-scroll::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}

.cart-empty-box {
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 30px 10px;
  opacity: 0.6;
}

.cart-row-card {
  transition: all 0.15s ease;
}

.cart-item-name {
  font-size: 0.8rem;
  font-weight: 700;
  color: #1e293b;
  line-height: 1.2;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.cart-item-unit-price {
  font-size: 0.72rem;
  color: #64748b;
  font-weight: 600;
}

.cart-item-stepper {
  background: #f1f5f9;
  border-radius: 20px;
  padding: 2px 4px;
}

.stepper-btn {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  border: none;
  background: white;
  color: #334155;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.stepper-val {
  font-size: 0.78rem;
  font-weight: 800;
  min-width: 18px;
  text-align: center;
}

.subtotal-val {
  font-size: 0.82rem;
  font-weight: 800;
  color: #0f172a;
}

.btn-remove-row {
  border: none;
  background: transparent;
  color: #ef4444;
  cursor: pointer;
  padding: 0;
  opacity: 0.7;
}
.btn-remove-row:hover { opacity: 1; }

/* Payment Tiles */
.payment-tile {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 6px 4px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.payment-tile:hover {
  border-color: #cbd5e1;
}

.payment-tile.active {
  background: #eff2fe;
  border-color: #5c61e6;
  color: #5c61e6;
}

.payment-tile-text {
  font-size: 0.68rem;
  font-weight: 700;
  text-align: center;
}

.compact-input :deep(.v-field__input) {
  padding: 4px 8px !important;
  min-height: 32px !important;
  height: 32px !important;
  font-size: 0.8rem !important;
}

.btn-checkout {
  letter-spacing: 0.5px;
  text-transform: uppercase;
}

/* Dark mode overrides */
@media (prefers-color-scheme: dark) {
  .pos-wrapper {
    background-color: #0f172a;
  }
  .category-pill {
    background: #1e293b;
    border-color: #334155;
    color: #cbd5e1;
  }
  .pos-product-card {
    background: #1e293b;
    border-color: #334155;
  }
  .product-name {
    color: #f1f5f9;
  }
  .cart-receipt-card {
    background: #1e293b;
  }
  .cart-row-card {
    background: #0f172a;
    border-color: #334155;
  }
  .cart-item-name {
    color: #f1f5f9;
  }
  .subtotal-val {
    color: #f1f5f9;
  }
  .payment-tile {
    background: #0f172a;
    border-color: #334155;
    color: #94a3b8;
  }
  .payment-tile.active {
    background: #1e1b4b;
    border-color: #6366f1;
    color: #818cf8;
  }
}
</style>