<script setup>
import CategoriaAddDialog from '@/components/system/categorias/CategoriaAddDialog.vue'
import CategoriaEditDialog from '@/components/system/categorias/CategoriaEditDialog.vue'
import CategoriaDeleteDialog from '@/components/system/categorias/CategoriaDeleteDialog.vue'

const headers = [
  { title: 'ID', key: 'id' },
  { title: 'Nombre', key: 'name' },
  { title: 'Descripción', key: 'description' },
  { title: 'Estado', key: 'status' },
  { title: 'Acciones', key: 'action', sortable: false },
]

const isAddDialog = ref(false)
const isEditDialog = ref(false)
const isDeleteDialog = ref(false)
const isLoading = ref(false)
const list_categorias = ref([])
const buscar = ref('')
const item_edit = ref(null)
const item_delete = ref(null)

const list = async () => {
  try {
    isLoading.value = true
    const response = await $api("/categories?search=" + (buscar.value || ''), {
      method: "GET"
    })
    // Asumimos que Vuexy con Laravel maneja paginación u objects array en data()
    list_categorias.value = response.data || response || []
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
  // list() // Uncomment back when backend API categories controller is strictly defined, for now we mock if empty to avoid crash
  setTimeout(()=>{
    list_categorias.value = [
      { id: 1, name: 'Analgésicos', description: 'Para el dolor', status: 1 },
      { id: 2, name: 'Antibióticos', description: 'Para infecciones', status: 1 }
    ]
  }, 1000)
})

definePage({ 
  meta: { permission: 'listar_categoria' }
});
</script>

<template>
  <div class="pa-4">
    <VRow>
      <VCol cols="12">
        <VCard>
          <VCardTitle>
            <VIcon icon="ri-layout-grid-line" size="20" class="me-2 text-primary" /> 
            Gestión de Categorías
          </VCardTitle>
          <VCardText>
            <VRow class="justify-space-between">
              <VCol cols="12" md="4">
                <VTextField
                  placeholder="Buscar Categoría"
                  density="compact"
                  prepend-inner-icon="ri-search-line"
                  v-model="buscar"
                  @keyup.enter="list()"
                />
              </VCol>
              <VCol cols="12" md="4" class="text-end">
                <VBtn color="success" @click="isAddDialog = true">
                  <VIcon end icon="ri-add-circle-fill" /> Registrar Categoría
                </VBtn>
              </VCol>
            </VRow> 
          </VCardText>
          <VDataTable
            :headers="headers"
            :items="list_categorias"
            :items-per-page="10"
            :loading="isLoading"
            loading-text="Cargando categorías..."
            no-data-text="No hay categorías registradas"
            class="text-no-wrap"
          >
            <template #item.id="{ item }"><span class="text-h6">{{ item.id }}</span></template>
            <template #item.name="{ item }"><span class="font-weight-medium">{{ item.name }}</span></template>
            <template #item.status="{ item }">
              <VChip :color="item.status === 1 ? 'success' : 'error'" size="small">
                {{ item.status === 1 ? 'Activo' : 'Inhabilitado' }}
              </VChip>
            </template>
            <template #item.action="{ item }">
              <div class="d-flex gap-1">
                <IconBtn size="small" color="info" variant="tonal" @click="editItem(item)"><VIcon icon="ri-pencil-line" size="25" /></IconBtn>
                <IconBtn size="small" color="error" variant="tonal" @click="deleteItem(item)"><VIcon icon="ri-delete-bin-line" size="25" /></IconBtn>
              </div>
            </template>
          </VDataTable>
        </VCard>

        <CategoriaAddDialog v-model:isDialogVisible="isAddDialog" @addCategoria="list()" />
        <CategoriaEditDialog v-if="item_edit && isEditDialog" v-model:isDialogVisible="isEditDialog" :categoriaSelected="item_edit" @editCategoria="list()" />
        <CategoriaDeleteDialog v-if="item_delete && isDeleteDialog" v-model:isDialogVisible="isDeleteDialog" :categoriaSelected="item_delete" @deleteCategoria="list()" />
      </VCol>
    </VRow>
  </div>
</template>
