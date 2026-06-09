<script setup>
import { ref, onMounted } from 'vue'
import { $api } from '@/utils/api'

const toast = useMaterializeToast()

// --- Estados del Listado ---
const eventosList = ref([])
const isLoading = ref(false)
const totalItems = ref(0)
const itemsPerPage = ref(10)
const currentPage = ref(1)

// --- Estados de Carga por Evento (Transmisión) ---
const transmittingIds = ref(new Set())

// --- Estados de Registro (Modal) ---
const isAddDialog = ref(false)
const isSaving = ref(false)
const form = ref({
  codigo_motivo: 2, // 2: Falla de internet por defecto
  descripcion: '',
  fecha_inicio: '',
  fecha_fin: ''
})

const motivosEvento = [
  { title: 'Corte de energía eléctrica', value: 1 },
  { title: 'Falla del servicio de internet', value: 2 },
  { title: 'Inaccesibilidad al servicio web de la Administración Tributaria', value: 3 },
  { title: 'Falla en el software de facturación o problemas de hardware', value: 4 }
]

// Cargar historial de eventos de contingencia
const fetchEventos = async () => {
  isLoading.value = true
  try {
    const response = await $api(`/v1/siat/contingencias?page=${currentPage.value}&per_page=${itemsPerPage.value}`)
    eventosList.value = response.data || response.data?.data || response || []
    totalItems.value = response.total || response.data?.total || eventosList.value.length
  } catch (error) {
    console.error('Error fetching contingency events:', error)
    toast.showError('No se pudo cargar el historial de contingencias', 'Error')
  } finally {
    isLoading.value = false
  }
}

// Registrar un nuevo evento significador ante el SIN
const saveEvento = async () => {
  if (!form.value.descripcion.trim()) return toast.showWarning('Ingrese una descripción u observación', 'Campo Requerido')
  if (!form.value.fecha_inicio) return toast.showWarning('Seleccione la fecha y hora de inicio', 'Campo Requerido')
  if (!form.value.fecha_fin) return toast.showWarning('Seleccione la fecha y hora de fin', 'Campo Requerido')
  
  if (new Date(form.value.fecha_fin) <= new Date(form.value.fecha_inicio)) {
    return toast.showWarning('La fecha de fin debe ser posterior a la fecha de inicio', 'Fechas Inválidas')
  }

  isSaving.value = true
  try {
    const res = await $api('/v1/siat/contingencias', {
      method: 'POST',
      body: form.value
    })
    toast.showSuccess(res.message || 'Evento registrado en Impuestos exitosamente', 'Registro Exitoso')
    isAddDialog.value = false
    form.value = { codigo_motivo: 2, descripcion: '', fecha_inicio: '', fecha_fin: '' }
    await fetchEventos()
  } catch (error) {
    console.error('Error saving contingency event:', error)
    toast.showError(error.response?._data?.error || 'Error al registrar el evento en el SIAT', 'Error')
  } finally {
    isSaving.value = false
  }
}

// Transmitir las facturas del evento
const transmitirFacturas = async (evento) => {
  transmittingIds.value.add(evento.id)
  try {
    const res = await $api(`/v1/siat/contingencias/${evento.id}/transmitir`, { method: 'POST' })
    toast.showSuccess(res.message || 'Facturas empaquetadas, enviadas y validadas correctamente', 'Transmisión Exitosa')
    await fetchEventos()
  } catch (error) {
    console.error('Error transmitting invoices:', error)
    toast.showError(error.response?._data?.error || 'Error en la transmisión de facturas offline', 'Error de Transmisión')
    await fetchEventos()
  } finally {
    transmittingIds.value.delete(evento.id)
  }
}

// Mapear motivo a texto
const getMotivoLabel = (code) => {
  const motivo = motivosEvento.find(m => m.value === Number(code))
  return motivo ? motivo.title : `Otro Motivo (${code})`
}

// Formatear fechas
const formatDate = (dateStr) => {
  if (!dateStr) return '—'
  return new Date(dateStr).toLocaleString()
}

onMounted(fetchEventos)

definePage({ meta: { permission: 'all' } })
</script>

