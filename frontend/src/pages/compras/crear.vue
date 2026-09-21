<script setup>
import { useRouter } from 'vue-router'
const router = useRouter()
const toast = useMaterializeToast()

// General Data
const isLoading = ref(false)
const suppliers = ref([])
const searchProducts = ref([])

// Form Data - Header
const formData = ref({
  supplier_id: null,
  reason: 'COMPRA',
  purchase_date: new Date().toISOString().slice(0,10),
  invoice_number: '',
  items: []
})

import { nextTick } from 'vue'
import { Html5Qrcode } from 'html5-qrcode'

// Scanner and Barcode Association State
const isScanning = ref(false)
const isScannerModalOpen = ref(false)
const html5QrScanner = ref(null)
const scannedBarcode = ref('')

const isAssociating = ref(false)
const associateSearchQuery = ref('')
const associateSearchLoading = ref(false)
const associateProducts = ref([])
const selectedProductForAssociation = ref(null)

const playBeep = () => {
  try {
    const audioCtx = new (window.AudioContext || window.webkitAudioContext)()
    const oscillator = audioCtx.createOscillator()
    const gainNode = audioCtx.createGain()
    
    oscillator.type = 'sine'
    oscillator.frequency.setValueAtTime(800, audioCtx.currentTime)
    gainNode.gain.setValueAtTime(0.1, audioCtx.currentTime)
    
    oscillator.connect(gainNode)
    gainNode.connect(audioCtx.destination)
    
    oscillator.start()
    oscillator.stop(audioCtx.currentTime + 0.15)
  } catch (e) {
    console.error('Error playing beep:', e)
  }
}

const startScanner = () => {
  isScannerModalOpen.value = true
  isScanning.value = true
  scannedBarcode.value = ''
  isAssociating.value = false
  selectedProductForAssociation.value = null
  
  nextTick(() => {
    const qrCodeSuccessCallback = async (decodedText) => {
      pauseScanner()
      await handleScannedBarcode(decodedText)
    }
    
    html5QrScanner.value = new Html5Qrcode("reader")
    
    html5QrScanner.value.start(
      { facingMode: "environment" },
      {
        fps: 10,
        qrbox: { width: 250, height: 150 }
      },
      qrCodeSuccessCallback,
      () => {
        // Ignorar errores de lectura parcial
      }
    ).catch((err) => {
      console.error("Error al iniciar scanner:", err)
      toast.showError("No se pudo iniciar la cámara. Verifique los permisos e HTTPS.", "Error de Cámara")
      isScannerModalOpen.value = false
      isScanning.value = false
    })
  })
}

const pauseScanner = () => {
  if (html5QrScanner.value && html5QrScanner.value.isScanning) {
    html5QrScanner.value.pause(true)
  }
  isScanning.value = false
}

const resumeScanner = () => {
  if (html5QrScanner.value) {
    html5QrScanner.value.resume()
  }
  isScanning.value = true
  scannedBarcode.value = ''
  isAssociating.value = false
  selectedProductForAssociation.value = null
}

const stopScanner = () => {
  if (html5QrScanner.value) {
    html5QrScanner.value.stop().then(() => {
      html5QrScanner.value = null
    }).catch(err => {
      console.error("Error al detener scanner:", err)
    })
  }
  isScannerModalOpen.value = false
  isScanning.value = false
  isAssociating.value = false
  scannedBarcode.value = ''
}

const addProductToPurchase = (product) => {
  const exists = formData.value.items.find(i => i.product_id === product.id)
  if (exists) {
    exists.quantity += 1
  } else {
    formData.value.items.push({
      product_id: product.id,
      name: product.name,
      batch: '',
      expiration_date: '',
      quantity: 1,
      unit_cost: product.average_cost > 0 ? product.average_cost : 0,
      sale_price: product.sale_price > 0 ? product.sale_price : 0
    })
  }
}

const handleScannedBarcode = async (barcode) => {
  scannedBarcode.value = barcode.trim()
  
  try {
    isLoading.value = true
    const response = await $api(`/v1/products?search=${scannedBarcode.value}`, { method: 'GET' })
    const productsList = response.data?.data || response.data || []

    // Buscar coincidencia en barcode principal O en barcodes alternos
    const matchedProduct = productsList.find(p =>
      p.barcode === scannedBarcode.value ||
      (p.barcodes && p.barcodes.some(b => b.barcode === scannedBarcode.value))
    )
    
    if (matchedProduct) {
      playBeep()
      addProductToPurchase(matchedProduct)
      toast.showSuccess(`Producto "${matchedProduct.name}" agregado.`, "Escaneado")
      
      setTimeout(() => {
        resumeScanner()
      }, 1000)
    } else {
      isAssociating.value = true
      associateSearchQuery.value = ''
      associateProducts.value = []
      selectedProductForAssociation.value = null
      toast.showWarning(`Código "${scannedBarcode.value}" no registrado. Vincúlelo.`, "No Registrado")
    }
  } catch (err) {
    toast.showError("Error al consultar producto.", "Error")
    resumeScanner()
  } finally {
    isLoading.value = false
  }
}

