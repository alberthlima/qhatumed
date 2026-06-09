<script setup>
import { useRouter } from 'vue-router'

const router = useRouter()
const isLoading = ref(false)
const list_compras = ref([])

const headers = [
  { title: '#', key: 'index', sortable: false },
  { title: 'Fecha', key: 'purchase_date' },
  { title: 'Proveedor', key: 'proveedor' },
  { title: 'N° Factura', key: 'invoice_number' },
  { title: 'Responsable', key: 'responsable' },
  { title: 'Total Compra', key: 'total_amount' },
  { title: 'Estado', key: 'status' },
  { title: 'Acciones', key: 'action', sortable: false, align: 'end' },
]

const list = async () => {
  try {
    isLoading.value = true
    const response = await $api('/v1/purchases', { method: 'GET' })
    list_compras.value = response.data?.data || response.data || response || []
  } catch (error) {
    console.error(error)
  } finally {
    isLoading.value = false
  }
}

const goToAdd = () => router.push('/compras/crear')
const goToView = (item) => router.push(`/compras/ver/${item.id}`)
const downloadPdf = (purchase) => {
  const baseUrl = import.meta.env.VITE_API_BASE_URL || '/api'
  let url = baseUrl
  if (url.startsWith('http')) {
    url = `${url.replace(/\/$/, '')}/v1/purchases/${purchase.id}/pdf`
  } else {
    url = `${window.location.origin}${url.replace(/\/$/, '')}/v1/purchases/${purchase.id}/pdf`
  }
  window.open(url, '_blank')
}

onMounted(() => {
  list()
})

definePage({ meta: { permission: 'all' } }); // En produccion usar permiso 'listar_ingreso'
</script>

<template>
  <div class="pa-4">
    <VCard>
      <VCardTitle class="py-4 d-flex align-center">
        <VIcon icon="ri-truck-line" size="24" class="me-2 text-primary" /> 
        Historial de Compras (Ingresos)
      </VCardTitle>
      
      <VCardText>
        <div class="d-flex justify-end mb-4">
          <VBtn color="primary" @click="goToAdd">
            <VIcon icon="ri-add-line" start /> Registrar Nuevo Ingreso
          </VBtn>
        </div>
        
        <VDataTable :headers="headers" :items="list_compras" :loading="isLoading" class="text-no-wrap">
          <template #item.index="{ index }">
            <span class="font-weight-bold text-caption text-medium-emphasis">{{ index + 1 }}</span>
          </template>

          <template #item.purchase_date="{ item }">
            <span class="text-subtitle-2">{{ item.purchase_date }}</span>
          </template>
          
          <template #item.proveedor="{ item }">
            <span class="font-weight-medium">{{ item.supplier?.name || 'S/N' }}</span>
          </template>

          <template #item.responsable="{ item }">
            <span>{{ item.user?.name || 'Sistema' }}</span>
          </template>
          
          <template #item.total_amount="{ item }">
            <span class="font-weight-bold text-success">Bs. {{ Number(item.total_amount).toFixed(2) }}</span>
          </template>
          
          <template #item.status="{ item }">
            <VChip size="small" :color="item.status === 'COMPLETED' ? 'success' : 'error'">
              {{ item.status === 'COMPLETED' ? 'Completado' : 'Anulado' }}
            </VChip>
          </template>
          
          <template #item.action="{ item }">
            <div class="d-flex gap-1 justify-end">
              <IconBtn size="small" color="primary" variant="tonal" @click="goToView(item)" title="Ver Detalle">
                <VIcon icon="ri-eye-line" size="22" />
              </IconBtn>
              <IconBtn size="small" color="error" variant="tonal" @click="downloadPdf(item)" title="Ver PDF / Nota de Ingreso">
                <VIcon icon="ri-file-pdf-line" size="22" />
              </IconBtn>
            </div>
          </template>
        </VDataTable>
      </VCardText>
    </VCard>
  </div>
</template>
