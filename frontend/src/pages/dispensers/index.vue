<script setup>
import DispenserAddDialog from '@/components/system/dispenser/DispenserAddDialog.vue'
import DispenserDeleteDialog from '@/components/system/dispenser/DispenserDeleteDialog.vue'

const headers = [
  { title: 'ID', key: 'id' },
  { title: 'Nombre', key: 'name' },
  { title: 'Estado', key: 'status' },
  { title: 'Acciones', key: 'action', sortable: false, align: 'end' },
]

const list = ref([])
const search = ref('')
const isLoading = ref(false)
const toast = useMaterializeToast()

const isDialogVisible = ref(false)
const selectedItem = ref({})

const isDeleteDialogVisible = ref(false)
const selectedItemDelete = ref({})

const loadData = async () => {
  try {
    isLoading.value = true
    const response = await $api('/v1/dispensers', {
      method: 'GET',
      query: { search: search.value }
    })
    list.value = response.data || response || []
  } catch (err) {
    console.error(err)
  } finally {
    isLoading.value = false
  }
}

const openAdd = () => {
  selectedItem.value = {}
  isDialogVisible.value = true
}

const openEdit = (item) => {
  selectedItem.value = { ...item }
  isDialogVisible.value = true
}

const openDelete = (item) => {
  selectedItemDelete.value = { ...item }
  isDeleteDialogVisible.value = true
}

const enableItem = async (item) => {
  try {
    isLoading.value = true
    await $api(`/v1/dispensers/${item.id}/enable`, {
      method: "POST"
    })
    toast.showSuccess('Dispenser habilitado nuevamente', 'Éxito')
    loadData()
  } catch (error) {
    toast.showError('Error al habilitar dispenser', 'Error')
  } finally {
    isLoading.value = false
  }
}

watch(search, () => {
  loadData()
})

onMounted(() => {
  loadData()
})

const filteredList = computed(() => {
  if (!search.value) return list.value
  return list.value.filter(item => 
    item.name.toLowerCase().includes(search.value.toLowerCase())
  )
})
</script>

<template>
  <div class="pa-4">
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between">
        <div class="d-flex align-center">
          <VIcon icon="ri-box-3-line" class="me-2 text-primary" />
          <span>Gestión de Dispensadores (Empaques)</span>
        </div>
      </VCardTitle>
      
      <VCardText>
        <VRow class="justify-space-between mb-4">
          <VCol cols="12" md="4">
            <VTextField v-model="search" label="Buscar..." density="compact" prepend-inner-icon="ri-search-line" hide-details />
          </VCol>
          <VCol cols="12" md="4" class="text-end">
            <VBtn color="success" @click="openAdd"><VIcon icon="ri-add-line" start /> Nuevo Dispenser</VBtn>
          </VCol>
        </VRow>
        
        <VDataTable 
          :headers="headers" 
          :items="filteredList" 
          :sort-by="[{ key: 'id', order: 'desc' }]"
          :loading="isLoading"
          loading-text="Cargando dispensadores..."
          no-data-text="No hay dispensadores registrados"
          :items-per-page="10"
          :items-per-page-text="'Dispensadores por página:'"
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
            <span class="font-weight-medium">{{ item.name }}</span>
          </template>
          <template #item.status="{ item }">
             <VChip size="small" :color="item.status === 1 || item.status === true ? 'success' : 'error'">
               {{ item.status === 1 || item.status === true ? 'Activo' : 'Inactivo' }}
             </VChip>
          </template>
          <template #item.action="{ item }">
            <div class="d-flex gap-1 justify-end">
              <template v-if="item.status === 1 || item.status === true">
                <IconBtn size="small" color="info" variant="tonal" title="Editar" @click="openEdit(item)"><VIcon icon="ri-pencil-line" size="20" /></IconBtn>
                <IconBtn size="small" color="error" variant="tonal" title="Inhabilitar" @click="openDelete(item)"><VIcon icon="ri-delete-bin-line" size="20" /></IconBtn>
              </template>
              <template v-else>
                <IconBtn size="small" color="success" variant="tonal" title="Habilitar Dispenser" @click="enableItem(item)"><VIcon icon="ri-checkbox-circle-line" size="20" /></IconBtn>
              </template>
            </div>
          </template>
        </VDataTable>
      </VCardText>
    </VCard>

    <DispenserAddDialog v-if="isDialogVisible" v-model:isDialogVisible="isDialogVisible" :dispenserSelected="selectedItem" @reload="loadData" />
    <DispenserDeleteDialog v-if="isDeleteDialogVisible" v-model:isDialogVisible="isDeleteDialogVisible" :dispenserSelected="selectedItemDelete" @reload="loadData" />
  </div>
</template>