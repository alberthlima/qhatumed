<script setup>
const toast = useMaterializeToast()

const isLoading = ref(false)
const isSubmitting = ref(false)
const adjustments = ref([])
const isAdjustmentModal = ref(false)

// Table Headers
const headers = [
  { title: '#', key: 'index', sortable: false },
  { title: 'Fecha / Hora', key: 'created_at' },
  { title: 'Responsable', key: 'user.name' },
  { title: 'Producto / Medicamento', key: 'product.name' },
  { title: 'Lote Afectado', key: 'purchase_item.batch' },
  { title: 'Tipo', key: 'type', align: 'center' },
  { title: 'Cantidad', key: 'quantity', align: 'end' },
  { title: 'Motivo', key: 'reason' },
  { title: 'Observación', key: 'notes' },
]

// Modal Form Data
const refForm = ref(null)
const formData = ref({
  product_id: null,
  purchase_item_id: null,
  type: 'SUBTRACT',
  quantity: 1,
  reason: 'AUDITORIA_FISICA',
  notes: ''
})

// Search Products Autocomplete
const searchProducts = ref([])
const productSearchQuery = ref('')
const selectedProduct = ref(null)
const searchLoading = ref(false)

// Loaded Batches for selected product
const productBatches = ref([])
const batchesLoading = ref(false)

// Server side pagination refs
const itemsPerPage = ref(10)
const totalItems = ref(0)
const currentPage = ref(1)

const fetchAdjustments = async () => {
  try {
    isLoading.value = true
    const response = await $api('/v1/inventory/adjustments', {
      method: 'GET',
      query: {
        page: currentPage.value,
        per_page: itemsPerPage.value
      }
    })
    adjustments.value = response.data || response || []
    totalItems.value = response.total || response.meta?.total || 0
  } catch (error) {
    console.error('Error fetching adjustments:', error)
    toast.showError('No se pudo cargar el historial de ajustes', 'Error')
  } finally {
    isLoading.value = false
  }
}

// Watch product search query to load matching products
watch(productSearchQuery, async (newVal) => {
  if (!newVal || newVal.length < 2) return
  try {
    searchLoading.value = true
    const res = await $api(`/v1/products?search=${newVal}`, { method: 'GET' })
    searchProducts.value = res.data?.data || res.data || []
  } catch (e) {
    console.error(e)
  } finally {
    searchLoading.value = false
  }
})

// Load batches when a product is selected
watch(selectedProduct, async (newProd) => {
  productBatches.value = []
  formData.value.purchase_item_id = null
  if (newProd) {
    formData.value.product_id = newProd.id
    try {
      batchesLoading.value = true
      // Query batches belonging to the selected product using its barcode
      const res = await $api('/v1/inventory/batches', {
        method: 'GET',
        query: { search: newProd.barcode || newProd.name, per_page: 50 }
      })
      // Double check that the returned items belong to the selected product
      const rawBatches = res.data || res.data?.data || res || []
      productBatches.value = rawBatches.filter(b => b.product_id === newProd.id)
    } catch (e) {
      console.error(e)
      toast.showError('Error al cargar los lotes del producto', 'Error')
    } finally {
      batchesLoading.value = false
    }
  } else {
    formData.value.product_id = null
  }
})

const updateOptions = (options) => {
  currentPage.value = options.page
  itemsPerPage.value = options.itemsPerPage
  fetchAdjustments()
}

const openAdjustmentModal = () => {
  selectedProduct.value = null
  productBatches.value = []
  formData.value = {
    product_id: null,
    purchase_item_id: null,
    type: 'SUBTRACT',
    quantity: 1,
    reason: 'AUDITORIA_FISICA',
    notes: ''
  }
  isAdjustmentModal.value = true
}

const submitAdjustment = async () => {
  const validation = await refForm.value?.validate()
  if (!validation?.valid) return

  if (!formData.value.purchase_item_id) {
    toast.showWarning('Seleccione un lote físico para aplicar el ajuste', 'Falta lote')
    return
  }

  // Double check batch stock in SUBTRACT adjustments
  if (formData.value.type === 'SUBTRACT') {
    const selectedBatchObj = productBatches.value.find(b => b.id === formData.value.purchase_item_id)
    if (selectedBatchObj && selectedBatchObj.current_stock < formData.value.quantity) {
      toast.showError(`El stock disponible en el lote (${selectedBatchObj.current_stock} unds) es menor al ajuste solicitado.`, 'Stock Insuficiente')
      return
    }
  }

  try {
    isSubmitting.value = true
    await $api('/v1/inventory/adjustments', {
      method: 'POST',
      body: formData.value
    })
    
    isAdjustmentModal.value = false
    fetchAdjustments() // Refresh list
    toast.showSuccess('Ajuste de inventario registrado correctamente', 'Éxito')
  } catch (error) {
    const msg = error.response?._data?.error || 'Error al procesar ajuste'
    toast.showError(msg, 'Error')
  } finally {
    isSubmitting.value = false
  }
}

