<script setup>
import { useRouter } from 'vue-router'
import ProductoDeleteDialog from '@/components/system/productos/ProductoDeleteDialog.vue'

const router = useRouter()

const headers = [
  { title: 'ID', key: 'id' },
  { title: 'Imagen', key: 'image', sortable: false },
  { title: 'Código', key: 'barcode' },
  { title: 'Nombre del Producto', key: 'name' },
  { title: 'Laboratorio', key: 'laboratory_id' },
  { title: 'P. Venta', key: 'sale_price' },
  { title: 'Stock', key: 'stock' },
  { title: 'Acciones', key: 'action', sortable: false },
]

const isDeleteDialog = ref(false)
const isLoading = ref(false)
const list_productos = ref([])
const buscar = ref('')
const item_delete = ref(null)

// Server side pagination & sorting refs
const itemsPerPage = ref(10)
const totalItems = ref(0)
const currentPage = ref(1)
const sortBy = ref([{ key: 'id', order: 'desc' }])

const list = async () => {
  try {
    isLoading.value = true
    
    let sortField = 'id'
    let sortOrder = 'desc'
    if (sortBy.value.length) {
      sortField = sortBy.value[0].key
      sortOrder = sortBy.value[0].order
    }

    const response = await $api("/v1/products", {
      method: "GET",
      query: {
        search: buscar.value || '',
        page: currentPage.value,
        per_page: itemsPerPage.value,
        sort_by: sortField,
        sort_order: sortOrder
      }
    })
    
    list_productos.value = response.data || response || []
    totalItems.value = response.meta?.total || response.total || 0
  } catch (error) { 
    console.error(error) 
  } finally { 
    isLoading.value = false 
  }
}

const updateOptions = (options) => {
  currentPage.value = options.page
  itemsPerPage.value = options.itemsPerPage
  sortBy.value = options.sortBy
  list()
}

const goToAdd = () => router.push('/productos/crear')
const goToEdit = (item) => router.push(`/productos/editar/${item.id}`)
const deleteItem = (item) => { isDeleteDialog.value = true; item_delete.value = item }

onMounted(() => {
  // Se cargará automáticamente mediante updateOptions en el montaje de VDataTableServer
})

watch(buscar, () => {
  currentPage.value = 1
  list()
})

definePage({ meta: { permission: 'listar_producto' } });
</script>

<template>
  <div class="pa-4">
    <VRow>
      <VCol cols="12">
        <VCard>
          <VCardTitle><VIcon icon="ri-medicine-bottle-line" size="20" class="me-2 text-primary" />Catálogo Maestro de Productos</VCardTitle>
          <VCardText>
            <VRow class="justify-space-between">
              <VCol cols="12" md="5">
                <VTextField placeholder="Escanear Código o Buscar Nombre..." density="compact" prepend-inner-icon="ri-barcode-box-line" v-model="buscar" @keyup.enter="list()" autofocus />
              </VCol>
              <VCol cols="12" md="4" class="text-end">
                <VBtn color="success" @click="goToAdd"><VIcon end icon="ri-add-circle-fill" /> Registrar Producto</VBtn>
              </VCol>
            </VRow> 
          </VCardText>
          <VDataTableServer
            v-model:items-per-page="itemsPerPage"
            v-model:page="currentPage"
            v-model:sort-by="sortBy"
            :headers="headers"
            :items="list_productos"
            :items-length="totalItems"
            :loading="isLoading"
            loading-text="Cargando productos..."
            no-data-text="No hay productos registrados"
            class="text-no-wrap"
            :items-per-page-text="'Productos por página:'"
            :page-text="'{0}-{1} de {2}'"
            :items-per-page-options="[
              { value: 5, title: '5' },
              { value: 10, title: '10' },
              { value: 25, title: '25' },
              { value: 50, title: '50' }
            ]"
            @update:options="updateOptions"
          >
            <template #item.id="{ item }">
              <span class="text-h6">{{ item.id }}</span>
            </template>
            <template #item.image="{ item }">
              <VAvatar rounded variant="tonal" color="primary">
                <VImg v-if="item.image_path" :src="`/storage/${item.image_path}`" />
                <span v-else>{{ item.name.charAt(0) }}</span>
              </VAvatar>
            </template>
            <template #item.barcode="{ item }"><span class="text-caption text-medium-emphasis">{{ item.barcode || 'S/C' }}</span></template>
            <template #item.name="{ item }"><span class="font-weight-medium">{{ item.name }}</span></template>
            <template #item.laboratory_id="{ item }"><span>{{ item.laboratory?.name || 'Gnérico' }}</span></template>
            <template #item.sale_price="{ item }"><span class="font-weight-bold text-success">BS. {{ Number(item.sale_price).toFixed(2) }}</span></template>
            <template #item.stock="{ item }">
              <VChip :color="item.stock <= 0 ? 'error' : (item.stock <= item.min_stock ? 'warning' : 'success')" size="small">
                {{ item.stock }} UNDDS
              </VChip>
            </template>
            <template #item.action="{ item }">
              <div class="d-flex gap-1">
                <IconBtn size="small" color="info" variant="tonal" @click="goToEdit(item)"><VIcon icon="ri-pencil-line" size="25" /></IconBtn>
                <IconBtn size="small" color="error" variant="tonal" @click="deleteItem(item)"><VIcon icon="ri-delete-bin-line" size="25" /></IconBtn>
              </div>
            </template>
          </VDataTableServer>
        </VCard>

        <ProductoDeleteDialog v-if="item_delete && isDeleteDialog" v-model:isDialogVisible="isDeleteDialog" :productoSelected="item_delete" @deleteProducto="list()" />
      </VCol>
    </VRow>
  </div>
</template>
