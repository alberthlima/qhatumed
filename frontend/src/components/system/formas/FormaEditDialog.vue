<script setup>
import { requiredValidator } from '@/utils/validators'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  formaSelected: { type: Object, required: true },
})

const emit = defineEmits(['update:isDialogVisible', 'editForma'])

const name = ref('')
const description = ref('')
const isLoading = ref(false)
const toast = useMaterializeToast()

watch(() => props.formaSelected, (newVal) => {
  if (newVal) {
    name.value = newVal.name;
    description.value = newVal.description;
  }
}, { immediate: true })

const update = async() => {
  let formData = new URLSearchParams()
  formData.append('name', name.value)
  if(description.value) formData.append('description', description.value)
  
  try {
    isLoading.value = true
    const response = await $api(`/pharmaceutical-forms/${props.formaSelected.id}`, {
      method: "PUT",
      body: formData,
      onResponse({ response }){
        if(response.status === 200){
          toast.showSuccess('Actualizado', 'Éxito')
          emit('update:isDialogVisible', false)
          emit('editForma')
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
  <VDialog :width="$vuetify.display.smAndDown ? 'auto' : 500" :model-value="props.isDialogVisible" @update:model-value="dialogVisibleUpdate">
    <VCard class="pa-sm-8 pa-3">
      <DialogCloseBtn variant="text" size="default" @click="onFormReset" />
      <VCardText class="pt-5">
        <div class="text-center pb-6">
          <VIcon 
            icon="ri-capsule-fill" 
            size="48" 
            color="primary"
            class="mb-3"
          />
          <h4 class="text-h4 font-weight-bold mb-2">Editar Forma</h4>
        </div>
        <VForm class="mt-4" @submit.prevent="update">
          <VRow>
            <VCol cols="12">
              <VTextField v-model="name" label="Nombre" :rules="[requiredValidator]" />
            </VCol>
            <VCol cols="12">
              <VTextarea v-model="description" label="Descripción" rows="2" />
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
