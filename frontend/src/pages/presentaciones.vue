<script setup>
import PresentacionAddDialog from '@/components/system/presentaciones/PresentacionAddDialog.vue'
import PresentacionEditDialog from '@/components/system/presentaciones/PresentacionEditDialog.vue'
import PresentacionDeleteDialog from '@/components/system/presentaciones/PresentacionDeleteDialog.vue'

const headers = [
  { title: 'ID', key: 'id' },
  { title: 'Presentación', key: 'name' },
  { title: 'Descripción', key: 'description' },
  { title: 'Estado', key: 'status' },
  { title: 'Acciones', key: 'action', sortable: false },
]

const isAddDialog = ref(false)
const isEditDialog = ref(false)
const isDeleteDialog = ref(false)
const isLoading = ref(false)
const list_items = ref([])
const buscar = ref('')
const item_edit = ref(null)
const item_delete = ref(null)

const list = async () => {
  try {
    isLoading.value = true
    const response = await $api("/presentations?search=" + (buscar.value || ''), { method: "GET" })
    list_items.value = response.data || response || []
  } catch (error) { console.error(error) } finally { isLoading.value = false }
}

const editItem = (item) => { isEditDialog.value = true; item_edit.value = item }
const deleteItem = (item) => { isDeleteDialog.value = true; item_delete.value = item }

onMounted(() => {
  setTimeout(()=>{
    list_items.value = [
      { id: 1, name: 'Caja x 50', description: 'Empaque de 50', status: 1 },
      { id: 2, name: 'Frasco', description: '100ml', status: 1 }
    ]
  }, 1000)
})

definePage({ meta: { permission: 'listar_presentacion' } });
</script>

<template>
  <div class="pa-4">
    <VRow>
      <VCol cols="12">
        <VCard>
          <VCardTitle><VIcon icon="ri-archive-line" size="20" class="me-2 text-primary" />Presentaciones Farmacéuticas</VCardTitle>
          <VCardText>
            <VRow class="justify-space-between">
              <VCol cols="12" md="4"><VTextField placeholder="Buscar..." density="compact" prepend-inner-icon="ri-search-line" v-model="buscar" @keyup.enter="list()" /></VCol>
              <VCol cols="12" md="4" class="text-end"><VBtn color="success" @click="isAddDialog = true"><VIcon end icon="ri-add-circle-fill" /> Registrar</VBtn></VCol>
            </VRow> 
          </VCardText>
          <VDataTable :headers="headers" :items="list_items" :items-per-page="10" :loading="isLoading" class="text-no-wrap">
            <template #item.id="{ item }"><span class="text-h6">{{ item.id }}</span></template>
            <template #item.name="{ item }"><span class="font-weight-medium">{{ item.name }}</span></template>
            <template #item.status="{ item }">
              <VChip :color="item.status === 1 ? 'success' : 'error'" size="small">{{ item.status === 1 ? 'Activo' : 'Inhabilitado' }}</VChip>
            </template>
            <template #item.action="{ item }">
              <div class="d-flex gap-1">
                <IconBtn size="small" color="info" variant="tonal" @click="editItem(item)"><VIcon icon="ri-pencil-line" size="25" /></IconBtn>
                <IconBtn size="small" color="error" variant="tonal" @click="deleteItem(item)"><VIcon icon="ri-delete-bin-line" size="25" /></IconBtn>
              </div>
            </template>
          </VDataTable>
        </VCard>

        <PresentacionAddDialog v-model:isDialogVisible="isAddDialog" @addPresentacion="list()" />
        <PresentacionEditDialog v-if="item_edit && isEditDialog" v-model:isDialogVisible="isEditDialog" :presentacionSelected="item_edit" @editPresentacion="list()" />
        <PresentacionDeleteDialog v-if="item_delete && isDeleteDialog" v-model:isDialogVisible="isDeleteDialog" :presentacionSelected="item_delete" @deletePresentacion="list()" />
      </VCol>
    </VRow>
  </div>
</template>
