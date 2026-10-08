<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'

const toast = useMaterializeToast()
const route = useRoute()

const sales = ref([])
const isLoading = ref(false)
const searchQuery = ref('')
const isAnnulDialog = ref(false)
const selectedSale = ref(null)
const annulReason = ref(1) // 1: Factura mal emitida

const isOnlyInvoices = computed(() => route.query.type === 'invoices' || route.name === 'facturas' || route.path.includes('/facturas'))

// Filtros para Reporte de Facturación SIAT
const filterDateFrom = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10)) // Primer día del mes actual
const filterDateTo = ref(new Date().toISOString().slice(0, 10)) // Hoy
const filterCustomer = ref(null) // null = Todos
const customersList = ref([])

const fetchCustomersList = async () => {
  try {
    const response = await $api('/v1/customers')
    customersList.value = response.data || response || []
  } catch (error) {
    console.error('Error fetching customers:', error)
  }
}

const displaySales = computed(() => {
  let list = sales.value
  
  if (isOnlyInvoices.value) {
    // 1. Filtrar solo facturas (que tengan cuf)
    list = list.filter(s => !!s.cuf)
    
    // 2. Filtro Fecha Desde
    if (filterDateFrom.value) {
      list = list.filter(s => {
        const sDate = s.sale_date.substring(0, 10)
        return sDate >= filterDateFrom.value
      })
    }
    
    // 3. Filtro Fecha Hasta
    if (filterDateTo.value) {
      list = list.filter(s => {
        const sDate = s.sale_date.substring(0, 10)
        return sDate <= filterDateTo.value
      })
    }
    
    // 4. Filtro de Cliente
    if (filterCustomer.value) {
      list = list.filter(s => s.customer_id === filterCustomer.value)
    }
  }
  
  return list
})

// Resumen del Reporte Financiero (solo si está en modo facturas)
const reportTotals = computed(() => {
  if (!isOnlyInvoices.value) return null
  
  const list = displaySales.value
  const activeList = list.filter(s => s.status === 'COMPLETED')
  const annulledList = list.filter(s => s.status === 'ANNULLED')
  
  const totalAmount = activeList.reduce((sum, s) => sum + Number(s.total_amount), 0)
  const annulledAmount = annulledList.reduce((sum, s) => sum + Number(s.total_amount), 0)
  
  return {
    count: list.length,
    activeCount: activeList.length,
    annulledCount: annulledList.length,
    totalAmount,
    annulledAmount
  }
})

const headers = computed(() => {
  const base = [
    { title: 'ID', key: 'id' },
    { title: 'Fecha', key: 'sale_date' },
    { title: 'Cliente', key: 'business_name' },
  ]
  
  if (!isOnlyInvoices.value) {
    base.push({ title: 'Tipo', key: 'cuf', align: 'center', sortable: false })
  }
  
  base.push(
    { title: isOnlyInvoices.value ? 'Nro Factura' : 'Nro Factura / Recibo', key: 'invoice_number' },
    { title: 'Total (Bs)', key: 'total_amount', align: 'end' },
    { title: 'Estado', key: 'status' },
    { title: 'Acciones', key: 'actions', sortable: false, align: 'center' }
  )
  
  return base
})

const annulReasons = [
  { title: 'Factura mal emitida', value: 1 },
  { title: 'Nota de Crédito-Débito mal emitida', value: 2 }, // No aplica aquí pero es parte del catálogo
  { title: 'Datos de emisión incorrectos', value: 3 },
  { title: 'Devolución total de mercadería', value: 4 }
]

const fetchSales = async () => {
  try {
    isLoading.value = true
    const response = await $api('/v1/sales?per_page=50')
    sales.value = response.data || response || []
  } catch (error) {
    console.error('Error fetching sales:', error)
  } finally {
    isLoading.value = false
  }
}

const printSaleThermal = (sale) => {
  const url = `${window.location.origin}/api/v1/sales/${sale.id}/print/thermal`
  window.open(url, '_blank')
}

const printSaleLetter = (sale) => {
  const url = `${window.location.origin}/api/v1/sales/${sale.id}/print/letter`
  window.open(url, '_blank')
}

// Visor de Detalles
const isDetailDialog = ref(false)
const detailedSale = ref(null)
const isDetailLoading = ref(false)

