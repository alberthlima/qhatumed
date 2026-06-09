<script setup>
const toast = useMaterializeToast()

const search = ref('')
const isLoading = ref(false)
const products = ref([])

// Stats summary
const summary = ref({
  total_cost: 0,
  total_sale: 0,
  total_margin: 0,
  margin_percent: 0
})

// Table Headers
const headers = [
  { title: '#', key: 'index', sortable: false },
  { title: 'Código', key: 'barcode' },
  { title: 'Producto / Medicamento', key: 'name' },
  { title: 'Laboratorio', key: 'laboratory' },
  { title: 'Stock Físico', key: 'stock', align: 'end' },
  { title: 'Costo Prom. (CPP)', key: 'average_cost', align: 'end' },
  { title: 'P. Venta Ref.', key: 'sale_price', align: 'end' },
  { title: 'Valor Costo (PEPS)', key: 'cost_valuation', align: 'end' },
  { title: 'Valor Venta Potencial', key: 'sale_valuation', align: 'end' },
  { title: 'Rentabilidad (Margen)', key: 'margin', align: 'end', sortable: false },
]

// Server side pagination refs
const itemsPerPage = ref(10)
const totalItems = ref(0)
const currentPage = ref(1)

const fetchValuation = async () => {
  try {
    isLoading.value = true
    const response = await $api('/v1/inventory/valuation', {
      method: 'GET',
      query: {
        search: search.value || '',
        page: currentPage.value,
        per_page: itemsPerPage.value
      }
    })

    products.value = response.products?.data || response.products || []
    totalItems.value = response.products?.total || response.products?.meta?.total || 0
    
    if (response.summary) {
      summary.value = response.summary
    }

  } catch (error) {
    console.error('Error fetching valuation:', error)
    toast.showError('No se pudo cargar la valorización de inventario', 'Error')
  } finally {
    isLoading.value = false
  }
}

const updateOptions = (options) => {
  currentPage.value = options.page
  itemsPerPage.value = options.itemsPerPage
  fetchValuation()
}

const downloadPdf = () => {
  const baseUrl = import.meta.env.VITE_API_BASE_URL || '/api'
  let url = baseUrl
  if (url.startsWith('http')) {
    url = `${url.replace(/\/$/, '')}/v1/inventory/valuation/pdf`
  } else {
    url = `${window.location.origin}${url.replace(/\/$/, '')}/v1/inventory/valuation/pdf`
  }
  window.open(url, '_blank')
}

watch(search, () => {
  currentPage.value = 1
  fetchValuation()
})

onMounted(() => {
  // Loaded by VDataTableServer updateOptions
})

definePage({ meta: { permission: 'all' } })
</script>

