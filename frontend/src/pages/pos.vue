<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import ClienteAddDialog from '@/components/system/clientes/ClienteAddDialog.vue'
import { useCashSession } from '@/composables/useCashSession'

const toast = useMaterializeToast()

// --- Estado de la Caja ---
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
    await checkSession() // Refrescar estado de caja en el POS
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

// Estado del POS
const cart = ref([])
const searchQuery = ref('')
const categoryFilter = ref(null)
const cashTendered = ref(0)
const discount = ref(0)
const saleDate = ref(new Date().toISOString().slice(0, 10))

// Estados de Datos Reales
const products = ref([])
const categories = ref([])
const customers = ref([])
const selectedCustomer = ref(null)
const isFetching = ref(false)

// Estados de Facturación SIAT
const isInvoice = ref(true)
const businessName = ref('')
const nitCi = ref('')
const paymentMethod = ref(1) // 1: Efectivo por defecto
const documentType = ref(1)   // 1: CI por defecto
const isAddClientDialog = ref(false)

// Modal de Resultado
const isResultDialog = ref(false)
const saleResult = ref(null)

// ── DATA FETCHING ──

const fetchProducts = async (search = '') => {
  try {
    isFetching.value = true
    const response = await $api(`/v1/products?per_page=100&search=${search}`)
    products.value = response.data || response || []
    
    // Extraer categorías únicas solo en la carga inicial
    if (!search) {
      const cats = new Set()
      products.value.forEach(p => { if(p.category) cats.add(p.category.name) })
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
  return cart.value.reduce((total, item) => total + (item.sale_price * item.quantity), 0)
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
      businessName.value = customer.first_name || ''
      nitCi.value = customer.document_number || ''
      documentType.value = customer.document_type || 1
    }
  } else {
    businessName.value = 'S/N'
    nitCi.value = '0'
    documentType.value = 1
  }
})

const addToCart = (product) => {
  if (product.stock <= 0 || product.is_expired) return
  const existingItem = cart.value.find(item => item.id === product.id)
  if (existingItem) {
    if (existingItem.quantity < product.stock) existingItem.quantity++
  } else {
    cart.value.push({ ...product, quantity: 1 })
  }
}

