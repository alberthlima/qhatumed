<script setup>
const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  dispenserSelected: { type: Object, required: true },
})

const emit = defineEmits(['update:isDialogVisible', 'reload'])
const isLoading = ref(false)
const toast = useMaterializeToast()

const destroy = async() => {
  try {
    isLoading.value = true
    const response = await $api(`/v1/dispensers/${props.dispenserSelected.id}`, {
      method: "DELETE",
      onResponse({ response }){
        if(response.status === 200 || response.status === 204){
          toast.showSuccess('Dispenser inhabilitado', 'Éxito')
          emit('update:isDialogVisible', false)
          emit('reload')
        } else {
          toast.showError('Error al eliminar', 'Error')
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
  <VDialog :width="$vuetify.display.smAndDown ? 'auto' : 400" :model-value="props.isDialogVisible" @update:model-value="dialogVisibleUpdate">
    <VCard class="pa-sm-8 pa-3 text-center">
      <VCardText>
        <VIcon icon="ri-error-warning-line" color="error" size="64" class="mb-4" />
        <h4 class="text-h4 mb-2">¿Estás Seguro?</h4>
        <p>Vas a eliminar al dispenser <b>{{ props.dispenserSelected?.name }}</b>.</p>
        <VRow class="justify-center mt-4">
          <VCol cols="12" class="d-flex gap-3 justify-center">
            <VBtn color="error" class="px-8" :loading="isLoading" :disabled="isLoading" @click="destroy">
              <VIcon start icon="ri-delete-bin-line"/>
              <span class="font-weight-bold">{{ isLoading ? 'Eliminando...' : 'Sí, Eliminar' }}</span>
            </VBtn>

            <VBtn color="secondary" class="px-8" :disabled="isLoading" @click="onFormReset">
              <VIcon start icon="ri-arrow-left-line"/>
              <span class="font-weight-bold">Cancelar</span>
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>
  </VDialog>
</template>
