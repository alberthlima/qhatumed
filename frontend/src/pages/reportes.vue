<script setup>
import { ref, onMounted, watch } from 'vue'

definePage({
  meta: {
    permission: 'all'
  }
})

const toast = useMaterializeToast()

const activeTab = ref('ventas')

// --- DATA LISTS FOR FILTERS ---
const customers = ref([])
const suppliers = ref([])
const users = ref([])

const loadFilterData = async () => {
  try {
    const custRes = await $api('/v1/customers')
    customers.value = custRes.data || custRes || []

    const suppRes = await $api('/v1/suppliers')
    suppliers.value = suppRes.data || suppRes || []

    const userRes = await $api('/user')
    users.value = userRes.users || userRes || []
  } catch (e) {
    console.error('Error loading filter data:', e)
  }
}

// --- SECURE FILE DOWNLOAD HELPER ---
const downloadReport = async (path, filename, params = {}) => {
  try {
    const token = localStorage.getItem("token")
    const baseUrl = import.meta.env.VITE_API_BASE_URL || '/api'
    
    // Eliminar parámetros vacíos o nulos
    const cleanParams = {}
    Object.keys(params).forEach(key => {
      if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
        cleanParams[key] = params[key]
      }
    })

    const qStr = new URLSearchParams(cleanParams).toString()
    const fullUrl = `${baseUrl}${path}${qStr ? '?' + qStr : ''}`
    
    toast.showInfo('Generando reporte, por favor espere...', 'Generando Archivo')
    
    const res = await fetch(fullUrl, {
      headers: {
        'Authorization': `Bearer ${token}`
      }
    })
    
    if (!res.ok) {
      throw new Error(`Servidor retornó código ${res.status}`)
    }
    
    const blob = await res.blob()
    const blobUrl = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = blobUrl
    link.setAttribute('download', filename)
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(blobUrl)
    
    toast.showSuccess('Reporte descargado exitosamente.', 'Descargado')
  } catch (e) {
    console.error('Error downloading report:', e)
    toast.showError('No se pudo descargar el reporte. Verifique su conexión y filtros.', 'Error de Descarga')
  }
}

// ==========================================
// TABS & LOGIC (WITH SERVER SIDE PAGINATION)
// ==========================================

// --- TAB 1: VENTAS ---
const salesList = ref([])
const salesTotal = ref(0)
const salesCount = ref(0)
const salesLoading = ref(false)
const salesPage = ref(1)
const salesPerPage = ref(15)
const salesTotalItems = ref(0)
const salesFilters = ref({
  start_date: new Date().toISOString().slice(0, 8) + '01', // Primer día del mes
  end_date: new Date().toISOString().slice(0, 10),
  type: 'all',
  customer_id: null,
  user_id: null,
  status: 'COMPLETED'
})

const fetchSales = async () => {
  salesLoading.value = true
  try {
    const params = {
      page: salesPage.value,
      per_page: salesPerPage.value,
      ...salesFilters.value
    }
    const response = await $api('/v1/reports/sales', { params })
    if (response.success) {
      salesList.value = response.data.data || []
      salesTotal.value = response.totals.amount || 0
      salesCount.value = response.totals.count || 0
      salesTotalItems.value = response.data.total || response.data.meta?.total || 0
    }
  } catch (e) {
    console.error(e)
    toast.showError('No se pudo cargar el listado de ventas.')
  } finally {
    salesLoading.value = false
  }
}

const updateSalesOptions = (options) => {
  salesPage.value = options.page
  salesPerPage.value = options.itemsPerPage
  fetchSales()
}

const triggerSalesQuery = () => {
  salesPage.value = 1
  fetchSales()
}

const exportSalesPdf = () => {
  downloadReport('/v1/reports/sales/pdf', `reporte_ventas_${new Date().toISOString().slice(0, 10)}.pdf`, salesFilters.value)
}

const exportSalesExcel = () => {
  downloadReport('/v1/reports/sales/excel', `reporte_ventas_${new Date().toISOString().slice(0, 10)}.csv`, salesFilters.value)
}

// --- TAB 2: COMPRAS ---
const purchasesList = ref([])
const purchasesTotal = ref(0)
const purchasesCount = ref(0)
const purchasesLoading = ref(false)
const purchasesPage = ref(1)
const purchasesPerPage = ref(15)
const purchasesTotalItems = ref(0)
const purchasesFilters = ref({
  start_date: new Date().toISOString().slice(0, 8) + '01',
  end_date: new Date().toISOString().slice(0, 10),
  supplier_id: null,
  status: 'COMPLETED'
})

