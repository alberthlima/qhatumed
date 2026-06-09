<script setup>
import { requiredValidator } from '@/utils/validators'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  dispenserSelected: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:isDialogVisible', 'reload'])

const isLoading = ref(false)
const toast = useMaterializeToast()

const formData = ref({
  name: '',
})

watch(() => props.dispenserSelected, (newVal) => {
  if (Object.keys(newVal).length) {
    formData.value = { ...newVal }
  } else {
    formData.value = { name: '' }
  }
}, { immediate: true })

const save = async () => {
  try {
    isLoading.value = true
    const method = formData.value.id ? 'PUT' : 'POST'
    const url = formData.value.id ? `/v1/dispensers/${formData.value.id}` : '/v1/dispensers'
    
    const response = await $api(url, {
      method,
      body: formData.value,
      onResponse({ response }) {
        if (response.status === 200 || response.status === 201) {
          toast.showSuccess(formData.value.id ? 'Actualizado' : 'Creado', 'Éxito')
          emit('update:isDialogVisible', false)
          emit('reload')
        } else {
          toast.showError('Ocurrió un error', 'Error')
        }
      }
    })
  } catch (err) {
    toast.showError('Error de conexión', 'Error')
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <VDialog :width="$vuetify.display.smAndDown ? 'auto' : 500" :model-value="props.isDialogVisible" @update:model-value="val => emit('update:isDialogVisible', val)">
    <VCard class="pa-sm-8 pa-3">
      <DialogCloseBtn variant="text" size="default" @click="emit('update:isDialogVisible', false)" />
      
      <VCardText class="pt-5">
        <div class="text-center pb-6">
          <VIcon 
            icon="ri-box-3-line" 
            size="48" 
            color="primary"
            class="mb-3"
          />
          <h4 class="text-h4 font-weight-bold mb-2">
            {{ formData.id ? 'Editar Dispenser (Empaque)' : 'Añadir Dispenser (Empaque)' }}
          </h4>
        </div>

        <VForm class="mt-4" @submit.prevent="save">
          <VRow>
            <VCol cols="12">
              <VTextField v-model="formData.name" label="Nombre del Dispenser *" placeholder="Ej: Caja, Blíster, Frasco..." :rules="[requiredValidator]" />
            </VCol>
          </VRow>
          
          <VRow>
            <!-- 👉 Submit and Cancel -->
            <VCol
              cols="12"
              class="d-flex flex-wrap justify-center gap-4 mt-6"
            >
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
                @click="emit('update:isDialogVisible', false)"
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
