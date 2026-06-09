<script setup>
import { requiredValidator, optionalImageValidator } from '@/utils/validators'
import { useRouter, useRoute } from 'vue-router'

const router = useRouter()
const route = useRoute()
const toast = useMaterializeToast()
const isLoading = ref(false)
const isFetching = ref(false)
const refForm = ref()

// Form fields
const product_id = route.params.id
const barcode = ref('')
const name = ref('')
const laboratory_id = ref(null)
const pharmaceutical_form_id = ref(null)

// SIAT fields
const siat_product_code = ref('99100')
const siat_unit_measure_code = ref(57)

const therapeutic_action = ref('')
const active_ingredient = ref('')
const indications = ref('')
const dispenser_id = ref(null)
const dispenser_quantity = ref(1)

const min_stock = ref(5)
const image = ref(null)
const previewUrl = ref(null)

// Relational lists
const laboratories = ref([])
const forms = ref([])
const dispensers = ref([])
const siatProducts = ref([])
const siatUnits = ref([])

const loadDependencies = async () => {
  try {
    const resL = await $api('/v1/laboratories', { method: 'GET' })
    laboratories.value = resL.data || resL || []
    const resD = await $api('/v1/dispensers', { method: 'GET' })
    dispensers.value = resD.data || resD || []
    const resF = await $api('/v1/pharmaceutical-forms', { method: 'GET' })
    forms.value = resF.data || resF || []

    const resSiat = await $api('/v1/siat/catalogs', { method: 'GET' })
    siatProducts.value = resSiat.productos_servicios || []
    siatUnits.value = resSiat.unidades_medida || []
  } catch (e) {
    console.error(e)
  }
}

const loadProductData = async () => {
  try {
    isFetching.value = true
    const response = await $api(`/v1/products/${product_id}`, { method: "GET" })
    const data = response.data || response.product || response
    if (data) {
       barcode.value = data.barcode
       name.value = data.name
       laboratory_id.value = data.laboratory_id
       pharmaceutical_form_id.value = data.pharmaceutical_form_id
       
       siat_product_code.value = data.siat_product_code || '99100'
       siat_unit_measure_code.value = data.siat_unit_measure_code || 57
       
       therapeutic_action.value = data.therapeutic_action
       active_ingredient.value = data.active_ingredient
       indications.value = data.indications
       dispenser_id.value = data.dispenser_id
       dispenser_quantity.value = data.dispenser_quantity
       
       min_stock.value = data.min_stock
       previewUrl.value = data.image_path ? `/storage/${data.image_path}` : null
    }
  } catch (error) {
    name.value = "Producto Carga"
  } finally {
    isFetching.value = false
  }
}

onMounted(() => {
  loadDependencies()
  loadProductData()
})

const onImageSelect = (files) => {
  if (previewUrl.value && !previewUrl.value.includes('/storage/')) URL.revokeObjectURL(previewUrl.value)
  const file = Array.isArray(files) ? files[0] : files
  previewUrl.value = file ? URL.createObjectURL(file) : null
}

const goBack = () => router.push('/productos')