const showSaleDetails = async (sale) => {
  try {
    isDetailLoading.value = true
    isDetailDialog.value = true
    const response = await $api(`/v1/sales/${sale.id}`)
    detailedSale.value = response.data || response
  } catch (error) {
    toast.showError('No se pudieron obtener los detalles de la venta', 'Error')
    isDetailDialog.value = false
  } finally {
    isDetailLoading.value = false
  }
}

// Envío WhatsApp
const isEmailDialog = ref(false)
const emailTarget = ref('')
const selectedEmailSale = ref(null)

const openEmailDialog = (sale) => {
  selectedEmailSale.value = sale
  emailTarget.value = sale.customer?.email || ''
  isEmailDialog.value = true
}

const sendEmail = async () => {
  if (!emailTarget.value) return toast.showWarning('Ingrese un correo válido', 'Atención')
  try {
    isLoading.value = true
    const response = await $api(`/v1/sales/${selectedEmailSale.value.id}/send-email`, {
      method: 'POST',
      body: { email: emailTarget.value }
    })
    toast.showSuccess(response.message || 'Correo enviado exitosamente', 'Éxito')
    isEmailDialog.value = false
  } catch (err) {
    toast.showError(err.response?._data?.error || 'Error al enviar correo', 'Error')
  } finally {
    isLoading.value = false
  }
}

const isWhatsAppDialog = ref(false)
const whatsappPhone = ref('')
const selectedWhatsAppSale = ref(null)

const openWhatsAppDialog = (sale) => {
  selectedWhatsAppSale.value = sale
  whatsappPhone.value = sale.customer?.phone || ''
  isWhatsAppDialog.value = true
}

const sendWhatsApp = () => {
  if (!whatsappPhone.value) {
    toast.showWarning('Por favor ingrese un número de teléfono', 'Teléfono requerido')
    return
  }
  
  const cleanPhone = whatsappPhone.value.replace(/\D/g, '')
  const sale = selectedWhatsAppSale.value
  const docType = sale.cuf ? 'Factura' : 'Recibo'
  const docNumber = sale.invoice_number || sale.id
  const url = `${window.location.origin}/api/v1/sales/${sale.id}/print/thermal`
  
  const text = `*FARMACIA MISALUD*\nEstimado(a) *${sale.business_name || 'Cliente'}*, adjuntamos el detalle de su compra.\n\n*Tipo:* ${docType}\n*Nro Comprobante:* ${docNumber}\n*Total:* Bs. ${Number(sale.total_amount).toFixed(2)}\n\nDescargue su comprobante aquí:\n${url}\n\n¡Gracias por su preferencia!`
  
  const whatsappUrl = `https://api.whatsapp.com/send?phone=${cleanPhone}&text=${encodeURIComponent(text)}`
  window.open(whatsappUrl, '_blank')
  isWhatsAppDialog.value = false
}

// Anulación Condicional
const isSimpleAnnulDialog = ref(false)

const openAnnulDialog = (sale) => {
  selectedSale.value = sale
  if (sale.cuf) {
    isAnnulDialog.value = true
  } else {
    isSimpleAnnulDialog.value = true
  }
}

const confirmAnnul = async () => {
  if (!selectedSale.value) return
  
  try {
    isLoading.value = true
    await $api(`/v1/sales/${selectedSale.value.id}/annul`, {
      method: 'POST',
      body: { reason_code: annulReason.value }
    })
    
    isAnnulDialog.value = false
    fetchSales() // Refrescar lista
    toast.showSuccess('Factura anulada en SIAT correctamente', 'Éxito')
  } catch (error) {
    const msg = error.response?._data?.error || 'Error al anular'
    toast.showError(msg, 'Error al Anular')
  } finally {
    isLoading.value = false
  }
}

const confirmSimpleAnnul = async () => {
  if (!selectedSale.value) return
  try {
    isLoading.value = true
    await $api(`/v1/sales/${selectedSale.value.id}/annul`, {
      method: 'POST',
      body: { reason_code: 1 } // Motivo por defecto para notas de venta
    })
    isSimpleAnnulDialog.value = false
    fetchSales()
    toast.showSuccess('Recibo anulado correctamente', 'Éxito')
  } catch (error) {
    const msg = error.response?._data?.error || 'Error al anular'
    toast.showError(msg, 'Error')
  } finally {
    isLoading.value = false
  }
}

// Reversión de Anulación (RND 102100000011 Art. 45)
const isRevertDialog = ref(false)
const selectedRevertSale = ref(null)
const isReverting = ref(false)

