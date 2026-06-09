<script setup>
const toast = useMaterializeToast()

const search = ref('')
const statusFilter = ref('TODOS')
const isLoading = ref(false)
const batches = ref([])

// Stats counters
const stats = ref({
  total: 0,
  expired: 0,
  soon: 0,
  valid: 0
})

// Table Headers
const headers = [
  { title: '#', key: 'index', sortable: false },
  { title: 'Código', key: 'product.barcode' },
  { title: 'Producto / Medicamento', key: 'product.name' },
  { title: 'Laboratorio', key: 'product.laboratory.name' },
  { title: 'Lote', key: 'batch' },
  { title: 'Vencimiento', key: 'expiration_date' },
  { title: 'Estado / Resta', key: 'status', align: 'center', sortable: false },
  { title: 'Stock Físico', key: 'current_stock', align: 'end' },
  { title: 'Costo Unit.', key: 'unit_cost', align: 'end' },
  { title: 'Total Costo', key: 'total_cost', align: 'end', sortable: false },
]

// Server side pagination & sorting refs
const itemsPerPage = ref(10)
const totalItems = ref(0)
const currentPage = ref(1)

const fetchBatches = async () => {
  try {
    isLoading.value = true
    const response = await $api('/v1/inventory/batches', {
      method: 'GET',
      query: {
        search: search.value || '',
        status: statusFilter.value,
        page: currentPage.value,
        per_page: itemsPerPage.value
      }
    })

    batches.value = response.data || response || []
    totalItems.value = response.total || response.meta?.total || 0

    // Fetch stats counters dynamically by querying the count for each category
    // (To keep it lightweight we calculate them based on the response if not paginated, 
    // or run a quick helper endpoint. Since we want accurate global stats, let's fetch all 
    // summaries. For simplicity, we can sum them from a quick unpaginated query or local calculations)
    calculateStats()

  } catch (error) {
    console.error('Error fetching batches:', error)
    toast.showError('No se pudo cargar la lista de lotes', 'Error')
  } finally {
    isLoading.value = false
  }
}

const calculateStats = async () => {
  try {
    // Quick unpaginated request to get all active batches to update stats cards
    const res = await $api('/v1/inventory/batches', {
      method: 'GET',
      query: { per_page: 1000 }
    })
    const all = res.data || res || []
    
    let total = all.length
    let expired = 0
    let soon = 0
    let valid = 0

    all.forEach(b => {
      if (!b.expiration_date) {
        valid++
      } else if (b.days_remaining < 0) {
        expired++
      } else if (b.days_remaining <= 60) {
        soon++
      } else {
        valid++
      }
    })

    stats.value = { total, expired, soon, valid }
  } catch (e) {
    console.error(e)
  }
}

const updateOptions = (options) => {
  currentPage.value = options.page
  itemsPerPage.value = options.itemsPerPage
  fetchBatches()
}

const selectFilter = (filter) => {
  statusFilter.value = filter
  currentPage.value = 1
  fetchBatches()
}

const downloadPdf = () => {
  const baseUrl = import.meta.env.VITE_API_BASE_URL || '/api'
  let url = baseUrl
  if (url.startsWith('http')) {
    url = `${url.replace(/\/$/, '')}/v1/inventory/batches/pdf?status=${statusFilter.value}`
  } else {
    url = `${window.location.origin}${url.replace(/\/$/, '')}/v1/inventory/batches/pdf?status=${statusFilter.value}`
  }
  window.open(url, '_blank')
}

watch(search, () => {
  currentPage.value = 1
  fetchBatches()
})

onMounted(() => {
  // Loaded by VDataTableServer updateOptions
})

definePage({ meta: { permission: 'all' } })
</script>

