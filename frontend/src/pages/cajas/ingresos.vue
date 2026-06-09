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

// --- Formulario Nuevo Ingreso ---
const showForm = ref(false)
const isSaving = ref(false)
const form = ref({
  emisor: '',
  description: '',
  payment_method: 'EFECTIVO',
  amount: 0,
})

// ─────── Métodos ───────
const fetchHistorial = async () => {
  isLoadingHistorial.value = true
  try {
    const params = new URLSearchParams({ type: 'IN' })
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
  if (!hasOpenSession.value) return toast.showWarning('Debe tener una caja abierta para registrar ingresos.', 'Caja Cerrada')
  if (form.value.amount <= 0) return toast.showWarning('El monto debe ser mayor a 0', 'Monto Inválido')
  if (!form.value.emisor.trim()) return toast.showWarning('Especifique quién entrega el dinero (Emisor)', 'Campo Requerido')
  if (!form.value.description.trim()) return toast.showWarning('Ingrese una descripción u observación', 'Campo Requerido')

  isSaving.value = true
  try {
    await $api('/v1/cash-movements', {
      method: 'POST',
      body: {
        cash_register_session_id: session.value.id,
        type: 'IN',
        amount: form.value.amount,
        payment_method: form.value.payment_method,
        description: `[EMISOR: ${form.value.emisor}] ${form.value.description}`,
      }
    })
    showForm.value = false
    form.value = { emisor: '', description: '', payment_method: 'EFECTIVO', amount: 0 }
    fetchHistorial()
    toast.showSuccess('Ingreso registrado correctamente', 'Éxito')
  } catch (e) {
    toast.showError(e._data?.error || 'Error al registrar ingreso', 'Error')
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
          <VIcon icon="ri-arrow-down-circle-line" color="success" class="me-2" />
          Ingresos a Caja
        </h2>
        <p class="text-body-2 text-medium-emphasis mt-1">
          Registro de entradas de dinero externas al flujo de ventas (sencillo, aportes, cobros varios).
        </p>
      </VCol>
      <VCol cols="auto">
        <VBtn
          color="success"
          :disabled="!session"
          @click="showForm = !showForm"
        >
          <VIcon start icon="ri-add-circle-line" />
          Nuevo Ingreso
        </VBtn>
      </VCol>
    </VRow>

    <!-- Alerta sin caja abierta -->
    <VAlert v-if="!isLoadingSession && !session" type="warning" variant="tonal" class="mb-4">
      <VIcon start icon="ri-lock-line" />
      No tiene una caja abierta. Abra un turno desde <strong>Cajas / Arqueos</strong> para registrar ingresos.
    </VAlert>

    <!-- Formulario Nuevo Ingreso -->
    <VExpandTransition>
      <VCard v-if="showForm" class="mb-6" elevation="3">
        <VCardTitle class="bg-success text-white pa-4">
          <VIcon start icon="ri-coins-line" />
          Registrar Ingreso de Caja
        </VCardTitle>
        <VCardText class="pt-4">
          <VRow>
            <VCol cols="12" md="6">
              <VTextField
                v-model="form.emisor"
                label="Emisor (¿Quién entrega el dinero?)"
                variant="outlined"
                prepend-inner-icon="ri-user-line"
                placeholder="Nombre del emisor"
              />
            </VCol>
            <VCol cols="12" md="3">
              <VTextField
                v-model.number="form.amount"
                label="Monto Total"
                type="number"
                min="0.01"
                variant="outlined"
                prefix="Bs"
              />
            </VCol>
            <VCol cols="12" md="3">
              <VSelect
                v-model="form.payment_method"
                :items="['EFECTIVO', 'QR', 'TRANSFERENCIA']"
                label="Tipo de Ingreso"
                variant="outlined"
                prepend-inner-icon="ri-bank-card-line"
              />
            </VCol>
            <VCol cols="12">
              <VTextarea
                v-model="form.description"
                label="Observaciones / Motivo"
                variant="outlined"
                rows="2"
                placeholder="Ej: Sencillo adicional, Cobro de deuda cliente, Aporte de capital..."
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions class="px-4 pb-4">
          <VSpacer />
          <VBtn variant="text" @click="showForm = false">Cancelar</VBtn>
          <VBtn color="success" :loading="isSaving" @click="handleSave">
            <VIcon start icon="ri-save-line" />
            Guardar Ingreso
          </VBtn>
        </VCardActions>
      </VCard>
    </VExpandTransition>



    <!-- Tabla Historial -->
    <VCard>
      <VCardTitle class="pa-4 d-flex justify-space-between align-center">
        <span>Historial de Ingresos</span>
        <VChip color="success" size="small">{{ totalItems }} registros</VChip>
      </VCardTitle>

      <VProgressLinear v-if="isLoadingHistorial" indeterminate color="success" />

      <VTable>
        <thead>
          <tr>
            <th>#</th>
            <th>Fecha / Hora</th>
            <th>Caja</th>
            <th>Usuario</th>
            <th>Emisor / Descripción</th>
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
            <td class="text-right font-weight-bold text-success">
              + Bs {{ Number(mv.amount).toFixed(2) }}
            </td>
          </tr>
          <tr v-if="!isLoadingHistorial && historial.length === 0">
            <td colspan="7" class="text-center py-8 text-medium-emphasis">
              <VIcon icon="ri-inbox-line" size="32" class="mb-2 d-block" />
              No hay ingresos registrados con los filtros seleccionados.
            </td>
          </tr>
        </tbody>
        <tfoot v-if="historial.length > 0">
          <tr class="bg-success-lighten-5">
            <td colspan="6" class="font-weight-bold text-right pr-4">TOTAL INGRESOS:</td>
            <td class="text-right font-weight-bold text-success text-h6">
              Bs {{ historial.reduce((s, m) => s + Number(m.amount), 0).toFixed(2) }}
            </td>
          </tr>
        </tfoot>
      </VTable>
    </VCard>
  </div>
</template>
