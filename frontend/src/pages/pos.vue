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

// Modal de Cobro / Checkout Profesional
const isCheckoutModalOpen = ref(false)

// Facturación SIAT
const isInvoice = ref(true)
const businessName = ref('CONTROL TRIBUTARIO')
const nitCi = ref('0')
const paymentMethod = ref(1) // 1: Efectivo, 2: Tarjeta, 7: QR
const documentType = ref(1)   // 1: CI
const isAddClientDialog = ref(false)

// Modal de Resultado
const isResultDialog = ref(false)
const saleResult = ref(null)

// Métodos de Pago visuales
const paymentMethodsList = [
  { value: 1, title: 'Efectivo', icon: 'ri-money-dollar-circle-line' },
  { value: 2, title: 'Tarjeta', icon: 'ri-bank-card-line' },
  { value: 7, title: 'QR / Transfer.', icon: 'ri-qr-code-line' }
]

// Billetes rápidos para cambio en efectivo
const quickCashAmounts = [10, 20, 50, 100, 200]

// ── DATA FETCHING ──

const fetchProducts = async (search = '') => {
  try {
    isFetching.value = true
    const response = await $api(`/v1/products?per_page=100&search=${encodeURIComponent(search)}`)
    products.value = response.data || response || []
    
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
}

const openCheckoutModal = () => {
  if (cart.value.length === 0) return
  if (!hasOpenSession.value) {
    openCashDialog()
    return
  }
  cashTendered.value = total.value
  isCheckoutModalOpen.value = true
}

const setQuickCash = (amount) => {
  if (amount === 'exact') {
    cashTendered.value = total.value
  } else {
    cashTendered.value = amount
  }
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
        
        <!-- BUSCADOR SUPERIOR -->
        <div class="catalog-header-bar pa-2 pa-sm-3 bg-surface rounded-xl border mb-2 shadow-xs d-flex align-center gap-2">
          <VTextField
            v-model="searchQuery"
            prepend-inner-icon="ri-search-line"
            placeholder="Buscar por medicamento, principio activo o código de barras (Enter para agregar)..."
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

        <!-- CATEGORÍAS RÁPIDAS -->
        <div class="categories-bar mb-2">
          <div class="d-flex align-center gap-2 overflow-x-auto py-1 categories-scroll">
            <button
              class="category-pill"
              :class="{ active: categoryFilter === '' }"
              @click="categoryFilter = ''"
            >
              <VIcon icon="ri-apps-2-line" size="16" class="me-1" />
              <span>Todos</span>
            </button>

            <button
              v-for="cat in categories"
              :key="cat"
              class="category-pill"
              :class="{ active: categoryFilter === cat }"
              @click="categoryFilter = cat"
            >
              <VIcon icon="ri-capsule-line" size="16" class="me-1" />
              <span>{{ cat }}</span>
            </button>
          </div>
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

          <div v-else-if="!isFetching && filteredProducts.length === 0" class="empty-state-box">
            <VIcon icon="ri-inbox-line" size="48" class="text-disabled mb-1" />
            <div class="text-body-1 font-weight-bold text-medium-emphasis">No se encontraron medicamentos</div>
            <p class="text-caption text-disabled">Intente con otra búsqueda o categoría.</p>
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

          <!-- 2. CLIENTE ASIGNADO (BARRA RÁPIDA SUPERIOR DISCRETA) -->
          <div class="client-quick-bar px-3 py-1 bg-grey-lighten-4 border-b d-flex justify-space-between align-center">
            <div class="d-flex align-center text-truncate pe-2">
              <VIcon icon="ri-user-smile-line" size="16" class="me-1 text-primary" />
              <span class="text-caption font-weight-bold text-high-emphasis text-truncate">
                {{ businessName }} ({{ nitCi }})
              </span>
            </div>
            
            <VBtn 
              size="x-small" 
              variant="tonal" 
              color="primary" 
              class="font-weight-bold px-2 text-none"
              @click="isAddClientDialog = true"
            >
              + Cliente
            </VBtn>
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
                  <th class="text-left" style="min-width: 140px;">Producto</th>
                  <th class="text-center" style="width: 105px;">Cant.</th>
                  <th class="text-right" style="width: 75px;">P. Unit</th>
                  <th class="text-right" style="width: 80px;">Total</th>
                  <th class="text-center" style="width: 32px;"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, index) in cart" :key="item.id" class="cart-tr-row">
                  <!-- Nombre y Lab -->
                  <td class="text-left">
                    <div class="item-table-name" :title="item.name">{{ item.name }}</div>
                    <div class="item-table-lab">{{ item.laboratory?.name || 'GENÉRICO' }}</div>
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
                    Bs. {{ (item.quantity * Number(item.sale_price || 0)).toFixed(2) }}
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

          <!-- 4. TOTALES Y BOTÓN DE COBRO (FIJO INFERIOR) -->
          <div class="cart-bottom-checkout pa-3 bg-grey-lighten-5 border-t">
            <!-- Desglose de Totales -->
            <div class="d-flex justify-space-between align-center mb-1">
              <span class="text-caption text-medium-emphasis">Subtotal:</span>
              <span class="text-body-2 font-weight-bold">Bs. {{ subtotal.toFixed(2) }}</span>
            </div>

            <div class="d-flex justify-space-between align-center mb-3">
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
    <!-- MODAL DE PAGO / CHECKOUT PROFESIONAL (SE ABRE AL COBRAR) -->
    <!-- ======================================================= -->
    <VDialog v-model="isCheckoutModalOpen" max-width="560" persistent>
      <VCard class="rounded-xl overflow-hidden">
        <!-- Cabecera del Modal de Pago -->
        <VCardTitle class="bg-primary text-white d-flex justify-space-between align-center py-3 px-4">
          <div class="d-flex align-center">
            <VIcon icon="ri-hand-coin-line" size="24" class="me-2" />
            <span class="text-subtitle-1 font-weight-bold text-white">Finalizar Venta y Cobro</span>
          </div>
          <VBtn icon="ri-close-line" variant="text" color="white" size="small" @click="isCheckoutModalOpen = false" />
        </VCardTitle>

        <VCardText class="pa-4">
          <!-- Resumen de Monto Total -->
          <div class="total-banner pa-3 rounded-xl bg-primary-lighten-5 border border-primary border-opacity-25 d-flex justify-space-between align-center mb-4">
            <div>
              <span class="text-caption text-uppercase font-weight-bold text-primary">Total a Cobrar</span>
              <div class="text-caption text-medium-emphasis">{{ cart.length }} productos seleccionados</div>
            </div>
            <div class="text-h4 font-weight-black text-primary">
              Bs. {{ total.toFixed(2) }}
            </div>
          </div>

          <!-- 1. Método de Pago (Tiles Visuales Grandes) -->
          <div class="mb-4">
            <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-2">Seleccione Método de Pago</div>
            <div class="d-flex gap-2">
              <div 
                v-for="pm in paymentMethodsList" 
                :key="pm.value"
                class="modal-payment-tile"
                :class="{ active: paymentMethod === pm.value }"
                @click="paymentMethod = pm.value"
              >
                <VIcon :icon="pm.icon" size="24" class="mb-1" />
                <span class="font-weight-bold text-caption">{{ pm.title }}</span>
              </div>
            </div>
          </div>

          <!-- 2. Si es Efectivo: Paga Con + Billetes Rápidos + Cambio -->
          <div v-if="paymentMethod === 1" class="cash-calc-box pa-3 rounded-xl bg-grey-lighten-4 mb-4 border">
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
                <div class="change-display-box pa-2 rounded-lg border text-center" :class="change >= 0 ? 'bg-green-lighten-5 border-green' : 'bg-red-lighten-5'">
                  <div class="text-caption font-weight-bold text-uppercase" :class="change >= 0 ? 'text-green-darken-3' : 'text-error'">
                    {{ change >= 0 ? 'Cambio / Vuelto' : 'Falta Dinero' }}
                  </div>
                  <div class="text-h5 font-weight-black" :class="change >= 0 ? 'text-green-darken-4' : 'text-error'">
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

          <!-- 3. Datos Fiscales (Factura SIAT vs Recibo) -->
          <div class="fiscal-settings-box pa-3 rounded-xl border bg-surface mb-2">
            <div class="d-flex align-center justify-space-between mb-2">
              <div class="d-flex align-center">
                <VIcon :icon="isInvoice ? 'ri-file-text-line' : 'ri-receipt-line'" size="18" class="me-1 text-primary" />
                <span class="text-subtitle-2 font-weight-bold">{{ isInvoice ? 'Emitir Factura Oficial SIAT' : 'Guardar como Recibo / Nota de Venta' }}</span>
              </div>
              <VSwitch v-model="isInvoice" color="primary" density="compact" hide-details inset />
            </div>

            <!-- Cliente Razón Social y NIT -->
            <VRow density="compact" class="mt-1">
              <VCol cols="12" sm="6">
                <VTextField 
                  v-model="businessName" 
                  label="Razón Social / Nombre" 
                  variant="outlined" 
                  density="compact" 
                  hide-details
                />
              </VCol>
              <VCol cols="12" sm="6">
                <VTextField 
                  v-model="nitCi" 
                  label="NIT / CI" 
                  variant="outlined" 
                  density="compact" 
                  hide-details
                />
              </VCol>
            </VRow>
          </div>
        </VCardText>

        <VCardActions class="pa-4 bg-grey-lighten-4 border-t d-flex justify-space-between">
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
            <span>CONFIRMAR Y EMITIR COMPROBANTE</span>
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- MODAL CLIENTE NUEVO -->
    <ClienteAddDialog v-model:isDialogVisible="isAddClientDialog" @addCliente="fetchCustomers" />

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

/* Category Pills */
.categories-scroll {
  scrollbar-width: none;
}
.categories-scroll::-webkit-scrollbar {
  display: none;
}

.category-pill {
  display: inline-flex;
  align-items: center;
  padding: 5px 12px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 18px;
  font-size: 0.78rem;
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
}

/* Product Cards */
.pos-card-med {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 8px;
  cursor: pointer;
  position: relative;
  transition: all 0.2s ease;
  display: flex;
  flex-direction: column;
  height: 100%;
}

.pos-card-med:hover:not(.is-disabled) {
  transform: translateY(-2px);
  border-color: #5c61e6;
  box-shadow: 0 6px 14px rgba(0, 0, 0, 0.05);
}

.pos-card-med.is-in-cart {
  border-color: #5c61e6;
  background-color: #f8f9ff;
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

.stock-ok { background: #dcfce7; color: #15803d; }
.stock-low { background: #fef9c3; color: #a16207; }
.stock-out { background: #fee2e2; color: #b91c1c; }
.stock-expired { background: #fee2e2; color: #b91c1c; }

.card-img-wrapper {
  width: 100%;
  border-radius: 8px;
  overflow: hidden;
  background: #f8fafc;
}

.card-body-wrapper {
  padding-top: 6px;
  display: flex;
  flex-direction: column;
  flex-grow: 1;
}

.card-lab-name {
  font-size: 0.65rem;
  font-weight: 700;
  color: #0d9488;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.card-title-name {
  font-size: 0.8rem;
  font-weight: 700;
  color: #1e293b;
  line-height: 1.2;
  margin: 1px 0 4px 0;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  min-height: 2.4em;
}

.card-price-txt {
  font-size: 0.9rem;
  font-weight: 900;
  color: #16a34a;
}

.btn-quick-add {
  width: 26px;
  height: 26px;
  border-radius: 6px;
  border: none;
  background: #5c61e6;
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-quick-add:hover:not(:disabled) {
  background: #474cc7;
  transform: scale(1.1);
}

/* Cart Container */
.cart-card-container {
  background: #ffffff;
  overflow: hidden;
}

.cart-table-wrapper {
  scrollbar-width: thin;
}
.cart-table-wrapper::-webkit-scrollbar {
  width: 4px;
}
.cart-table-wrapper::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}

.empty-cart-state {
  height: 100%;
  min-height: 200px;
  display: flex;
  flex-direction: column;
  align-center: center;
  justify-content: center;
  text-align: center;
  padding: 40px 10px;
  opacity: 0.6;
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
  color: #64748b;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
}

.cart-tr-row td {
  padding: 6px 8px;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
}

.item-table-name {
  font-size: 0.78rem;
  font-weight: 700;
  color: #1e293b;
  line-height: 1.2;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.item-table-lab {
  font-size: 0.65rem;
  font-weight: 600;
  color: #0d9488;
}

.stepper-box {
  display: inline-flex;
  align-items: center;
  background: #f1f5f9;
  border-radius: 16px;
  padding: 2px 4px;
  gap: 2px;
}

.stepper-btn {
  width: 18px;
  height: 18px;
  border-radius: 50%;
  border: none;
  background: white;
  color: #334155;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 1px 2px rgba(0,0,0,0.08);
}

.stepper-input {
  width: 26px;
  border: none;
  background: transparent;
  text-align: center;
  font-size: 0.78rem;
  font-weight: 800;
  color: #0f172a;
}
.stepper-input:focus { outline: none; }

.btn-del-row {
  border: none;
  background: transparent;
  color: #ef4444;
  cursor: pointer;
  padding: 0;
  opacity: 0.7;
}
.btn-del-row:hover { opacity: 1; }

.btn-open-payment {
  letter-spacing: 0.5px;
  height: 48px !important;
}

/* Modal de Pago */
.modal-payment-tile {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 12px 6px;
  background: #f8fafc;
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.modal-payment-tile:hover {
  border-color: #cbd5e1;
}

.modal-payment-tile.active {
  background: #eef2ff;
  border-color: #5c61e6;
  color: #5c61e6;
}

/* Dark mode overrides */
@media (prefers-color-scheme: dark) {
  .pos-wrapper {
    background-color: #0f172a;
  }
  .catalog-header-bar,
  .cart-card-container,
  .pos-card-med {
    background-color: #1e293b;
    border-color: #334155;
  }
  .category-pill {
    background: #1e293b;
    border-color: #334155;
    color: #cbd5e1;
  }
  .card-title-name,
  .item-table-name {
    color: #f1f5f9;
  }
  .cart-th-row th {
    background-color: #0f172a;
    color: #94a3b8;
    border-color: #334155;
  }
  .cart-tr-row td {
    border-color: #334155;
  }
  .client-quick-bar,
  .cart-bottom-checkout {
    background-color: #1e293b !important;
    border-color: #334155 !important;
  }
  .stepper-box {
    background: #0f172a;
  }
  .stepper-btn {
    background: #334155;
    color: #f1f5f9;
  }
  .stepper-input {
    color: #f1f5f9;
  }
}
</style>