<template>
  <div class="pa-4">
    <!-- Encabezado -->
    <VRow class="mb-4" align="center">
      <VCol>
        <h2 class="mb-0 d-flex align-center">
          <VIcon icon="ri-wifi-off-line" color="error" class="me-2" size="32" />
          Contingencias SIAT (Facturación Offline)
        </h2>
        <p class="text-body-2 text-medium-emphasis mt-1">
          Registre eventos significativos de caída de servicio y envíe en paquetes ZIP firmados las facturas emitidas fuera de línea una vez restablecida la conexión.
        </p>
      </VCol>
      <VCol cols="auto">
        <VBtn
          color="error"
          @click="isAddDialog = true"
          class="font-weight-bold"
          elevation="2"
        >
          <VIcon start icon="ri-add-circle-line" />
          Registrar Evento Significativo
        </VBtn>
      </VCol>
    </VRow>

    <!-- Historial de Contingencias -->
    <VCard class="rounded-xl border" elevation="0">
      <VCardTitle class="pa-4 d-flex justify-space-between align-center bg-error-lighten-5">
        <span class="text-h6 font-weight-bold">Historial de Contingencias</span>
        <VChip color="error" size="small">{{ totalItems }} eventos registrados</VChip>
      </VCardTitle>

      <VDataTableServer
        :headers="[
          { title: 'ID', key: 'id' },
          { title: 'Motivo del Evento (SIN)', key: 'codigo_motivo' },
          { title: 'Inicio', key: 'fecha_inicio' },
          { title: 'Fin', key: 'fecha_fin' },
          { title: 'Cód. Recepción Evento', key: 'codigo_recepcion_evento' },
          { title: 'Cód. Recepción Paquete', key: 'codigo_recepcion_paquete' },
          { title: 'Estado Transmisión', key: 'estado_transmision', align: 'center' },
          { title: 'Acciones', key: 'actions', sortable: false, align: 'center' }
        ]"
        :items="eventosList"
        :items-length="totalItems"
        v-model:items-per-page="itemsPerPage"
        v-model:page="currentPage"
        :loading="isLoading"
        loading-text="Cargando historial de eventos..."
        no-data-text="No hay eventos de contingencia registrados"
        @update:options="fetchEventos"
        :items-per-page-text="'Eventos por página:'"
        :page-text="'{0}-{1} de {2}'"
        :items-per-page-options="[
          { value: 5, title: '5' },
          { value: 10, title: '10' },
          { value: 25, title: '25' }
        ]"
      >
        <template #item.codigo_motivo="{ item }">
          <span class="text-body-2 font-weight-bold text-wrap" style="max-width: 250px; display: inline-block;">
            {{ getMotivoLabel(item.codigo_motivo) }}
          </span>
        </template>

        <template #item.fecha_inicio="{ item }">
          {{ formatDate(item.fecha_inicio) }}
        </template>

        <template #item.fecha_fin="{ item }">
          {{ formatDate(item.fecha_fin) }}
        </template>

        <template #item.codigo_recepcion_evento="{ item }">
          <code class="text-caption font-weight-bold">{{ item.codigo_recepcion_evento }}</code>
        </template>

        <template #item.codigo_recepcion_paquete="{ item }">
          <code v-if="item.codigo_recepcion_paquete" class="text-caption font-weight-bold text-success">
            {{ item.codigo_recepcion_paquete }}
          </code>
          <span v-else class="text-medium-emphasis">—</span>
        </template>

        <template #item.estado_transmision="{ item }">
          <VTooltip v-if="item.estado_transmision === 'RECHAZADA' && item.detalles_error" :text="item.detalles_error" location="top">
            <template #activator="{ props }">
              <VChip
                v-bind="props"
                color="error"
                size="small"
                class="font-weight-bold cursor-pointer"
                variant="flat"
              >
                Rechazado <VIcon end icon="ri-error-warning-line" />
              </VChip>
            </template>
          </VTooltip>
          
          <VChip
            v-else
            :color="item.estado_transmision === 'VALIDADO' ? 'success' : (item.estado_transmision === 'TRANSMITIDO' ? 'info' : 'warning')"
            size="small"
            class="font-weight-bold"
            variant="flat"
          >
            {{ item.estado_transmision === 'VALIDADO' ? 'Validado' : (item.estado_transmision === 'TRANSMITIDO' ? 'Transmitido' : 'Pendiente') }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <VBtn
            v-if="item.estado_transmision === 'PENDIENTE' || item.estado_transmision === 'RECHAZADA'"
            icon="ri-upload-cloud-line"
            variant="tonal"
            color="success"
            size="small"
            :loading="transmittingIds.has(item.id)"
            @click="transmitirFacturas(item)"
            title="Empaquetar y Transmitir Facturas Offline"
          />
          <span v-else class="text-success font-weight-bold">
            <VIcon icon="ri-checkbox-circle-line" class="me-1" /> Listo
          </span>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- MODAL REGISTRO DE EVENTO (NUEVA CONTINGENCIA) -->
    <VDialog v-model="isAddDialog" max-width="600">
      <VCard class="pa-sm-8 pa-4 rounded-xl">
        <DialogCloseBtn variant="text" size="default" @click="isAddDialog = false" />

        <VCardTitle class="pb-4 border-b">
          <div class="d-flex align-center">
            <VIcon icon="ri-wifi-off-line" color="error" size="32" class="me-3" />
            <div>
              <span class="text-h5 font-weight-bold">Registrar Contingencia</span>
              <div class="text-caption text-medium-emphasis">Declara un evento de corte o desconexión del SIAT</div>
            </div>
          </div>
        </VCardTitle>

        <VCardText class="pt-6">
          <VForm @submit.prevent="saveEvento">
            <VRow>
              <VCol cols="12">
                <VSelect
                  v-model="form.codigo_motivo"
                  :items="motivosEvento"
                  label="Motivo del Evento Significativo *"
                  variant="outlined"
                  density="comfortable"
                />
              </VCol>
              
              <VCol cols="12" sm="6">
                <VTextField
                  v-model="form.fecha_inicio"
                  type="datetime-local"
                  label="Fecha / Hora Inicio *"
                  variant="outlined"
                  density="comfortable"
                />
              </VCol>

              <VCol cols="12" sm="6">
                <VTextField
                  v-model="form.fecha_fin"
                  type="datetime-local"
                  label="Fecha / Hora Fin *"
                  variant="outlined"
                  density="comfortable"
                />
              </VCol>

              <VCol cols="12">
                <VTextarea
                  v-model="form.descripcion"
                  label="Descripción / Justificación *"
                  variant="outlined"
                  rows="3"
                  placeholder="Ej: Falla en el router principal de internet de la farmacia..."
                  hint="Detalle la causa del incidente para control e Impuestos."
                  persistent-hint
                />
              </VCol>
            </VRow>

            <div class="d-flex justify-end gap-3 mt-6">
              <VBtn variant="tonal" color="secondary" @click="isAddDialog = false">Cancelar</VBtn>
              <VBtn type="submit" color="error" :loading="isSaving" class="font-weight-bold">
                <VIcon start icon="ri-save-line" />
                Registrar Contingencia
              </VBtn>
            </div>
          </VForm>
        </VCardText>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.catalog-table-wrapper {
  max-height: 400px;
  overflow-y: auto;
}
.gap-3 {
  gap: 12px;
}
</style>
