<script setup>
import { $api } from '@/utils/api'
import { VForm } from 'vuetify/components/VForm'

const toast = useMaterializeToast()

const session = ref(null)
const isLoading = ref(true)
const movements = ref([])

// Models
const openDialog = ref(false)
const moveDialog = ref(false)
const closeDialog = ref(false)

const openData = ref({ cash_register_id: 1, opening_amount: 0 })
const moveData = ref({ type: 'IN', amount: 0, description: '', payment_method: 'EFECTIVO' })
const closeData = ref({ reported_amount: 0 })

const registers = ref([])
const isAdmin = ref(false)
const calculatedBalance = ref(0) // Hidden from user unless admin
const sessionDetails = ref({
  cash_sales: 0,
  qr_sales: 0,
  card_sales: 0,
  manual_in: 0,
  manual_out: 0,
  opening_amount: 0
})

const fetchCurrentSession = async () => {
    isLoading.value = true
    try {
        const { session: currentSession, calculated_balance, details } = await $api('/v1/cash-registers/current-session')
        session.value = currentSession
        calculatedBalance.value = calculated_balance
        if (details) {
            sessionDetails.value = details
        }
        fetchMovements(currentSession.id)
    } catch (e) {
        session.value = null
    } finally {
        isLoading.value = false
    }
}

const fetchRegisters = async () => {
    try {
        registers.value = await $api('/v1/cash-registers')
    } catch (e) {
        console.error(e)
    }
}

const fetchMovements = async (sessionId) => {
    try {
        movements.value = await $api(`/v1/cash-movements/session/${sessionId}`)
    } catch (e) {
        console.error(e)
    }
}

const handleOpenSession = async () => {
    if (openData.value.opening_amount < 0) return toast.showWarning('Monto inválido', 'Atención')
    
    try {
        await $api('/v1/cash-registers/open', {
            method: 'POST',
            body: openData.value
        })
        openDialog.value = false
        toast.showSuccess('Caja aperturada con éxito', 'Éxito')
        fetchCurrentSession()
    } catch (e) {
        toast.showError(e.response?._data?.error || 'Error al abrir caja', 'Error')
    }
}

const handleMove = async () => {
    if (moveData.value.amount <= 0 || !moveData.value.description) {
        return toast.showWarning('Datos incompletos o inválidos', 'Atención')
    }
    
    try {
        await $api('/v1/cash-movements', {
            method: 'POST',
            body: {
                ...moveData.value,
                cash_register_session_id: session.value.id
            }
        })
        moveDialog.value = false
        moveData.value.amount = 0
        moveData.value.description = ''
        toast.showSuccess('Movimiento registrado con éxito', 'Éxito')
        fetchCurrentSession() // reload balances and movements
    } catch (e) {
        toast.showError(e.response?._data?.error || 'Error al registrar movimiento', 'Error')
    }
}

const handleCloseSession = async () => {
    if (closeData.value.reported_amount < 0) return toast.showWarning('Monto reportado no válido', 'Atención')
    
    try {
        await $api(`/v1/cash-registers/${session.value.id}/close`, {
            method: 'POST',
            body: closeData.value
        })
        closeDialog.value = false
        toast.showSuccess('Caja cerrada con éxito. El sistema calculó posibles sobrantes/faltantes internamente.', 'Éxito')
        session.value = null
    } catch (e) {
        toast.showError(e.response?._data?.error || 'Error al cerrar caja', 'Error')
    }
}

onMounted(() => {
    let user = localStorage.getItem('user') ? JSON.parse(localStorage.getItem('user')) : null
    if (user && user.role.name === 'Super-Admin') {
        isAdmin.value = true
    }
    fetchRegisters()
    fetchCurrentSession()
})

definePage({ meta: { permission: 'all' } })
</script>