const associateBarcodeToProduct = async () => {
  if (!selectedProductForAssociation.value) {
    toast.showWarning("Seleccione un producto para asociar.", "Advertencia")
    return
  }
  
  try {
    isLoading.value = true
    const productId = selectedProductForAssociation.value.id
    const response = await $api(`/v1/products/${productId}/associate-barcode`, {
      method: 'POST',
      body: { barcode: scannedBarcode.value }
    })
    
    playBeep()
    const updatedProduct = response.data || response
    addProductToPurchase(updatedProduct)
    
    toast.showSuccess(`Código asociado a "${updatedProduct.name}"`, "Vincular Código")
    resumeScanner()
  } catch (err) {
    const errorMsg = err.response?._data?.message || err.message || "Error al asociar código."
    toast.showError(errorMsg, "Error")
  } finally {
    isLoading.value = false
  }
}

watch(associateSearchQuery, async (newVal) => {
  if (!newVal || newVal.length < 2) return
  try {
    associateSearchLoading.value = true
    const res = await $api(`/v1/products?search=${newVal}`, { method: 'GET' })
    associateProducts.value = res.data?.data || res.data || []
  } catch (e) {
  } finally {
    associateSearchLoading.value = false
  }
})

// Search Field binding
const productSearch = ref(null)
const searchLoading = ref(false)
const searchQuery = ref('')

const loadSuppliers = async () => {
  try {
    const response = await $api('/v1/suppliers', { method: 'GET' })
    suppliers.value = response.data || response || []
  } catch (e) { console.error('Error suppliers:', e) }
}

onMounted(() => {
  loadSuppliers()
})

watch(searchQuery, async (newVal) => {
  if (!newVal || newVal.length < 2) return
  try {
    searchLoading.value = true
    const res = await $api(`/v1/products?search=${newVal}`, { method: 'GET' })
    searchProducts.value = res.data?.data || res.data || []
  } catch (e) {
  } finally {
    searchLoading.value = false
  }
})

// Add item to purchase table
watch(productSearch, (selectedProduct) => {
  if (selectedProduct) {
    // Check if already in list
    const exists = formData.value.items.find(i => i.product_id === selectedProduct.id)
    if (!exists) {
      formData.value.items.push({
        product_id: selectedProduct.id,
        name: selectedProduct.name,
        batch: '',
        expiration_date: '',
        quantity: 1,
        unit_cost: selectedProduct.average_cost > 0 ? selectedProduct.average_cost : 0,
        sale_price: selectedProduct.sale_price > 0 ? selectedProduct.sale_price : 0
      })
    }
    // Clear search asynchronously
    setTimeout(() => { productSearch.value = null; searchQuery.value = '' }, 100)
  }
})

const removeItem = (index) => {
  formData.value.items.splice(index, 1)
}

const totalPurchase = computed(() => {
  return formData.value.items.reduce((acc, item) => acc + (Number(item.quantity) * Number(item.unit_cost)), 0)
})

const submitPurchase = async () => {
  if (formData.value.reason === 'COMPRA' && !formData.value.supplier_id) {
    toast.showWarning('Faltan datos: Seleccione un proveedor principal.', 'Faltan Datos')
    return
  }
  if (formData.value.items.length === 0) {
    toast.showWarning('Faltan datos: Agregue al menos un producto a la compra.', 'Faltan Datos')
    return
  }
  
  // Validate items
  for(let i=0; i<formData.value.items.length; i++) {
    const it = formData.value.items[i]
    if (it.quantity < 1 || it.unit_cost < 0) {
      toast.showError('Error: Cantidades o costos inválidos en los detalles.', 'Error')
      return;
    }
    if (!it.expiration_date) {
        toast.showWarning('Lote Incompleto: Ingrese la Fecha de Vencimiento obligatoriamente.', 'Lote Incompleto')
        return;
    }
  }

  try {
    isLoading.value = true
    const res = await $api('/v1/purchases', {
      method: 'POST',
      body: formData.value,
      onResponse({ response }) {
        if (response.status === 200 || response.status === 201) {
          toast.showSuccess('Compra Procesada: Inventario Actualizado y Lotes registrados', 'Operación Exitosa')
          router.push('/compras')
        } else {
          toast.showError('Error: ' + (response._data?.error || 'No se pudo procesar'), 'Error')
        }
      }
    })
  } catch (e) {
    toast.showError('Error interno', 'Error')
  } finally {
    isLoading.value = false
  }
}

