<script setup>
import { ref, onMounted, computed } from 'vue'
import VueApexCharts from 'vue3-apexcharts'

definePage({ 
  meta: {
    permission: 'all'
  }
})

const toast = useMaterializeToast()
const isLoading = ref(true)
const stats = ref(null)

const loadStats = async () => {
  try {
    isLoading.value = true
    const response = await $api('/v1/dashboard/statistics')
    stats.value = response
  } catch (error) {
    console.error('Error loading dashboard stats:', error)
    toast.showError('No se pudieron cargar las estadísticas de control.', 'Error de Servidor')
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  loadStats()
})

// Configuración de Gráfico de Tendencias (Ventas vs Compras)
const trendChartOptions = computed(() => {
  if (!stats.value || !stats.value.trend) return {}
  return {
    chart: {
      type: 'area',
      toolbar: { show: false },
      zoom: { enabled: false },
      fontFamily: 'inherit'
    },
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 3 },
    colors: ['#3a7bd5', '#f857a6'],
    fill: {
      type: 'gradient',
      gradient: {
        shadeIntensity: 1,
        opacityFrom: 0.45,
        opacityTo: 0.05,
        stops: [0, 90, 100]
      }
    },
    xaxis: {
      categories: stats.value.trend.categories || [],
      axisBorder: { show: false },
      axisTicks: { show: false },
      labels: {
        style: { colors: '#a3a3a3', fontSize: '11px' }
      }
    },
    yaxis: {
      labels: {
        style: { colors: '#a3a3a3', fontSize: '11px' },
        formatter: (val) => `Bs. ${val.toLocaleString('es-BO', { minimumFractionDigits: 0 })}`
      }
    },
    grid: {
      borderColor: '#f1f1f1',
      strokeDashArray: 4,
      xaxis: { lines: { show: true } }
    },
    tooltip: {
      y: {
        formatter: (val) => `Bs. ${val.toLocaleString('es-BO', { minimumFractionDigits: 2 })}`
      }
    },
    legend: {
      position: 'top',
      horizontalAlign: 'right',
      labels: { colors: '#4b5563' }
    }
  }
})

const trendChartSeries = computed(() => {
  if (!stats.value || !stats.value.trend) return []
  return [
    {
      name: 'Ventas Completadas',
      data: stats.value.trend.sales || []
    },
    {
      name: 'Compras de Lotes',
      data: stats.value.trend.purchases || []
    }
  ]
})

// Configuración de Gráfico de Métodos de Pago
const paymentChartOptions = computed(() => {
  if (!stats.value || !stats.value.payments) return {}
  return {
    chart: {
      type: 'donut',
      fontFamily: 'inherit'
    },
    labels: ['Efectivo', 'Tarjeta', 'QR / Transf.'],
    colors: ['#2ecc71', '#3498db', '#9b59b6'],
    stroke: { show: false },
    plotOptions: {
      pie: {
        donut: {
          size: '72%',
          labels: {
            show: true,
            total: {
              show: true,
              label: 'Total Ventas',
              formatter: () => {
                const total = (stats.value.payments.cash || 0) + (stats.value.payments.card || 0) + (stats.value.payments.qr || 0)
                return `Bs. ${total.toLocaleString('es-BO', { maximumFractionDigits: 0 })}`
              },
              style: {
                fontSize: '14px',
                fontWeight: 'bold',
                color: '#374151'
              }
            }
          }
        }
      }
    },
    legend: {
      position: 'bottom',
      fontSize: '12px',
      labels: { colors: '#4b5563' }
    },
    dataLabels: { enabled: false }
  }
})

const paymentChartSeries = computed(() => {
  if (!stats.value || !stats.value.payments) return []
  return [
    stats.value.payments.cash || 0,
    stats.value.payments.card || 0,
    stats.value.payments.qr || 0
  ]
})
</script>

