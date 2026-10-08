<script setup>
import { requiredValidator, emailValidator } from '@/utils/validators'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  clienteSelected: { type: Object, required: true },
})

const emit = defineEmits(['update:isDialogVisible', 'editCliente'])

const full_name = ref('')
const document_type = ref(1)
const document_number = ref('')
const complement = ref('')
const email = ref('')
const phone = ref('')

const documentTypes = [
  { title: 'CI - Cédula de Identidad', value: 1 },
  { title: 'CEX - Cédula de Extranjero', value: 2 },
  { title: 'PAS - Pasaporte', value: 3 },
  { title: 'OD - Otro Documento', value: 4 },
  { title: 'NIT - Número de Identificación Tributaria', value: 5 }
]

const isLoading = ref(false)
const toast = useMaterializeToast()

watch(() => props.clienteSelected, (newVal) => {
  if (newVal) {
    full_name.value = newVal.first_name || '';
    document_number.value = newVal.document_number || '';
    complement.value = newVal.complement || '';
    email.value = newVal.email || '';
    phone.value = newVal.phone || '';
    document_type.value = newVal.document_type || 1;
  }
}, { immediate: true })

// Validación y restricción en tiempo real de cuadros numéricos
watch([document_number, document_type], ([newNum, docType]) => {
  if (!newNum) return

  // Tipos 1 (CI) y 5 (NIT) son estrictamente numéricos (norma SIN)
  if ([1, 5].includes(docType) && /[^\d]/.test(String(newNum))) {
    document_number.value = String(newNum).replace(/\D/g, '')
    toast.showWarning('El Número de Documento es estrictamente numérico para CI y NIT. Se filtraron las letras.', 'Restricción Numérica (SIN)')
    return
  }

  // Auto-detectar NIT si tiene 9 o más dígitos y está en CI
  if (String(newNum).length >= 9 && docType === 1) {
    document_type.value = 5 // NIT
  }
})

// Teléfono: estrictamente numérico
watch(phone, (newPhone) => {
  if (!newPhone) return
  if (/[^\d+]/.test(String(newPhone))) {
    phone.value = String(newPhone).replace(/[^\d+]/g, '')
    toast.showWarning('El campo Celular / WhatsApp solo admite caracteres numéricos.', 'Restricción Numérica')
  }
})

// Bloqueo físico en teclado al escribir letras en cuadros numéricos
const onDocNumberKeypress = (e) => {
  if ([1, 5].includes(document_type.value)) {
    if (!/^\d$/.test(e.key)) {
      e.preventDefault()
    }
  }
}

const onPhoneKeypress = (e) => {
  if (!/^\d$/.test(e.key) && !(e.key === '+' && (!phone.value || phone.value.length === 0))) {
    e.preventDefault()
  }
}

const docNumberValidator = (val) => {
  if (!val) return true
  if ([1, 5].includes(document_type.value) && /[^\d]/.test(String(val))) {
    return 'Formato inválido: solo se admiten números para CI o NIT.'
  }
  return true
}

const update = async() => {
  // Validación previa de formato numérico
  if ([1, 5].includes(document_type.value) && /[^\d]/.test(String(document_number.value || ''))) {
    toast.showError('El Número de Documento solo puede contener números para CI o NIT.', 'Restricción de Formato')
    return
  }

  let formData = new URLSearchParams()
  formData.append('document_number', document_number.value)
  formData.append('document_type', document_type.value)
  if(complement.value) formData.append('complement', complement.value)
  formData.append('first_name', full_name.value)
  if(phone.value) formData.append('phone', phone.value)
  if(email.value) formData.append('email', email.value)

  try {
    isLoading.value = true
    const response = await $api(`/v1/customers/${props.clienteSelected.id}`, {
      method: "PUT",
      body: formData,
      onResponse({ response }){
        if(response.status === 200){
          toast.showSuccess('Cliente actualizado', 'Éxito')
          emit('update:isDialogVisible', false)
          emit('editCliente')
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
    <VCard class="pa-sm-11 pa-3">
      <DialogCloseBtn variant="text" size="default" @click="onFormReset" />
      <VCardText class="pt-5">
        <div class="text-center pb-8">
          <VIcon 
            icon="ri-user-settings-line" 
            size="48" 
            color="primary"
            class="mb-3"
          />
          <h4 class="text-h4 font-weight-bold mb-2">Editar Cliente</h4>
        </div>
        <VForm class="mt-4" @submit.prevent="update">
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
              <VTextField 
                v-model="document_number" 
                label="Número de Documento *" 
                :rules="[requiredValidator, docNumberValidator]" 
                @keypress="onDocNumberKeypress"
              />
            </VCol>
            <VCol cols="12" md="3">
              <VTextField 
                v-model="complement" 
                label="Complemento" 
                maxlength="5"
                @input="complement = (complement || '').toUpperCase()"
              />
            </VCol>

            <VCol cols="12">
              <VTextField v-model="full_name" label="Razón Social / Nombre Completo *" :rules="[requiredValidator]" />
            </VCol>
            
            <VCol cols="12" md="6">
              <VTextField 
                v-model="phone" 
                label="Celular / WhatsApp" 
                @keypress="onPhoneKeypress"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField v-model="email" label="Correo Electrónico (Factura)" :rules="[val => !val || emailValidator(val)]" />
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
                <span class="font-weight-bold">Guardar Cambios</span>
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
