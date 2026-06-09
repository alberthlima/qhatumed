<script setup>
import { ref, onMounted } from 'vue'

const toast = useMaterializeToast()

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
const isSyncing = ref(false)

const loadSettings = async () => {
    try {
        const response = await $api('/v1/settings')
        if (response && response.data && response.data.id) {
            formData.value = response.data
        } else if (response && response.id) {
            formData.value = response
        }
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
        toast.showSuccess('Configuración Empresarial y SIAT guardada exitosamente.', 'Éxito')
    } catch (e) {
        toast.showError('Error al guardar la configuración: ' + (e.response?._data?.message || e.message), 'Error al Guardar')
    } finally {
        isLoading.value = false
    }
}

const syncCatalogos = async () => {
    isSyncing.value = true
    try {
        const res = await $api('/v1/siat/sync', { method: 'POST' })
        toast.showSuccess(res.message || 'Catálogos sincronizados', 'Sincronización Exitosa')
    } catch (e) {
        toast.showError('Error en la sincronización: Revisa que tu Token y Código Sistema sean válidos.', 'Error de Sincronización')
    } finally {
        isSyncing.value = false
    }
}

onMounted(() => {
    loadSettings()
})
</script>

<template>
  <div class="pa-4">
    <VRow>
      <VCol cols="12" md="10" lg="8" class="mx-auto">
        <VCard elevation="3" class="rounded-xl border">
          <VCardTitle class="bg-primary text-white py-4 d-flex align-center font-weight-bold">
            <VIcon icon="ri-government-line" class="mr-3" size="28" />
            Configuración Empresarial y SIAT
          </VCardTitle>
          
          <VCardText class="pt-6">
            <VAlert color="info" variant="tonal" icon="ri-information-line" class="mb-6">
              Los datos guardados aquí sobrescribirán al archivo .env local y serán usados oficialmente para declarar ante el Servicio de Impuestos Nacionales.
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
                  <VTextField v-model.number="formData.siat_pos_code" label="Punto de Venta" type="number" variant="outlined" prepend-inner-icon="ri-computer-line" />
                </VCol>
                <VCol cols="12" md="6">
                  <VTextField v-model="formData.siat_system_code" label="Código de Sistema (SIN)" variant="outlined" prepend-inner-icon="ri-qr-code-line" />
                </VCol>
                <VCol cols="12" md="6">
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

                <VBtn color="primary" variant="tonal" size="large" @click="syncCatalogos" :loading="isSyncing">
                  <VIcon start icon="ri-refresh-line" /> Sincronizar Catálogos SIAT
                </VBtn>
               </div>
            </VForm>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