const openRevertDialog = (sale) => {
  selectedRevertSale.value = sale
  isRevertDialog.value = true
}

const confirmRevert = async () => {
  if (!selectedRevertSale.value) return
  isReverting.value = true
  try {
    const res = await $api(`/v1/sales/${selectedRevertSale.value.id}/revert`, {
      method: 'POST'
    })
    isRevertDialog.value = false
    fetchSales()
    toast.showSuccess(res.message || 'Anulación revertida exitosamente. La factura vuelve a estado VALIDADA.', 'Reversión Exitosa')
  } catch (error) {
    const msg = error.response?._data?.error || 'Error al revertir la anulación'
    toast.showError(msg, 'Error en Reversión')
  } finally {
    isReverting.value = false
  }
}

// Verificación de Estado SIAT
const isVerifyDialog = ref(false)
const verifyData = ref(null)
const isVerifying = ref(false)

const verificarEstadoSiat = async (sale) => {
  isVerifying.value = true
  try {
    const res = await $api(`/v1/sales/${sale.id}/verificar-siat`, {
      method: 'POST'
    })
    verifyData.value = {
      sale,
      siat: res.data || res
    }
    isVerifyDialog.value = true
  } catch (error) {
    const msg = error.response?._data?.error || 'Error al verificar con SIAT'
    toast.showError(msg, 'Verificación SIAT')
  } finally {
    isVerifying.value = false
  }
}
onMounted(() => {
  fetchSales()
  fetchCustomersList()
})
</script>

