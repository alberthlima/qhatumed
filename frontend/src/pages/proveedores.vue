<script setup>
import ProveedorAddDialog from '@/components/system/proveedores/ProveedorAddDialog.vue'
import ProveedorEditDialog from '@/components/system/proveedores/ProveedorEditDialog.vue'
import ProveedorDeleteDialog from '@/components/system/proveedores/ProveedorDeleteDialog.vue'

const headers = [
  { title: 'ID', key: 'id' },
  { title: 'Empresa', key: 'name' },
  { title: 'Representante', key: 'contact_name' },
  { title: 'Celular', key: 'phone' },
  { title: 'Acciones', key: 'action', sortable: false },
]

const isAddDialog = ref(false)
const isEditDialog = ref(false)
const isDeleteDialog = ref(false)
const isLoading = ref(false)
const list_proveedores = ref([])
const buscar = ref('')
const item_edit = ref(null)
const item_delete = ref(null)

const list = async () => {
  try {
    isLoading.value = true
    const response = await $api("/v1/suppliers?search=" + (buscar.value || ''), {
      method: "GET"
    })
    list_proveedores.value = response.data || response || []
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
  meta: { permission: 'listar_proveedor' }
});
</script>

<template>
  <div class="pa-4">
    <VRow>
      <VCol cols="12">
        <VCard>
          <VCardTitle>
            <VIcon icon="ri-truck-line" size="20" class="me-2 text-primary" /> 
            Gestión de Proveedores
          </VCardTitle>
          <VCardText>
            <VRow class="justify-space-between">
              <VCol cols="12" md="4">
                <VTextField
                  placeholder="Buscar Proveedor"
                  density="compact"
                  prepend-inner-icon="ri-search-line"
                  v-model="buscar"
                  @keyup.enter="list()"
                />
              </VCol>
              <VCol cols="12" md="4" class="text-end">
                <VBtn color="success" @click="isAddDialog = true">
                  <VIcon end icon="ri-add-circle-fill" /> Registrar Proveedor
                </VBtn>
              </VCol>
            </VRow> 
          </VCardText>
          <VDataTable
            :headers="headers"
            :items="list_proveedores"
            :sort-by="[{ key: 'id', order: 'desc' }]"
            :items-per-page="10"
            :loading="isLoading"
            loading-text="Cargando proveedores..."
            no-data-text="No hay proveedores registrados"
            class="text-no-wrap"
            :items-per-page-text="'Proveedores por página:'"
            :page-text="'{0}-{1} de {2}'"
            :items-per-page-options="[
              { value: 5, title: '5' },
              { value: 10, title: '10' },
              { value: 25, title: '25' },
              { value: 50, title: '50' },
              { value: -1, title: 'Todo' }
            ]"
          >
            <template #item.id="{ item }">
              <span class="text-h6">{{ item.id }}</span>
            </template>
            <template #item.name="{ item }">
              <span class="font-weight-bold text-primary">{{ item.name }}</span>
            </template>
            <template #item.contact_name="{ item }">
              <span>{{ item.contact_name || '-' }}</span>
            </template>
             <template #item.phone="{ item }">
              <span>{{ item.phone || '-' }}</span>
            </template>
            <template #item.action="{ item }">
              <div class="d-flex gap-1">
                <IconBtn size="small" color="info" variant="tonal" @click="editItem(item)"><VIcon icon="ri-pencil-line" size="25" /></IconBtn>
                <IconBtn size="small" color="error" variant="tonal" @click="deleteItem(item)"><VIcon icon="ri-delete-bin-line" size="25" /></IconBtn>
              </div>
            </template>
          </VDataTable>
        </VCard>

        <ProveedorAddDialog v-model:isDialogVisible="isAddDialog" @addProveedor="list()" />
        <ProveedorEditDialog v-if="item_edit && isEditDialog" v-model:isDialogVisible="isEditDialog" :proveedorSelected="item_edit" @editProveedor="list()" />
        <ProveedorDeleteDialog v-if="item_delete && isDeleteDialog" v-model:isDialogVisible="isDeleteDialog" :proveedorSelected="item_delete" @deleteProveedor="list()" />
      </VCol>
    </VRow>
  </div>
</template>