const fetchPurchases = async () => {
  purchasesLoading.value = true
  try {
    const params = {
      page: purchasesPage.value,
      per_page: purchasesPerPage.value,
      ...purchasesFilters.value
    }
    const response = await $api('/v1/reports/purchases', { params })
    if (response.success) {
      purchasesList.value = response.data.data || []
      purchasesTotal.value = response.totals.amount || 0
      purchasesCount.value = response.totals.count || 0
      purchasesTotalItems.value = response.data.total || response.data.meta?.total || 0
    }
  } catch (e) {
    console.error(e)
    toast.showError('No se pudo cargar el listado de compras.')
  } finally {
    purchasesLoading.value = false
  }
}

const updatePurchasesOptions = (options) => {
  purchasesPage.value = options.page
  purchasesPerPage.value = options.itemsPerPage
  fetchPurchases()
}

const triggerPurchasesQuery = () => {
  purchasesPage.value = 1
  fetchPurchases()
}

const exportPurchasesPdf = () => {
  downloadReport('/v1/reports/purchases/pdf', `reporte_compras_${new Date().toISOString().slice(0, 10)}.pdf`, purchasesFilters.value)
}

const exportPurchasesExcel = () => {
  downloadReport('/v1/reports/purchases/excel', `reporte_compras_${new Date().toISOString().slice(0, 10)}.csv`, purchasesFilters.value)
}

// --- TAB 3: VENCIMIENTO DE LOTES (FEFO) ---
const batchesList = ref([])
const batchesLoading = ref(false)
const batchesPage = ref(1)
const batchesPerPage = ref(15)
const batchesTotalItems = ref(0)
const batchesSearch = ref('')
const batchesStatus = ref('TODOS')

const fetchBatches = async () => {
  batchesLoading.value = true
  try {
    const params = {
      page: batchesPage.value,
      per_page: batchesPerPage.value,
      search: batchesSearch.value,
      status: batchesStatus.value
    }
    const response = await $api('/v1/inventory/batches', { params })
    batchesList.value = response.data || []
    batchesTotalItems.value = response.total || response.meta?.total || 0
  } catch (e) {
    console.error(e)
    toast.showError('No se pudieron cargar los lotes de inventario.')
  } finally {
    batchesLoading.value = false
  }
}

const updateBatchesOptions = (options) => {
  batchesPage.value = options.page
  batchesPerPage.value = options.itemsPerPage
  fetchBatches()
}

const triggerBatchesQuery = () => {
  batchesPage.value = 1
  fetchBatches()
}

const exportBatchesPdf = () => {
  downloadReport('/v1/inventory/batches/pdf', 'control_lotes_vencimientos.pdf', { status: batchesStatus.value, search: batchesSearch.value })
}

// --- TAB 4: VALUACIÓN PEPS / CPP ---
const valuationList = ref([])
const valuationSummary = ref({
  total_cost: 0,
  total_cost_cpp: 0,
  total_sale: 0,
  total_margin: 0,
  margin_percent: 0,
  total_margin_cpp: 0,
  margin_percent_cpp: 0
})
const valuationLoading = ref(false)
const valuationPage = ref(1)
const valuationPerPage = ref(15)
const valuationTotalItems = ref(0)
const valuationSearch = ref('')

const fetchValuation = async () => {
  valuationLoading.value = true
  try {
    const params = {
      page: valuationPage.value,
      per_page: valuationPerPage.value,
      search: valuationSearch.value
    }
    const response = await $api('/v1/inventory/valuation', { params })
    valuationList.value = response.products.data || []
    valuationTotalItems.value = response.products.total || response.products.meta?.total || 0
    valuationSummary.value = response.summary || {
      total_cost: 0,
      total_cost_cpp: 0,
      total_sale: 0,
      total_margin: 0,
      margin_percent: 0,
      total_margin_cpp: 0,
      margin_percent_cpp: 0
    }
  } catch (e) {
    console.error(e)
    toast.showError('No se pudo cargar la valuación de inventario.')
  } finally {
    valuationLoading.value = false
  }
}

const updateValuationOptions = (options) => {
  valuationPage.value = options.page
  valuationPerPage.value = options.itemsPerPage
  fetchValuation()
}

