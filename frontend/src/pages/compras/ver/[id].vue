<script setup>
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()
const isLoading = ref(false)
const isAnnulLoading = ref(false)
const toast = useMaterializeToast()

const purchase = ref(null)
const isAnnulDialogVisible = ref(false)

const loadPurchase = async () => {
  try {
    isLoading.value = true
    const res = await $api(`/v1/purchases/${route.params.id}`, { method: 'GET' })
    purchase.value = res.data || res
  } catch (e) {
    console.error(e)
    toast.showError('Compra no encontrada')
  } finally {
    isLoading.value = false
  }
}

const confirmAnnul = async () => {
  isAnnulDialogVisible.value = false
  try {
    isAnnulLoading.value = true
    const res = await $api(`/v1/purchases/${purchase.value.id}/annul`, { 
        method: 'POST',
        onResponse({ response }) {
          if (response.status === 200) {
            toast.showSuccess('Stock reversado exitosamente.', 'Operación Exitosa')
            loadPurchase()
          } else {
            toast.showError(response._data?.error || 'Falló la anulación. Verifique que no se vendió el stock.', 'Error')
          }
        }
    })
  } catch (e) {
    toast.showError('Error de red', 'Error')
  } finally {
    isAnnulLoading.value = false
  }
}

const downloadPdf = () => {
  const baseUrl = import.meta.env.VITE_API_BASE_URL || '/api'
  let url = baseUrl
  if (url.startsWith('http')) {
    url = `${url.replace(/\/$/, '')}/v1/purchases/${purchase.value.id}/pdf`
  } else {
    url = `${window.location.origin}${url.replace(/\/$/, '')}/v1/purchases/${purchase.value.id}/pdf`
  }
  window.open(url, '_blank')
}

onMounted(() => {
  loadPurchase()
})
</script>

<template>
  <div class="pa-4">
    <VRow v-if="isLoading">
        <VCol cols="12" class="text-center py-10"><VProgressCircular indeterminate color="primary" /></VCol>
    </VRow>
    <VRow v-else-if="purchase">
      <VCol cols="12" md="10" class="mx-auto">
        <VCard>
          <VCardTitle class="py-4 d-flex justify-space-between align-center" :class="purchase.status === 'COMPLETED' ? 'bg-primary' : 'bg-error'">
            <div class="text-white">
                <VIcon icon="ri-file-text-line" class="me-2" />
                VISOR DE COMPRA (I-{{ purchase.id }})
            </div>
            <VChip size="small" variant="elevated" :color="purchase.status === 'COMPLETED' ? 'success' : 'white'">ESTADO: {{ purchase.status }}</VChip>
          </VCardTitle>
          <VCardText class="pt-6">
            <VRow>
              <VCol cols="12" md="4"><strong>Proveedor:</strong> {{ purchase.supplier?.name }}</VCol>
              <VCol cols="12" md="4"><strong>Fecha Ingreso:</strong> {{ purchase.purchase_date }}</VCol>
              <VCol cols="12" md="4"><strong>Factura Física:</strong> {{ purchase.invoice_number || 'S/N' }}</VCol>
            </VRow>
            
            <VDivider class="my-6" />
            <h6 class="text-h6 mb-4 text-primary">Detalle Físico de Lotes (FEFO)</h6>
            
            <VTable density="compact" class="border rounded">
              <thead class="bg-grey-lighten-3">
                <tr>
                  <th class="font-weight-bold text-center" width="5%">#</th>
                  <th class="font-weight-bold">Producto / Medicamento</th>
                  <th class="font-weight-bold">Lote</th>
                  <th class="font-weight-bold">F. Vencimiento</th>
                  <th class="font-weight-bold text-center">Cant. Original</th>
                  <th class="font-weight-bold text-center">Stock Físico (Activo)</th>
                  <th class="font-weight-bold text-right">Costo C/U</th>
                  <th class="font-weight-bold text-right">Subtotal</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, idx) in purchase.items" :key="item.id">
                  <td class="text-center font-weight-bold">{{ idx + 1 }}</td>
                  <td class="font-weight-medium">{{ item.product?.name }}</td>
                  <td>{{ item.batch || 'N/A' }}</td>
                  <td :class="(new Date(item.expiration_date) < new Date()) ? 'text-error font-weight-bold' : ''">{{ item.expiration_date }}</td>
                  <td class="text-center font-weight-black">{{ item.quantity }}</td>
                  <td class="text-center">
                    <VChip size="small" :color="item.current_stock < item.quantity ? 'warning' : 'success'">
                        {{ item.current_stock }} unds
                    </VChip>
                  </td>
                  <td class="text-right">Bs. {{ Number(item.unit_cost).toFixed(2) }}</td>
                  <td class="text-right bg-green-lighten-5 font-weight-bold">Bs. {{ (item.quantity * item.unit_cost).toFixed(2) }}</td>
                </tr>
              </tbody>
            </VTable>
            
            <div class="text-end mt-4 text-h5 text-primary pb-6 border-b">
                Total Ingresado: <strong>Bs. {{ Number(purchase.total_amount).toFixed(2) }}</strong>
            </div>

            <div class="d-flex justify-space-between align-center mt-6">
                <div>
                  <VBtn color="secondary" variant="tonal" class="me-2" @click="router.push('/compras')">
                    <VIcon start icon="ri-arrow-left-line" /> Atrás
                  </VBtn>
                  <VBtn color="info" variant="outlined" @click="downloadPdf">
                    <VIcon start icon="ri-printer-line" /> Imprimir Comprobante
                  </VBtn>
                </div>
                <VBtn color="error" variant="flat" v-if="purchase.status === 'COMPLETED'" @click="isAnnulDialogVisible = true">
                    <VIcon start icon="ri-close-circle-fill"/> Anular Ingreso Contable
                </VBtn>
            </div>

            <!-- Diálogo de Confirmación de Anulación -->
            <VDialog v-model="isAnnulDialogVisible" max-width="500">
              <VCard>
                <VCardTitle class="text-h6 bg-error text-white py-3">
                  Confirmar Anulación
                </VCardTitle>
                <VCardText class="pt-4">
                  ¿Está absolutamente seguro de ANULAR esta compra? El stock ingresado se restará físicamente de la farmacia.
                </VCardText>
                <VCardActions class="pb-4 px-4 justify-end gap-2">
                  <VBtn color="secondary" variant="outlined" @click="isAnnulDialogVisible = false">
                    Cancelar
                  </VBtn>
                  <VBtn color="error" variant="flat" :loading="isAnnulLoading" @click="confirmAnnul">
                    Sí, Anular
                  </VBtn>
                </VCardActions>
              </VCard>
            </VDialog>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
