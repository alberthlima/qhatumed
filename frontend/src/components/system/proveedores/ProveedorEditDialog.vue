<script setup>
import { requiredValidator, emailValidator } from '@/utils/validators'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  proveedorSelected: { type: Object, required: true },
})

const emit = defineEmits(['update:isDialogVisible', 'editProveedor'])

const name = ref('')
const contact_name = ref('')
const phone = ref('')
const email = ref('')
const address = ref('')
const isLoading = ref(false)
const toast = useMaterializeToast()

watch(() => props.proveedorSelected, (newVal) => {
  if (newVal) {
    name.value = newVal.name || '';
    contact_name.value = newVal.contact_name || '';
    phone.value = newVal.phone || '';
    email.value = newVal.email || '';
    address.value = newVal.address || '';
  }
}, { immediate: true })

const update = async() => {
  let formData = new URLSearchParams()
  formData.append('name', name.value)
  if(contact_name.value) formData.append('contact_name', contact_name.value)
  if(phone.value) formData.append('phone', phone.value)
  if(email.value) formData.append('email', email.value)
  if(address.value) formData.append('address', address.value)

  try {
    isLoading.value = true
    const response = await $api(`/suppliers/${props.proveedorSelected.id}`, {
      method: "PUT",
      body: formData,
      onResponse({ response }){
        if(response.status === 200){
          toast.showSuccess('Proveedor actualizado', 'Éxito')
          emit('update:isDialogVisible', false)
          emit('editProveedor')
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
  <VDialog :width="$vuetify.display.smAndDown ? 'auto' : 650" :model-value="props.isDialogVisible" @update:model-value="dialogVisibleUpdate">
    <VCard class="pa-sm-8 pa-3">
      <DialogCloseBtn variant="text" size="default" @click="onFormReset" />
      <VCardText class="pt-5">
        <div class="text-center pb-6">
          <VIcon 
            icon="ri-group-2-fill" 
            size="48" 
            color="primary"
            class="mb-3"
          />
          <h4 class="text-h4 font-weight-bold mb-2">Editar Proveedor</h4>
        </div>
        <VForm class="mt-4" @submit.prevent="update">
          <VRow>
            <VCol cols="12">
              <VTextField v-model="name" label="Nombre Empresa *" :rules="[requiredValidator]" />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField v-model="contact_name" label="Representante (Contacto)" />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField v-model="phone" label="Teléfono / Celular" />
            </VCol>
            <VCol cols="12" md="12">
              <VTextField v-model="email" label="Correo Electrónico (Opcional)" :rules="[val => !val || emailValidator(val)]" />
            </VCol>
            <VCol cols="12">
              <VTextarea v-model="address" label="Dirección Física" rows="2" />
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