<template>
  <div class="pa-4">
    <!-- STATS CARDS -->
    <VRow class="mb-4">
      <VCol cols="12" sm="6" md="3">
        <VCard @click="selectFilter('TODOS')" :class="statusFilter === 'TODOS' ? 'border-primary border-md border-opacity-100 bg-primary-lighten-5' : 'border'" class="cursor-pointer transition-all elevation-1 rounded-xl">
          <VCardText class="d-flex align-center justify-space-between pa-5">
            <div>
              <div class="text-caption text-medium-emphasis font-weight-medium">Lotes Activos</div>
              <div class="text-h4 font-weight-black mt-1 text-primary">{{ stats.total }}</div>
            </div>
            <VAvatar size="48" color="primary" variant="tonal" class="rounded-lg">
              <VIcon icon="ri-archive-line" size="24" />
            </VAvatar>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard @click="selectFilter('VENCIDOS')" :class="statusFilter === 'VENCIDOS' ? 'border-error border-md border-opacity-100 bg-error-lighten-5' : 'border'" class="cursor-pointer transition-all elevation-1 rounded-xl">
          <VCardText class="d-flex align-center justify-space-between pa-5">
            <div>
              <div class="text-caption text-medium-emphasis font-weight-medium">Lotes Vencidos</div>
              <div class="text-h4 font-weight-black mt-1 text-error">{{ stats.expired }}</div>
            </div>
            <VAvatar size="48" color="error" variant="tonal" class="rounded-lg">
              <VIcon icon="ri-close-circle-fill" size="24" />
            </VAvatar>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard @click="selectFilter('POR_VENCER')" :class="statusFilter === 'POR_VENCER' ? 'border-warning border-md border-opacity-100 bg-warning-lighten-5' : 'border'" class="cursor-pointer transition-all elevation-1 rounded-xl">
          <VCardText class="d-flex align-center justify-space-between pa-5">
            <div>
              <div class="text-caption text-medium-emphasis font-weight-medium">Vencen en &lt; 60 Días</div>
              <div class="text-h4 font-weight-black mt-1 text-warning">{{ stats.soon }}</div>
            </div>
            <VAvatar size="48" color="warning" variant="tonal" class="rounded-lg">
              <VIcon icon="ri-time-line" size="24" />
            </VAvatar>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard @click="selectFilter('VIGENTES')" :class="statusFilter === 'VIGENTES' ? 'border-success border-md border-opacity-100 bg-success-lighten-5' : 'border'" class="cursor-pointer transition-all elevation-1 rounded-xl">
          <VCardText class="d-flex align-center justify-space-between pa-5">
            <div>
              <div class="text-caption text-medium-emphasis font-weight-medium">Lotes Vigentes</div>
              <div class="text-h4 font-weight-black mt-1 text-success">{{ stats.valid }}</div>
            </div>
            <VAvatar size="48" color="success" variant="tonal" class="rounded-lg">
              <VIcon icon="ri-checkbox-circle-fill" size="24" />
            </VAvatar>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- MAIN CARD TABLE -->
    <VCard class="rounded-xl border" elevation="0">
      <VCardTitle class="pa-6 d-flex align-center justify-space-between bg-primary-lighten-5">
        <div class="d-flex align-center">
          <VAvatar color="primary" class="me-3" size="38">
            <VIcon icon="ri-calendar-todo-line" color="white" />
          </VAvatar>
          <div>
            <div class="text-h5 font-weight-bold">Control de Lotes y Vencimientos (FEFO)</div>
            <div class="text-caption text-medium-emphasis">Priorización de salida según fecha de caducidad más próxima</div>
          </div>
        </div>
        
        <div class="d-flex gap-3 align-center">
          <VTextField
            v-model="search"
            placeholder="Buscar por lote o medicamento..."
            density="compact"
            variant="outlined"
            hide-details
            prepend-inner-icon="ri-search-line"
            class="max-width-280"
          />
          <VBtn color="error" prepend-icon="ri-file-pdf-line" @click="downloadPdf">
            Exportar PDF
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText class="pt-4">
        <VDataTableServer
          v-model:items-per-page="itemsPerPage"
          v-model:page="currentPage"
          :headers="headers"
          :items="batches"
          :items-length="totalItems"
          :loading="isLoading"
          loading-text="Buscando lotes..."
          no-data-text="No se encontraron lotes activos para esta categoría."
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

          <template #item.product.barcode="{ item }">
            <span class="text-caption text-medium-emphasis font-weight-bold">{{ item.product?.barcode || 'S/C' }}</span>
          </template>

          <template #item.product.name="{ item }">
            <span class="font-weight-bold text-primary">{{ item.product?.name }}</span>
          </template>

          <template #item.product.laboratory.name="{ item }">
            <span>{{ item.product?.laboratory?.name || 'Genérico' }}</span>
          </template>

          <template #item.batch="{ item }">
            <VChip size="small" variant="outlined" color="secondary" class="font-weight-bold">{{ item.batch || 'N/A' }}</VChip>
          </template>

          <template #item.expiration_date="{ item }">
            <span class="font-weight-medium">{{ item.expiration_date || 'Sin Vencimiento' }}</span>
          </template>

          <template #item.status="{ item }">
            <template v-if="!item.expiration_date">
              <VChip size="small" color="success" class="font-weight-bold">Vigente (S/V)</VChip>
            </template>
            <template v-else-if="item.days_remaining < 0">
              <VChip size="small" color="error" class="font-weight-bold">Vencido (hace {{ Math.abs(item.days_remaining) }} d.)</VChip>
            </template>
            <template v-else-if="item.days_remaining <= 60">
              <VChip size="small" color="warning" class="font-weight-bold">Por Vencer ({{ item.days_remaining }} d.)</VChip>
            </template>
            <template v-else>
              <VChip size="small" color="success" class="font-weight-bold">Vigente ({{ item.days_remaining }} d.)</VChip>
            </template>
          </template>

          <template #item.current_stock="{ item }">
            <span class="font-weight-black text-subtitle-2">{{ item.current_stock }} unds</span>
          </template>

          <template #item.unit_cost="{ item }">
            <span>Bs. {{ Number(item.unit_cost).toFixed(2) }}</span>
          </template>

          <template #item.total_cost="{ item }">
            <span class="font-weight-bold text-success">Bs. {{ (item.current_stock * item.unit_cost).toFixed(2) }}</span>
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
.transition-all {
  transition: all 0.2s ease-in-out;
}
.transition-all:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.08) !important;
}
.cursor-pointer {
  cursor: pointer;
}
.gap-3 {
  gap: 12px;
}
</style>
