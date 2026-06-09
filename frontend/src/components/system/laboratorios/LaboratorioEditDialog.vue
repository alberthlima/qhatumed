<script setup>
import { requiredValidator, optionalImageValidator } from '@/utils/validators'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  laboratorioSelected: { type: Object, required: true },
})

const emit = defineEmits(['update:isDialogVisible', 'editLaboratorio'])

const name = ref('')
const description = ref('')
const image = ref(null)
const previewUrl = ref(null)
const isLoading = ref(false)
const toast = useMaterializeToast()

watch(() => props.laboratorioSelected, (newVal) => {
  if (newVal) {
    name.value = newVal.name || '';
    description.value = newVal.description || '';
    previewUrl.value = newVal.image_path ? `/storage/${newVal.image_path}` : null;
  }
}, { immediate: true })

const onImageSelect = (files) => {
  if (previewUrl.value && !previewUrl.value.includes('/storage/')) URL.revokeObjectURL(previewUrl.value)
  const file = Array.isArray(files) ? files[0] : files
  previewUrl.value = file ? URL.createObjectURL(file) : (props.laboratorioSelected.image_path ? `/storage/${props.laboratorioSelected.image_path}` : null)
}

const update = async() => {
  let formData = new FormData()
  formData.append('_method', 'PUT') // Permite enviar Form-Data en un PUT real hacia Laravel
  formData.append('name', name.value)
  if(description.value) formData.append('description', description.value)
  if (image.value) {
    formData.append('image', Array.isArray(image.value) ? image.value[0] : image.value)
  }

  try {
    isLoading.value = true
    const response = await $api(`/laboratories/${props.laboratorioSelected.id}`, {
      method: "POST", // Laravel interpreta POST + _method=PUT
      body: formData,
      onResponse({ response }){
        if(response.status === 200){
          toast.showSuccess('Laboratorio actualizado', 'Éxito')
          emit('update:isDialogVisible', false)
          emit('editLaboratorio')
        } else {
          toast.showError('Error al actualizar', 'Error')
        }
      }
    })
  } catch (error) {
    toast.showError('Error de conexión', 'Error')
  } finally {
    isLoading.value = false
  }
}

const onFormReset = () => emit('update:isDialogVisible', false)
const dialogVisibleUpdate = val => emit('update:isDialogVisible', val)
</script>

<template>
  <VDialog :width="$vuetify.display.smAndDown ? 'auto' : 550" :model-value="props.isDialogVisible" @update:model-value="dialogVisibleUpdate">
    <VCard class="pa-sm-8 pa-3">
      <DialogCloseBtn variant="text" size="default" @click="onFormReset" />
      <VCardText class="pt-5">
        <div class="text-center pb-6">
          <VIcon 
            icon="ri-flask-fill" 
            size="48" 
            color="primary"
            class="mb-3"
          />
          <h4 class="text-h4 font-weight-bold mb-2">Editar Laboratorio</h4>
        </div>
        <VForm class="mt-4" @submit.prevent="update">
          <VRow>
            <VCol cols="12">
              <VTextField v-model="name" label="Nombre *" :rules="[requiredValidator]" />
            </VCol>
            <VCol cols="12">
              <VTextarea v-model="description" label="Descripción (Opcional)" rows="2" />
            </VCol>
            
            <VCol cols="12" md="8">
              <VFileInput v-model="image" label="Actualizar Logotipo" accept="image/*" :rules="[optionalImageValidator]" @update:model-value="onImageSelect" />
            </VCol>
            <VCol cols="12" md="4">
              <div v-if="previewUrl" class="text-center">
                <VImg :src="previewUrl" max-width="100" max-height="100" class="mx-auto rounded" cover />
              </div>
            </VCol>

            <VCol cols="12" class="d-flex flex-wrap justify-center gap-4 mt-6">
              <VBtn 
                type="submit" 
                color="success"
                class="px-8"
                :loading="isLoading"
                :disabled="isLoading"
              >
                <VIcon start icon="ri-save-3-fill"/>
                <span class="font-weight-bold">{{ isLoading ? 'Guardando...' : 'Guardar' }}</span>
              </VBtn>

              <VBtn 
                color="secondary"
                class="px-8"
                :disabled="isLoading"
                @click="onFormReset"
              >
                <VIcon start icon="ri-arrow-left-line"/>
                <span class="font-weight-bold">Cancelar</span>
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template>