<template>
  <div>
    <VRow justify="center" class="mb-4">
      <VCol cols="12" md="10">
        <h2 class="mb-2">Control de Cajas</h2>
        <p class="text-body-2 text-medium-emphasis">
          Verifica aperturas, ingresos, retiros y cierres ciegos garantizando transparencia financiera.
        </p>

        <VCard v-if="isLoading" class="text-center pa-10">
          <VProgressCircular indeterminate color="primary"></VProgressCircular>
        </VCard>

        <!-- No Open Session -->
        <VCard v-else-if="!session" class="text-center pa-10">
            <VIcon icon="ri-lock-line" size="64" color="warning" class="mb-4" />
            <h3 class="mb-2">Caja Cerrada</h3>
            <p>Debe abrir una caja (declarar sencillo inicial) para procesar ventas o movimientos dinerarios en su turno.</p>
            <VBtn color="primary" class="mt-4" @click="openDialog = true">
                Aperturar Turno de Caja
            </VBtn>
        </VCard>

        <!-- Open Session Dashboard -->
        <template v-else>
            <!-- Header Resumen -->
            <VRow>
                <VCol cols="12" md="4">
                    <VCard elevation="2" class="mb-4 pa-4 bg-primary text-white" style="min-height: 140px;">
                        <div class="d-flex justify-space-between">
                            <div>
                                <div class="text-overline mb-1 text-white opacity-80">TURNO ACTIVO</div>
                                <div class="text-h6 mb-2 text-white font-weight-bold">{{ session.cash_register?.name || 'Caja Principal' }}</div>
                                <div class="text-body-2 text-white">Monto Base Inicial: Bs {{ session.opening_amount }}</div>
                                <div class="text-body-2 text-white">Inició: {{ new Date(session.opening_time).toLocaleString() }}</div>
                            </div>
                            <VAvatar color="white" rounded="lg" variant="flat">
                                <VIcon icon="ri-safe-2-line" color="primary"></VIcon>
                            </VAvatar>
                        </div>
                    </VCard>
                </VCol>

                <VCol cols="12" md="4">
                    <VCard elevation="2" class="mb-4 pa-4 bg-surface border" style="min-height: 140px;">
                        <div class="text-overline mb-1 text-primary">Resumen en Vivo (Sistema)</div>
                        <div class="text-subtitle-1 mb-1 font-weight-bold">
                            Efectivo Esperado: <span class="text-success">Bs. {{ Number(calculatedBalance).toFixed(2) }}</span>
                        </div>
                        <div class="text-caption text-medium-emphasis">
                            Ventas QR: Bs. {{ Number(sessionDetails.qr_sales).toFixed(2) }} | Tarjeta: Bs. {{ Number(sessionDetails.card_sales).toFixed(2) }}
                        </div>
                    </VCard>
                </VCol>
                
                <VCol cols="12" md="4">
                    <VCard elevation="2" class="mb-4 pa-4 d-flex flex-column justify-center" style="min-height: 140px;">
                        <div class="d-flex gap-2 justify-space-between flex-wrap">
                            <VBtn variant="tonal" color="success" size="small" class="flex-grow-1" @click="moveData.type = 'IN'; moveDialog = true">
                                <VIcon start icon="ri-arrow-up-line" />
                                Ingreso
                            </VBtn>
                            <VBtn variant="tonal" color="error" size="small" class="flex-grow-1" @click="moveData.type = 'OUT'; moveDialog = true">
                                <VIcon start icon="ri-arrow-down-line" />
                                Gasto
                            </VBtn>
                            <VBtn color="warning" size="small" class="flex-grow-1" @click="closeDialog = true">
                                <VIcon start icon="ri-lock-2-line" />
                                Arqueo
                            </VBtn>
                        </div>
                        
                        <div v-if="isAdmin" class="mt-2 text-caption text-right text-success d-flex align-center justify-end">
                            <VIcon icon="ri-spy-line" start size="x-small" class="me-1"></VIcon>
                            Admin: Gaveta Bs. {{ calculatedBalance }}
                        </div>
                    </VCard>
                </VCol>
            </VRow>

            <!-- Movimientos -->
            <VCard title="Movimientos del Turno">
                <VTable>
                    <thead>
                        <tr>
                            <th>Hora</th>
                            <th>Tipo</th>
                            <th>Descripción</th>
                            <th>Método</th>
                            <th class="text-right">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="mv in movements" :key="mv.id">
                            <td>{{ new Date(mv.created_at).toLocaleTimeString() }}</td>
                            <td>
                                <VChip :color="mv.type === 'IN' ? 'success' : 'error'" size="small">
                                    {{ mv.type === 'IN' ? 'INGRESO' : 'EGRESO' }}
                                </VChip>
                            </td>
                            <td>{{ mv.description }}</td>
                            <td>{{ mv.payment_method }}</td>
                            <td class="text-right font-weight-medium">
                                <span :class="mv.type === 'IN' ? 'text-success' : 'text-error'">
                                    {{ mv.type === 'IN' ? '+' : '-' }} Bs {{ Number(mv.amount).toFixed(2) }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="movements.length === 0">
                            <td colspan="5" class="text-center text-medium-emphasis">No hay movimientos manuales rápidos.</td>
                        </tr>
                    </tbody>
                </VTable>
            </VCard>
        </template>
      </VCol>
    </VRow>

    <!-- Dialog Apertura -->
    <VDialog v-model="openDialog" max-width="400">
      <VCard title="Abrir Caja / Turno">
        <VCardText>
          <VTextField
            v-model.number="openData.opening_amount"
            label="Monto Inicial en Efectivo (Sencillo/Base)"
            type="number"
            min="0"
            variant="outlined"
            prefix="Bs"
          ></VTextField>
        </VCardText>
        <VCardActions class="px-4 pb-4">
          <VSpacer />
          <VBtn variant="text" @click="openDialog = false">Cancelar</VBtn>
          <VBtn color="primary" @click="handleOpenSession">Abrir Turno</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Dialog Movimiento -->
    <VDialog v-model="moveDialog" max-width="400">
      <VCard :title="moveData.type === 'IN' ? 'Ingreso de Dinero' : 'Retiro / Egreso'">
        <VCardText>
            <VAlert
                v-if="moveData.type === 'OUT'"
                type="warning"
                variant="tonal"
                class="mb-4 px-3 py-2"
                density="compact"
            >
                Cualquier retiro afecta el saldo de caja. Justifica bien.
            </VAlert>

            <VTextField
                v-model.number="moveData.amount"
                label="Monto"
                type="number"
                min="0.1"
                variant="outlined"
                class="mb-4"
                prefix="Bs"
            ></VTextField>

            <VSelect
                v-model="moveData.payment_method"
                :items="['EFECTIVO', 'QR', 'TARJETA']"
                label="Método de Pago"
                variant="outlined"
                class="mb-4"
            ></VSelect>

            <VTextarea
                v-model="moveData.description"
                label="Descripción / Justificación"
                variant="outlined"
                rows="2"
            ></VTextarea>
        </VCardText>
        <VCardActions class="px-4 pb-4">
          <VSpacer />
          <VBtn variant="text" @click="moveDialog = false">Cancelar</VBtn>
          <VBtn :color="moveData.type === 'IN' ? 'success' : 'error'" @click="handleMove">
              Registrar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Dialog Cierre Ciego -->
    <VDialog v-model="closeDialog" max-width="500">
      <VCard title="Cierre de Caja (Arqueo Ciego)">
        <VCardText>
            <div class="text-center mb-6 mt-2">
                <VIcon icon="ri-eye-off-line" size="48" color="medium-emphasis" class="mb-2"></VIcon>
                <div class="text-body-1 font-weight-medium">Contabilización requerida</div>
                <p class="text-body-2">Cuente los billetes y monedas físicos en la gaveta, así como vouchers o registros, e indíquelo aquí. El sistema validará posibles faltantes.</p>
            </div>

            <div class="bg-grey-lighten-4 pa-4 rounded-xl border mb-6 text-left">
                <div class="text-subtitle-2 font-weight-bold mb-2 text-primary">Resumen de Turno (Sistema)</div>
                <div class="d-flex justify-space-between mb-1 text-body-2">
                    <span>Monto Inicial (Base):</span>
                    <span class="font-weight-medium">Bs. {{ Number(sessionDetails.opening_amount).toFixed(2) }}</span>
                </div>
                <div class="d-flex justify-space-between mb-1 text-body-2">
                    <span>Ventas en Efectivo:</span>
                    <span class="font-weight-medium text-success">+ Bs. {{ Number(sessionDetails.cash_sales).toFixed(2) }}</span>
                </div>
                <div class="d-flex justify-space-between mb-1 text-body-2">
                    <span>Ingresos Manuales (Efectivo):</span>
                    <span class="font-weight-medium text-success">+ Bs. {{ Number(sessionDetails.manual_in).toFixed(2) }}</span>
                </div>
                <div class="d-flex justify-space-between mb-1 text-body-2">
                    <span>Egresos Manuales (Efectivo):</span>
                    <span class="font-weight-medium text-error">- Bs. {{ Number(sessionDetails.manual_out).toFixed(2) }}</span>
                </div>
                <VDivider class="my-2" />
                <div class="d-flex justify-space-between mb-2 text-body-1 font-weight-bold text-success">
                    <span>Efectivo Esperado en Gaveta:</span>
                    <span>Bs. {{ Number(calculatedBalance).toFixed(2) }}</span>
                </div>
                
                <!-- Separador de Pagos Digitales -->
                <VDivider class="my-2" />
                <div class="text-caption font-weight-bold mb-1 text-info text-uppercase">Pagos Digitales (No van a la gaveta)</div>
                <div class="d-flex justify-space-between mb-1 text-caption text-medium-emphasis">
                    <span>Ventas por QR / Transferencia:</span>
                    <span class="font-weight-bold text-info">Bs. {{ Number(sessionDetails.qr_sales).toFixed(2) }}</span>
                </div>
                <div class="d-flex justify-space-between text-caption text-medium-emphasis">
                    <span>Ventas por Tarjeta:</span>
                    <span class="font-weight-bold text-info">Bs. {{ Number(sessionDetails.card_sales).toFixed(2) }}</span>
                </div>
            </div>

            <VTextField
                v-model.number="closeData.reported_amount"
                label="Efectivo Físico Contado"
                type="number"
                min="0"
                variant="outlined"
                class="mb-2"
                prefix="Bs"
                hint="Cuente y registre únicamente el efectivo físico de la gaveta."
                persistent-hint
            ></VTextField>
        </VCardText>
        <VCardActions class="px-4 pb-4 justify-center">
          <VBtn variant="text" @click="closeDialog = false">Cancelar</VBtn>
          <VBtn color="primary" variant="elevated" @click="handleCloseSession">
              Declarar y Cerrar Caja
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
