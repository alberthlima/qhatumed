<script setup>
import { $api } from '@/utils/api'
import { useCashSession } from '@/composables/useCashSession'

const toast = useMaterializeToast()

// --- Estado de la Sesión Activa ---
const { session, isLoading: isLoadingSession, hasOpenSession } = useCashSession()

// --- Listado Histórico ---
const historial = ref([])
const isLoadingHistorial = ref(false)
const totalItems = ref(0)

// --- Formulario Nueva Salida ---
const showForm = ref(false)
const isSaving = ref(false)
const form = ref({
  receptor: '',
  codigo: '',        // authorization code / reference
  description: '',
  payment_method: 'EFECTIVO',
  amount: 0,
})

// ─────── Métodos ───────
const fetchHistorial = async () => {
  isLoadingHistorial.value = true
  try {
    const params = new URLSearchParams({ type: 'OUT' })
    const res = await $api(`/v1/cash-movements/historial?${params}`)
    historial.value = res.data ?? res
    totalItems.value = res.total ?? (res.data ?? res).length
  } catch (e) {
    console.error(e)
  } finally {
    isLoadingHistorial.value = false
  }
}

const handleSave = async () => {
  if (!hasOpenSession.value) return toast.showWarning('Debe tener una caja abierta para registrar salidas.', 'Caja Cerrada')
  if (form.value.amount <= 0) return toast.showWarning('El monto debe ser mayor a 0', 'Monto Inválido')
  if (!form.value.receptor.trim()) return toast.showWarning('Especifique a quién se entrega el dinero (Receptor)', 'Campo Requerido')
  if (!form.value.description.trim()) return toast.showWarning('Ingrese una descripción u observación', 'Campo Requerido')

  isSaving.value = true
  try {
    const descText = form.value.codigo
      ? `[RECEPTOR: ${form.value.receptor}] [COD: ${form.value.codigo}] ${form.value.description}`
      : `[RECEPTOR: ${form.value.receptor}] ${form.value.description}`

    await $api('/v1/cash-movements', {
      method: 'POST',
      body: {
        cash_register_session_id: session.value.id,
        type: 'OUT',
        amount: form.value.amount,
        payment_method: form.value.payment_method,
        description: descText,
      }
    })
    showForm.value = false
    form.value = { receptor: '', codigo: '', description: '', payment_method: 'EFECTIVO', amount: 0 }
    fetchHistorial()
    toast.showSuccess('Salida registrada correctamente', 'Éxito')
  } catch (e) {
    toast.showError(e._data?.error || 'Error al registrar salida', 'Error')
  } finally {
    isSaving.value = false
  }
}

onMounted(() => {
  fetchHistorial()
})

definePage({ meta: { permission: 'all' } })
</script>

