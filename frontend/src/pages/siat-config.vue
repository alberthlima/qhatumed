<script setup>
import { ref, onMounted } from 'vue'

const toast = useMaterializeToast()

// Pestaña Activa
const activeTab = ref('config')

// Formulario de Credenciales y Empresa
const formData = ref({
    trade_name: '',
    legal_name: '',
    nit: '',
    siat_token: '',
    siat_system_code: '',
    siat_branch_code: 0,
    siat_pos_code: 0,
    siat_environment: 2,
    siat_modality: 2
})

const isLoading = ref(false)

// ── ESTADO DE GESTIÓN DE PUNTOS DE VENTA (SIAT) ──
const puntosVenta = ref([])
const currentPosCode = ref(0)
const isLoadingPV = ref(false)
const isSubmittingPV = ref(false)
const isCreateDialog = ref(false)
const isCloseDialog = ref(false)
const selectedPVToClose = ref(null)

const posTypesList = [
  { value: 1, title: '1 - Punto de Venta Fijo (Caja / Mostrador)' },
  { value: 2, title: '2 - Punto de Venta Móvil (Dispositivo Móvil / Reparto)' },
  { value: 4, title: '4 - Punto de Venta Comisionista' },
  { value: 5, title: '5 - Ventanilla de Cobranza' },
]

const newPVData = ref({
  codigo_tipo_punto_venta: 1,
  nombre_punto_venta: '',
  descripcion: '',
  codigo_sucursal: 0,
  set_as_active: true
})

const loadSettings = async () => {
    try {
        const response = await $api('/v1/settings')
        if (response && response.data && response.data.id) {
            formData.value = response.data
        } else if (response && response.id) {
            formData.value = response
        }
        currentPosCode.value = Number(formData.value.siat_pos_code || 0)
    } catch (e) {
        console.error('Error loading settings', e)
        toast.showError('No se pudieron cargar los datos de configuración.', 'Error de Carga')
    }
}

const saveConfig = async () => {
    isLoading.value = true
    try {
        await $api('/v1/settings', {
            method: 'POST',
            body: formData.value
        })
        currentPosCode.value = Number(formData.value.siat_pos_code || 0)
        toast.showSuccess('Configuración Empresarial y SIAT guardada exitosamente.', 'Éxito')
    } catch (e) {
        toast.showError('Error al guardar la configuración: ' + (e.response?._data?.message || e.message), 'Error al Guardar')
    } finally {
        isLoading.value = false
    }
}

// ── OPERACIONES CON PUNTOS DE VENTA (SIAT / SIN) ──

const fetchPuntosVenta = async () => {
    try {
        isLoadingPV.value = true
        const res = await $api('/v1/siat/puntos-venta')
        if (res && res.data) {
            puntosVenta.value = res.data
            if (res.current_pos_code !== undefined) {
                currentPosCode.value = Number(res.current_pos_code)
                formData.value.siat_pos_code = currentPosCode.value
            }
        }
    } catch (e) {
        console.error('Error fetching puntos de venta:', e)
        toast.showError('No se pudo consultar los puntos de venta en el SIAT.', 'Error SIAT')
    } finally {
        isLoadingPV.value = false
    }
}

const handleCreatePV = async () => {
    if (!newPVData.value.nombre_punto_venta.trim()) {
        toast.showWarning('Ingrese el nombre del punto de venta', 'Campo Requerido')
        return
    }
    if (!newPVData.value.descripcion.trim()) {
        toast.showWarning('Ingrese la descripción del punto de venta', 'Campo Requerido')
        return
    }

    isSubmittingPV.value = true
    try {
        const res = await $api('/v1/siat/puntos-venta', {
            method: 'POST',
            body: newPVData.value
        })

        if (res.success) {
            toast.showSuccess(
                res.message || `Punto de Venta #${res.codigo_punto_venta} registrado en el SIN con éxito.`,
                'SIN Autorizado'
            )
            isCreateDialog.value = false
            newPVData.value.nombre_punto_venta = ''
            newPVData.value.descripcion = ''
            await fetchPuntosVenta()
            await loadSettings()
        } else {
            toast.showError(res.error || 'El SIN rechazó la creación del punto de venta', 'Rechazo SIAT')
        }
    } catch (e) {
        const msg = e.response?._data?.error || e.message || 'Error al contactar con el SIAT'
        toast.showError(msg, 'Error en Registro')
    } finally {
        isSubmittingPV.value = false
    }
}

