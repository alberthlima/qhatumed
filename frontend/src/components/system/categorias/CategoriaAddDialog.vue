<script setup>
import { requiredValidator } from '@/utils/validators'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
})

const emit = defineEmits(['update:isDialogVisible', 'addCategoria'])

const name = ref(null)
const description = ref(null)
const isLoading = ref(false)
const toast = useMaterializeToast()

const store = async() => {
  let formData = new FormData()
  formData.append('name', name.value)
  if(description.value) formData.append('description', description.value)
  formData.append('status', 1)

  try {
    isLoading.value = true
    const response = await $api("/categories", { // Mapeado al CategoryController de Laravel
      method: "POST",
      body: formData,
      onResponse({ response }){
        if(response.status === 200 || response.status === 201){
          toast.showSuccess('Categoría guardada', 'Éxito')
          name.value = null; description.value = null;
          emit('update:isDialogVisible', false)
          emit('addCategoria')
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

const onFormReset = () => emit('update:isDialogVisible', false)
const dialogVisibleUpdate = val => emit('update:isDialogVisible', val)
</script>

<template>
  <VDialog :width="$vuetify.display.smAndDown ? 'auto' : 500" :model-value="props.isDialogVisible" @update:model-value="dialogVisibleUpdate">
    <VCard class="pa-sm-8 pa-3">
      <DialogCloseBtn variant="text" size="default" @click="onFormReset" />
      <VCardText class="pt-5">
        <div class="text-center pb-6">
          <h4 class="text-h4 font-weight-bold mb-2">Añadir Categoría</h4>
        </div>
        <VForm class="mt-4" @submit.prevent="store">
          <VRow>
            <VCol cols="12">
              <VTextField v-model="name" label="Nombre" placeholder="Ej: Analgésicos" :rules="[requiredValidator]" />
            </VCol>
            <VCol cols="12">
              <VTextarea v-model="description" label="Descripción (Opcional)" placeholder="Detalle de la categoría..." rows="2" />
            </VCol>
            <VCol cols="12" class="d-flex justify-center gap-4 mt-6">
              <VBtn type="submit" color="success" :loading="isLoading" :disabled="isLoading">
                <VIcon start icon="ri-save-3-fill"/> Guardar
              </VBtn>
              <VBtn color="secondary" :disabled="isLoading" @click="onFormReset">Cancelar</VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template>
