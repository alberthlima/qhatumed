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
  <div class="pa-2 pa-sm-4">
    <VRow v-if="isLoading">
        <VCol cols="12" class="text-center py-10"><VProgressCircular indeterminate color="primary" /></VCol>
    </VRow>
    <VRow v-else-if="purchase">
      <VCol cols="12" md="10" class="mx-auto px-2 px-sm-3">
        <VCard>
          <VCardTitle class="py-4 d-flex flex-column flex-sm-row justify-space-between align-start align-sm-center gap-2" :class="purchase.status === 'COMPLETED' ? 'bg-primary' : 'bg-error'">
            <div class="text-white text-subtitle-1 text-sm-h6">
                <VIcon icon="ri-file-text-line" class="me-2" />
                VISOR DE COMPRA (I-{{ purchase.id }})
            </div>
            <VChip size="small" variant="elevated" :color="purchase.status === 'COMPLETED' ? 'success' : 'white'">ESTADO: {{ purchase.status }}</VChip>
          </VCardTitle>
          <VCardText class="pt-4 pt-sm-6 px-3 px-sm-6">
            <VRow>
              <VCol cols="12" md="4"><strong>Proveedor:</strong> {{ purchase.supplier?.name }}</VCol>
              <VCol cols="12" md="4"><strong>Fecha Ingreso:</strong> {{ purchase.purchase_date }}</VCol>
              <VCol cols="12" md="4"><strong>Factura Física:</strong> {{ purchase.invoice_number || 'S/N' }}</VCol>
            </VRow>
            
            <VDivider class="my-6" />
            <h6 class="text-h6 mb-4 text-primary">Detalle Físico de Lotes (FEFO)</h6>
            
            <div class="d-block d-sm-none">
              <div v-for="(item, idx) in purchase.items" :key="'mobile-' + item.id" class="border rounded mb-4 overflow-hidden bg-white elevation-1">
                <div class="bg-grey-lighten-4 px-3 py-2 d-flex justify-space-between align-center border-b">
                  <span class="font-weight-bold text-caption text-primary">#{{ idx + 1 }} - Producto</span>
                  <VChip size="small" :color="item.current_stock < item.quantity ? 'warning' : 'success'">
                    {{ item.current_stock }} unds
                  </VChip>
                </div>
                <div class="px-3 py-3">
                  <div class="font-weight-bold text-subtitle-2 mb-3">{{ item.product?.name }}</div>
                  <VRow density="compact">
                    <VCol cols="6">
                      <div class="text-caption text-medium-emphasis">Lote</div>
                      <div class="font-weight-medium">{{ item.batch || 'N/A' }}</div>
                    </VCol>
                    <VCol cols="6">
                      <div class="text-caption text-medium-emphasis">Vencimiento</div>
                      <div :class="(new Date(item.expiration_date) < new Date()) ? 'text-error font-weight-bold' : 'font-weight-medium'">{{ item.expiration_date }}</div>
                    </VCol>
                    <VCol cols="6">
                      <div class="text-caption text-medium-emphasis">Cant. Original</div>
                      <div class="font-weight-black">{{ item.quantity }}</div>
                    </VCol>
                    <VCol cols="6">
                      <div class="text-caption text-medium-emphasis">Costo C/U</div>
                      <div class="font-weight-medium">Bs. {{ Number(item.unit_cost).toFixed(2) }}</div>
                    </VCol>
                  </VRow>
                </div>
                <div class="bg-green-lighten-5 px-3 py-2 d-flex justify-space-between align-center border-t">
                  <span class="text-caption font-weight-bold">Subtotal:</span>
                  <span class="font-weight-black text-green-darken-3">Bs. {{ (item.quantity * item.unit_cost).toFixed(2) }}</span>
                </div>
              </div>
            </div>

            <VTable density="compact" class="border rounded d-none d-sm-table">
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
            
            <div class="text-start text-sm-end mt-4 text-h6 text-sm-h5 text-primary pb-6 border-b">
                Total Ingresado: <strong>Bs. {{ Number(purchase.total_amount).toFixed(2) }}</strong>
            </div>

            <div class="d-flex flex-column flex-sm-row justify-space-between align-stretch align-sm-center gap-3 mt-6">
                <div class="d-flex flex-column flex-sm-row gap-2">
                  <VBtn color="secondary" variant="tonal" class="mobile-full-btn" @click="router.push('/compras')">
                    <VIcon start icon="ri-arrow-left-line" /> Atrás
                  </VBtn>
                  <VBtn color="info" variant="outlined" class="mobile-full-btn" @click="downloadPdf">
                    <VIcon start icon="ri-printer-line" /> Imprimir Comprobante
                  </VBtn>
                </div>
                <VBtn color="error" variant="flat" class="mobile-full-btn" v-if="purchase.status === 'COMPLETED'" @click="isAnnulDialogVisible = true">
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

<style scoped>
.mobile-full-btn {
  inline-size: 100%;
}

@media (min-width: 600px) {
  .mobile-full-btn {
    inline-size: auto;
  }
}
</style>