const checkout = async () => {
  if (cart.value.length === 0) return

  const payload = {
    customer_id: selectedCustomer.value,
    business_name: businessName.value || 'S/N',
    nit_ci: nitCi.value || '0',
    sale_date: saleDate.value,
    total_amount: total.value,
    cash_tendered: cashTendered.value,
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
    cart.value = []
    cashTendered.value = 0
    discount.value = 0
    saleDate.value = new Date().toISOString().slice(0, 10)
    fetchProducts() // Refrescar stock
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
  <VRow class="pos-layout ma-0 overflow-hidden">
    <!-- COLUMNA IZQUIERDA: CARRITO Y FACTURACIÓN -->
    <VCol cols="12" md="4" lg="3" class="cart-sidebar pa-2 h-100 d-flex flex-column">
      <VCard class="flex-grow-1 d-flex flex-column border rounded-xl shadow-lg" elevation="0">
        <!-- HEADER CARRITO -->
        <div class="pa-4 bg-primary text-white d-flex align-center justify-space-between rounded-t-xl">
          <div class="d-flex align-center">
            <VIcon icon="ri-shopping-basket-line" class="mr-2" size="24"/>
            <span class="text-h6 font-weight-bold">Venta Actual</span>
          </div>
          <VChip color="white" text-color="primary" density="comfortable" class="font-weight-black">
            {{ cart.length }}
          </VChip>
        </div>

        <!-- ITEMS DEL CARRITO -->
        <div v-if="!isLoadingSession && !hasOpenSession" class="pa-4 bg-error-lighten-5 border-b text-center">
          <VIcon icon="ri-lock-line" size="40" color="error" class="mb-2" />
          <h4 class="text-subtitle-1 font-weight-black text-error mb-1">Caja Cerrada</h4>
          <p class="text-caption text-medium-emphasis mb-3">Debe aperturar un turno de caja antes de realizar ventas.</p>
          <VBtn
            color="error"
            size="small"
            block
            elevation="2"
            class="font-weight-bold rounded-lg"
            @click="openCashDialog"
          >
            <VIcon start icon="ri-safe-2-line" />
            Aperturar Turno de Caja
          </VBtn>
        </div>

        <div class="flex-grow-1 overflow-y-auto px-2 pt-2 cart-list">
          <div v-if="cart.length === 0" class="h-100 d-flex flex-column align-center justify-center opacity-40 py-10">
            <VIcon icon="ri-shopping-cart-2-line" size="64" class="mb-2"/>
            <p class="text-subtitle-1">Carrito vacío</p>
          </div>
          
          <VList v-else lines="two" class="bg-transparent pa-0">
            <VListItem v-for="(item, index) in cart" :key="item.id" class="px-2 py-2 mb-2 bg-surface rounded-lg border">
              <template #prepend>
                <VAvatar rounded variant="tonal" color="primary" size="48">
                  <VImg v-if="item.image_path" :src="`/storage/${item.image_path}`" />
                  <span v-else class="text-h6 font-weight-bold">{{ item.name.charAt(0) }}</span>
                </VAvatar>
              </template>
              
              <VListItemTitle class="font-weight-bold text-body-1">{{ item.name }}</VListItemTitle>
              <VListItemSubtitle class="text-primary font-weight-bold">
                Bs. {{ Number(item.sale_price).toFixed(2) }} 
                <span class="text-caption text-medium-emphasis ml-1">x {{ item.quantity }}</span>
              </VListItemSubtitle>
              
              <template #append>
                <div class="d-flex flex-column align-center gap-1">
                  <VBtn density="compact" variant="tonal" icon="ri-close-line" size="small" @click="cart.splice(index, 1)" color="error"/>
                  <div class="d-flex align-center bg-grey-lighten-4 rounded-pill px-1 border">
                    <VBtn density="compact" variant="text" icon="ri-subtract-line" size="small" @click="item.quantity > 1 ? item.quantity-- : cart.splice(index, 1)"/>
                    <span class="px-2 font-weight-bold">{{ item.quantity }}</span>
                    <VBtn density="compact" variant="text" icon="ri-add-line" size="small" @click="item.quantity < item.stock ? item.quantity++ : null" color="success"/>
                  </div>
                </div>
              </template>
            </VListItem>
          </VList>
        </div>

        <VDivider />

        <!-- SECCIÓN DE FACTURACIÓN SIAT -->
        <div class="pa-4 bg-grey-lighten-5">
          <!-- Fecha de Venta para Inventario Retroactivo -->
          <div class="mb-3">
            <VTextField 
              v-model="saleDate" 
              type="date" 
              label="Fecha de Venta" 
              density="compact" 
              variant="outlined" 
              prepend-inner-icon="ri-calendar-line"
              hide-details 
            />
          </div>

          <!-- Advertencia de Facturación Retroactiva -->
          <VAlert
            v-if="!isToday && isInvoice"
            type="warning"
            density="compact"
            variant="tonal"
            class="mb-3 text-caption py-1 px-2"
            border="start"
          >
            Impuestos Nacionales no permite emitir facturas con fechas pasadas. Por favor desactive "Emitir Factura" para guardar como nota de venta.
          </VAlert>

          <div class="d-flex align-center justify-space-between mb-3">
             <span class="text-subtitle-2 font-weight-bold">DATOS FISCALES</span>
             <VSwitch v-model="isInvoice" label="Emitir Factura" color="primary" density="compact" hide-details />
          </div>

          <VRow v-if="isInvoice" no-gutters class="gap-2 mb-2">
            <VCol cols="12" class="mb-2 d-flex align-center gap-2">
              <VAutocomplete
                v-model="selectedCustomer"
                :items="customers"
                item-title="first_name"
                item-value="id"
                label="Seleccionar Cliente"
                density="compact"
                variant="outlined"
                hide-details
                prepend-inner-icon="ri-user-search-line"
                class="flex-grow-1"
              />
              <VBtn icon="ri-user-add-line" color="info" variant="tonal" density="compact" rounded @click="isAddClientDialog = true" title="Nuevo Cliente"/>
            </VCol>
            
            <VCol cols="12" class="mb-2">
              <VTextField v-model="businessName" label="Razón Social" density="compact" variant="outlined" hide-details />
            </VCol>
            <VCol cols="12">
              <VTextField v-model="nitCi" label="NIT / CI" density="compact" variant="outlined" hide-details />
            </VCol>
          </VRow>

          <VSelect
            v-model="paymentMethod"
            :items="[
              { title: 'Efectivo', value: 1 },
              { title: 'Tarjeta Debito/Crédito', value: 2 },
              { title: 'QR / Transferencia', value: 7 }
            ]"
            label="Método de Pago"
            density="compact"
            variant="outlined"
            hide-details
            class="mt-2"
          />
        </div>

        <VDivider />

        <!-- TOTALES Y PAGO -->
        <div class="pa-4 bg-surface rounded-b-xl">
          <div class="d-flex justify-space-between mb-1 text-subtitle-1">
            <span>Subtotal:</span>
            <span>Bs. {{ subtotal.toFixed(2) }}</span>
          </div>
          <div class="d-flex justify-space-between mb-4 text-h5 font-weight-black text-primary border-t pt-2 mt-2">
            <span>TOTAL:</span>
            <span>Bs. {{ total.toFixed(2) }}</span>
          </div>

          <VRow no-gutters class="gap-2 mb-4">
            <VCol>
              <VTextField v-model.number="cashTendered" type="number" label="Paga con" density="compact" variant="outlined" hide-details prefix="Bs." bg-color="white"/>
            </VCol>
            <VCol>
              <VTextField :value="change.toFixed(2)" label="Cambio" readonly density="compact" variant="outlined" hide-details prefix="Bs." :class="{'text-success font-weight-bold': change > 0}"/>
            </VCol>
          </VRow>

          <VBtn block color="success" size="x-large" elevation="8" class="font-weight-bold text-uppercase rounded-lg" @click="checkout" :disabled="cart.length === 0 || isFetching || !hasOpenSession" :loading="isFetching">
            <VIcon icon="ri-checkbox-circle-line" class="mr-2"/> Procesar Venta
          </VBtn>
        </div>
      </VCard>
    </VCol>

    <!-- GRILLA DE PRODUCTOS (DERECHA) -->
    <VCol cols="12" md="8" lg="9" class="pa-2 h-100 d-flex flex-column">
      <div class="pa-4 bg-white rounded-xl border mb-4 shadow-sm">
        <VRow class="align-center">
          <VCol cols="12" md="6" class="py-1">
            <VTextField
              v-model="searchQuery"
              prepend-inner-icon="ri-search-line"
              placeholder="Buscar por medicamento o barras..."
              variant="outlined"
              density="comfortable"
              hide-details
              clearable
              autofocus
              color="primary"
              class="rounded-lg"
            />
          </VCol>
          <VCol cols="12" md="6" class="py-1">
            <VChipGroup v-model="categoryFilter" selected-class="bg-primary text-white" mandatory>
               <VChip value="" variant="outlined" class="rounded-pill">Todos</VChip>
               <VChip v-for="cat in categories" :key="cat" :value="cat" variant="outlined" class="rounded-pill">{{ cat }}</VChip>
            </VChipGroup>
          </VCol>
        </VRow>
      </div>

      <div class="product-grid flex-grow-1 overflow-y-auto px-1">
        <VRow v-if="!isFetching">
          <VCol v-for="item in filteredProducts" :key="item.id" cols="12" sm="6" md="4" lg="3" xl="2">
            <VCard 
              @click="addToCart(item)" 
              hover 
              class="h-100 d-flex flex-column product-card rounded-lg border" 
              :class="{'out-of-stock': item.stock <= 0, 'is-expired': item.is_expired}"
            >
              <div class="card-image-wrapper">
                <VImg 
                  :src="item.image_path ? `/storage/${item.image_path}` : 'https://placehold.co/400x300/E8F5E9/4CAF50?text='+item.name.substring(0,5)" 
                  height="140" 
                  cover
                  class="bg-grey-lighten-4 rounded-t-lg"
                />
                
                <!-- Stock indicator overlay on image for clear visual hierarchy -->
                <VChip 
                  v-if="item.stock > 0"
                  size="x-small" 
                  :color="item.is_expired ? 'error' : (item.stock > 10 ? 'success' : 'warning')" 
                  class="stock-badge-overlay font-weight-black text-white" 
                  variant="flat"
                  elevation="2"
                >
                  Stock: {{ item.stock }}
                </VChip>
 
                <!-- Out of Stock Overlay -->
                <div v-if="item.stock <= 0" class="inset-0 bg-black bg-opacity-40 d-flex align-center justify-center rounded-t-lg">
                  <VChip color="error" class="font-weight-black px-3" variant="flat" size="small">
                    AGOTADO
                  </VChip>
                </div>

                <!-- Expired Overlay -->
                <div v-else-if="item.is_expired" class="inset-0 bg-black bg-opacity-40 d-flex align-center justify-center rounded-t-lg">
                  <VChip color="error" class="font-weight-black px-3" variant="flat" size="small">
                    VENCIDO
                  </VChip>
                </div>
              </div>
              
              <VCardItem class="pb-0 pt-3 flex-grow-1">
                <div class="d-flex justify-space-between align-center mb-1">
                  <!-- Laboratory: styled in a beautiful emerald teal to stand out -->
                  <span class="product-lab text-uppercase">{{ item.laboratory?.name || 'Genérico' }}</span>
                </div>
                <VCardTitle class="text-body-1 font-weight-bold text-wrap line-clamp-2" style="line-height:1.2; min-height:2.4em;">{{ item.name }}</VCardTitle>
              </VCardItem>

              <VCardActions class="pa-3 pt-0 d-flex justify-space-between align-center">
                <span class="text-h6 font-weight-black text-success">Bs. {{ Number(item.sale_price).toFixed(2) }}</span>
                <VIcon icon="ri-add-circle-fill" color="primary" size="28" class="add-icon" />
              </VCardActions>
            </VCard>
          </VCol>
        </VRow>
        
        <div v-else class="h-100 d-flex align-center justify-center">
          <VProgressCircular indeterminate color="primary" size="64" />
        </div>
      </div>
    </VCol>

    <!-- MODALES -->
    <ClienteAddDialog v-model:isDialogVisible="isAddClientDialog" @addCliente="fetchCustomers" />

    <!-- MODAL APERTURA DE CAJA RÁPIDA -->
    <VDialog v-model="isOpenCashDialog" max-width="400">
      <VCard class="rounded-xl">
        <DialogCloseBtn variant="text" size="default" @click="isOpenCashDialog = false" />
        <VCardTitle class="pa-4 bg-primary text-white d-flex align-center font-weight-bold">
          <VIcon icon="ri-safe-2-line" class="me-2" />
          Aperturar Turno de Caja
        </VCardTitle>
        <VCardText class="pt-6">
          <VSelect
            v-model="openCashData.cash_register_id"
            :items="cashRegistersList"
            item-title="name"
            item-value="id"
            label="Seleccionar Caja Física"
            variant="outlined"
            density="comfortable"
            class="mb-4"
          />
          <VTextField
            v-model.number="openCashData.opening_amount"
            label="Monto Inicial en Efectivo (Sencillo/Base)"
            type="number"
            min="0"
            variant="outlined"
            density="comfortable"
            prefix="Bs"
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
    <VDialog v-model="isResultDialog" max-width="500" persistent>
      <VCard v-if="saleResult" class="pa-4 text-center rounded-xl">
        <VCardTitle>
          <VIcon icon="ri-checkbox-circle-line" color="success" size="64" class="mb-4"/>
          <div class="text-h4 font-weight-bold">Venta Exitosa</div>
        </VCardTitle>
        <VCardText>
          <p class="mb-4">Se ha generado el comprobante correctamente.</p>
          
          <div v-if="saleResult.cuf" class="bg-primary-lighten-5 pa-4 rounded-lg border border-primary border-dashed mb-6">
            <div class="text-caption text-uppercase font-weight-bold text-primary mb-1">CÓDIGO CUF (SIAT)</div>
            <div class="text-body-2 font-weight-black text-break">{{ saleResult.cuf }}</div>
          </div>

          <VRow class="gap-2">
            <template v-if="saleResult.cuf">
              <VCol cols="12" sm="6" class="pb-0">
                 <VBtn :href="`/api/v1/sales/${saleResult.id}/print/thermal`" target="_blank" block color="secondary" size="large" prepend-icon="ri-receipt-line">
                   Ticket Térmico
                 </VBtn>
              </VCol>
              <VCol cols="12" sm="6" class="pb-0">
                 <VBtn :href="`/api/v1/sales/${saleResult.id}/print/letter`" target="_blank" block color="primary" size="large" prepend-icon="ri-file-text-line">
                   Factura Carta
                 </VBtn>
              </VCol>
            </template>
            <!-- Receipt if not invoice -->
            <VCol v-else cols="12" class="pb-0">
                 <VBtn :href="`/api/v1/sales/${saleResult.id}/print/thermal`" target="_blank" block color="secondary" size="large" prepend-icon="ri-receipt-line">
                   Imprimir Recibo
                 </VBtn>
            </VCol>
            <VCol cols="12">
               <VBtn block variant="tonal" size="large" @click="isResultDialog = false">Nueva Venta</VBtn>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </VDialog>
  </VRow>
</template>

<style scoped>
.pos-layout {
  height: calc(100vh - 84px);
  background-color: #f4f7f6;
}
.cart-list::-webkit-scrollbar,
.product-grid::-webkit-scrollbar {
  width: 4px;
}
.cart-list::-webkit-scrollbar-thumb,
.product-grid::-webkit-scrollbar-thumb {
  background-color: #ddd;
  border-radius: 10px;
}
.product-card {
  transition: all 0.3s ease;
  position: relative;
}
.product-card:hover:not(.out-of-stock):not(.is-expired) {
  transform: translateY(-5px);
  box-shadow: 0 12px 20px rgba(0,0,0,0.1) !important;
}
.product-card.out-of-stock {
  border: 1px dashed rgba(255, 77, 73, 0.4) !important;
  background-color: #fffafb !important;
  cursor: not-allowed;
}
.product-card.out-of-stock .v-img {
  filter: grayscale(1) opacity(0.6);
}
.product-card.is-expired {
  border: 1px dashed rgba(255, 77, 73, 0.6) !important;
  background-color: #fffafb !important;
  cursor: not-allowed;
}
.product-card.is-expired .v-img {
  filter: grayscale(0.5) opacity(0.7);
}
.product-lab {
  color: #009688 !important; /* Elegant Emerald Teal */
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.5px;
}
.product-card:hover:not(.out-of-stock):not(.is-expired) .add-icon {
  transform: scale(1.2);
  color: #5c61e6 !important;
}
.add-icon {
  transition: all 0.2s ease-in-out;
}
.card-image-wrapper {
  position: relative;
  width: 100%;
  height: 140px;
  overflow: hidden;
}
.stock-badge-overlay {
  position: absolute !important;
  top: 8px;
  right: 8px;
  z-index: 5;
}
.inset-0 {
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  left: 0;
}
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