<template>
  <div>
    <!-- Encabezado -->
    <VRow class="mb-4" align="center">
      <VCol>
        <h2 class="mb-0">
          <VIcon icon="ri-arrow-up-circle-line" color="error" class="me-2" />
          Salidas de Caja
        </h2>
        <p class="text-body-2 text-medium-emphasis mt-1">
          Registro de retiros y egresos de dinero de la caja (pagos, gastos, devoluciones).
        </p>
      </VCol>
      <VCol cols="auto">
        <VBtn
          color="error"
          :disabled="!session"
          @click="showForm = !showForm"
        >
          <VIcon start icon="ri-add-circle-line" />
          Nueva Salida
        </VBtn>
      </VCol>
    </VRow>

    <!-- Alerta sin caja abierta -->
    <VAlert v-if="!isLoadingSession && !session" type="warning" variant="tonal" class="mb-4">
      <VIcon start icon="ri-lock-line" />
      No tiene una caja abierta. Abra un turno desde <strong>Cajas / Arqueos</strong> para registrar salidas.
    </VAlert>

    <!-- Formulario Nueva Salida -->
    <VExpandTransition>
      <VCard v-if="showForm" class="mb-6" elevation="3">
        <VCardTitle class="bg-error text-white pa-4">
          <VIcon start icon="ri-money-dollar-circle-line" />
          Registrar Salida / Egreso de Caja
        </VCardTitle>
        <VCardText class="pt-4">
          <VAlert type="warning" variant="tonal" density="compact" class="mb-4">
            <strong>Atención:</strong> Todo egreso afecta el saldo de caja y será auditado en el arqueo de cierre.
          </VAlert>
          <VRow>
            <VCol cols="12" md="5">
              <VTextField
                v-model="form.receptor"
                label="Receptor (¿A quién se entrega?)"
                variant="outlined"
                prepend-inner-icon="ri-user-received-line"
                placeholder="Nombre del receptor o proveedor"
              />
            </VCol>
            <VCol cols="12" md="3">
              <VTextField
                v-model="form.codigo"
                label="Código / Referencia (opcional)"
                variant="outlined"
                prepend-inner-icon="ri-barcode-line"
                placeholder="Nro. factura, voucher..."
              />
            </VCol>
            <VCol cols="12" md="2">
              <VTextField
                v-model.number="form.amount"
                label="Monto"
                type="number"
                min="0.01"
                variant="outlined"
                prefix="Bs"
              />
            </VCol>
            <VCol cols="12" md="2">
              <VSelect
                v-model="form.payment_method"
                :items="['EFECTIVO', 'QR', 'TRANSFERENCIA']"
                label="Método"
                variant="outlined"
                prepend-inner-icon="ri-bank-card-line"
              />
            </VCol>
            <VCol cols="12">
              <VTextarea
                v-model="form.description"
                label="Motivo / Observaciones"
                variant="outlined"
                rows="2"
                placeholder="Ej: Pago de luz, Pasajes, Pago a proveedor, Devolución a cliente..."
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions class="px-4 pb-4">
          <VSpacer />
          <VBtn variant="text" @click="showForm = false">Cancelar</VBtn>
          <VBtn color="error" :loading="isSaving" @click="handleSave">
            <VIcon start icon="ri-save-line" />
            Registrar Salida
          </VBtn>
        </VCardActions>
      </VCard>
    </VExpandTransition>



    <!-- Tabla Historial -->
    <VCard>
      <VCardTitle class="pa-4 d-flex justify-space-between align-center">
        <span>Historial de Salidas / Egresos</span>
        <VChip color="error" size="small">{{ totalItems }} registros</VChip>
      </VCardTitle>

      <VProgressLinear v-if="isLoadingHistorial" indeterminate color="error" />

      <VTable>
        <thead>
          <tr>
            <th>#</th>
            <th>Fecha / Hora</th>
            <th>Caja</th>
            <th>Usuario</th>
            <th>Receptor / Motivo</th>
            <th>Método</th>
            <th class="text-right">Monto</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(mv, idx) in historial" :key="mv.id">
            <td class="text-medium-emphasis">{{ idx + 1 }}</td>
            <td>{{ new Date(mv.created_at).toLocaleString() }}</td>
            <td>
              <VChip size="small" color="primary" variant="tonal">
                {{ mv.session?.cash_register?.name ?? '—' }}
              </VChip>
            </td>
            <td>{{ mv.user?.name ?? mv.session?.user?.name ?? '—' }}</td>
            <td class="text-caption">{{ mv.description }}</td>
            <td>
              <VChip size="x-small" :color="mv.payment_method === 'EFECTIVO' ? 'secondary' : 'info'" variant="tonal">
                {{ mv.payment_method }}
              </VChip>
            </td>
            <td class="text-right font-weight-bold text-error">
              - Bs {{ Number(mv.amount).toFixed(2) }}
            </td>
          </tr>
          <tr v-if="!isLoadingHistorial && historial.length === 0">
            <td colspan="7" class="text-center py-8 text-medium-emphasis">
              <VIcon icon="ri-inbox-line" size="32" class="mb-2 d-block" />
              No hay salidas registradas con los filtros seleccionados.
            </td>
          </tr>
        </tbody>
        <tfoot v-if="historial.length > 0">
          <tr class="bg-error-lighten-5">
            <td colspan="6" class="font-weight-bold text-right pr-4">TOTAL EGRESOS:</td>
            <td class="text-right font-weight-bold text-error text-h6">
              Bs {{ historial.reduce((s, m) => s + Number(m.amount), 0).toFixed(2) }}
            </td>
          </tr>
        </tfoot>
      </VTable>
    </VCard>
  </div>
</template>
