<script setup>
import { ref, onMounted, computed } from 'vue'
import { $api } from '@/utils/api'

const toast = useMaterializeToast()

// --- Estados del Listado ---
const catalogosStatus = ref([])
const isLoading = ref(false)
const isSyncing = ref(false)

// --- Estados del Modal Detalle ---
const isDetailDialog = ref(false)
const selectedCatalogName = ref('')
const selectedCatalogLabel = ref('')
const catalogItems = ref([])
const isLoadingItems = ref(false)
const catalogSearch = ref('')

// Obtener estado de sincronización de los catálogos
const fetchStatus = async () => {
  isLoading.value = true
  try {
    const res = await $api('/v1/siat/sync-status')
    catalogosStatus.value = res || []
  } catch (error) {
    console.error('Error fetching sync status:', error)
    toast.showError('No se pudo obtener el estado de los catálogos', 'Error')
  } finally {
    isLoading.value = false
  }
}

// Sincronizar todos los catálogos
const runSync = async () => {
  isSyncing.value = true
  try {
    const res = await $api('/v1/siat/sync', { method: 'POST' })
    toast.showSuccess(res.message || 'Catálogos sincronizados exitosamente', 'Éxito')
    await fetchStatus()
  } catch (error) {
    console.error('Error syncing catalogs:', error)
    toast.showError('Error en la sincronización. Verifique el token de Impuestos.', 'Error de Sincronización')
  } finally {
    isSyncing.value = false
  }
}

// Cargar ítems de un catálogo específico
const openCatalogDetails = async (catalog) => {
  selectedCatalogName.value = catalog.key
  selectedCatalogLabel.value = catalog.label
  isDetailDialog.value = true
  isLoadingItems.value = true
  catalogItems.value = []
  catalogSearch.value = ''
  
  try {
    const res = await $api(`/v1/siat/catalog/${catalog.key}`)
    catalogItems.value = res || []
  } catch (error) {
    console.error('Error fetching catalog details:', error)
    toast.showError('No se pudieron obtener los registros de este catálogo', 'Error')
  } finally {
    isLoadingItems.value = false
  }
}

// Estadísticas de Sincronización
const stats = computed(() => {
  const total = catalogosStatus.value.length
  const synced = catalogosStatus.value.filter(c => c.sincronizado).length
  const pending = total - synced
  return { total, synced, pending }
})

// Filtrado de ítems dentro del modal
const filteredCatalogItems = computed(() => {
  const query = catalogSearch.value.toLowerCase().trim()
  if (!query) return catalogItems.value
  
  return catalogItems.value.filter(item => {
    const code = String(item.codigo || item.codigoClasificador || item.codigoActividad || '').toLowerCase()
    const desc = String(item.descripcion || item.descripcionLeyenda || '').toLowerCase()
    return code.includes(query) || desc.includes(query)
  })
})

// Obtener las llaves dinámicas del objeto de catálogo para las cabeceras de la tabla del modal
const catalogTableHeaders = computed(() => {
  if (catalogItems.value.length === 0) return []
  
  const sample = catalogItems.value[0]
  const headers = []
  
  // Mapear de forma amigable las columnas comunes del SIAT
  if ('codigo' in sample || 'codigoClasificador' in sample || 'codigoActividad' in sample) {
    headers.push({ title: 'Código / Clasificador', key: 'code' })
  }
  if ('descripcion' in sample || 'descripcionLeyenda' in sample) {
    headers.push({ title: 'Descripción / Detalle', key: 'description' })
  }
  if ('codigoActividad' in sample && !('codigo' in sample)) {
    headers.push({ title: 'Actividad Económica', key: 'codigoActividad' })
  }
  
  // Si no mapeó nada, colocar llaves genéricas
  if (headers.length === 0) {
    Object.keys(sample).slice(0, 3).forEach(k => {
      headers.push({ title: k.toUpperCase(), key: k })
    })
  }
  
  return headers
})

// Resolver valor de código de un ítem dinámico
const getItemCode = (item) => {
  return item.codigo !== undefined ? item.codigo : (item.codigoClasificador !== undefined ? item.codigoClasificador : (item.codigoActividad !== undefined ? item.codigoActividad : '—'))
}

// Resolver valor de descripción de un ítem dinámico
const getItemDescription = (item) => {
  return item.descripcion || item.descripcionLeyenda || '—'
}

onMounted(fetchStatus)

definePage({ meta: { permission: 'all' } })
</script>