const update = async () => {
  const validation = await refForm.value?.validate()
  if (!validation || !validation.valid) {
    toast.showError('Por favor complete los campos requeridos', 'Error de Validación')
    return
  }

  let formData = new FormData()
  formData.append('_method', 'PUT')
  if (barcode.value) formData.append('barcode', barcode.value)
  formData.append('name', name.value)
  if (laboratory_id.value) formData.append('laboratory_id', laboratory_id.value)
  if (pharmaceutical_form_id.value) formData.append('pharmaceutical_form_id', pharmaceutical_form_id.value)
  
  if (siat_product_code.value) formData.append('siat_product_code', siat_product_code.value)
  if (siat_unit_measure_code.value) formData.append('siat_unit_measure_code', siat_unit_measure_code.value)
  
  if (therapeutic_action.value) formData.append('therapeutic_action', therapeutic_action.value)
  if (active_ingredient.value) formData.append('active_ingredient', active_ingredient.value)
  if (indications.value) formData.append('indications', indications.value)
  if (dispenser_id.value) formData.append('dispenser_id', dispenser_id.value)
  formData.append('dispenser_quantity', dispenser_quantity.value || 1)
  
  formData.append('min_stock', min_stock.value)
  
  if (image.value) {
    formData.append('image', Array.isArray(image.value) ? image.value[0] : image.value)
  }

  try {
    isLoading.value = true
    const response = await $api(`/v1/products/${product_id}`, {
      method: "POST",
      body: formData,
      onResponse({ response }){
        if(response.status === 200){
          toast.showSuccess('Producto actualizado con Vademecum', 'Éxito')
          router.push('/productos')
        } else {
          toast.showError('Error al guardar', 'Error')
        }
      }
    })
  } catch (error) {
    toast.showError('Error de conexión', 'Error')
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <div class="pa-4">
    <VRow>
      <VCol cols="12" md="10" class="mx-auto">
        <VCard elevation="3" :loading="isFetching">
          <VCardTitle class="py-4 d-flex align-center">
             <VBtn icon="ri-arrow-left-line" variant="text" class="mr-2" @click="goBack" />
             <span class="text-h5 font-weight-bold" style="color: #1976d2;">Editar Insumo Farmacéutico</span>
          </VCardTitle>
          <VDivider />
          <VCardText class="pt-6" v-if="!isFetching">
            <VForm ref="refForm" @submit.prevent="update">
              <VRow>
                <VCol cols="12" md="4">
                  <VTextField v-model="barcode" label="Código de Barras (Opcional)" prepend-inner-icon="ri-barcode-box-line" bg-color="grey-lighten-4" />
                </VCol>
                <VCol cols="12" md="4">
                  <VTextField v-model="name" label="Nombre del Producto *" :rules="[requiredValidator]" placeholder="Ej: Paracetamol 500mg" />
                </VCol>
                <VCol cols="12" md="4">
                  <VAutocomplete v-model="laboratory_id" label="Laboratorio / Línea" :items="laboratories" item-title="name" item-value="id" clearable prepend-inner-icon="ri-building-4-line" />
                </VCol>
              </VRow>

              <VRow class="mb-4 mt-2">
                <VCol cols="12">
                   <h6 class="text-h6 mb-2 border-b pb-2 text-primary">Clasificación & Comercialización</h6>
                </VCol>

                <VCol cols="12" md="3">
                  <VAutocomplete v-model="dispenser_id" label="Tipo de Dispenser" :items="dispensers" item-title="name" item-value="id" clearable prepend-inner-icon="ri-box-3-line" />
                </VCol>
                <VCol cols="12" md="3">
                  <VTextField v-model.number="dispenser_quantity" label="Cantidad Dispenser" type="number" prepend-inner-icon="ri-numbers-line" />
                </VCol>
                <VCol cols="12" md="3">
                  <VAutocomplete v-model="pharmaceutical_form_id" label="Forma Farmacéutica" :items="forms" item-title="name" item-value="id" clearable prepend-inner-icon="ri-capsule-line" />
                </VCol>
                <VCol cols="12" md="3">
                  <VTextField v-model.number="min_stock" label="Stock Mínimo" type="number" prepend-inner-icon="ri-alert-line" />
                </VCol>
              </VRow>

              <VRow class="mb-4 mt-2">
                <VCol cols="12">
                   <h6 class="text-h6 mb-2 border-b pb-2 text-primary">Facturación SIAT</h6>
                </VCol>
                <VCol cols="12" md="6">
                  <VAutocomplete v-model="siat_product_code" label="Clasificación de Producto SIN *" :items="siatProducts" item-title="descripcionProducto" item-value="codigoProducto" prepend-inner-icon="ri-barcode-line" :rules="[requiredValidator]" required />
                </VCol>
                <VCol cols="12" md="6">
                  <VAutocomplete v-model="siat_unit_measure_code" label="Unidad de Medida SIN *" :items="siatUnits" item-title="descripcion" item-value="codigoClasificador" prepend-inner-icon="ri-ruler-2-line" :rules="[requiredValidator]" required />
                </VCol>
              </VRow>

              <VRow class="mt-4 align-center">
                <VCol cols="12" md="8">
                  <VFileInput v-model="image" label="Actualizar Fotografía" accept="image/*" :rules="[optionalImageValidator]" @update:model-value="onImageSelect" prepend-icon="ri-image-add-line" />
                </VCol>
                <VCol cols="12" md="4" class="text-center">
                  <VImg v-if="previewUrl" :src="previewUrl" max-height="150" class="rounded-lg shadow-sm" cover />
                  <div v-else class="text-medium-emphasis border rounded d-flex align-center justify-center bg-lighten-4" style="height: 150px; width: 100%;">Sin Foto</div>
                </VCol>
              </VRow>

              <VRow class="mt-4">
                <VCol cols="12">
                   <h6 class="text-h6 mb-2 border-b pb-2 text-primary">Detalles Farmacológicos (Vademecum)</h6>
                </VCol>
                <VCol cols="12" md="6">
                  <VTextField v-model="active_ingredient" label="Principio Activo" prepend-inner-icon="ri-microscope-line" placeholder="Ej: Paracetamol" />
                </VCol>
                <VCol cols="12" md="6">
                  <VTextField v-model="therapeutic_action" label="Acción Terapéutica" prepend-inner-icon="ri-heart-pulse-line" placeholder="Ej: Analgésico y Antipirético" />
                </VCol>
                <VCol cols="12">
                   <VTextarea v-model="indications" label="Indicaciones" prepend-inner-icon="ri-file-info-line" rows="2" placeholder="Escriba aquí la indicación del medicamento" />
                </VCol>
              </VRow>

              <VDivider class="my-6" />

               <div class="d-flex flex-wrap gap-4 mt-6 justify-start">
                <VBtn type="submit" color="primary" :loading="isLoading" elevation="2">
                  <VIcon start icon="ri-save-line" /> Actualizar
                </VBtn>
                 <VBtn color="error" variant="flat" @click="goBack" :disabled="isLoading">
                  <VIcon start icon="ri-close-circle-line" /> Cancelar
                </VBtn>
               </div>
            </VForm>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
