<script setup>
import { requiredValidator, emailValidator } from '@/utils/validators'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
})

const emit = defineEmits(['update:isDialogVisible', 'addCliente'])

const full_name = ref(null)
const document_type = ref(1) // Default to CI
const document_number = ref(null) // NIT o CI
const complement = ref(null) // Complemento SIAT
const email = ref(null)
const phone = ref(null)

const documentTypes = [
  { title: 'CI - Cédula de Identidad', value: 1 },
  { title: 'CEX - Cédula de Extranjero', value: 2 },
  { title: 'PAS - Pasaporte', value: 3 },
  { title: 'OD - Otro Documento', value: 4 },
  { title: 'NIT - Número de Identificación Tributaria', value: 5 }
]

const isLoading = ref(false)
const toast = useMaterializeToast()

const store = async() => {
  let formData = new URLSearchParams()
  formData.append('document_type', document_type.value)
  formData.append('document_number', document_number.value)
  if(complement.value) formData.append('complement', complement.value)
  formData.append('first_name', full_name.value)
  if(phone.value) formData.append('phone', phone.value)
  if(email.value) formData.append('email', email.value)

  try {
    isLoading.value = true
    const response = await $api("/v1/customers", {
      method: "POST",
      body: formData,
      onResponse({ response }){
        if(response.status === 200 || response.status === 201){
          toast.showSuccess('Cliente guardado con éxito', 'Éxito')
          emit('update:isDialogVisible', false)
          emit('addCliente')
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
  <VDialog :width="$vuetify.display.smAndDown ? 'auto' : 650" :model-value="props.isDialogVisible" @update:model-value="dialogVisibleUpdate">
    <VCard class="pa-sm-11 pa-3">
      <DialogCloseBtn variant="text" size="default" @click="onFormReset" />
      <VCardText class="pt-5">
        <div class="text-center pb-8">
          <VIcon 
            icon="ri-user-add-line" 
            size="48" 
            color="primary"
            class="mb-3"
          />
          <h4 class="text-h4 font-weight-bold mb-2">Registrar Nuevo Cliente</h4>
        </div>
        <VForm class="mt-4" @submit.prevent="store">
          <VRow>
            <VCol cols="12" md="4">
              <VSelect
                v-model="document_type"
                :items="documentTypes"
                item-title="title"
                item-value="value"
                label="Tipo de Documento *"
                variant="outlined"
              />
            </VCol>
            <VCol cols="12" md="5">
              <VTextField v-model="document_number" label="Número de Documento *" placeholder="Ej: 83749321" :rules="[requiredValidator]" />
            </VCol>
            <VCol cols="12" md="3">
              <VTextField v-model="complement" label="Complemento" placeholder="Ej: 1E (Opcional)" />
            </VCol>

            <VCol cols="12">
              <VTextField v-model="full_name" label="Razón Social / Nombre Completo *" placeholder="Ej: Juan Perez" :rules="[requiredValidator]" />
            </VCol>
            
            <VCol cols="12" md="6">
              <VTextField v-model="phone" label="Celular / WhatsApp" placeholder="Ej: 71122334" />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField v-model="email" label="Correo Electrónico (Factura)" placeholder="cliente@email.com" :rules="[val => !val || emailValidator(val)]" />
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
                <span class="font-weight-bold">Guardar Cliente</span>
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