<template>
  <div class="pa-4">
    <!-- Loader de Pantalla -->
    <div v-if="isLoading" class="d-flex flex-column align-center justify-center py-12" style="min-height: 400px;">
      <VProgressCircular indeterminate color="primary" size="64" class="mb-4" />
      <span class="text-subtitle-1 text-grey-darken-1 font-weight-medium">Cargando métricas en tiempo real...</span>
    </div>

    <div v-else-if="stats">
      <!-- Fila de Cabecera -->
      <VRow class="mb-6 align-center">
        <VCol cols="12" sm="8">
          <h1 class="text-h4 font-weight-black text-primary mb-1">
            Escritorio Principal
          </h1>
          <p class="text-subtitle-1 text-grey-darken-1 mb-0">
            Resumen de actividad diaria, facturación siat y control de inventario
          </p>
        </VCol>
        <VCol cols="12" sm="4" class="text-sm-end">
          <VBtn color="primary" variant="tonal" class="rounded-lg" @click="loadStats">
            <VIcon icon="ri-refresh-line" class="mr-2" /> Actualizar Datos
          </VBtn>
        </VCol>
      </VRow>

      <!-- Tarjetas KPIs principales -->
      <VRow class="mb-6">
        <!-- KPI: Ventas -->
        <VCol cols="12" sm="6" lg="3">
          <VCard class="kpi-card text-white overflow-hidden elevation-3 rounded-xl" style="background: linear-gradient(135deg, #3a7bd5, #3a6073);">
            <div class="pa-5 relative-container">
              <div class="d-flex justify-space-between align-center mb-2">
                <span class="text-subtitle-2 font-weight-bold text-uppercase opacity-75">Ventas del Mes</span>
                <VIcon icon="ri-hand-coin-line" size="28" class="opacity-75" />
              </div>
              <h2 class="text-h3 font-weight-black mb-2">
                Bs. {{ stats.kpis.sales.value.toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
              </h2>
              <div class="d-flex align-center font-weight-bold text-caption">
                <VIcon :icon="stats.kpis.sales.diff >= 0 ? 'ri-arrow-left-up-line' : 'ri-arrow-left-down-line'" class="mr-1" />
                <span :class="stats.kpis.sales.diff >= 0 ? 'text-green-accent-2' : 'text-red-accent-2'">
                  {{ stats.kpis.sales.diff >= 0 ? '+' : '' }}{{ stats.kpis.sales.diff }}%
                </span>
                <span class="ml-1 opacity-75">vs mes anterior</span>
              </div>
            </div>
          </VCard>
        </VCol>

        <!-- KPI: Compras -->
        <VCol cols="12" sm="6" lg="3">
          <VCard class="kpi-card text-white overflow-hidden elevation-3 rounded-xl" style="background: linear-gradient(135deg, #7474bf, #348ac7);">
            <div class="pa-5 relative-container">
              <div class="d-flex justify-space-between align-center mb-2">
                <span class="text-subtitle-2 font-weight-bold text-uppercase opacity-75">Compras / Lotes</span>
                <VIcon icon="ri-shopping-cart-2-line" size="28" class="opacity-75" />
              </div>
              <h2 class="text-h3 font-weight-black mb-2">
                Bs. {{ stats.kpis.purchases.value.toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
              </h2>
              <div class="d-flex align-center font-weight-bold text-caption">
                <VIcon :icon="stats.kpis.purchases.diff >= 0 ? 'ri-arrow-left-up-line' : 'ri-arrow-left-down-line'" class="mr-1" />
                <span :class="stats.kpis.purchases.diff >= 0 ? 'text-red-accent-2' : 'text-green-accent-2'">
                  {{ stats.kpis.purchases.diff >= 0 ? '+' : '' }}{{ stats.kpis.purchases.diff }}%
                </span>
                <span class="ml-1 opacity-75">vs mes anterior</span>
              </div>
            </div>
          </VCard>
        </VCol>

        <!-- KPI: Transacciones -->
        <VCol cols="12" sm="6" lg="3">
          <VCard class="kpi-card text-white overflow-hidden elevation-3 rounded-xl" style="background: linear-gradient(135deg, #f857a6, #ff5858);">
            <div class="pa-5 relative-container">
              <div class="d-flex justify-space-between align-center mb-2">
                <span class="text-subtitle-2 font-weight-bold text-uppercase opacity-75">Transacciones</span>
                <VIcon icon="ri-exchange-funds-line" size="28" class="opacity-75" />
              </div>
              <h2 class="text-h3 font-weight-black mb-2">
                {{ stats.kpis.transactions.value }}
              </h2>
              <div class="d-flex align-center font-weight-bold text-caption">
                <VIcon :icon="stats.kpis.transactions.diff >= 0 ? 'ri-arrow-left-up-line' : 'ri-arrow-left-down-line'" class="mr-1" />
                <span :class="stats.kpis.transactions.diff >= 0 ? 'text-green-accent-2' : 'text-red-accent-2'">
                  {{ stats.kpis.transactions.diff >= 0 ? '+' : '' }}{{ stats.kpis.transactions.diff }}%
                </span>
                <span class="ml-1 opacity-75">vs mes anterior</span>
              </div>
            </div>
          </VCard>
        </VCol>

        <!-- KPI: Ganancia -->
        <VCol cols="12" sm="6" lg="3">
          <VCard class="kpi-card text-white overflow-hidden elevation-3 rounded-xl" style="background: linear-gradient(135deg, #11998e, #38ef7d);">
            <div class="pa-5 relative-container">
              <div class="d-flex justify-space-between align-center mb-2">
                <span class="text-subtitle-2 font-weight-bold text-uppercase opacity-75">Ganancia Neta</span>
                <VIcon icon="ri-coins-line" size="28" class="opacity-75" />
              </div>
              <h2 class="text-h3 font-weight-black mb-2">
                Bs. {{ stats.kpis.revenue.value.toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
              </h2>
              <div class="d-flex align-center font-weight-bold text-caption">
                <VIcon :icon="stats.kpis.revenue.diff >= 0 ? 'ri-arrow-left-up-line' : 'ri-arrow-left-down-line'" class="mr-1" />
                <span :class="stats.kpis.revenue.diff >= 0 ? 'text-green-accent-2' : 'text-red-accent-2'">
                  {{ stats.kpis.revenue.diff >= 0 ? '+' : '' }}{{ stats.kpis.revenue.diff }}%
                </span>
                <span class="ml-1 opacity-75">vs mes anterior</span>
              </div>
            </div>
          </VCard>
        </VCol>
      </VRow>

      <!-- Fila de Gráficos Principales -->
      <VRow class="mb-6">
        <!-- Gráfico de Tendencias -->
        <VCol cols="12" lg="8">
          <VCard class="rounded-xl border h-100" elevation="2">
            <VCardTitle class="py-4 font-weight-bold d-flex align-center border-bottom">
              <VIcon icon="ri-pulse-line" class="text-primary mr-2" />
              Tendencia de Operaciones (Últimos 30 días)
            </VCardTitle>
            <VCardText class="pt-6">
              <VueApexCharts 
                v-if="trendChartSeries.length > 0"
                type="area" 
                height="320" 
                :options="trendChartOptions" 
                :series="trendChartSeries" 
              />
            </VCardText>
          </VCard>
        </VCol>

        <!-- Distribución Métodos de Pago -->
        <VCol cols="12" lg="4">
          <VCard class="rounded-xl border h-100" elevation="2">
            <VCardTitle class="py-4 font-weight-bold d-flex align-center border-bottom">
              <VIcon icon="ri-pie-chart-line" class="text-primary mr-2" />
              Métodos de Pago
            </VCardTitle>
            <VCardText class="pt-6 d-flex flex-column align-center justify-center" style="min-height: 320px;">
              <VueApexCharts 
                v-if="paymentChartSeries.length > 0"
                type="donut" 
                width="280"
                :options="paymentChartOptions" 
                :series="paymentChartSeries" 
              />
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Grid Secundario (Productos, Caja, Alertas) -->
      <VRow>
        <!-- Columna de Rankings -->
        <VCol cols="12" lg="7">
          <!-- Top Productos -->
          <VCard class="rounded-xl border mb-6" elevation="2">
            <VCardTitle class="py-4 font-weight-bold d-flex align-center border-bottom">
              <VIcon icon="ri-star-line" class="text-primary mr-2" />
              Top 5 Productos más Vendidos
            </VCardTitle>
            <VCardText class="pt-4">
              <div v-if="stats.top_products.length > 0">
                <div v-for="(prod, i) in stats.top_products" :key="i" class="mb-4">
                  <div class="d-flex justify-space-between align-center mb-1">
                    <span class="font-weight-medium text-body-1 text-truncate" style="max-width: 70%;">
                      {{ i + 1 }}. {{ prod.name }}
                    </span>
                    <span class="text-body-2 font-weight-bold text-grey-darken-3">
                      {{ prod.qty }} uds <span class="text-grey-darken-1">({{ parseFloat(prod.total).toLocaleString('es-BO') }} Bs)</span>
                    </span>
                  </div>
                  <VProgressLinear 
                    :value="(prod.qty / stats.top_products[0].qty) * 100" 
                    height="8" 
                    color="primary" 
                    rounded 
                    striped
                  />
                </div>
              </div>
              <div v-else class="text-center py-6 text-grey">
                No hay ventas registradas este mes.
              </div>
            </VCardText>
          </VCard>

          <!-- Top Laboratorios -->
          <VCard class="rounded-xl border" elevation="2">
            <VCardTitle class="py-4 font-weight-bold d-flex align-center border-bottom">
              <VIcon icon="ri-medicine-bottle-line" class="text-primary mr-2" />
              Top 5 Laboratorios de Mayor Facturación
            </VCardTitle>
            <VCardText class="pt-4">
              <div v-if="stats.top_laboratories.length > 0">
                <div v-for="(lab, i) in stats.top_laboratories" :key="i" class="mb-4">
                  <div class="d-flex justify-space-between align-center mb-1">
                    <span class="font-weight-medium text-body-1 text-truncate" style="max-width: 70%;">
                      {{ i + 1 }}. {{ lab.name }}
                    </span>
                    <span class="text-body-2 font-weight-bold text-grey-darken-3">
                      Bs. {{ parseFloat(lab.total).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                    </span>
                  </div>
                  <VProgressLinear 
                    :value="(parseFloat(lab.total) / parseFloat(stats.top_laboratories[0].total)) * 100" 
                    height="8" 
                    color="secondary" 
                    rounded
                  />
                </div>
              </div>
              <div v-else class="text-center py-6 text-grey">
                No hay facturación de laboratorios registrada.
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Columna de Turnos de Caja y Alertas de Inventario -->
        <VCol cols="12" lg="5">
          <!-- Tarjeta de Caja Activa -->
          <VCard class="rounded-xl border mb-6" elevation="2">
            <VCardTitle class="py-4 font-weight-bold d-flex align-center border-bottom">
              <VIcon icon="ri-safe-2-line" class="text-primary mr-2" />
              Estado de Caja Activa
            </VCardTitle>
            <VCardText class="pt-4">
              <div v-if="stats.cash_register.active" class="d-flex flex-column">
                <div class="d-flex align-center mb-4">
                  <VBadge dot inline color="success" class="mr-2" />
                  <span class="font-weight-bold text-subtitle-1 text-success">Caja Abierta y Activa</span>
                </div>
                <div class="d-flex justify-space-between mb-2">
                  <span class="text-grey-darken-1">Caja Registradora:</span>
                  <span class="font-weight-bold">{{ stats.cash_register.register_name }}</span>
                </div>
                <div class="d-flex justify-space-between mb-2">
                  <span class="text-grey-darken-1">Cajero de Turno:</span>
                  <span class="font-weight-bold">{{ stats.cash_register.user_name }}</span>
                </div>
                <div class="d-flex justify-space-between mb-2">
                  <span class="text-grey-darken-1">Fecha de Apertura:</span>
                  <span>{{ new Date(stats.cash_register.opening_date).toLocaleString('es-BO') }}</span>
                </div>
                <VDivider class="my-3" />
                <div class="d-flex justify-space-between align-center mt-1">
                  <span class="font-weight-bold text-subtitle-1">Saldo en Efectivo:</span>
                  <span class="text-h5 font-weight-black text-primary">
                    Bs. {{ stats.cash_register.current_balance.toLocaleString('es-BO', { minimumFractionDigits: 2 }) }}
                  </span>
                </div>
              </div>
              <div v-else class="text-center py-6">
                <VIcon icon="ri-shut-down-line" size="48" color="error" class="mb-2" />
                <div class="text-subtitle-1 font-weight-bold mb-2">No tienes un turno de caja abierto</div>
                <p class="text-caption text-grey-darken-1 mb-4">Debe abrir una caja antes de procesar ventas o movimientos.</p>
                <VBtn color="primary" to="/cajas" class="rounded-lg">
                  <VIcon icon="ri-lock-unlock-line" class="mr-2" /> Abrir Turno de Caja
                </VBtn>
              </div>
            </VCardText>
          </VCard>

          <!-- Semáforo de Lotes por Vencer -->
          <VCard class="rounded-xl border mb-6" elevation="2">
            <VCardTitle class="py-4 font-weight-bold d-flex align-center border-bottom">
              <VIcon icon="ri-time-line" class="text-primary mr-2" />
              Semáforo de Vencimiento de Lotes
            </VCardTitle>
            <VCardText class="pt-4">
              <VRow no-gutters class="text-center">
                <!-- Críticos -->
                <VCol cols="3" class="pa-1">
                  <div class="rounded-lg pa-2 bg-red-lighten-5 text-red border border-red-lighten-3">
                    <div class="text-h6 font-weight-black">{{ stats.inventory_alerts.batches.critical }}</div>
                    <div class="text-caption opacity-75 font-weight-bold">Crit. (&le;30d)</div>
                  </div>
                </VCol>
                <!-- Advertencia -->
                <VCol cols="3" class="pa-1">
                  <div class="rounded-lg pa-2 bg-amber-lighten-5 text-amber-darken-3 border border-amber-lighten-3">
                    <div class="text-h6 font-weight-black">{{ stats.inventory_alerts.batches.warning }}</div>
                    <div class="text-caption opacity-75 font-weight-bold">Alerta (30-90d)</div>
                  </div>
                </VCol>
                <!-- Vigentes -->
                <VCol cols="3" class="pa-1">
                  <div class="rounded-lg pa-2 bg-green-lighten-5 text-green-darken-3 border border-green-lighten-3">
                    <div class="text-h6 font-weight-black">{{ stats.inventory_alerts.batches.healthy }}</div>
                    <div class="text-caption opacity-75 font-weight-bold">Vig. (&gt;90d)</div>
                  </div>
                </VCol>
                <!-- Vencidos -->
                <VCol cols="3" class="pa-1">
                  <div class="rounded-lg pa-2 bg-grey-lighten-4 text-grey-darken-3 border border-grey-lighten-3">
                    <div class="text-h6 font-weight-black text-red-darken-4">{{ stats.inventory_alerts.batches.expired }}</div>
                    <div class="text-caption opacity-75 font-weight-bold text-red-darken-4">Vencidos</div>
                  </div>
                </VCol>
              </VRow>
              <div class="d-flex justify-end mt-4">
                <VBtn variant="text" size="small" color="primary" to="/reportes?tab=lotes">
                  <VIcon icon="ri-eye-line" class="mr-1" /> Ver todos los vencimientos
                </VBtn>
              </div>
            </VCardText>
          </VCard>

          <!-- Productos con Stock Crítico -->
          <VCard class="rounded-xl border" elevation="2">
            <VCardTitle class="py-4 font-weight-bold d-flex align-center justify-space-between border-bottom">
              <div class="d-flex align-center">
                <VIcon icon="ri-alert-line" class="text-error mr-2" />
                Alertas de Stock Crítico
              </div>
              <VChip v-if="stats.inventory_alerts.stock_critical_count > 0" color="error" size="small" class="font-weight-bold">
                {{ stats.inventory_alerts.stock_critical_count }} Productos
              </VChip>
            </VCardTitle>
            <VCardText class="pt-4">
              <div v-if="stats.inventory_alerts.products.length > 0" style="max-height: 250px; overflow-y: auto;">
                <VList lines="two" density="compact" class="py-0">
                  <VListItem v-for="prod in stats.inventory_alerts.products" :key="prod.id" class="px-0">
                    <template #prepend>
                      <VIcon icon="ri-error-warning-line" color="error" class="mr-2" />
                    </template>
                    <VListItemTitle class="font-weight-bold">{{ prod.name }}</VListItemTitle>
                    <VListItemSubtitle>
                      Stock actual: <span class="text-error font-weight-black">{{ prod.stock }}</span> uds | Min: {{ prod.min_stock }} uds
                    </VListItemSubtitle>
                    <template #append>
                      <VBtn size="small" variant="flat" color="primary" class="rounded-lg" to="/compras">
                        Comprar
                      </VBtn>
                    </template>
                  </VListItem>
                </VList>
              </div>
              <div v-else class="text-center py-6 text-green font-weight-bold">
                <VIcon icon="ri-checkbox-circle-line" size="36" class="mb-1" />
                <div>¡Perfecto! Todo el inventario está sobre el mínimo</div>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </div>
  </div>
</template>

<style scoped>
.kpi-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.kpi-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 10px 20px rgba(0,0,0,0.15) !important;
}
.relative-container {
  position: relative;
  z-index: 2;
}
.border-bottom {
  border-bottom: 1px solid #f1f1f1 !important;
}
</style>
