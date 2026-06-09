<script setup>
import ClienteAddDialog from '@/components/system/clientes/ClienteAddDialog.vue'
import ClienteEditDialog from '@/components/system/clientes/ClienteEditDialog.vue'
import ClienteDeleteDialog from '@/components/system/clientes/ClienteDeleteDialog.vue'

const headers = [
  { title: 'DNI / NIT', key: 'document_number' },
  { title: 'Razón Social / Nombre', key: 'first_name' },
  { title: 'Correo de Facturación', key: 'email' },
  { title: 'Puntos de Fidelidad', key: 'fidelity_points' },
  { title: 'Acciones', key: 'action', sortable: false },
]

const isAddDialog = ref(false)
const isEditDialog = ref(false)
const isDeleteDialog = ref(false)
const isLoading = ref(false)
const list_clientes = ref([])
const buscar = ref('')
const item_edit = ref(null)
const item_delete = ref(null)

const list = async () => {
  try {
    isLoading.value = true
    const response = await $api("/v1/customers?search=" + (buscar.value || ''), {
      method: "GET"
    })
    list_clientes.value = response.data || response || []
  } catch (error) {
    console.error(error)
  } finally {
    isLoading.value = false
  }
}

const editItem = (item) => {
  isEditDialog.value = true
  item_edit.value = item
}

const deleteItem = (item) => {
  isDeleteDialog.value = true
  item_delete.value = item
}

onMounted(() => {
  list()
})

watch(buscar, () => {
  list()
})

definePage({ 
  meta: { permission: 'listar_cliente' }
});
</script>

<template>
  <div class="pa-4">
    <VRow>
      <VCol cols="12">
        <VCard>
          <VCardTitle class="d-flex align-center">
            <VIcon icon="ri-group-line" size="20" class="me-2 text-primary" /> 
            Gestión de Clientes (SIAT)
          </VCardTitle>
          <VCardText>
            <VAlert
              color="info"
              variant="tonal"
              icon="ri-information-line"
              class="mb-4"
            >
              Registra los datos fiscales de tus clientes (NIT o CI). Esto agilizará el proceso en tu módulo POS a la hora de facturar en línea.
            </VAlert>
            <VRow class="justify-space-between">
              <VCol cols="12" md="4">
                <VTextField
                  placeholder="Buscar por NIT o Nombre"
                  density="compact"
                  prepend-inner-icon="ri-search-line"
                  v-model="buscar"
                  @keyup.enter="list()"
                />
              </VCol>
              <VCol cols="12" md="4" class="text-end">
                <VBtn color="success" @click="isAddDialog = true">
                  <VIcon end icon="ri-add-circle-fill" /> Añadir Cliente
                </VBtn>
              </VCol>
            </VRow> 
          </VCardText>
          <VDataTable
            :headers="headers"
            :items="list_clientes"
            :items-per-page="10"
            :loading="isLoading"
            loading-text="Cargando clientes..."
            no-data-text="No hay clientes registrados"
            class="text-no-wrap"
            :items-per-page-text="'Clientes por página:'"
            :page-text="'{0}-{1} de {2}'"
            :items-per-page-options="[
              { value: 5, title: '5' },
              { value: 10, title: '10' },
              { value: 25, title: '25' },
              { value: 50, title: '50' },
              { value: -1, title: 'Todo' }
            ]"
          >
            <template #item.document_number="{ item }">
              <span class="font-weight-bold text-primary">{{ item.document_number }} <span v-if="item.complement">- {{item.complement}}</span></span>
            </template>
            <template #item.fidelity_points="{ item }">
              <VChip size="small" color="warning" prepend-icon="ri-star-fill">
                {{ item.fidelity_points || 0 }} pts
              </VChip>
            </template>
            <template #item.action="{ item }">
              <div class="d-flex gap-1" v-if="item.id !== 2"> <!-- Evitar borrar "SN" -->
                <IconBtn size="small" color="info" variant="tonal" @click="editItem(item)"><VIcon icon="ri-pencil-line" size="25" /></IconBtn>
                <IconBtn size="small" color="error" variant="tonal" @click="deleteItem(item)"><VIcon icon="ri-delete-bin-line" size="25" /></IconBtn>
              </div>
            </template>
          </VDataTable>
        </VCard>

        <ClienteAddDialog v-model:isDialogVisible="isAddDialog" @addCliente="list()" />
        <ClienteEditDialog v-if="item_edit && isEditDialog" v-model:isDialogVisible="isEditDialog" :clienteSelected="item_edit" @editCliente="list()" />
        <ClienteDeleteDialog v-if="item_delete && isDeleteDialog" v-model:isDialogVisible="isDeleteDialog" :clienteSelected="item_delete" @deleteCliente="list()" />
      </VCol>
    </VRow>
  </div>
</template>