<template>
  <div class="pa-4">
    <!-- VALUATION STATS CARDS -->
    <VRow class="mb-4">
      <VCol cols="12" sm="4" md="4">
        <VCard class="border elevation-1 rounded-xl">
          <VCardText class="d-flex align-center justify-space-between pa-5">
            <div>
              <div class="text-caption text-medium-emphasis font-weight-medium">Costo Total del Inventario</div>
              <div class="text-h4 font-weight-black mt-1 text-primary">Bs. {{ Number(summary.total_cost).toLocaleString('es-BO', {minimumFractionDigits: 2, maximumFractionDigits: 2}) }}</div>
              <div class="text-caption text-medium-emphasis mt-1">Valuación Real PEPS</div>
            </div>
            <VAvatar size="48" color="primary" variant="tonal" class="rounded-lg">
              <VIcon icon="ri-money-dollar-circle-line" size="24" />
            </VAvatar>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="4" md="4">
        <VCard class="border elevation-1 rounded-xl">
          <VCardText class="d-flex align-center justify-space-between pa-5">
            <div>
              <div class="text-caption text-medium-emphasis font-weight-medium">Venta Potencial Estimada</div>
              <div class="text-h4 font-weight-black mt-1 text-success">Bs. {{ Number(summary.total_sale).toLocaleString('es-BO', {minimumFractionDigits: 2, maximumFractionDigits: 2}) }}</div>
              <div class="text-caption text-medium-emphasis mt-1">Ingresos Brutos Proyectados</div>
            </div>
            <VAvatar size="48" color="success" variant="tonal" class="rounded-lg">
              <VIcon icon="ri-funds-box-line" size="24" />
            </VAvatar>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="4" md="4">
        <VCard class="border elevation-1 rounded-xl">
          <VCardText class="d-flex align-center justify-space-between pa-5">
            <div>
              <div class="text-caption text-medium-emphasis font-weight-medium">Utilidad / Margen Estimado</div>
              <div class="text-h4 font-weight-black mt-1 text-info">Bs. {{ Number(summary.total_margin).toLocaleString('es-BO', {minimumFractionDigits: 2, maximumFractionDigits: 2}) }}</div>
              <div class="text-caption text-info font-weight-bold mt-1">Rentabilidad: +{{ Number(summary.margin_percent).toFixed(2) }}%</div>
            </div>
            <VAvatar size="48" color="info" variant="tonal" class="rounded-lg">
              <VIcon icon="ri-percent-line" size="24" />
            </VAvatar>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- MAIN DATA TABLE CARD -->
    <VCard class="rounded-xl border" elevation="0">
      <VCardTitle class="pa-6 d-flex align-center justify-space-between bg-primary-lighten-5">
        <div class="d-flex align-center">
          <VAvatar color="primary" class="me-3" size="38">
            <VIcon icon="ri-archive-line" color="white" />
          </VAvatar>
          <div>
            <div class="text-h5 font-weight-bold">Kárdex Valorizado de Inventario</div>
            <div class="text-caption text-medium-emphasis">Reporte financiero del valor del almacén general</div>
          </div>
        </div>
        
        <div class="d-flex gap-3 align-center">
          <VTextField
            v-model="search"
            placeholder="Buscar por código o medicamento..."
            density="compact"
            variant="outlined"
            hide-details
            prepend-inner-icon="ri-search-line"
            class="max-width-280"
          />
          <VBtn color="primary" prepend-icon="ri-file-pdf-line" @click="downloadPdf">
            Imprimir Reporte
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText class="pt-4">
        <VDataTableServer
          v-model:items-per-page="itemsPerPage"
          v-model:page="currentPage"
          :headers="headers"
          :items="products"
          :items-length="totalItems"
          :loading="isLoading"
          loading-text="Valorizando inventario..."
          no-data-text="No hay productos registrados con stock activo."
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

          <template #item.barcode="{ item }">
            <span class="text-caption text-medium-emphasis font-weight-bold">{{ item.barcode || 'S/C' }}</span>
          </template>

          <template #item.name="{ item }">
            <span class="font-weight-bold text-primary">{{ item.name }}</span>
          </template>

          <template #item.stock="{ item }">
            <span class="font-weight-black">{{ item.stock }} unds</span>
          </template>

          <template #item.average_cost="{ item }">
            <span>Bs. {{ Number(item.average_cost).toFixed(2) }}</span>
          </template>

          <template #item.sale_price="{ item }">
            <span>Bs. {{ Number(item.sale_price).toFixed(2) }}</span>
          </template>

          <template #item.cost_valuation="{ item }">
            <span class="font-weight-bold text-primary">Bs. {{ Number(item.cost_valuation).toFixed(2) }}</span>
          </template>

          <template #item.sale_valuation="{ item }">
            <span class="font-weight-bold text-success">Bs. {{ Number(item.sale_valuation).toFixed(2) }}</span>
          </template>

          <template #item.margin="{ item }">
            <div class="text-end">
              <span class="font-weight-bold text-info">+Bs. {{ Number(item.margin_value).toFixed(2) }}</span>
              <div class="text-caption text-medium-emphasis font-weight-bold">({{ item.margin_percent }}%)</div>
            </div>
          </template>
        </VDataTableServer>
      </VCardText>
    </VCard>
  </div>
</template>

<style scoped>
.max-width-280 {
  max-width: 280px;
}
.gap-3 {
  gap: 12px;
}
</style>