<template>
  <VCard class="ma-4 rounded-xl border" elevation="0">
    <VCardTitle class="pa-6 d-flex align-center justify-space-between bg-primary-lighten-5">
      <div class="d-flex align-center">
        <VIcon :icon="isOnlyInvoices ? 'ri-file-list-line' : 'ri-history-line'" class="mr-3 text-primary" size="32"/>
        <div>
          <div class="text-h5 font-weight-bold">
            {{ isOnlyInvoices ? 'Lista de Facturas Emitidas (SIAT)' : 'Historial de Ventas y Recibos' }}
          </div>
          <div class="text-caption">
            {{ isOnlyInvoices ? 'Consulta y anulación de facturas oficiales del SIN' : 'Gestión de comprobantes y anulación local de recibos' }}
          </div>
        </div>
      </div>
      <VTextField
        v-model="searchQuery"
        :placeholder="isOnlyInvoices ? 'Buscar por cliente o Nro Factura...' : 'Buscar por cliente o Nro Comprobante...'"
        density="compact"
        variant="outlined"
        hide-details
        prepend-inner-icon="ri-search-line"
        class="max-width-300"
      />
    </VCardTitle>

    <!-- PANEL DE REPORTE DE FACTURACIÓN (Solo modo SIAT) -->
    <div v-if="isOnlyInvoices && reportTotals" class="px-6 pt-6 pb-0">
      <!-- Tarjetas Estadísticas -->
      <VRow class="mb-4">
        <VCol cols="12" sm="4">
          <VCard variant="flat" class="pa-4 rounded-xl border bg-success-lighten-5 border-success-lighten-3 d-flex align-center">
            <VAvatar color="success" class="me-3" size="48" rounded>
              <VIcon icon="ri-money-dollar-circle-line" size="28" class="text-white" />
            </VAvatar>
            <div>
              <div class="text-caption text-success font-weight-bold text-uppercase">Total Neto Facturado</div>
              <div class="text-h5 font-weight-black text-success-darken-2">
                Bs. {{ Number(reportTotals.totalAmount).toFixed(2) }}
              </div>
            </div>
          </VCard>
        </VCol>
        
        <VCol cols="12" sm="4">
          <VCard variant="flat" class="pa-4 rounded-xl border bg-info-lighten-5 border-info-lighten-3 d-flex align-center">
            <VAvatar color="info" class="me-3" size="48" rounded>
              <VIcon icon="ri-file-text-line" size="28" class="text-white" />
            </VAvatar>
            <div>
              <div class="text-caption text-info font-weight-bold text-uppercase">Facturas Emitidas</div>
              <div class="text-h5 font-weight-black text-info-darken-2">
                {{ reportTotals.activeCount }} <span class="text-caption text-medium-emphasis">Válidas</span>
                <span class="text-body-2 font-weight-bold mx-1">/</span>
                {{ reportTotals.count }} <span class="text-caption text-medium-emphasis">Total</span>
              </div>
            </div>
          </VCard>
        </VCol>
        
        <VCol cols="12" sm="4">
          <VCard variant="flat" class="pa-4 rounded-xl border bg-error-lighten-5 border-error-lighten-3 d-flex align-center">
            <VAvatar color="error" class="me-3" size="48" rounded>
              <VIcon icon="ri-close-circle-line" size="28" class="text-white" />
            </VAvatar>
            <div>
              <div class="text-caption text-error font-weight-bold text-uppercase">Monto Total Anulado</div>
              <div class="text-h5 font-weight-black text-error-darken-2">
                Bs. {{ Number(reportTotals.annulledAmount).toFixed(2) }}
              </div>
            </div>
          </VCard>
        </VCol>
      </VRow>

      <!-- Panel de Filtros -->
      <VCard variant="outlined" class="pa-4 rounded-xl mb-4 bg-grey-lighten-5" style="border-style: dashed !important;">
        <div class="text-subtitle-2 font-weight-bold mb-3 d-flex align-center">
          <VIcon icon="ri-filter-2-line" class="me-2 text-primary" />
          Filtros del Reporte de Facturación
        </div>
        <VRow>
          <VCol cols="12" sm="4" class="py-1">
            <VTextField
              v-model="filterDateFrom"
              type="date"
              label="Fecha Desde"
              density="comfortable"
              variant="outlined"
              prepend-inner-icon="ri-calendar-line"
              hide-details
            />
          </VCol>
          <VCol cols="12" sm="4" class="py-1">
            <VTextField
              v-model="filterDateTo"
              type="date"
              label="Fecha Hasta"
              density="comfortable"
              variant="outlined"
              prepend-inner-icon="ri-calendar-line"
              hide-details
            />
          </VCol>
          <VCol cols="12" sm="4" class="py-1">
            <VAutocomplete
              v-model="filterCustomer"
              :items="customersList"
              item-title="first_name"
              item-value="id"
              label="Cliente (Filtrar o buscar)"
              placeholder="Todos los clientes"
              density="comfortable"
              variant="outlined"
              clearable
              prepend-inner-icon="ri-user-search-line"
              hide-details
            />
          </VCol>
        </VRow>
      </VCard>
    </div>

    <VDataTable
      :headers="headers"
      :items="displaySales"
      :search="searchQuery"
      :loading="isLoading"
      :loading-text="isOnlyInvoices ? 'Cargando facturas emitidas...' : 'Cargando historial de ventas...'"
      :no-data-text="isOnlyInvoices ? 'No hay facturas emitidas registradas' : 'No hay ventas ni recibos registrados'"
      class="text-no-wrap"
      :items-per-page-text="'Ventas por página:'"
      :page-text="'{0}-{1} de {2}'"
      :items-per-page-options="[
        { value: 5, title: '5' },
        { value: 10, title: '10' },
        { value: 25, title: '25' },
        { value: 50, title: '50' },
        { value: -1, title: 'Todo' }
      ]"
    >
      <template #item.sale_date="{ item }">
        {{ new Date(item.sale_date).toLocaleString() }}
      </template>

      <template #item.status="{ item }">
        <VChip
          :color="item.status === 'COMPLETED' ? 'success' : 'error'"
          size="small"
          class="font-weight-bold"
          variant="tonal"
        >
          {{ item.status === 'COMPLETED' ? 'Válida' : 'Anulada' }}
        </VChip>
      </template>

      <template #item.cuf="{ item }">
        <VChip
          :color="item.cuf ? 'primary' : 'secondary'"
          size="small"
          class="font-weight-bold text-uppercase"
          variant="tonal"
        >
          {{ item.cuf ? 'Factura' : 'Recibo' }}
        </VChip>
      </template>

      <template #item.invoice_number="{ item }">
        <span class="font-weight-bold text-primary" v-if="item.cuf">
          Nro {{ item.invoice_number }}
        </span>
        <span class="text-medium-emphasis" v-else>
          Recibo #{{ item.id }}
        </span>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex gap-2 justify-center">
          <!-- Ver Detalles -->
          <VBtn
            icon="ri-eye-line"
            variant="tonal"
            color="info"
            size="small"
            @click="showSaleDetails(item)"
            title="Ver Detalle"
          />

          <!-- Imprimir Térmico Rápido (Factura o Recibo) -->
          <VBtn
            v-if="item.status !== 'ANNULLED'"
            icon="ri-printer-line"
            variant="tonal"
            color="primary"
            size="small"
            @click="printSaleThermal(item)"
            title="Imprimir Térmico"
          />

                    <!-- Email Factura Oficial -->
          <VBtn
            v-if="item.cuf && item.status !== 'ANNULLED'"
            icon="ri-mail-send-line"
            variant="tonal"
            color="warning"
            size="small"
            @click="openEmailDialog(item)"
            title="Enviar Factura por Correo"
          />

          <!-- WhatsApp -->
          <VBtn
            v-if="item.status !== 'ANNULLED'"
            icon="ri-whatsapp-line"
            variant="tonal"
            color="success"
            size="small"
            @click="openWhatsAppDialog(item)"
            title="Enviar por WhatsApp"
          />
          
          <!-- Anular (Condicional SIAT/Simple) -->
          <VBtn
            v-if="item.status === 'COMPLETED'"
            icon="ri-close-circle-line"
            variant="tonal"
            color="error"
            size="small"
            @click="openAnnulDialog(item)"
            :title="item.cuf ? 'Anular Factura SIAT' : 'Anular Recibo'"
          />
          <!-- Revertir Anulación (RND 102100000011 Art. 45) -->
          <VBtn
            v-if="item.status === 'ANNULLED'"
            icon="ri-restart-line"
            variant="tonal"
            color="warning"
            size="small"
            @click="openRevertDialog(item)"
            :title="item.cuf ? 'Revertir Anulación en SIAT' : 'Reactivar Recibo'"
          />
          <!-- Verificar Estado SIAT -->
          <VBtn
            v-if="item.cuf"
            icon="ri-shield-check-line"
            variant="tonal"
            color="secondary"
            size="small"
            @click="verificarEstadoSiat(item)"
            title="Verificar Estado en SIAT"
          />
        </div>
      </template>
    </VDataTable>

    <!-- MODAL ANULACIÓN -->
    <VDialog v-model="isAnnulDialog" max-width="550">
      <VCard class="pa-sm-11 pa-3 text-center">
        <!-- Close button -->
        <DialogCloseBtn variant="text" size="default" @click="isAnnulDialog = false" />
        <VCardText class="pt-5">
          <div class="text-center pb-6">
            <VIcon icon="ri-error-warning-line" color="error" size="48" class="mb-3"/>
            <h4 class="text-h4 font-weight-bold mb-2">Anular Factura SIAT</h4>
            <p class="text-body-2 text-medium-emphasis">Envía solicitud anulacionFactura ante el SIN (RND 102100000011 Art. 44).</p>
          </div>
          
          <div class="text-body-1 mb-4">
            Está a punto de anular la factura <strong>Nro {{ selectedSale?.invoice_number }}</strong> de la venta ID #{{ selectedSale?.id }}.
          </div>

          <VSelect
            v-model="annulReason"
            :items="annulReasons"
            label="Motivo de Anulación *"
            variant="outlined"
            density="comfortable"
            class="mt-2 text-left"
          />

          <div class="d-flex flex-wrap justify-center gap-4 mt-6">
            <VBtn 
              color="error" 
              class="px-8"
              :loading="isLoading" 
              :disabled="isLoading"
              @click="confirmAnnul"
            >
              <VIcon start icon="ri-checkbox-circle-line" />
              <span class="font-weight-bold">Confirmar Anulación</span>
            </VBtn>

            <VBtn 
              color="secondary"
              class="px-8"
              :disabled="isLoading" 
              @click="isAnnulDialog = false"
            >
              <VIcon start icon="ri-arrow-left-line" />
              <span class="font-weight-bold">Cancelar</span>
            </VBtn>
          </div>
        </VCardText>
      </VCard>
    </VDialog>

    <!-- MODAL REVERSIÓN DE ANULACIÓN (RND 102100000011 Art. 45) -->
    <VDialog v-model="isRevertDialog" max-width="550">
      <VCard class="pa-sm-8 pa-4 text-center rounded-xl">
        <DialogCloseBtn variant="text" size="default" @click="isRevertDialog = false" />
        <VCardText class="pt-2">
          <div class="text-center pb-4">
            <VIcon icon="ri-restart-line" color="warning" size="48" class="mb-2"/>
            <h4 class="text-h5 font-weight-bold mb-1">Revertir Anulación de Factura</h4>
            <p class="text-body-2 text-medium-emphasis">Servicio de Impuestos Nacionales (RND 102100000011 Art. 45)</p>
          </div>
          
          <VAlert type="warning" variant="tonal" density="compact" class="text-caption mb-4 text-start">
            <strong>Atención:</strong> Esta operación enviará una solicitud SOAP <code>reversionAnulacionFactura</code> ante el SIN para restablecer la factura a estado <strong>VALIDADA</strong> y restaurará la deducción de inventario.
          </VAlert>

          <div class="text-body-1 mb-4" v-if="selectedRevertSale">
            ¿Confirmar la reversión de anulación para la Factura <strong>Nro {{ selectedRevertSale.invoice_number }}</strong> (Venta #{{ selectedRevertSale.id }})?
          </div>

          <div class="d-flex flex-wrap justify-center gap-4 mt-6">
            <VBtn 
              color="warning" 
              class="px-8 font-weight-bold"
              :loading="isReverting" 
              :disabled="isReverting"
              @click="confirmRevert"
            >
              <VIcon start icon="ri-check-line" />
              Revertir Anulación en SIN
            </VBtn>

            <VBtn 
              color="secondary"
              class="px-8 font-weight-bold"
              :disabled="isReverting" 
              @click="isRevertDialog = false"
            >
              Cancelar
            </VBtn>
          </div>
        </VCardText>
      </VCard>
    </VDialog>

    <!-- MODAL VERIFICAR ESTADO EN SIAT -->
    <VDialog v-model="isVerifyDialog" max-width="500">
      <VCard class="pa-4 rounded-xl" v-if="verifyData">
        <DialogCloseBtn variant="text" size="default" @click="isVerifyDialog = false" />
        <VCardTitle class="d-flex align-center gap-2">
          <VIcon icon="ri-shield-check-line" color="primary" />
          <span class="text-h6 font-weight-bold">Estado Fiscal en Impuestos (SIN)</span>
        </VCardTitle>
        <VCardText class="pt-2">
          <div class="mb-3">
            <div class="text-caption text-medium-emphasis">Factura / CUF:</div>
            <code class="text-caption font-weight-bold text-break">{{ verifyData.sale.cuf }}</code>
          </div>

          <VList density="compact" class="border rounded-lg">
            <VListItem>
              <template #title>Estado en Base de Datos Local</template>
              <template #append>
                <VChip size="small" :color="verifyData.sale.status === 'COMPLETED' ? 'success' : 'error'" class="font-weight-bold">
                  {{ verifyData.sale.status }}
                </VChip>
              </template>
            </VListItem>
            <VListItem>
              <template #title>Código Estado SIN</template>
              <template #append>
                <code class="font-weight-bold">{{ verifyData.siat?.codigoEstado || verifyData.siat?.codigoRecepcion || '908' }}</code>
              </template>
            </VListItem>
            <VListItem>
              <template #title>Descripción Estado SIN</template>
              <template #append>
                <span class="font-weight-bold text-success">
                  {{ verifyData.siat?.codigoDescripcion || (verifyData.sale.status === 'COMPLETED' ? 'VALIDADA' : 'ANULADA') }}
                </span>
              </template>
            </VListItem>
          </VList>
        </VCardText>
        <VCardActions class="justify-end">
          <VBtn color="primary" @click="isVerifyDialog = false">Cerrar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- MODAL DETALLES DE VENTA -->
    <VDialog v-model="isDetailDialog" max-width="650">
      <VCard class="rounded-xl overflow-hidden" v-if="detailedSale">
        <DialogCloseBtn variant="text" size="default" @click="isDetailDialog = false" />
        <VCardTitle class="pa-4 bg-primary text-white d-flex align-center font-weight-bold">
          <VIcon icon="ri-file-list-3-line" class="me-2" />
          Detalles de la Venta #{{ detailedSale.id }}
        </VCardTitle>
        
        <VCardText class="pa-6">
          <VRow class="mb-4">
            <VCol cols="12" sm="6" class="py-1">
              <div class="text-caption text-medium-emphasis">Fecha de Venta</div>
              <div class="text-body-1 font-weight-semibold">
                {{ new Date(detailedSale.sale_date).toLocaleString() }}
              </div>
            </VCol>
            <VCol cols="12" sm="6" class="py-1">
              <div class="text-caption text-medium-emphasis">Estado</div>
              <VChip
                :color="detailedSale.status === 'COMPLETED' ? 'success' : 'error'"
                size="small"
                class="font-weight-bold"
                variant="tonal"
              >
                {{ detailedSale.status === 'COMPLETED' ? 'Válida' : 'Anulada' }}
              </VChip>
            </VCol>
            
            <VCol cols="12" sm="6" class="py-1">
              <div class="text-caption text-medium-emphasis">Cliente</div>
              <div class="text-body-1 font-weight-semibold">
                {{ detailedSale.business_name || 'S/N' }}
              </div>
            </VCol>
            <VCol cols="12" sm="6" class="py-1">
              <div class="text-caption text-medium-emphasis">NIT / CI</div>
              <div class="text-body-1 font-weight-semibold">
                {{ detailedSale.nit_ci || '0' }}
              </div>
            </VCol>

            <VCol cols="12" sm="6" class="py-1" v-if="detailedSale.cuf">
              <div class="text-caption text-medium-emphasis font-weight-bold text-primary">Nro Factura</div>
              <div class="text-body-1 font-weight-bold text-primary">
                {{ detailedSale.invoice_number }}
              </div>
            </VCol>
            <VCol cols="12" sm="6" class="py-1" v-if="detailedSale.cuf">
              <div class="text-caption text-medium-emphasis font-weight-bold text-primary">CUF (SIAT)</div>
              <div class="text-caption text-break font-weight-semibold">
                {{ detailedSale.cuf }}
              </div>
            </VCol>
          </VRow>

          <VDivider class="my-4" />

          <div class="text-subtitle-1 font-weight-bold mb-2">Productos Vendidos</div>
          
          <VTable density="comfortable" class="border rounded-lg mb-4">
            <thead>
              <tr>
                <th class="text-left font-weight-bold">Medicamento</th>
                <th class="text-center font-weight-bold" width="10%">Cant</th>
                <th class="text-right font-weight-bold" width="20%">P. Unit (Bs)</th>
                <th class="text-right font-weight-bold" width="20%">Subtotal (Bs)</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in detailedSale.items" :key="item.id">
                <td>{{ item.product?.name || 'Producto Desconocido' }}</td>
                <td class="text-center">{{ item.quantity }}</td>
                <td class="text-right">{{ Number(item.unit_price).toFixed(2) }}</td>
                <td class="text-right font-weight-semibold">{{ Number(item.subtotal).toFixed(2) }}</td>
              </tr>
              <tr class="bg-grey-lighten-5">
                <td colspan="3" class="text-right font-weight-black text-body-1">TOTAL:</td>
                <td class="text-right font-weight-black text-body-1 text-primary">
                  Bs. {{ Number(detailedSale.total_amount).toFixed(2) }}
                </td>
              </tr>
            </tbody>
          </VTable>

          <!-- Acciones de Comprobante / WhatsApp en el Detalle -->
          <VRow class="gap-2 justify-center mt-6">
            <template v-if="detailedSale.cuf">
              <VCol cols="12" sm="4" class="py-1">
                <VBtn block color="secondary" prepend-icon="ri-receipt-line" @click="printSaleThermal(detailedSale)">
                  Factura Rollo
                </VBtn>
              </VCol>
              <VCol cols="12" sm="4" class="py-1">
                <VBtn block color="primary" prepend-icon="ri-file-text-line" @click="printSaleLetter(detailedSale)">
                  Factura Carta
                </VBtn>
              </VCol>
            </template>
            <VCol v-else cols="12" class="py-1">
              <VBtn block color="secondary" prepend-icon="ri-receipt-line" @click="printSaleThermal(detailedSale)">
                Imprimir Recibo
              </VBtn>
            </VCol>
            
            <VCol v-if="detailedSale.cuf" cols="12" sm="4" class="py-1">
              <VBtn block color="success" prepend-icon="ri-whatsapp-line" @click="openWhatsAppDialog(detailedSale)">
                Enviar WhatsApp
              </VBtn>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
      <VCard class="pa-6 text-center rounded-xl" v-else>
        <VProgressCircular indeterminate color="primary" size="40" />
        <div class="mt-2 text-medium-emphasis">Cargando detalles...</div>
      </VCard>
    </VDialog>

    <!-- MODAL ENVIAR POR WHATSAPP -->
    <VDialog v-model="isWhatsAppDialog" max-width="450">
      <VCard class="rounded-xl pa-4 text-center">
        <DialogCloseBtn variant="text" size="default" @click="isWhatsAppDialog = false" />
        <VCardText class="pt-5">
          <VIcon icon="ri-whatsapp-line" color="success" size="56" class="mb-4" />
          <h4 class="text-h4 font-weight-bold mb-2">Enviar por WhatsApp</h4>
          <p class="text-body-2 text-medium-emphasis mb-6">
            Se enviará un mensaje con el enlace de descarga del PDF de la factura/recibo.
          </p>

          <VTextField
            v-model="whatsappPhone"
            label="Número de Celular (Ej: 71234567)"
            placeholder="Ingrese el número de celular del cliente"
            variant="outlined"
            density="comfortable"
            prepend-inner-icon="ri-phone-line"
            autofocus
            class="text-left mb-6"
          />

          <div class="d-flex justify-center gap-4">
            <VBtn color="success" class="px-6 font-weight-bold" @click="sendWhatsApp">
              <VIcon start icon="ri-send-plane-line" /> Enviar
            </VBtn>
            <VBtn color="secondary" variant="tonal" class="px-6" @click="isWhatsAppDialog = false">
              Cancelar
            </VBtn>
          </div>
        </VCardText>
      </VCard>
    </VDialog>

    <!-- MODAL ENVIAR POR EMAIL -->
    <VDialog v-model="isEmailDialog" max-width="480">
      <VCard class="rounded-xl pa-4 text-center">
        <DialogCloseBtn variant="text" size="default" @click="isEmailDialog = false" />
        <VCardText class="pt-5">
          <VIcon icon="ri-mail-send-line" color="warning" size="56" class="mb-4" />
          <h4 class="text-h4 font-weight-bold mb-2">Enviar Factura por Correo</h4>
          <p class="text-body-2 text-medium-emphasis mb-6">
            Se enviará la Factura Oficial en PDF (Formato Carta) y el archivo XML firmado por el SIN directamente al cliente.
          </p>

          <VTextField
            v-model="emailTarget"
            label="Correo Electrónico del Cliente"
            placeholder="cliente@ejemplo.com"
            variant="outlined"
            density="comfortable"
            prepend-inner-icon="ri-mail-line"
            autofocus
            class="text-left mb-6"
          />

          <div class="d-flex justify-center gap-4">
            <VBtn color="warning" class="px-6 font-weight-bold text-white" :loading="isLoading" :disabled="isLoading" @click="sendEmail">
              <VIcon start icon="ri-send-plane-fill" /> Enviar Factura
            </VBtn>
            <VBtn color="secondary" variant="tonal" class="px-6" :disabled="isLoading" @click="isEmailDialog = false">
              Cancelar
            </VBtn>
          </div>
        </VCardText>
      </VCard>
    </VDialog>

    <!-- MODAL ANULACIÓN SIMPLE (RECIBOS) -->
    <VDialog v-model="isSimpleAnnulDialog" max-width="500">
      <VCard class="rounded-xl pa-4 text-center">
        <DialogCloseBtn variant="text" size="default" @click="isSimpleAnnulDialog = false" />
        <VCardText class="pt-5">
          <VIcon icon="ri-error-warning-line" color="warning" size="56" class="mb-4" />
          <h4 class="text-h4 font-weight-bold mb-2">Anular Nota de Venta</h4>
          <p class="text-body-2 text-medium-emphasis mb-6">
            Esta acción es irreversible. Se cancelará la venta localmente y el stock de los productos se devolverá a sus respectivos lotes.
          </p>

          <div class="text-body-1 mb-6">
            ¿Está seguro de que desea anular el recibo de la venta <strong>ID #{{ selectedSale?.id }}</strong> por un monto de <strong>Bs. {{ Number(selectedSale?.total_amount).toFixed(2) }}</strong>?
          </div>

          <div class="d-flex justify-center gap-4">
            <VBtn color="error" class="px-6 font-weight-bold" :loading="isLoading" :disabled="isLoading" @click="confirmSimpleAnnul">
              <VIcon start icon="ri-checkbox-circle-line" /> Confirmar Anulación
            </VBtn>
            <VBtn color="secondary" variant="tonal" class="px-6" :disabled="isLoading" @click="isSimpleAnnulDialog = false">
              Cancelar
            </VBtn>
          </div>
        </VCardText>
      </VCard>
    </VDialog>
  </VCard>
</template>

<style scoped>
.max-width-300 {
  max-width: 300px;
}
.gap-2 {
  gap: 8px;
}
</style>
