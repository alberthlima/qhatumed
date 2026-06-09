<script setup>
import { ref, onMounted } from 'vue'
import { $api } from '@/utils/api'

const toast = useMaterializeToast()

// --- Estados del Listado ---
const sessionsList = ref([])
const isLoading = ref(false)
const totalItems = ref(0)
const itemsPerPage = ref(10)
const currentPage = ref(1)

// --- Estados del Detalle (Modal) ---
const isDetailDialog = ref(false)
const selectedSession = ref(null)
const movements = ref([])
const isLoadingMovements = ref(false)

// Cargar sesiones con paginación
const fetchSessions = async () => {
  isLoading.value = true
  try {
    const response = await $api(`/v1/cash-registers/sessions?page=${currentPage.value}&per_page=${itemsPerPage.value}`)
    sessionsList.value = response.data || response.data?.data || response || []
    totalItems.value = response.total || response.data?.total || sessionsList.value.length
  } catch (error) {
    console.error('Error fetching cash sessions:', error)
    toast.showError('No se pudo cargar el registro de cajas', 'Error')
  } finally {
    isLoading.value = false
  }
}

// Abrir detalle de sesión y cargar movimientos
const openDetails = async (session) => {
  selectedSession.value = session
  isDetailDialog.value = true
  isLoadingMovements.value = true
  movements.value = []
  
  try {
    const res = await $api(`/v1/cash-movements/session/${session.id}`)
    movements.value = res || []
  } catch (error) {
    console.error('Error fetching movements:', error)
    toast.showError('No se pudieron obtener los movimientos de la caja', 'Error')
  } finally {
    isLoadingMovements.value = false
  }
}

// Formatear montos
const formatMoney = (amount) => {
  return `Bs. ${Number(amount).toFixed(2)}`
}

// Formatear fechas
const formatDate = (dateStr) => {
  if (!dateStr) return '—'
  return new Date(dateStr).toLocaleString()
}

// Calcular totales de movimientos del modal
const sessionTotals = computed(() => {
  if (!selectedSession.value) return { incoming: 0, outgoing: 0, expected: 0, cashIncoming: 0, qrIncoming: 0, cardIncoming: 0 }
  
  const cashIncoming = movements.value
    .filter(m => m.type === 'IN' && m.payment_method === 'EFECTIVO')
    .reduce((sum, m) => sum + Number(m.amount), 0)
    
  const qrIncoming = movements.value
    .filter(m => m.type === 'IN' && m.payment_method === 'QR')
    .reduce((sum, m) => sum + Number(m.amount), 0)
    
  const cardIncoming = movements.value
    .filter(m => m.type === 'IN' && m.payment_method === 'TARJETA')
    .reduce((sum, m) => sum + Number(m.amount), 0)
    
  const outgoing = movements.value
    .filter(m => m.type === 'OUT' && m.payment_method === 'EFECTIVO')
    .reduce((sum, m) => sum + Number(m.amount), 0)
    
  const expected = Number(selectedSession.value.opening_amount) + cashIncoming - outgoing
  
  return { 
    incoming: cashIncoming, 
    outgoing, 
    expected, 
    cashIncoming, 
    qrIncoming, 
    cardIncoming 
  }
})

onMounted(fetchSessions)

definePage({ meta: { permission: 'all' } })
</script>