const triggerValuationQuery = () => {
  valuationPage.value = 1
  fetchValuation()
}

const exportValuationPdf = () => {
  downloadReport('/v1/inventory/valuation/pdf', 'inventario_valorizado.pdf')
}

// --- MOUNTED & WATCHERS ---
onMounted(() => {
  loadFilterData()
})

watch(activeTab, (newTab) => {
  if (newTab === 'ventas') triggerSalesQuery()
  if (newTab === 'compras') triggerPurchasesQuery()
  if (newTab === 'lotes') triggerBatchesQuery()
  if (newTab === 'valuacion') triggerValuationQuery()
})
</script>

<template>
  <div class="pa-4">
    <!-- Header General -->
    <VRow class="mb-4 align-center">
      <VCol cols="12">
        <h1 class="text-h4 font-weight-black text-primary mb-1">
          Centro de Reportes y Cierres
        </h1>
        <p class="text-subtitle-1 text-grey-darken-1 mb-0">
          Consulte, analice y exporte información de facturación, almacenes y valuación PEPS / CPP.
        </p>
      </VCol>
    </VRow>

    <VCard elevation="3" class="rounded-xl border">
      <!-- Pestañas Modulares -->
      <VTabs v-model="activeTab" color="primary" grow align-tabs="center" class="border-bottom">
        <VTab value="ventas">
          <VIcon start icon="ri-hand-coin-line" class="mr-2" /> Ventas y Recibos
        </VTab>
        <VTab value="compras">
          <VIcon start icon="ri-shopping-cart-2-line" class="mr-2" /> Compras y Proveedores
        </VTab>
        <VTab value="lotes">
          <VIcon start icon="ri-time-line" class="mr-2" /> Vencimiento de Lotes
        </VTab>
        <VTab value="valuacion">
          <VIcon start icon="ri-line-chart-line" class="mr-2" /> Valuación PEPS / CPP
        </VTab>
      </VTabs>

      <VWindow v-model="activeTab" class="pa-6">
        <!-- 1. PESTAÑA VENTAS -->
        <VWindowItem value="ventas">
          <!-- Formulario de Filtros de Ventas -->
          <VCard variant="outlined" class="rounded-lg mb-6 bg-grey-lighten-5 pa-4">
            <h3 class="text-subtitle-1 font-weight-bold text-primary mb-4">
              <VIcon icon="ri-filter-2-line" size="18" class="mr-1" /> Filtros de Búsqueda
            </h3>
            <VRow>
              <VCol cols="12" sm="6" md="2">
                <VTextField v-model="salesFilters.start_date" type="date" label="Fecha Inicio" variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="6" md="2">
                <VTextField v-model="salesFilters.end_date" type="date" label="Fecha Fin" variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="6" md="2">
                <VSelect 
                  v-model="salesFilters.type" 
                  :items="[{title:'Todos', value:'all'}, {title:'Factura SIAT', value:'invoice'}, {title:'Recibo Simple', value:'receipt'}]" 
                  label="Tipo" 
                  variant="outlined" 
                  density="compact" 
                />
              </VCol>
              <VCol cols="12" sm="6" md="2">
                <VSelect 
                  v-model="salesFilters.customer_id" 
                  :items="customers" 
                  item-title="name" 
                  item-value="id"
                  label="Cliente" 
                  clearable
                  variant="outlined" 
                  density="compact" 
                />
              </VCol>
              <VCol cols="12" sm="6" md="2">
                <VSelect 
                  v-model="salesFilters.user_id" 
                  :items="users" 
                  item-title="full_name" 
                  item-value="id"
                  label="Cajero" 
                  clearable
                  variant="outlined" 
                  density="compact" 
                />
              </VCol>
              <VCol cols="12" sm="6" md="2">
                <VSelect 
                  v-model="salesFilters.status" 
                  :items="[{title:'Todo', value:''}, {title:'Completado', value:'COMPLETED'}, {title:'Anulado', value:'ANNULLED'}]" 
                  label="Estado" 
                  variant="outlined" 
                  density="compact" 
                />
              </VCol>
            </VRow>
            
            <div class="d-flex flex-wrap gap-3 mt-4">
              <VBtn color="primary" @click="triggerSalesQuery" :loading="salesLoading">
                <VIcon start icon="ri-search-line" /> Consultar
              </VBtn>
              <VSpacer />
              <VBtn color="error" variant="tonal" @click="exportSalesPdf" :disabled="salesTotalItems === 0">
                <VIcon start icon="ri-file-pdf-line" /> Descargar PDF
              </VBtn>
              <VBtn color="success" variant="tonal" @click="exportSalesExcel" :disabled="salesTotalItems === 0">
                <VIcon start icon="ri-file-excel-line" /> Exportar Excel
              </VBtn>
            </div>
          </VCard>

          <!-- Resumen Rápido -->
          <VRow v-if="!salesLoading && salesTotalItems > 0" class="mb-4">
            <VCol cols="12" sm="6">
              <VCard class="pa-4 border bg-blue-lighten-5 text-blue-darken-3 rounded-lg" elevation="0">
                <div class="text-caption text-uppercase font-weight-bold">Transacciones Encontradas</div>
                <div class="text-h4 font-weight-bold">{{ salesCount }}</div>
              </VCard>
            </VCol>
            <VCol cols="12" sm="6">
              <VCard class="pa-4 border bg-green-lighten-5 text-green-darken-3 rounded-lg" elevation="0">
                <div class="text-caption text-uppercase font-weight-bold">Total Facturado / Recibido</div>
                <div class="text-h4 font-weight-bold">Bs. {{ salesTotal.toLocaleString('es-BO', { minimumFractionDigits: 2 }) }}</div>
              </VCard>
            </VCol>
          </VRow>

          <!-- Listado de Ventas -->
          <VDataTableServer
            v-model:items-per-page="salesPerPage"
            v-model:page="salesPage"
            :headers="[
              { title: 'Fecha', key: 'sale_date' },
              { title: 'Cliente', key: 'customer' },
              { title: 'NIT/CI', key: 'nit_ci' },
              { title: 'Tipo', key: 'is_invoice' },
              { title: 'Nro Fact.', key: 'invoice_number' },
              { title: 'Total', key: 'total_amount', align: 'end' },
              { title: 'Estado', key: 'status', align: 'center' }
            ]"
            :items="salesList"
            :items-length="salesTotalItems"
            :loading="salesLoading"
            loading-text="Buscando ventas en base de datos..."
            no-data-text="No se encontraron ventas para los criterios seleccionados."
            class="elevation-0 border rounded-lg"
            :items-per-page-text="'Filas por página:'"
            :page-text="'{0}-{1} de {2}'"
            :items-per-page-options="[
              { value: 5, title: '5' },
              { value: 15, title: '15' },
              { value: 30, title: '30' },
              { value: 50, title: '50' }
            ]"
            @update:options="updateSalesOptions"
          >
            <template #item.sale_date="{ item }">
              {{ new Date(item.sale_date).toLocaleString('es-BO') }}
            </template>
            <template #item.customer="{ item }">
              <span class="font-weight-bold">{{ item.customer ? item.customer.name : (item.business_name || 'S/N') }}</span>
            </template>
            <template #item.is_invoice="{ item }">
              <VChip size="small" :color="item.is_invoice ? 'primary' : 'secondary'">
                {{ item.is_invoice ? 'Factura SIAT' : 'Recibo' }}
              </VChip>
            </template>
            <template #item.invoice_number="{ item }">
              {{ item.invoice_number || '-' }}
            </template>
            <template #item.total_amount="{ item }">
              Bs. {{ parseFloat(item.total_amount).toLocaleString('es-BO', { minimumFractionDigits: 2 }) }}
            </template>
            <template #item.status="{ item }">
              <VChip size="small" :color="item.status === 'COMPLETED' ? 'success' : 'error'" class="font-weight-bold">
                {{ item.status === 'COMPLETED' ? 'Completado' : 'Anulado' }}
              </VChip>
            </template>
          </VDataTableServer>
        </VWindowItem>

        <!-- 2. PESTAÑA COMPRAS -->
        <VWindowItem value="compras">
          <VCard variant="outlined" class="rounded-lg mb-6 bg-grey-lighten-5 pa-4">
            <h3 class="text-subtitle-1 font-weight-bold text-primary mb-4">
              <VIcon icon="ri-filter-2-line" size="18" class="mr-1" /> Filtros de Búsqueda
            </h3>
            <VRow>
              <VCol cols="12" sm="6" md="3">
                <VTextField v-model="purchasesFilters.start_date" type="date" label="Fecha Inicio" variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="6" md="3">
                <VTextField v-model="purchasesFilters.end_date" type="date" label="Fecha Fin" variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="6" md="3">
                <VSelect 
                  v-model="purchasesFilters.supplier_id" 
                  :items="suppliers" 
                  item-title="company_name" 
                  item-value="id"
                  label="Proveedor" 
                  clearable
                  variant="outlined" 
                  density="compact" 
                />
              </VCol>
              <VCol cols="12" sm="6" md="3">
                <VSelect 
                  v-model="purchasesFilters.status" 
                  :items="[{title:'Todo', value:''}, {title:'Completado', value:'COMPLETED'}, {title:'Anulado', value:'ANNULLED'}]" 
                  label="Estado" 
                  variant="outlined" 
                  density="compact" 
                />
              </VCol>
            </VRow>
            
            <div class="d-flex flex-wrap gap-3 mt-4">
              <VBtn color="primary" @click="triggerPurchasesQuery" :loading="purchasesLoading">
                <VIcon start icon="ri-search-line" /> Consultar
              </VBtn>
              <VSpacer />
              <VBtn color="error" variant="tonal" @click="exportPurchasesPdf" :disabled="purchasesTotalItems === 0">
                <VIcon start icon="ri-file-pdf-line" /> Descargar PDF
              </VBtn>
              <VBtn color="success" variant="tonal" @click="exportPurchasesExcel" :disabled="purchasesTotalItems === 0">
                <VIcon start icon="ri-file-excel-line" /> Exportar Excel
              </VBtn>
            </div>
          </VCard>

          <!-- Resumen Rápido -->
          <VRow v-if="!purchasesLoading && purchasesTotalItems > 0" class="mb-4">
            <VCol cols="12" sm="6">
              <VCard class="pa-4 border bg-blue-lighten-5 text-blue-darken-3 rounded-lg" elevation="0">
                <div class="text-caption text-uppercase font-weight-bold">Registros de Compra</div>
                <div class="text-h4 font-weight-bold">{{ purchasesCount }}</div>
              </VCard>
            </VCol>
            <VCol cols="12" sm="6">
              <VCard class="pa-4 border bg-green-lighten-5 text-green-darken-3 rounded-lg" elevation="0">
                <div class="text-caption text-uppercase font-weight-bold">Monto Total de Compras</div>
                <div class="text-h4 font-weight-bold">Bs. {{ purchasesTotal.toLocaleString('es-BO', { minimumFractionDigits: 2 }) }}</div>
              </VCard>
            </VCol>
          </VRow>

          <!-- Listado de Compras -->
          <VDataTableServer
            v-model:items-per-page="purchasesPerPage"
            v-model:page="purchasesPage"
            :headers="[
              { title: 'Fecha Compra', key: 'purchase_date' },
              { title: 'Proveedor', key: 'supplier' },
              { title: 'Factura Nro', key: 'invoice_number' },
              { title: 'Monto Total', key: 'total_amount', align: 'end' },
              { title: 'Registrador', key: 'user' },
              { title: 'Estado', key: 'status', align: 'center' }
            ]"
            :items="purchasesList"
            :items-length="purchasesTotalItems"
            :loading="purchasesLoading"
            loading-text="Cargando compras..."
            no-data-text="No se encontraron compras para los filtros aplicados."
            class="elevation-0 border rounded-lg"
            :items-per-page-text="'Filas por página:'"
            :page-text="'{0}-{1} de {2}'"
            :items-per-page-options="[
              { value: 5, title: '5' },
              { value: 15, title: '15' },
              { value: 30, title: '30' },
              { value: 50, title: '50' }
            ]"
            @update:options="updatePurchasesOptions"
          >
            <template #item.purchase_date="{ item }">
              {{ new Date(item.purchase_date).toLocaleDateString('es-BO') }}
            </template>
            <template #item.supplier="{ item }">
              <span class="font-weight-bold">{{ item.supplier ? item.supplier.company_name : '-' }}</span>
            </template>
            <template #item.invoice_number="{ item }">
              {{ item.invoice_number || '-' }}
            </template>
            <template #item.total_amount="{ item }">
              Bs. {{ parseFloat(item.total_amount).toLocaleString('es-BO', { minimumFractionDigits: 2 }) }}
            </template>
            <template #item.user="{ item }">
              {{ item.user ? item.user.name : '-' }}
            </template>
            <template #item.status="{ item }">
              <VChip size="small" :color="item.status === 'COMPLETED' ? 'success' : 'error'" class="font-weight-bold">
                {{ item.status === 'COMPLETED' ? 'Completado' : 'Anulado' }}
              </VChip>
            </template>
          </VDataTableServer>
        </VWindowItem>

        <!-- 3. PESTAÑA VENCIMIENTO DE LOTES (FEFO) -->
        <VWindowItem value="lotes">
          <VCard variant="outlined" class="rounded-lg mb-6 bg-grey-lighten-5 pa-4">
            <h3 class="text-subtitle-1 font-weight-bold text-primary mb-4">
              <VIcon icon="ri-shield-keyhole-line" size="18" class="mr-1" /> Monitoreo y Filtros
            </h3>
            <VRow>
              <VCol cols="12" sm="6" md="6">
                <VTextField 
                  v-model="batchesSearch" 
                  placeholder="Buscar producto por nombre o código de barra..." 
                  prepend-inner-icon="ri-search-line" 
                  variant="outlined" 
                  density="compact"
                  clearable
                  @keyup.enter="triggerBatchesQuery"
                />
              </VCol>
              <VCol cols="12" sm="6" md="4">
                <VSelect
                  v-model="batchesStatus"
                  :items="[
                    {title: 'Todos los lotes activos', value: 'TODOS'},
                    {title: 'Lotes vencidos', value: 'VENCIDOS'},
                    {title: 'Próximos a vencer (<= 60 días)', value: 'POR_VENCER'},
                    {title: 'Vigentes y seguros (> 60 días)', value: 'VIGENTES'}
                  ]"
                  label="Filtrar por Vencimiento"
                  variant="outlined"
                  density="compact"
                />
              </VCol>
              <VCol cols="12" sm="12" md="2" class="d-flex gap-2">
                <VBtn color="primary" block @click="triggerBatchesQuery" :loading="batchesLoading">
                  Buscar
                </VBtn>
              </VCol>
            </VRow>
            
            <div class="d-flex justify-end mt-4">
              <VBtn color="error" variant="tonal" @click="exportBatchesPdf" :disabled="batchesTotalItems === 0">
                <VIcon start icon="ri-file-pdf-line" /> Exportar Reporte de Vencimientos
              </VBtn>
            </div>
          </VCard>

          <!-- Listado de Lotes -->
          <VDataTableServer
            v-model:items-per-page="batchesPerPage"
            v-model:page="batchesPage"
            :headers="[
              { title: 'Medicamento / Producto', key: 'product.name' },
              { title: 'Laboratorio', key: 'product.laboratory.name' },
              { title: 'Lote', key: 'batch' },
              { title: 'Stock Lote', key: 'current_stock', align: 'end' },
              { title: 'Fecha Vence', key: 'expiration_date' },
              { title: 'Días Restantes', key: 'days_remaining', align: 'center' }
            ]"
            :items="batchesList"
            :items-length="batchesTotalItems"
            :loading="batchesLoading"
            loading-text="Cargando control FEFO de lotes..."
            no-data-text="No se encontraron lotes activos para los filtros actuales."
            class="elevation-0 border rounded-lg"
            :items-per-page-text="'Filas por página:'"
            :page-text="'{0}-{1} de {2}'"
            :items-per-page-options="[
              { value: 5, title: '5' },
              { value: 15, title: '15' },
              { value: 30, title: '30' },
              { value: 50, title: '50' }
            ]"
            @update:options="updateBatchesOptions"
          >
            <template #item.product.name="{ item }">
              <span class="font-weight-bold">{{ item.product ? item.product.name : '-' }}</span>
            </template>
            <template #item.product.laboratory.name="{ item }">
              {{ item.product && item.product.laboratory ? item.product.laboratory.name : 'Genérico' }}
            </template>
            <template #item.current_stock="{ item }">
              <span class="font-weight-black">{{ item.current_stock }}</span> uds
            </template>
            <template #item.expiration_date="{ item }">
              <span class="font-weight-bold">{{ item.expiration_date ? new Date(item.expiration_date).toLocaleDateString('es-BO') : 'Sin vencimiento' }}</span>
            </template>
            <template #item.days_remaining="{ item }">
              <span v-if="item.days_remaining === null" class="text-grey font-weight-bold">
                N/A
              </span>
              <span v-else-if="item.days_remaining < 0" class="text-red font-weight-black">
                Vencido hace {{ Math.abs(item.days_remaining) }} días
              </span>
              <span v-else-if="item.days_remaining <= 30" class="text-red-accent-4 font-weight-black blink-effect">
                Crítico ({{ item.days_remaining }} días)
              </span>
              <span v-else-if="item.days_remaining <= 90" class="text-amber-darken-3 font-weight-bold">
                Advertencia ({{ item.days_remaining }} días)
              </span>
              <span v-else class="text-success font-weight-bold">
                {{ item.days_remaining }} días
              </span>
            </template>
          </VDataTableServer>
        </VWindowItem>

        <!-- 4. PESTAÑA VALUACIÓN PEPS / CPP -->
        <VWindowItem value="valuacion">
          <!-- Cuadro de Estadísticas PEPS y CPP -->
          <VRow class="mb-6" v-if="!valuationLoading">
            <VCol cols="12" sm="6" md="2.4" class="d-flex">
              <VCard class="pa-4 border bg-blue-grey-lighten-5 rounded-lg w-100" elevation="0">
                <div class="text-caption text-uppercase font-weight-bold text-blue-grey">Costo Total PEPS</div>
                <div class="text-h5 font-weight-bold text-blue-grey-darken-3 mt-1">
                  Bs. {{ valuationSummary.total_cost.toLocaleString('es-BO', { minimumFractionDigits: 2 }) }}
                </div>
              </VCard>
            </VCol>
            <VCol cols="12" sm="6" md="2.4" class="d-flex">
              <VCard class="pa-4 border bg-teal-lighten-5 rounded-lg w-100" elevation="0">
                <div class="text-caption text-uppercase font-weight-bold text-teal">Costo Total CPP</div>
                <div class="text-h5 font-weight-bold text-teal-darken-4 mt-1">
                  Bs. {{ valuationSummary.total_cost_cpp.toLocaleString('es-BO', { minimumFractionDigits: 2 }) }}
                </div>
              </VCard>
            </VCol>
            <VCol cols="12" sm="6" md="2.4" class="d-flex">
              <VCard class="pa-4 border bg-blue-lighten-5 rounded-lg w-100" elevation="0">
                <div class="text-caption text-uppercase font-weight-bold text-blue">Venta Estimada</div>
                <div class="text-h5 font-weight-bold text-blue-darken-3 mt-1">
                  Bs. {{ valuationSummary.total_sale.toLocaleString('es-BO', { minimumFractionDigits: 2 }) }}
                </div>
              </VCard>
            </VCol>
            <VCol cols="12" sm="6" md="2.4" class="d-flex">
              <VCard class="pa-4 border bg-green-lighten-5 rounded-lg w-100" elevation="0">
                <div class="text-caption text-uppercase font-weight-bold text-green">Utilidad PEPS / CPP</div>
                <div class="text-subtitle-2 font-weight-bold text-green-darken-4 mt-1">
                  PEPS: Bs. {{ valuationSummary.total_margin.toLocaleString('es-BO', { maximumFractionDigits: 0 }) }}<br>
                  CPP: Bs. {{ valuationSummary.total_margin_cpp.toLocaleString('es-BO', { maximumFractionDigits: 0 }) }}
                </div>
              </VCard>
            </VCol>
            <VCol cols="12" sm="6" md="2.4" class="d-flex">
              <VCard class="pa-4 border bg-indigo-lighten-5 rounded-lg w-100" elevation="0">
                <div class="text-caption text-uppercase font-weight-bold text-indigo">Rentabilidad PEPS / CPP</div>
                <div class="text-subtitle-2 font-weight-bold text-indigo-darken-4 mt-1">
                  PEPS: +{{ valuationSummary.margin_percent.toFixed(1) }}%<br>
                  CPP: +{{ valuationSummary.margin_percent_cpp.toFixed(1) }}%
                </div>
              </VCard>
            </VCol>
          </VRow>

          <VCard variant="outlined" class="rounded-lg mb-6 bg-grey-lighten-5 pa-4">
            <h3 class="text-subtitle-1 font-weight-bold text-primary mb-4">
              <VIcon icon="ri-search-line" size="18" class="mr-1" /> Buscar Producto
            </h3>
            <VRow>
              <VCol cols="12" sm="8">
                <VTextField 
                  v-model="valuationSearch" 
                  placeholder="Ingrese el nombre del medicamento para filtrar la valuación..." 
                  prepend-inner-icon="ri-search-line" 
                  variant="outlined" 
                  density="compact"
                  clearable
                  @keyup.enter="triggerValuationQuery"
                />
              </VCol>
              <VCol cols="12" sm="4" class="d-flex gap-2">
                <VBtn color="primary" @click="triggerValuationQuery" :loading="valuationLoading">
                  Filtrar
                </VBtn>
                <VBtn color="error" variant="tonal" @click="exportValuationPdf" :disabled="valuationTotalItems === 0">
                  <VIcon start icon="ri-file-pdf-line" /> PDF PEPS / CPP
                </VBtn>
              </VCol>
            </VRow>
          </VCard>

          <!-- Listado de Valuación de Productos -->
          <VDataTableServer
            v-model:items-per-page="valuationPerPage"
            v-model:page="valuationPage"
            :headers="[
              { title: 'Código de Barra', key: 'barcode' },
              { title: 'Producto / Medicamento', key: 'name' },
              { title: 'Laboratorio', key: 'laboratory' },
              { title: 'Stock Actual', key: 'stock', align: 'end' },
              { title: 'Costo Prom (CPP)', key: 'average_cost', align: 'end' },
              { title: 'P. Venta', key: 'sale_price', align: 'end' },
              { title: 'Valuación Costo PEPS', key: 'cost_valuation', align: 'end' },
              { title: 'Valuación Costo CPP', key: 'cpp_valuation', align: 'end' },
              { title: 'Valuación Venta', key: 'sale_valuation', align: 'end' },
              { title: 'Rentab. PEPS/CPP', key: 'margin_percent', align: 'center' }
            ]"
            :items="valuationList"
            :items-length="valuationTotalItems"
            :loading="valuationLoading"
            loading-text="Evaluando inventario bajo métodos PEPS y CPP..."
            no-data-text="No hay productos valorizados en inventario."
            class="elevation-0 border rounded-lg"
            :items-per-page-text="'Filas por página:'"
            :page-text="'{0}-{1} de {2}'"
            :items-per-page-options="[
              { value: 5, title: '5' },
              { value: 15, title: '15' },
              { value: 30, title: '30' },
              { value: 50, title: '50' }
            ]"
            @update:options="updateValuationOptions"
          >
            <template #item.barcode="{ item }">
              {{ item.barcode || 'S/C' }}
            </template>
            <template #item.name="{ item }">
              <span class="font-weight-bold text-primary">{{ item.name }}</span>
            </template>
            <template #item.stock="{ item }">
              <span class="font-weight-black">{{ item.stock }}</span> uds
            </template>
            <template #item.average_cost="{ item }">
              Bs. {{ parseFloat(item.average_cost).toLocaleString('es-BO', { minimumFractionDigits: 2 }) }}
            </template>
            <template #item.sale_price="{ item }">
              Bs. {{ parseFloat(item.sale_price).toLocaleString('es-BO', { minimumFractionDigits: 2 }) }}
            </template>
            <template #item.cost_valuation="{ item }">
              <span class="font-weight-bold text-grey-darken-3">Bs. {{ parseFloat(item.cost_valuation).toLocaleString('es-BO', { minimumFractionDigits: 2 }) }}</span>
            </template>
            <template #item.cpp_valuation="{ item }">
              <span class="font-weight-bold text-teal-darken-3">Bs. {{ parseFloat(item.cpp_valuation).toLocaleString('es-BO', { minimumFractionDigits: 2 }) }}</span>
            </template>
            <template #item.sale_valuation="{ item }">
              <span class="font-weight-bold text-success">Bs. {{ parseFloat(item.sale_valuation).toLocaleString('es-BO', { minimumFractionDigits: 2 }) }}</span>
            </template>
            <template #item.margin_percent="{ item }">
              <VChip size="small" color="success" class="font-weight-bold">
                +{{ parseFloat(item.margin_percent).toFixed(0) }}%/+{{ parseFloat(item.margin_percent_cpp).toFixed(0) }}%
              </VChip>
            </template>
          </VDataTableServer>
        </VWindowItem>
      </VWindow>
    </VCard>
  </div>
</template>

<style scoped>
.border-bottom {
  border-bottom: 1px solid #f1f1f1 !important;
}
.blink-effect {
  animation: blinker 1.5s linear infinite;
}
@keyframes blinker {
  50% { opacity: 0.5; }
}
</style>
