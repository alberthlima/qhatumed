<script setup>
import LaboratorioAddDialog from '@/components/system/laboratorios/LaboratorioAddDialog.vue'
import LaboratorioEditDialog from '@/components/system/laboratorios/LaboratorioEditDialog.vue'
import LaboratorioDeleteDialog from '@/components/system/laboratorios/LaboratorioDeleteDialog.vue'

const headers = [
  { title: 'ID', key: 'id' },
  { title: 'Logotipo', key: 'logo', sortable: false },
  { title: 'Nombre', key: 'name' },
  { title: 'Descripción', key: 'description' },
  { title: 'Estado', key: 'status' },
  { title: 'Acciones', key: 'action', sortable: false },
]

const isAddDialog = ref(false)
const isEditDialog = ref(false)
const isDeleteDialog = ref(false)
const isLoading = ref(false)
const list_laboratorios = ref([])
const buscar = ref('')
const item_edit = ref(null)
const item_delete = ref(null)

const list = async () => {
  try {
    isLoading.value = true
    const response = await $api("/v1/laboratories?search=" + (buscar.value || ''), {
      method: "GET"
    })
    list_laboratorios.value = response.data || response || []
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
  meta: { permission: 'listar_laboratorio' }
});
</script>

<template>
  <div class="pa-4">
    <VRow>
      <VCol cols="12">
        <VCard>
          <VCardTitle>
            <VIcon icon="ri-test-tube-line" size="20" class="me-2 text-primary" /> 
            Gestión de Laboratorios
          </VCardTitle>
          <VCardText>
            <VRow class="justify-space-between">
              <VCol cols="12" md="4">
                <VTextField
                  placeholder="Buscar Laboratorio"
                  density="compact"
                  prepend-inner-icon="ri-search-line"
                  v-model="buscar"
                  @keyup.enter="list()"
                />
              </VCol>
              <VCol cols="12" md="4" class="text-end">
                <VBtn color="success" @click="isAddDialog = true">
                  <VIcon end icon="ri-add-circle-fill" /> Registrar Laboratorio
                </VBtn>
              </VCol>
            </VRow> 
          </VCardText>
          <VDataTable
            :headers="headers"
            :items="list_laboratorios"
            :sort-by="[{ key: 'id', order: 'desc' }]"
            :items-per-page="10"
            :loading="isLoading"
            loading-text="Cargando laboratorios..."
            no-data-text="No hay laboratorios registrados"
            class="text-no-wrap"
            :items-per-page-text="'Laboratorios por página:'"
            :page-text="'{0}-{1} de {2}'"
            :items-per-page-options="[
              { value: 5, title: '5' },
              { value: 10, title: '10' },
              { value: 25, title: '25' },
              { value: 50, title: '50' },
              { value: -1, title: 'Todo' }
            ]"
          >
            <template #item.id="{ item }"><span class="text-h6">{{ item.id }}</span></template>
            <template #item.logo="{ item }">
              <VAvatar rounded variant="tonal" color="primary">
                <VImg v-if="item.image_path" :src="`/storage/${item.image_path}`" />
                <span v-else>{{ item.name.charAt(0) }}</span>
              </VAvatar>
            </template>
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

        <LaboratorioAddDialog v-model:isDialogVisible="isAddDialog" @addLaboratorio="list()" />
        <LaboratorioEditDialog v-if="item_edit && isEditDialog" v-model:isDialogVisible="isEditDialog" :laboratorioSelected="item_edit" @editLaboratorio="list()" />
        <LaboratorioDeleteDialog v-if="item_delete && isDeleteDialog" v-model:isDialogVisible="isDeleteDialog" :laboratorioSelected="item_delete" @deleteLaboratorio="list()" />
      </VCol>
    </VRow>
  </div>
</template>
