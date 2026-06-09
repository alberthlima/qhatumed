<script setup>
const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  clienteSelected: { type: Object, required: true },
})

const emit = defineEmits(['update:isDialogVisible', 'deleteCliente'])
const isLoading = ref(false)
const toast = useMaterializeToast()

const destroy = async() => {
  try {
    isLoading.value = true
    const response = await $api(`/v1/customers/${props.clienteSelected.id}`, {
      method: "DELETE",
      onResponse({ response }){
        if(response.status === 200 || response.status === 204){
          toast.showSuccess('Cliente eliminado / inhabilitado', 'Éxito')
          emit('update:isDialogVisible', false)
          emit('deleteCliente')
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
        <p>Vas a eliminar al cliente <b>{{ props.clienteSelected?.first_name }}</b>.</p>
        <div class="d-flex justify-center gap-4 mt-6">
          <VBtn color="error" :loading="isLoading" @click="destroy">Sí, eliminar</VBtn>
          <VBtn color="secondary" variant="outlined" :disabled="isLoading" @click="onFormReset">Cancelar</VBtn>
        </div>
      </VCardText>
    </VCard>
  </VDialog>
</template>