const openCloseDialog = (pv) => {
    selectedPVToClose.value = pv
    isCloseDialog.value = true
}

const handleClosePV = async () => {
    if (!selectedPVToClose.value) return
    isSubmittingPV.value = true
    try {
        const res = await $api('/v1/siat/puntos-venta/cierre', {
            method: 'POST',
            body: {
                codigo_punto_venta: selectedPVToClose.value.codigoPuntoVenta,
                codigo_sucursal: 0
            }
        })

        if (res.success) {
            toast.showSuccess(res.message || 'Punto de venta cerrado exitosamente en el SIN.', 'Cierre Autorizado')
            isCloseDialog.value = false
            selectedPVToClose.value = null
            await fetchPuntosVenta()
            await loadSettings()
        } else {
            toast.showError(res.error || 'El SIN rechazó el cierre del punto de venta', 'Rechazo SIAT')
        }
    } catch (e) {
        const msg = e.response?._data?.error || e.message || 'Error al contactar con el SIAT'
        toast.showError(msg, 'Error en Cierre')
    } finally {
        isSubmittingPV.value = false
    }
}

const handleSelectActivePV = async (pv) => {
    try {
        await $api('/v1/siat/puntos-venta/seleccionar', {
            method: 'POST',
            body: { codigo_punto_venta: pv.codigoPuntoVenta }
        })
        currentPosCode.value = pv.codigoPuntoVenta
        formData.value.siat_pos_code = pv.codigoPuntoVenta
        toast.showSuccess(
            `Terminal configurada para emitir con el Punto de Venta #${pv.codigoPuntoVenta} (${pv.nombrePuntoVenta}).`,
            'Punto de Venta Activo'
        )
    } catch (e) {
        toast.showError('Error al seleccionar punto de venta: ' + e.message, 'Error')
    }
}

onMounted(async () => {
    await loadSettings()
    await fetchPuntosVenta()
})
</script>