const getReasonLabel = (reason) => {
  const labels = {
    'AUDITORIA_FISICA': 'Auditoría Física',
    'VENCIMIENTO': 'Merma por Vencimiento',
    'DANADO': 'Medicamento Dañado',
    'PERDIDA': 'Pérdida / Robo',
    'OTROS': 'Otros'
  }
  return labels[reason] || reason
}

onMounted(() => {
  // Loaded by VDataTableServer updateOptions
})

definePage({ meta: { permission: 'all' } })
</script>

<template>
  <div class="pa-4">
    <VCard class="rounded-xl border" elevation="0">
      <VCardTitle class="pa-6 d-flex align-center justify-space-between bg-primary-lighten-5">
        <div class="d-flex align-center">
          <VAvatar color="primary" class="me-3" size="38">
            <VIcon icon="ri-equalizer-line" color="white" />
          </VAvatar>
          <div>
            <div class="text-h5 font-weight-bold">Ajustes Manuales de Inventario</div>
            <div class="text-caption text-medium-emphasis">Historial y registro de correcciones y mermas físicas</div>
          </div>
        </div>
        
        <VBtn color="success" prepend-icon="ri-add-circle-fill" @click="openAdjustmentModal">
          Registrar Ajuste Manual
        </VBtn>
      </VCardTitle>

      <VCardText class="pt-4">
        <VDataTableServer
          v-model:items-per-page="itemsPerPage"
          v-model:page="currentPage"
          :headers="headers"
          :items="adjustments"
          :items-length="totalItems"
          :loading="isLoading"
          loading-text="Cargando historial de ajustes..."
          no-data-text="No se han registrado ajustes manuales de stock."
          class="text-no-wrap"
          :items-per-page-text="'Filas por página:'"
          :page-text="'{0}-{1} de {2}'"
          :items-per-page-options="[
            { value: 5, title: '5' },
            { value: 10, title: '10' },
            { value: 25, title: '25' },
            { value: 50, title: '50' }
          ]"
          @update:options="updateOptions"
        >
          <template #item.index="{ index }">
            <span class="font-weight-bold text-caption text-medium-emphasis">{{ (currentPage - 1) * itemsPerPage + index + 1 }}</span>
          </template>

          <template #item.created_at="{ item }">
            <span>{{ new Date(item.created_at).toLocaleString() }}</span>
          </template>

          <template #item.user.name="{ item }">
            <span class="font-weight-medium">{{ item.user?.name || 'Sistema' }}</span>
          </template>

          <template #item.product.name="{ item }">
            <span class="font-weight-bold text-primary">{{ item.product?.name }}</span>
          </template>

          <template #item.purchase_item.batch="{ item }">
            <VChip size="small" variant="tonal" color="secondary" class="font-weight-bold">
              {{ item.purchase_item?.batch || 'N/A' }}
            </VChip>
          </template>

          <template #item.type="{ item }">
            <VChip
              size="small"
              :color="item.type === 'ADD' ? 'success' : 'error'"
              class="font-weight-bold"
              variant="flat"
            >
              {{ item.type === 'ADD' ? '+ Incremento' : '- Reducción' }}
            </VChip>
          </template>

          <template #item.quantity="{ item }">
            <span class="font-weight-black">{{ item.quantity }} unds</span>
          </template>

          <template #item.reason="{ item }">
            <span>{{ getReasonLabel(item.reason) }}</span>
          </template>

          <template #item.notes="{ item }">
            <span class="text-caption text-medium-emphasis">{{ item.notes || '-' }}</span>
          </template>
        </VDataTableServer>
      </VCardText>
    </VCard>

    <!-- REGISTER ADJUSTMENT MODAL -->
    <VDialog v-model="isAdjustmentModal" max-width="600" persistent>
      <VCard class="pa-4 rounded-xl">
        <VCardTitle class="py-4 d-flex align-center justify-space-between bg-primary text-white rounded-t-xl">
          <div class="d-flex align-center">
            <VIcon icon="ri-equalizer-line" class="me-2" />
            <span class="text-h6">Nuevo Ajuste de Inventario</span>
          </div>
          <IconBtn color="white" @click="isAdjustmentModal = false">
            <VIcon icon="ri-close-line" />
          </IconBtn>
        </VCardTitle>

        <VCardText class="pt-6">
          <VForm ref="refForm" @submit.prevent="submitAdjustment">
            <VRow>
              <!-- Search Product -->
              <VCol cols="12">
                <VAutocomplete
                  v-model="selectedProduct"
                  v-model:search="productSearchQuery"
                  :loading="searchLoading"
                  :items="searchProducts"
                  item-title="name"
                  item-value="id"
                  return-object
                  label="Buscar medicamento por nombre o barras *"
                  placeholder="Escriba para buscar medicamento..."
                  prepend-inner-icon="ri-search-line"
                  required
                >
                  <template #item="{ props, item }">
                    <VListItem v-bind="props" :title="item.raw.name" :subtitle="`Stock Global: ${item.raw.stock} unds`" />
                  </template>
                </VAutocomplete>
              </VCol>

              <!-- Batch Selector (only if product is selected) -->
              <VCol cols="12" v-if="selectedProduct">
                <div v-if="batchesLoading" class="text-center py-2">
                  <VProgressCircular indeterminate size="24" color="primary" class="me-2" />
                  <span class="text-caption text-medium-emphasis">Cargando lotes del producto...</span>
                </div>
                <VSelect
                  v-else
                  v-model="formData.purchase_item_id"
                  :items="productBatches"
                  item-title="batch"
                  item-value="id"
                  label="Seleccionar Lote Físico *"
                  placeholder="Seleccione el lote al cual se afectará"
                  required
                >
                  <template #item="{ props, item }">
                    <VListItem v-bind="props" :title="`Lote: ${item.raw.batch || 'N/A'} (Stock: ${item.raw.current_stock} unds)`" :subtitle="`Vencimiento: ${item.raw.expiration_date || 'Sin Vencimiento'} | Costo: Bs. ${item.raw.unit_cost}`" />
                  </template>
                  
                  <template #selection="{ item }">
                    <span>Lote: <strong>{{ item.raw.batch || 'N/A' }}</strong> (Stock: {{ item.raw.current_stock }} unds)</span>
                  </template>
                </VSelect>
                <div v-if="productBatches.length === 0 && !batchesLoading" class="text-caption text-error font-weight-bold mt-1">
                  ⚠️ Este producto no tiene lotes registrados con stock activo en inventario.
                </div>
              </VCol>

              <!-- Adjustment Type -->
              <VCol cols="12" sm="6">
                <VSelect
                  v-model="formData.type"
                  :items="[
                    { title: 'Reducir Stock (-)', value: 'SUBTRACT' },
                    { title: 'Incrementar Stock (+)', value: 'ADD' }
                  ]"
                  item-title="title"
                  item-value="value"
                  label="Tipo de Ajuste *"
                  required
                />
              </VCol>

              <!-- Quantity -->
              <VCol cols="12" sm="6">
                <VTextField
                  v-model.number="formData.quantity"
                  type="number"
                  min="1"
                  label="Cantidad de Ajuste (Unidades) *"
                  required
                />
              </VCol>

              <!-- Reason -->
              <VCol cols="12">
                <VSelect
                  v-model="formData.reason"
                  :items="[
                    { title: 'Auditoría Física / Cuadre', value: 'AUDITORIA_FISICA' },
                    { title: 'Merma por Medicamento Vencido', value: 'VENCIMIENTO' },
                    { title: 'Medicamento Dañado / Roto', value: 'DANADO' },
                    { title: 'Pérdida / Robo', value: 'PERDIDA' },
                    { title: 'Otros motivos', value: 'OTROS' }
                  ]"
                  item-title="title"
                  item-value="value"
                  label="Motivo del Ajuste *"
                  required
                />
              </VCol>

              <!-- Notes -->
              <VCol cols="12">
                <VTextarea
                  v-model="formData.notes"
                  label="Observaciones / Justificación"
                  placeholder="Detalle el motivo específico del ajuste manual para la auditoría..."
                  rows="2"
                />
              </VCol>
            </VRow>

            <div class="d-flex justify-end gap-3 mt-6">
              <VBtn variant="outlined" color="secondary" @click="isAdjustmentModal = false" :disabled="isSubmitting">
                Cancelar
              </VBtn>
              <VBtn color="success" type="submit" :loading="isSubmitting">
                Procesar Ajuste
              </VBtn>
            </div>
          </VForm>
        </VCardText>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.gap-3 {
  gap: 12px;
}
</style>
