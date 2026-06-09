<script setup>
import DispenserAddDialog from '@/components/system/dispenser/DispenserAddDialog.vue'
import DispenserDeleteDialog from '@/components/system/dispenser/DispenserDeleteDialog.vue'

const headers = [
  { title: 'ID', key: 'id' },
  { title: 'Nombre', key: 'name' },
  { title: 'Estado', key: 'status' },
  { title: 'Acciones', key: 'action', sortable: false, align: 'end' },
]

const list_data = ref([])
const isLoading = ref(false)
const search = ref('')
const isDialogVisible = ref(false)
const isDeleteDialogVisible = ref(false)
const selectedItem = ref({})
const selectedItemDelete = ref({})

const loadData = async () => {
  try {
    isLoading.value = true
    const response = await $api('/v1/dispensers', { method: 'GET' })
    list_data.value = response.data || response || []
  } catch (e) { console.error(e) } finally { isLoading.value = false }
}

const filteredList = computed(() => {
  if (!search.value) return list_data.value;
  return list_data.value.filter(i => i.name.toLowerCase().includes(search.value.toLowerCase()))
})

const openAdd = () => { selectedItem.value = {}; isDialogVisible.value = true }
const openEdit = (item) => { selectedItem.value = { ...item }; isDialogVisible.value = true }
const openDelete = (item) => { selectedItemDelete.value = { ...item }; isDeleteDialogVisible.value = true }

onMounted(() => { loadData() })

definePage({ meta: { permission: 'all' } }) // Ideally a specific permission 'listar_dispenser'
</script>

<template>
  <div class="pa-4">
    <VCard>
      <VCardTitle class="py-4"><VIcon icon="ri-box-3-line" class="me-2 text-primary" /> Gestión de Dispensers</VCardTitle>
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
          <template #item.status="{ item }">
             <VChip size="small" :color="item.status === 1 ? 'success' : 'error'">
               {{ item.status === 1 ? 'Activo' : 'Inactivo' }}
             </VChip>
          </template>
          <template #item.action="{ item }">
            <div class="d-flex gap-1 justify-end">
              <IconBtn size="small" color="info" variant="tonal" @click="openEdit(item)"><VIcon icon="ri-pencil-line" size="25" /></IconBtn>
              <IconBtn size="small" color="error" variant="tonal" @click="openDelete(item)"><VIcon icon="ri-delete-bin-line" size="25" /></IconBtn>
            </div>
          </template>
        </VDataTable>
      </VCardText>
    </VCard>

    <DispenserAddDialog v-if="isDialogVisible" v-model:isDialogVisible="isDialogVisible" :dispenserSelected="selectedItem" @reload="loadData" />
    <DispenserDeleteDialog v-if="isDeleteDialogVisible" v-model:isDialogVisible="isDeleteDialogVisible" :dispenserSelected="selectedItemDelete" @reload="loadData" />
  </div>
</template>