<template>
  <div class="pa-4">
    <!-- Encabezado -->
    <VRow class="mb-4" align="center">
      <VCol>
        <h2 class="mb-0 d-flex align-center">
          <VIcon icon="ri-refresh-line" color="primary" class="me-2" size="32" />
          Sincronización de Catálogos SIAT
        </h2>
        <p class="text-body-2 text-medium-emphasis mt-1">
          Sincronice los clasificadores y parámetros obligatorios directamente desde la plataforma oficial de Impuestos Nacionales.
        </p>
      </VCol>
      <VCol cols="auto">
        <VBtn
          color="primary"
          :loading="isSyncing"
          @click="runSync"
          class="font-weight-bold"
          elevation="2"
        >
          <VIcon start icon="ri-refresh-line" />
          Sincronizar Catálogos
        </VBtn>
      </VCol>
    </VRow>

    <!-- Tarjetas de Estado -->
    <VRow class="mb-6">
      <VCol cols="12" sm="4">
        <VCard border class="pa-4 text-center rounded-xl bg-primary-lighten-5" elevation="0">
          <span class="text-caption text-uppercase font-weight-bold text-primary">Total Obligatorios</span>
          <h3 class="text-h4 font-weight-black text-primary mt-1">{{ stats.total }}</h3>
        </VCard>
      </VCol>
      <VCol cols="12" sm="4">
        <VCard border class="pa-4 text-center rounded-xl bg-success-lighten-5" elevation="0">
          <span class="text-caption text-uppercase font-weight-bold text-success">Sincronizados</span>
          <h3 class="text-h4 font-weight-black text-success mt-1">{{ stats.synced }}</h3>
        </VCard>
      </VCol>
      <VCol cols="12" sm="4">
        <VCard border class="pa-4 text-center rounded-xl bg-error-lighten-5" elevation="0">
          <span class="text-caption text-uppercase font-weight-bold text-error">Pendientes / Faltantes</span>
          <h3 class="text-h4 font-weight-black text-error mt-1">{{ stats.pending }}</h3>
        </VCard>
      </VCol>
    </VRow>

    <!-- Tabla de Estado de Catálogos -->
    <VCard class="rounded-xl border" elevation="0">
      <VCardTitle class="pa-4 d-flex justify-space-between align-center bg-grey-lighten-5">
        <span class="text-h6 font-weight-bold">Paramétricas del SIAT</span>
      </VCardTitle>

      <VDataTable
        :headers="[
          { title: 'Nombre del Catálogo (SIN)', key: 'label' },
          { title: 'Ítems Descargados', key: 'items_count', align: 'center' },
          { title: 'Última Actualización', key: 'sincronizado_at' },
          { title: 'Estado', key: 'sincronizado', align: 'center' },
          { title: 'Acciones', key: 'actions', sortable: false, align: 'center' }
        ]"
        :items="catalogosStatus"
        :loading="isLoading"
        loading-text="Cargando estado de catálogos..."
        no-data-text="No se encontraron catálogos paramétricos en el sistema"
        class="text-no-wrap"
        :items-per-page="-1"
        hide-default-footer
      >
        <template #item.sincronizado_at="{ item }">
          <span v-if="item.sincronizado_at" class="text-body-2">
            {{ new Date(item.sincronizado_at).toLocaleString() }}
          </span>
          <span v-else class="text-medium-emphasis">—</span>
        </template>

        <template #item.sincronizado="{ item }">
          <VChip
            :color="item.sincronizado ? 'success' : 'error'"
            size="small"
            class="font-weight-bold"
            variant="tonal"
          >
            {{ item.sincronizado ? 'Sincronizado' : 'Pendiente' }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <VBtn
            v-if="item.sincronizado"
            icon="ri-eye-line"
            variant="tonal"
            color="primary"
            size="small"
            @click="openCatalogDetails(item)"
            title="Ver registros descargados"
          />
          <span v-else class="text-medium-emphasis">—</span>
        </template>
      </VDataTable>
    </VCard>

    <!-- MODAL DETALLE DE CATÁLOGO (VISOR DE ÍTEMS) -->
    <VDialog v-model="isDetailDialog" max-width="700">
      <VCard class="pa-sm-8 pa-4 rounded-xl">
        <DialogCloseBtn variant="text" size="default" @click="isDetailDialog = false" />
        
        <VCardTitle class="pb-4 border-b d-flex justify-space-between align-center">
          <div class="d-flex align-center">
            <VIcon icon="ri-government-line" color="primary" size="32" class="me-3" />
            <div>
              <span class="text-h5 font-weight-bold">{{ selectedCatalogLabel }}</span>
              <div class="text-caption text-medium-emphasis">Registros cargados de Impuestos Nacionales</div>
            </div>
          </div>
        </VCardTitle>

        <VCardText class="pt-6">
          <!-- Buscador interno -->
          <VTextField
            v-model="catalogSearch"
            prepend-inner-icon="ri-search-line"
            placeholder="Buscar en el catálogo..."
            variant="outlined"
            density="compact"
            class="mb-4"
            clearable
            hide-details
          />

          <VProgressLinear v-if="isLoadingItems" indeterminate color="primary" class="mb-4" />

          <!-- Tabla de Datos -->
          <div class="catalog-table-wrapper border rounded-lg overflow-hidden">
            <VTable density="comfortable" fixed-header class="h-100">
              <thead>
                <tr class="bg-grey-lighten-4">
                  <th style="width: 150px;">Código</th>
                  <th>Descripción / Detalle</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, idx) in filteredCatalogItems" :key="idx">
                  <td class="font-weight-bold text-primary">{{ getItemCode(item) }}</td>
                  <td class="text-caption text-wrap">{{ getItemDescription(item) }}</td>
                </tr>
                <tr v-if="filteredCatalogItems.length === 0 && !isLoadingItems">
                  <td colspan="2" class="text-center py-6 text-medium-emphasis">
                    No se encontraron registros que coincidan con la búsqueda.
                  </td>
                </tr>
              </tbody>
            </VTable>
          </div>
        </VCardText>

        <VCardActions class="pt-4">
          <VSpacer />
          <VBtn variant="tonal" color="secondary" @click="isDetailDialog = false">Cerrar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.catalog-table-wrapper {
  max-height: 400px;
  overflow-y: auto;
}
</style>