<template>
  <div class="pa-4">
    <!-- Encabezado -->
    <VRow class="mb-4" align="center">
      <VCol>
        <h2 class="mb-0 d-flex align-center">
          <VIcon icon="ri-history-line" color="primary" class="me-2" size="32" />
          Registro de Cajas
        </h2>
        <p class="text-body-2 text-medium-emphasis mt-1">
          Historial completo de turnos aperturados, montos iniciales, discrepancias y cierres de caja.
        </p>
      </VCol>
    </VRow>

    <!-- Listado de Sesiones -->
    <VCard class="rounded-xl border" elevation="0">
      <VCardTitle class="pa-4 d-flex justify-space-between align-center bg-primary-lighten-5">
        <span class="text-h6 font-weight-bold">Historial de Turnos</span>
        <VChip color="primary" size="small">{{ totalItems }} turnos registrados</VChip>
      </VCardTitle>

      <VDataTableServer
        :headers="[
          { title: 'ID', key: 'id' },
          { title: 'Caja', key: 'cash_register.name' },
          { title: 'Cajero / Usuario', key: 'user.name' },
          { title: 'Apertura', key: 'opening_time' },
          { title: 'Cierre', key: 'closing_time' },
          { title: 'Inicial', key: 'opening_amount', align: 'end' },
          { title: 'Final Declarado', key: 'closing_amount', align: 'end' },
          { title: 'Diferencia', key: 'discrepancy', align: 'end' },
          { title: 'Estado', key: 'status', align: 'center' },
          { title: 'Acciones', key: 'actions', sortable: false, align: 'center' }
        ]"
        :items="sessionsList"
        :items-length="totalItems"
        v-model:items-per-page="itemsPerPage"
        v-model:page="currentPage"
        :loading="isLoading"
        loading-text="Cargando historial de cajas..."
        no-data-text="No hay registros de cajas"
        @update:options="fetchSessions"
        :items-per-page-text="'Cajas por página:'"
        :page-text="'{0}-{1} de {2}'"
        :items-per-page-options="[
          { value: 5, title: '5' },
          { value: 10, title: '10' },
          { value: 25, title: '25' },
          { value: 50, title: '50' }
        ]"
      >
        <template #item.opening_time="{ item }">
          {{ formatDate(item.opening_time) }}
        </template>

        <template #item.closing_time="{ item }">
          <span v-if="item.status === 'OPEN'" class="text-success font-weight-bold">
            <VIcon icon="ri-record-circle-line" class="me-1" size="16" /> Activo
          </span>
          <span v-else>{{ formatDate(item.closing_time) }}</span>
        </template>

        <template #item.opening_amount="{ item }">
          <span class="font-weight-bold">{{ formatMoney(item.opening_amount) }}</span>
        </template>

        <template #item.closing_amount="{ item }">
          <span v-if="item.status === 'OPEN'" class="text-medium-emphasis">—</span>
          <span v-else class="font-weight-bold">{{ formatMoney(item.closing_amount) }}</span>
        </template>

        <template #item.discrepancy="{ item }">
          <span v-if="item.status === 'OPEN'" class="text-medium-emphasis">—</span>
          <template v-else>
            <VChip
              v-if="Number(item.discrepancy) === 0"
              color="secondary"
              size="small"
              variant="tonal"
            >
              Bs. 0.00
            </VChip>
            <VChip
              v-else-if="Number(item.discrepancy) > 0"
              color="success"
              size="small"
              variant="tonal"
              class="font-weight-bold"
            >
              + {{ formatMoney(item.discrepancy) }} (Sobrante)
            </VChip>
            <VChip
              v-else
              color="error"
              size="small"
              variant="tonal"
              class="font-weight-bold"
            >
              {{ formatMoney(item.discrepancy) }} (Faltante)
            </VChip>
          </template>
        </template>

        <template #item.status="{ item }">
          <VChip
            :color="item.status === 'OPEN' ? 'success' : 'secondary'"
            size="small"
            class="font-weight-bold"
            variant="flat"
          >
            {{ item.status === 'OPEN' ? 'Abierta' : 'Cerrada' }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <VBtn
            icon="ri-eye-line"
            variant="tonal"
            color="primary"
            size="small"
            @click="openDetails(item)"
            title="Ver Detalle de Movimientos"
          />
        </template>
      </VDataTableServer>
    </VCard>

    <!-- MODAL DETALLE DE MOVIMIENTOS -->
    <VDialog v-model="isDetailDialog" max-width="800">
      <VCard class="pa-sm-8 pa-4 rounded-xl">
        <DialogCloseBtn variant="text" size="default" @click="isDetailDialog = false" />
        
        <VCardTitle class="pb-4 border-b">
          <div class="d-flex align-center">
            <VIcon icon="ri-safe-line" color="primary" size="32" class="me-3" />
            <div>
              <span class="text-h5 font-weight-bold">Detalle de Turno #{{ selectedSession?.id }}</span>
              <div class="text-caption text-medium-emphasis">
                Caja: {{ selectedSession?.cash_register?.name }} | Cajero: {{ selectedSession?.user?.name }}
              </div>
            </div>
          </div>
        </VCardTitle>

        <VCardText class="pt-6">
          <!-- Resumen de Caja del Turno -->
          <VRow class="mb-6">
            <VCol cols="12" sm="3">
              <VCard variant="outlined" class="pa-3 text-center bg-grey-lighten-5">
                <span class="text-caption text-medium-emphasis">MONTO INICIAL</span>
                <h4 class="text-h6 font-weight-black mt-1">{{ formatMoney(selectedSession?.opening_amount) }}</h4>
              </VCard>
            </VCol>
            <VCol cols="12" sm="3">
              <VCard variant="outlined" class="pa-3 text-center bg-success-lighten-5">
                <span class="text-caption text-success font-weight-bold">INGRESOS EFECTIVO</span>
                <h4 class="text-h6 font-weight-black text-success mt-1">+ {{ formatMoney(sessionTotals.cashIncoming) }}</h4>
              </VCard>
            </VCol>
            <VCol cols="12" sm="3">
              <VCard variant="outlined" class="pa-3 text-center bg-info-lighten-5">
                <span class="text-caption text-info font-weight-bold">INGRESOS DIGITALES</span>
                <h4 class="text-h6 font-weight-black text-info mt-1">+ {{ formatMoney(sessionTotals.qrIncoming + sessionTotals.cardIncoming) }}</h4>
              </VCard>
            </VCol>
            <VCol cols="12" sm="3">
              <VCard variant="outlined" class="pa-3 text-center bg-error-lighten-5">
                <span class="text-caption text-error font-weight-bold">EGRESOS EFECTIVO</span>
                <h4 class="text-h6 font-weight-black text-error mt-1">- {{ formatMoney(sessionTotals.outgoing) }}</h4>
              </VCard>
            </VCol>
          </VRow>

          <VRow class="mb-6">
            <VCol cols="12" sm="6">
              <div class="d-flex justify-space-between mb-1 text-body-2">
                <span>Efectivo Esperado (Gaveta):</span>
                <span class="font-weight-bold">{{ formatMoney(sessionTotals.expected) }}</span>
              </div>
              <div class="d-flex justify-space-between mb-1 text-caption text-medium-emphasis">
                <span>└ Ventas QR / Transferencia:</span>
                <span>+ {{ formatMoney(sessionTotals.qrIncoming) }}</span>
              </div>
              <div class="d-flex justify-space-between mb-1 text-caption text-medium-emphasis">
                <span>└ Ventas Tarjeta:</span>
                <span>+ {{ formatMoney(sessionTotals.cardIncoming) }}</span>
              </div>
              <div class="d-flex justify-space-between mb-2 mt-2 pt-2 border-t text-body-2">
                <span>Efectivo Declarado al Cerrar:</span>
                <span class="font-weight-bold">
                  {{ selectedSession?.status === 'OPEN' ? 'Caja Activa (Sin declarar)' : formatMoney(selectedSession?.closing_amount) }}
                </span>
              </div>
            </VCol>
            <VCol cols="12" sm="6" class="border-s pl-6">
              <div v-if="selectedSession?.status !== 'OPEN'" class="d-flex justify-space-between align-center h-100">
                <span class="font-weight-bold text-body-2">Diferencia Efectivo:</span>
                <VChip
                  :color="Number(selectedSession?.discrepancy) === 0 ? 'secondary' : (Number(selectedSession?.discrepancy) > 0 ? 'success' : 'error')"
                  class="font-weight-black px-4"
                  size="large"
                >
                  {{ Number(selectedSession?.discrepancy) >= 0 ? '+' : '' }}{{ formatMoney(selectedSession?.discrepancy) }}
                </VChip>
              </div>
              <div v-else class="d-flex align-center h-100 text-success font-weight-bold">
                <VIcon icon="ri-record-circle-line" class="me-2" /> Caja actualmente abierta y operativa.
              </div>
            </VCol>
          </VRow>

          <VDivider class="mb-6" />

          <!-- Listado de Movimientos -->
          <h3 class="text-subtitle-1 font-weight-bold mb-3">Movimientos Registrados en el Turno</h3>
          
          <VProgressLinear v-if="isLoadingMovements" indeterminate color="primary" class="mb-4" />
          
          <div class="movements-table-wrapper border rounded-lg overflow-hidden">
            <VTable density="comfortable">
              <thead>
                <tr class="bg-grey-lighten-4">
                  <th>Hora</th>
                  <th>Tipo</th>
                  <th>Método</th>
                  <th>Descripción</th>
                  <th class="text-right">Monto</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="mv in movements" :key="mv.id">
                  <td>{{ new Date(mv.created_at).toLocaleTimeString() }}</td>
                  <td>
                    <VChip
                      size="x-small"
                      :color="mv.type === 'IN' ? 'success' : 'error'"
                      variant="tonal"
                      class="font-weight-bold"
                    >
                      {{ mv.type === 'IN' ? 'Ingreso' : 'Egreso' }}
                    </VChip>
                  </td>
                  <td class="text-caption">{{ mv.payment_method }}</td>
                  <td class="text-caption text-wrap max-width-300">{{ mv.description }}</td>
                  <td class="text-right font-weight-bold" :class="mv.type === 'IN' ? 'text-success' : 'text-error'">
                    {{ mv.type === 'IN' ? '+' : '-' }} Bs. {{ Number(mv.amount).toFixed(2) }}
                  </td>
                </tr>
                <tr v-if="movements.length === 0 && !isLoadingMovements">
                  <td colspan="5" class="text-center py-6 text-medium-emphasis">
                    No hay movimientos registrados en este turno.
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
.max-width-300 {
  max-width: 300px;
}
.movements-table-wrapper {
  max-height: 300px;
  overflow-y: auto;
}
</style>