definePage({ meta: { permission: 'listar_compra' } })
</script>

<template>
  <div class="pa-2 pa-sm-4">
    <VRow>
      <VCol cols="12" md="10" class="mx-auto px-2 px-sm-3">

        <VCard>
          <VCardTitle class="py-4 bg-primary text-white d-flex align-center flex-wrap text-subtitle-1 text-sm-h6">
            <VIcon icon="ri-truck-line" class="me-2" />
            <span>Insertar Compra / Nota de Ingreso</span>
          </VCardTitle>

          <VCardText class="pt-4 pt-sm-6 px-3 px-sm-6">
            <VForm @submit.prevent="submitPurchase">
              <!-- HEADER -->
              <VRow class="mb-4">
                <VCol cols="12" md="3">
                  <VSelect
                    v-model="formData.reason"
                    label="Motivo de Ingreso"
                    :items="[
                      { title: 'Compra Regular', value: 'COMPRA' },
                      { title: 'Inventario Inicial', value: 'INVENTARIO_INICIAL' }
                    ]"
                    item-title="title"
                    item-value="value"
                    prepend-inner-icon="ri-question-answer-line"
                    required
                  />
                </VCol>
                <VCol cols="12" md="3">
                  <VSelect
                    v-model="formData.supplier_id"
                    label="Proveedor"
                    :items="suppliers"
                    item-title="name"
                    item-value="id"
                    prepend-inner-icon="ri-building-4-line"
                    :disabled="formData.reason === 'INVENTARIO_INICIAL'"
                    :required="formData.reason === 'COMPRA'"
                    clearable
                  />
                </VCol>
                <VCol cols="12" md="3">
                  <VTextField v-model="formData.invoice_number" label="N° de Factura Física (Opcional)" prepend-inner-icon="ri-file-list-3-line" />
                </VCol>
                <VCol cols="12" md="3">
                  <VTextField v-model="formData.purchase_date" type="date" label="Fecha de Ingreso" required />
                </VCol>
              </VRow>

              <VDivider class="mb-6" />

              <!-- SEARCH IN-LINE & CSV IMPORT -->
              <VRow class="mb-2" align="center">
                <VCol cols="12" md="6">
                  <VAutocomplete
                    v-model="productSearch"
                    v-model:search="searchQuery"
                    :loading="searchLoading"
                    :items="searchProducts"
                    item-title="name"
                    item-value="id"
                    return-object
                    placeholder="Escriba para buscar medicamento e incorporarlo..."
                    prepend-inner-icon="ri-search-line"
                    variant="solo-filled"
                    hide-no-data
                  >
                    <template #item="{ props, item }">
                      <VListItem v-bind="props" :title="item.raw.name + (item.raw.laboratory ? ' - ' + item.raw.laboratory.name : '')" :subtitle="`Stock Actual: ${item.raw.stock} unds`" />
                    </template>
                  </VAutocomplete>
                </VCol>

                <VCol cols="12" md="6" class="d-flex justify-end">
                  <VBtn
                    color="primary"
                    prepend-icon="ri-qr-code-line"
                    class="mobile-full-btn"
                    @click="startScanner"
                  >
                    Escanear con Celular (Cámara)
                  </VBtn>
                </VCol>
              </VRow>

              <!-- Vista en Tarjetas para Celulares (oculto en pantallas sm y superiores) -->
              <div class="d-block d-sm-none">
                <div v-if="formData.items.length === 0" class="text-center text-medium-emphasis py-6 border rounded bg-grey-lighten-4">
                  Utilice el buscador para agregar productos.
                </div>
                <div v-else v-for="(item, index) in formData.items" :key="'card-' + index" class="border rounded mb-4 overflow-hidden bg-white elevation-1">
                  <!-- Cabecera de la Tarjeta -->
                  <div class="bg-grey-lighten-4 px-3 py-2 d-flex justify-space-between align-center border-b">
                    <span class="font-weight-bold text-caption text-primary">#{{ index + 1 }} - Producto</span>
                    <IconBtn size="small" color="error" @click="removeItem(index)">
                      <VIcon icon="ri-delete-bin-line" />
                    </IconBtn>
                  </div>
                  
                  <!-- Contenido de la Tarjeta -->
                  <div class="px-3 py-3">
                    <div class="font-weight-bold text-subtitle-2 mb-3">{{ item.name }}</div>
                    
                    <VRow density="compact" class="mb-1">
                      <VCol cols="6">
                        <VTextField 
                          v-model="item.batch" 
                          label="Lote" 
                          density="compact" 
                          variant="outlined" 
                          placeholder="Opcional"
                          hide-details
                        />
                      </VCol>
                      <VCol cols="6">
                        <VTextField 
                          v-model="item.expiration_date" 
                          label="Vencimiento*" 
                          type="date" 
                          density="compact" 
                          variant="outlined" 
                          required
                          hide-details
                        />
                      </VCol>
                    </VRow>
                    
                    <VRow density="compact" class="mb-1">
                      <VCol cols="12" xs="12" sm="4">
                        <VTextField 
                          v-model.number="item.quantity" 
                          label="Cantidad*" 
                          type="number" 
                          density="compact" 
                          variant="outlined"
                          hide-details
                        />
                      </VCol>
                      <VCol cols="6" sm="4">
                        <VTextField 
                          v-model.number="item.unit_cost" 
                          label="Costo Bs*" 
                          type="number" 
                          step="0.01" 
                          density="compact" 
                          variant="outlined"
                          hide-details
                          @input="item.sale_price = Number((item.unit_cost * 1.35).toFixed(2))"
                        />
                      </VCol>
                      <VCol cols="6" sm="4">
                        <VTextField 
                          v-model.number="item.sale_price" 
                          label="Venta Bs" 
                          type="number" 
                          step="0.01" 
                          density="compact" 
                          variant="outlined"
                          hide-details
                        />
                      </VCol>
                    </VRow>
                  </div>
                  
                  <!-- Subtotal de la Tarjeta -->
                  <div class="bg-green-lighten-5 px-3 py-2 d-flex justify-space-between align-center border-t">
                    <span class="text-caption font-weight-bold">Subtotal:</span>
                    <span class="font-weight-black text-green-darken-3">Bs. {{ (item.quantity * item.unit_cost).toFixed(2) }}</span>
                  </div>
                </div>
              </div>

              <!-- Vista en Tabla para Computadoras/Tablets (oculto en celulares) -->
              <VTable density="compact" class="border rounded align-middle d-none d-sm-table">
                <thead>
                  <tr class="bg-grey-lighten-4">
                    <th class="text-center font-weight-bold" width="5%">#</th>
                    <th class="text-left font-weight-bold">Producto</th>
                    <th class="text-left font-weight-bold" width="12%">Lote (Ref)</th>
                    <th class="text-left font-weight-bold" width="15%">Vencimiento*</th>
                    <th class="text-right font-weight-bold" width="12%">Cantidad*</th>
                    <th class="text-right font-weight-bold" width="14%">Costo Unid. Bs*</th>
                    <th class="text-right font-weight-bold" width="14%">Precio Vta Bs</th>
                    <th class="text-right font-weight-bold" width="12%">Subtotal</th>
                    <th width="5%"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="formData.items.length === 0">
                    <td colspan="9" class="text-center text-medium-emphasis py-4">Utilice el buscador para agregar productos.</td>
                  </tr>
                  <tr v-for="(item, index) in formData.items" :key="index">
                    <td class="text-center font-weight-bold">{{ index + 1 }}</td>
                    <td class="font-weight-medium text-caption">{{ item.name }}</td>
                    <td><VTextField v-model="item.batch" density="compact" hide-details variant="plain" placeholder="Opcional" /></td>
                    <td><VTextField v-model="item.expiration_date" type="date" density="compact" hide-details variant="plain" required /></td>
                    <td><VTextField v-model.number="item.quantity" type="number" density="compact" hide-details variant="plain" class="text-end" /></td>
                    <td><VTextField v-model.number="item.unit_cost" @input="item.sale_price = Number((item.unit_cost * 1.35).toFixed(2))" type="number" step="0.01" density="compact" hide-details variant="plain" class="text-end" /></td>
                    <td><VTextField v-model.number="item.sale_price" type="number" step="0.01" density="compact" hide-details variant="plain" class="text-end" /></td>
                    <td class="text-right font-weight-bold bg-green-lighten-5">Bs. {{ (item.quantity * item.unit_cost).toFixed(2) }}</td>
                    <td class="text-center">
                      <IconBtn size="small" color="error" @click="removeItem(index)"><VIcon icon="ri-delete-bin-line" /></IconBtn>
                    </td>
                  </tr>
                </tbody>
              </VTable>

              <VRow class="mt-4 pb-2 border-b">
                <VCol cols="12" class="text-start text-sm-end">
                    <div class="text-subtitle-1 text-sm-h6">Costo Total Compra: <span class="font-weight-black text-primary">Bs. {{ totalPurchase.toFixed(2) }}</span></div>
                   <div class="text-caption text-medium-emphasis">El Costo Promedio (CPP) del inventario se auto-recalculará al procesar.</div>
                </VCol>
              </VRow>

              <div class="d-flex flex-column flex-sm-row justify-end gap-3 mt-6">
                <VBtn color="secondary" variant="outlined" class="mobile-full-btn" @click="router.push('/compras')">Descartar</VBtn>
                <VBtn color="success" type="submit" :loading="isLoading" size="large" class="mobile-full-btn"><VIcon start icon="ri-check-line" /> Procesar Compra y Sincronizar Stock</VBtn>
              </div>
            </VForm>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- DIALOG DE ESCANEO POR CÁMARA Y ASOCIACIÓN -->
    <VDialog v-model="isScannerModalOpen" max-width="600" persistent>
      <VCard>
        <VCardTitle class="bg-primary text-white d-flex justify-space-between align-center py-3">
          <span class="d-flex align-center"><VIcon icon="ri-qr-code-line" class="me-2" /> Escanear Código de Barras</span>
          <VBtn icon="ri-close-line" variant="text" color="white" @click="stopScanner" />
        </VCardTitle>
        <VCardText class="px-3 px-sm-6 pt-4 text-center">
          <!-- Estado 1: Escaneando -->
          <div v-show="!isAssociating">
            <p class="text-subtitle-2 mb-3">Enfoque el código de barras del producto con la cámara de su celular</p>
            <div id="reader" style="width: 100%; max-width: 500px; margin: 0 auto; background: #fafafa; border-radius: 8px; overflow: hidden; min-height: 250px;"></div>
            <div class="d-flex justify-center gap-2 mt-4">
              <VBtn color="warning" variant="tonal" v-if="isScanning" prepend-icon="ri-pause-line" @click="pauseScanner">Pausar Cámara</VBtn>
              <VBtn color="success" variant="tonal" v-else prepend-icon="ri-play-line" @click="resumeScanner">Reanudar Cámara</VBtn>
            </div>
          </div>

          <!-- Estado 2: Asociando código desconocido -->
          <div v-if="isAssociating" class="text-left">
            <VAlert type="warning" variant="tonal" class="mb-4 text-caption">
              El código escaneado <strong>"{{ scannedBarcode }}"</strong> no está registrado.
              Asócielo a un producto del catálogo para continuar.
            </VAlert>

            <VAutocomplete
              v-model="selectedProductForAssociation"
              v-model:search="associateSearchQuery"
              :loading="associateSearchLoading"
              :items="associateProducts"
              item-title="name"
              item-value="id"
              return-object
              placeholder="Buscar por nombre..."
              prepend-inner-icon="ri-search-line"
              variant="outlined"
              class="mb-4"
              hide-no-data
            >
              <template #selection="{ item }">
                <span class="text-body-2 text-truncate font-weight-medium">
                  {{ item.raw.name }} {{ item.raw.laboratory ? `(${item.raw.laboratory.name})` : '' }}
                </span>
              </template>
              <template #item="{ props, item }">
                <VListItem 
                  v-bind="props" 
                  :title="item.raw.name" 
                  :subtitle="`${item.raw.laboratory ? 'Lab: ' + item.raw.laboratory.name : 'Sin Laboratorio'} | Código: ${item.raw.barcode || 'Ninguno'}`"
                />
              </template>
            </VAutocomplete>

            <div class="d-flex justify-end gap-2 mt-6">
              <VBtn color="secondary" variant="outlined" @click="resumeScanner">Cancelar y Reanudar</VBtn>
              <VBtn color="primary" :disabled="!selectedProductForAssociation" :loading="isLoading" @click="associateBarcodeToProduct">Vincular y Agregar</VBtn>
            </div>
          </div>
        </VCardText>
      </VCard>
    </VDialog>
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