<template>
  <div class="pa-4">
    <VRow>
      <VCol cols="12" md="10" lg="9" class="mx-auto">

        <!-- Barra de Navegación por Pestañas -->
        <VTabs v-model="activeTab" class="mb-4" color="primary">
          <VTab value="config" class="font-weight-bold">
            <VIcon start icon="ri-settings-4-line" />
            1. Credenciales y Empresa
          </VTab>
          <VTab value="pos_management" class="font-weight-bold">
            <VIcon start icon="ri-computer-line" />
            2. Puntos de Venta (SIAT / SIN)
            <VBadge color="success" content="Normado" inline class="ms-2" />
          </VTab>
        </VTabs>

        <VWindow v-model="activeTab">
          <!-- ========================================== -->
          <!-- PESTAÑA 1: CONFIGURACIÓN GENERAL Y CREDENCIALES -->
          <!-- ========================================== -->
          <VWindowItem value="config">
            <VCard elevation="3" class="rounded-xl border">
              <VCardTitle class="bg-primary text-white py-4 d-flex align-center font-weight-bold">
                <VIcon icon="ri-government-line" class="mr-3" size="28" />
                Configuración Empresarial y SIAT
              </VCardTitle>
              
              <VCardText class="pt-6">
                <VAlert color="info" variant="tonal" icon="ri-information-line" class="mb-6">
                  Los datos guardados aquí configuran la identidad tributaria y credenciales oficiales para declarar ante el Servicio de Impuestos Nacionales.
                </VAlert>

                <VForm @submit.prevent="saveConfig">
                  <div class="text-subtitle-1 font-weight-black text-primary mb-3 text-uppercase">1. Datos de Farmacia</div>
                  <VRow class="mb-4">
                    <VCol cols="12" md="6">
                      <VTextField v-model="formData.trade_name" label="Nombre Comercial (Frontend)" variant="outlined" prepend-inner-icon="ri-store-2-line" />
                    </VCol>
                    <VCol cols="12" md="6">
                      <VTextField v-model="formData.legal_name" label="Razón Social (Para Facturación)" variant="outlined" prepend-inner-icon="ri-building-4-line" />
                    </VCol>
                  </VRow>

                  <VDivider class="mb-4"/>

                  <div class="text-subtitle-1 font-weight-black text-primary mb-3 text-uppercase">2. Credenciales SIAT</div>
                  <VRow>
                    <VCol cols="12" md="4">
                      <VTextField v-model="formData.nit" label="NIT" variant="outlined" prepend-inner-icon="ri-fingerprint-line" />
                    </VCol>
                    <VCol cols="12" md="4">
                      <VTextField v-model.number="formData.siat_branch_code" label="Código Sucursal" type="number" variant="outlined" prepend-inner-icon="ri-map-pin-line" />
                    </VCol>
                    <VCol cols="12" md="4">
                      <VTextField v-model.number="formData.siat_pos_code" label="Punto de Venta Asignado" type="number" variant="outlined" prepend-inner-icon="ri-computer-line" />
                    </VCol>
                    <VCol cols="12" md="4">
                      <VTextField v-model="formData.siat_system_code" label="Código de Sistema (SIN)" variant="outlined" prepend-inner-icon="ri-qr-code-line" />
                    </VCol>
                    <VCol cols="12" md="4">
                      <VSelect 
                        v-model.number="formData.siat_modality" 
                        :items="[
                          { title: '1 - Electrónica en Línea (Firma Digital .p12)', value: 1 },
                          { title: '2 - Computarizada en Línea', value: 2 }
                        ]" 
                        label="Modalidad de Facturación *" 
                        variant="outlined" 
                        prepend-inner-icon="ri-shield-keyhole-line" 
                      />
                    </VCol>
                    <VCol cols="12" md="4">
                      <VSelect 
                        v-model.number="formData.siat_environment" 
                        :items="[{title:'Piloto (Pruebas)', value:2}, {title:'Producción', value:1}]" 
                        label="Ambiente SIAT" 
                        variant="outlined" 
                        prepend-inner-icon="ri-server-line" 
                      />
                    </VCol>
                    <VCol cols="12">
                      <VTextarea 
                        v-model="formData.siat_token" 
                        label="Token Delegado" 
                        variant="outlined" 
                        rows="3" 
                        prepend-inner-icon="ri-key-2-line" 
                        hint="El token provisto por la plataforma del SIAT."
                      />
                    </VCol>
                  </VRow>

                  <VDivider class="my-6" />

                  <div class="d-flex flex-wrap gap-4 mt-6">
                    <VBtn type="submit" color="success" :loading="isLoading" size="large" elevation="2" class="font-weight-bold">
                      <VIcon start icon="ri-save-line" /> Guardar Configuración
                    </VBtn>
                  </div>
                </VForm>
              </VCardText>
            </VCard>
          </VWindowItem>

          <!-- ========================================== -->
          <!-- PESTAÑA 2: GESTIÓN DE PUNTOS DE VENTA (SIAT) -->
          <!-- ========================================== -->
          <VWindowItem value="pos_management">
            <VCard elevation="3" class="rounded-xl border overflow-hidden">
              <!-- Cabecera de la Tarjeta con Acciones Principales -->
              <VCardTitle class="bg-primary text-white py-4 d-flex flex-wrap justify-space-between align-center">
                <div class="d-flex align-center">
                  <VIcon icon="ri-computer-line" class="mr-3" size="28" />
                  <div>
                    <div class="text-h6 font-weight-bold text-white">Puntos de Venta Autorizados ante el SIN</div>
                    <div class="text-caption text-white text-opacity-80">RND Nº 102100000011 Art. 10-12 (Servicio registroPuntoVenta)</div>
                  </div>
                </div>
                <div class="d-flex gap-2 mt-2 mt-sm-0">
                  <VBtn 
                    variant="tonal" 
                    color="white" 
                    size="small" 
                    :loading="isLoadingPV" 
                    @click="fetchPuntosVenta"
                  >
                    <VIcon start icon="ri-refresh-line" /> Sincronizar SIN
                  </VBtn>
                  <VBtn 
                    color="white" 
                    class="text-primary font-weight-bold" 
                    size="small"
                    prepend-icon="ri-add-circle-line"
                    @click="isCreateDialog = true"
                  >
                    Registrar Nuevo Punto
                  </VBtn>
                </div>
              </VCardTitle>

              <VCardText class="pt-5">
                <!-- Banner de Punto Activo Actual -->
                <div class="d-flex justify-space-between align-center pa-4 mb-4 rounded-xl bg-primary-lighten-5 border border-primary border-opacity-25 flex-wrap gap-2">
                  <div class="d-flex align-center">
                    <VAvatar color="primary" variant="flat" size="44" class="me-3">
                      <VIcon icon="ri-store-3-line" size="24" color="white" />
                    </VAvatar>
                    <div>
                      <div class="text-caption text-uppercase font-weight-bold text-primary">Punto de Venta Activo en esta Terminal</div>
                      <div class="text-h6 font-weight-black text-high-emphasis">
                        Punto de Venta #{{ currentPosCode }}
                      </div>
                    </div>
                  </div>
                  <VChip color="success" variant="flat" class="font-weight-bold" prepend-icon="ri-checkbox-circle-line">
                    Operando en Línea
                  </VChip>
                </div>

                <!-- Tabla de Puntos de Venta Registrados -->
                <VTable class="border rounded-xl">
                  <thead>
                    <tr class="bg-grey-lighten-4">
                      <th class="font-weight-bold text-center" style="width: 100px;">CÓDIGO</th>
                      <th class="font-weight-bold">NOMBRE DEL PUNTO</th>
                      <th class="font-weight-bold">TIPO DE PUNTO DE VENTA</th>
                      <th class="font-weight-bold text-center" style="width: 150px;">ESTADO</th>
                      <th class="font-weight-bold text-center" style="width: 180px;">ACCIONES</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="isLoadingPV">
                      <td colspan="5" class="text-center py-6 text-medium-emphasis">
                        <VProgressCircular indeterminate color="primary" size="28" class="me-2" />
                        Consultando puntos de venta vigentes en Impuestos Nacionales...
                      </td>
                    </tr>
                    <tr v-else-if="puntosVenta.length === 0">
                      <td colspan="5" class="text-center py-6 text-disabled">
                        No se encontraron puntos de venta registrados.
                      </td>
                    </tr>
                    <tr v-for="pv in puntosVenta" :key="pv.codigoPuntoVenta">
                      <!-- Código -->
                      <td class="text-center">
                        <VChip 
                          :color="pv.codigoPuntoVenta === currentPosCode ? 'primary' : 'default'" 
                          size="small" 
                          class="font-weight-black"
                        >
                          #{{ pv.codigoPuntoVenta }}
                        </VChip>
                      </td>

                      <!-- Nombre -->
                      <td>
                        <div class="font-weight-bold text-high-emphasis">{{ pv.nombrePuntoVenta }}</div>
                        <span v-if="pv.codigoPuntoVenta === 0" class="text-caption text-medium-emphasis">
                          Sede central / Punto no cerrado por norma
                        </span>
                      </td>

                      <!-- Tipo -->
                      <td>
                        <div class="d-flex align-center">
                          <VIcon 
                            :icon="pv.codigoPuntoVenta === 0 ? 'ri-building-line' : 'ri-computer-line'" 
                            size="16" 
                            class="me-1 text-primary" 
                          />
                          <span class="text-body-2">{{ pv.tipoPuntoVenta }}</span>
                        </div>
                      </td>

                      <!-- Estado en esta Terminal -->
                      <td class="text-center">
                        <VChip 
                          v-if="pv.codigoPuntoVenta === currentPosCode" 
                          color="success" 
                          size="small" 
                          variant="tonal"
                          class="font-weight-bold"
                          prepend-icon="ri-check-line"
                        >
                          Activo en esta Caja
                        </VChip>
                        <VChip 
                          v-else 
                          color="secondary" 
                          size="small" 
                          variant="tonal"
                        >
                          Disponible
                        </VChip>
                      </td>

                      <!-- Acciones -->
                      <td class="text-center">
                        <div class="d-flex justify-center align-center gap-1">
                          <!-- Botón Activar en esta Caja -->
                          <VBtn 
                            v-if="pv.codigoPuntoVenta !== currentPosCode"
                            size="small" 
                            variant="tonal" 
                            color="primary"
                            class="font-weight-bold"
                            title="Usar este Punto de Venta en este equipo"
                            @click="handleSelectActivePV(pv)"
                          >
                            <VIcon start icon="ri-check-line" size="14" /> Usar
                          </VBtn>

                          <!-- Botón Cerrar Punto de Venta en el SIN (No aplica a Punto 0) -->
                          <VBtn 
                            v-if="pv.codigoPuntoVenta > 0"
                            size="small" 
                            variant="text" 
                            color="error"
                            title="Cerrar / Dar de baja punto de venta en el SIN"
                            @click="openCloseDialog(pv)"
                          >
                            <VIcon icon="ri-close-circle-line" size="18" />
                          </VBtn>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </VTable>

                <!-- Tarjeta Explicativa de Conformidad Normativa -->
                <VAlert color="info" variant="tonal" icon="ri-shield-check-line" class="mt-4">
                  <div class="text-caption">
                    <strong>Normativa SIN (RND Nº 102100000011):</strong> Toda emisión de factura debe consignar el número de punto de venta autorizado. La Casa Matriz opera con el Punto de Venta 0. Cada caja adicional debe registrarse mediante el método <code>registroPuntoVenta</code> y recibe un CUIS y CUFD individualizado.
                  </div>
                </VAlert>
              </VCardText>
            </VCard>
          </VWindowItem>
        </VWindow>

      </VCol>
    </VRow>

    <!-- ============================================================= -->
    <!-- DIÁLOGO: REGISTRAR NUEVO PUNTO DE VENTA (registroPuntoVenta) -->
    <!-- ============================================================= -->
    <VDialog v-model="isCreateDialog" max-width="560" persistent>
      <VCard class="rounded-xl overflow-hidden">
        <VCardTitle class="bg-primary text-white py-3 px-4 d-flex justify-space-between align-center">
          <div class="d-flex align-center">
            <VIcon icon="ri-add-box-line" size="24" class="me-2" />
            <span class="text-subtitle-1 font-weight-bold">Registrar Nuevo Punto de Venta en el SIN</span>
          </div>
          <VBtn icon="ri-close-line" variant="text" color="white" size="small" @click="isCreateDialog = false" />
        </VCardTitle>

        <VCardText class="pa-4 pt-5">
          <VAlert color="primary" variant="tonal" icon="ri-information-line" class="mb-4">
            <div class="text-caption">
              El sistema solicitará el alta oficial en el Web Service de Impuestos Nacionales (Servicio Operaciones). El SIN asignará el nuevo código secuencial.
            </div>
          </VAlert>

          <VForm @submit.prevent="handleCreatePV">
            <VRow density="comfortable">
              <!-- Tipo de Punto de Venta -->
              <VCol cols="12">
                <VSelect 
                  v-model="newPVData.codigo_tipo_punto_venta"
                  :items="posTypesList"
                  item-title="title"
                  item-value="value"
                  label="Tipo de Punto de Venta (Clasificador SIN) *"
                  variant="outlined"
                  density="comfortable"
                />
              </VCol>

              <!-- Nombre del Punto -->
              <VCol cols="12">
                <VTextField 
                  v-model="newPVData.nombre_punto_venta"
                  label="Nombre del Punto de Venta *"
                  placeholder="Ej: Caja 2 Turno Tarde"
                  variant="outlined"
                  density="comfortable"
                  hint="Denominación descriptiva de la caja o terminal"
                  persistent-hint
                />
              </VCol>

              <!-- Descripción -->
              <VCol cols="12">
                <VTextarea 
                  v-model="newPVData.descripcion"
                  label="Descripción Operativa *"
                  placeholder="Ej: Terminal de mostrador para dispensación y cobro de medicamentos"
                  variant="outlined"
                  rows="2"
                  density="comfortable"
                />
              </VCol>

              <!-- Establecer como activo -->
              <VCol cols="12">
                <VSwitch 
                  v-model="newPVData.set_as_active" 
                  color="success" 
                  label="Establecer automáticamente como Punto de Venta activo en esta terminal"
                  hide-details
                />
              </VCol>
            </VRow>

            <div class="d-flex justify-end gap-2 mt-6">
              <VBtn variant="tonal" color="secondary" @click="isCreateDialog = false" :disabled="isSubmittingPV">
                Cancelar
              </VBtn>
              <VBtn color="primary" type="submit" :loading="isSubmittingPV" class="font-weight-bold">
                <VIcon start icon="ri-send-plane-fill" /> Registrar ante el SIN
              </VBtn>
            </div>
          </VForm>
        </VCardText>
      </VCard>
    </VDialog>

    <!-- ============================================================= -->
    <!-- DIÁLOGO: CONFIRMAR CIERRE DE PUNTO DE VENTA (cierrePuntoVenta) -->
    <!-- ============================================================= -->
    <VDialog v-model="isCloseDialog" max-width="480" persistent>
      <VCard class="rounded-xl overflow-hidden">
        <VCardTitle class="bg-error text-white py-3 px-4 d-flex align-center">
          <VIcon icon="ri-alert-line" size="24" class="me-2" />
          <span class="text-subtitle-1 font-weight-bold">Cierre de Punto de Venta ante el SIN</span>
        </VCardTitle>

        <VCardText class="pa-4 pt-5">
          <div class="text-body-1 font-weight-medium mb-2">
            ¿Está seguro de cerrar el <strong>Punto de Venta #{{ selectedPVToClose?.codigoPuntoVenta }} ({{ selectedPVToClose?.nombrePuntoVenta }})</strong>?
          </div>
          <div class="text-caption text-medium-emphasis mb-4">
            Esta acción invocará el servicio oficial <code>cierrePuntoVenta</code> del Servicio de Impuestos Nacionales. Una vez cerrado, el punto quedará dado de baja y no podrá emitir facturas fiscales.
          </div>

          <div class="d-flex justify-end gap-2 mt-4">
            <VBtn variant="tonal" color="secondary" @click="isCloseDialog = false" :disabled="isSubmittingPV">
              Cancelar
            </VBtn>
            <VBtn color="error" class="font-weight-bold" :loading="isSubmittingPV" @click="handleClosePV">
              <VIcon start icon="ri-lock-line" /> Confirmar Cierre en SIN
            </VBtn>
          </div>
        </VCardText>
      </VCard>
    </VDialog>

  </div>
</template>
