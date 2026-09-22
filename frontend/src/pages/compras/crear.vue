<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { Html5Qrcode } from 'html5-qrcode'
import * as XLSX from 'xlsx'

const router = useRouter()
const toast = useMaterializeToast()

// General Data
const isLoading = ref(false)
const suppliers = ref([])
const searchProducts = ref([])
const allCatalogProducts = ref([])

// Form Data - Header
const formData = ref({
  supplier_id: null,
  reason: 'COMPRA',
  purchase_date: new Date().toISOString().slice(0, 10),
  invoice_number: '',
  items: []
})

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

// Excel Import Ref
const excelFileInput = ref(null)
const isImportingExcel = ref(false)

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

// ── CÁMARA / SCANNER QR & BARCODE ──

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
        // Ignorar lecturas parciales
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

const addProductToPurchase = (product, customData = {}) => {
  const exists = formData.value.items.find(i => i.product_id === product.id)
  if (exists && !customData.batch) {
    exists.quantity += (customData.quantity || 1)
  } else {
    formData.value.items.push({
      product_id: product.id,
      name: product.name,
      laboratory_name: product.laboratory?.name || '',
      barcode: product.barcode || '',
      batch: customData.batch || '',
      expiration_date: customData.expiration_date || '',
      quantity: customData.quantity || 1,
      unit_cost: customData.unit_cost !== undefined ? Number(customData.unit_cost) : (product.average_cost > 0 ? Number(product.average_cost) : 0),
      sale_price: customData.sale_price !== undefined ? Number(customData.sale_price) : (product.sale_price > 0 ? Number(product.sale_price) : 0)
    })
  }
}

const handleScannedBarcode = async (barcode) => {
  scannedBarcode.value = barcode.trim()
  
  try {
    isLoading.value = true
    const response = await $api(`/v1/products?search=${encodeURIComponent(scannedBarcode.value)}`, { method: 'GET' })
    const productsList = response.data?.data || response.data || []

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
    const res = await $api(`/v1/products?search=${encodeURIComponent(newVal)}`, { method: 'GET' })
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
  } catch (e) {
    console.error('Error suppliers:', e)
  }
}

const loadAllProductsForLookup = async () => {
  try {
    const res = await $api('/v1/products?per_page=500', { method: 'GET' })
    allCatalogProducts.value = res.data?.data || res.data || []
  } catch (e) {
    console.error('Error loading catalog for Excel lookup:', e)
  }
}

onMounted(() => {
  loadSuppliers()
  loadAllProductsForLookup()
})

watch(searchQuery, async (newVal) => {
  if (!newVal || newVal.length < 2) return
  try {
    searchLoading.value = true
    const res = await $api(`/v1/products?search=${encodeURIComponent(newVal)}`, { method: 'GET' })
    searchProducts.value = res.data?.data || res.data || []
  } catch (e) {
  } finally {
    searchLoading.value = false
  }
})

// Enter on search input (useful for physical barcode guns)
const handleSearchEnter = async () => {
  const query = (searchQuery.value || '').trim()
  if (!query) return

  // Check if exactly matches a barcode or name in current search results
  const exactMatch = searchProducts.value.find(p => p.barcode === query || p.name.toLowerCase() === query.toLowerCase())
  if (exactMatch) {
    addProductToPurchase(exactMatch)
    productSearch.value = null
    searchQuery.value = ''
    playBeep()
    toast.showSuccess(`Producto "${exactMatch.name}" agregado.`, "Escaneado")
    return
  }

  // Otherwise query API directly
  try {
    searchLoading.value = true
    const res = await $api(`/v1/products?search=${encodeURIComponent(query)}`, { method: 'GET' })
    const list = res.data?.data || res.data || []
    const match = list.find(p => p.barcode === query || (p.barcodes && p.barcodes.some(b => b.barcode === query)))
    if (match) {
      addProductToPurchase(match)
      productSearch.value = null
      searchQuery.value = ''
      playBeep()
      toast.showSuccess(`Producto "${match.name}" agregado.`, "Escaneado")
    } else {
      toast.showWarning(`No se encontró producto con código/nombre "${query}"`, "Búsqueda")
    }
  } catch (e) {
    toast.showError('Error en búsqueda', 'Error')
  } finally {
    searchLoading.value = false
  }
}

// Add item to purchase table via Autocomplete selection
watch(productSearch, (selectedProduct) => {
  if (selectedProduct) {
    addProductToPurchase(selectedProduct)
    setTimeout(() => {
      productSearch.value = null
      searchQuery.value = ''
    }, 100)
  }
})

const removeItem = (index) => {
  formData.value.items.splice(index, 1)
}

const clearAllItems = () => {
  formData.value.items = []
}

const totalPurchase = computed(() => {
  return formData.value.items.reduce((acc, item) => acc + (Number(item.quantity || 0) * Number(item.unit_cost || 0)), 0)
})

const totalUnits = computed(() => {
  return formData.value.items.reduce((acc, item) => acc + Number(item.quantity || 0), 0)
})

// ── EXCEL TEMPLATE DOWNLOAD & IMPORT ──

const downloadExcelTemplate = () => {
  const templateData = [
    {
      "codigo_barras": "7771234567890",
      "nombre_producto": "PARACETAMOL 500MG COMPRIMIDOS",
      "lote": "LOTE-2026A",
      "fecha_vencimiento": "2027-12-31",
      "cantidad": 100,
      "costo_unitario": 0.50,
      "precio_venta": 1.00
    },
    {
      "codigo_barras": "7779876543210",
      "nombre_producto": "AMOXICILINA 500MG CAPSULAS",
      "lote": "AMX-8890",
      "fecha_vencimiento": "2028-06-30",
      "cantidad": 50,
      "costo_unitario": 2.20,
      "precio_venta": 3.50
    },
    {
      "codigo_barras": "",
      "nombre_producto": "IBUPROFENO 400MG",
      "lote": "IBU-400",
      "fecha_vencimiento": "2027-08-15",
      "cantidad": 30,
      "costo_unitario": 1.10,
      "precio_venta": 2.00
    }
  ]

  const ws = XLSX.utils.json_to_sheet(templateData)
  
  // Set custom column widths
  ws['!cols'] = [
    { wch: 18 }, // codigo_barras
    { wch: 35 }, // nombre_producto
    { wch: 15 }, // lote
    { wch: 18 }, // fecha_vencimiento
    { wch: 12 }, // cantidad
    { wch: 15 }, // costo_unitario
    { wch: 15 }  // precio_venta
  ]

  const wb = XLSX.utils.book_new()
  XLSX.utils.book_append_sheet(wb, ws, "Plantilla_Compras")
  XLSX.writeFile(wb, "Plantilla_Importacion_Compras_Qhatumed.xlsx")
  toast.showSuccess("Plantilla Excel descargada exitosamente", "Plantilla")
}

const triggerExcelUpload = () => {
  if (excelFileInput.value) {
    excelFileInput.value.click()
  }
}

const handleExcelFile = async (event) => {
  const file = event.target.files[0]
  if (!file) return

  isImportingExcel.value = true
  try {
    const data = await file.arrayBuffer()
    const workbook = XLSX.read(data, { type: 'array' })
    const firstSheetName = workbook.SheetNames[0]
    const worksheet = workbook.Sheets[firstSheetName]
    const rows = XLSX.utils.sheet_to_json(worksheet, { defval: '' })

    if (rows.length === 0) {
      toast.showWarning("El archivo Excel no contiene filas de datos.", "Archivo Vacío")
      return
    }

    let addedCount = 0
    let notFoundCount = 0
    const notFoundNames = []

    for (const row of rows) {
      // Clean keys (case-insensitive & trim)
      const normalizedRow = {}
      for (const key of Object.keys(row)) {
        const cleanKey = key.trim().toLowerCase().replace(/\s+/g, '_')
        normalizedRow[cleanKey] = row[key]
      }

      const barcode = String(normalizedRow['codigo_barras'] || normalizedRow['barcode'] || normalizedRow['codigo'] || '').trim()
      const productName = String(normalizedRow['nombre_producto'] || normalizedRow['producto'] || normalizedRow['nombre'] || normalizedRow['descripcion'] || '').trim()
      const batch = String(normalizedRow['lote'] || normalizedRow['nro_lote'] || '').trim()
      
      // Parse date format (support YYYY-MM-DD, DD/MM/YYYY, or Excel date numbers)
      let expirationDate = ''
      const rawDate = normalizedRow['fecha_vencimiento'] || normalizedRow['vencimiento'] || normalizedRow['fecha_expiracion'] || ''
      if (rawDate) {
        if (typeof rawDate === 'number') {
          const jsDate = new Date(Math.round((rawDate - 25569) * 86400 * 1000))
          expirationDate = jsDate.toISOString().slice(0, 10)
        } else if (String(rawDate).includes('/')) {
          const parts = String(rawDate).split('/')
          if (parts.length === 3) {
            // Assume DD/MM/YYYY
            const day = parts[0].padStart(2, '0')
            const month = parts[1].padStart(2, '0')
            const year = parts[2].length === 2 ? '20' + parts[2] : parts[2]
            expirationDate = `${year}-${month}-${day}`
          }
        } else {
          expirationDate = String(rawDate).slice(0, 10)
        }
      }

      const quantity = Math.max(1, parseInt(normalizedRow['cantidad'] || normalizedRow['cant'] || 1, 10))
      const unitCost = Math.max(0, parseFloat(normalizedRow['costo_unitario'] || normalizedRow['costo'] || normalizedRow['costo_bs'] || 0))
      let salePrice = parseFloat(normalizedRow['precio_venta'] || normalizedRow['precio'] || normalizedRow['venta_bs'] || 0)
      if (salePrice <= 0 && unitCost > 0) {
        salePrice = Number((unitCost * 1.35).toFixed(2))
      }

      // Lookup product in catalog
      let matchedProduct = null

      if (barcode) {
        matchedProduct = allCatalogProducts.value.find(p => p.barcode === barcode || (p.barcodes && p.barcodes.some(b => b.barcode === barcode)))
      }

      if (!matchedProduct && productName) {
        const pNameLower = productName.toLowerCase()
        matchedProduct = allCatalogProducts.value.find(p => p.name.toLowerCase() === pNameLower || p.name.toLowerCase().includes(pNameLower))
      }

      // If still not found in memory, try searching via API
      if (!matchedProduct && (barcode || productName)) {
        try {
          const searchParam = barcode || productName
          const res = await $api(`/v1/products?search=${encodeURIComponent(searchParam)}`, { method: 'GET' })
          const list = res.data?.data || res.data || []
          matchedProduct = list.find(p => 
            (barcode && (p.barcode === barcode || (p.barcodes && p.barcodes.some(b => b.barcode === barcode)))) ||
            (productName && p.name.toLowerCase().includes(productName.toLowerCase()))
          ) || list[0]
        } catch (e) {}
      }

      if (matchedProduct) {
        addProductToPurchase(matchedProduct, {
          batch,
          expiration_date: expirationDate,
          quantity,
          unit_cost: unitCost,
          sale_price: salePrice
        })
        addedCount++
      } else {
        notFoundCount++
        notFoundNames.push(productName || barcode || 'Fila sin identificar')
      }
    }

    if (addedCount > 0) {
      playBeep()
      toast.showSuccess(`Se importaron ${addedCount} productos a la lista de compra.`, "Importación Exitosa")
    }

    if (notFoundCount > 0) {
      toast.showWarning(`${notFoundCount} productos no pudieron ser encontrados en el catálogo: ${notFoundNames.slice(0, 3).join(', ')}...`, "Atención")
    }

  } catch (err) {
    console.error("Error al procesar archivo Excel:", err)
    toast.showError("Hubo un error al procesar el archivo Excel. Verifique el formato.", "Error de Importación")
  } finally {
    isImportingExcel.value = false
    if (event.target) event.target.value = ''
  }
}

// ── ENVÍO DE COMPRA ──

const submitPurchase = async () => {
  if (formData.value.reason === 'COMPRA' && !formData.value.supplier_id) {
    toast.showWarning('Faltan datos: Seleccione un proveedor para la compra.', 'Faltan Datos')
    return
  }
  if (formData.value.items.length === 0) {
    toast.showWarning('Faltan datos: Agregue al menos un producto a la compra.', 'Faltan Datos')
    return
  }
  
  // Validate items
  for (let i = 0; i < formData.value.items.length; i++) {
    const it = formData.value.items[i]
    if (it.quantity < 1 || it.unit_cost < 0) {
      toast.showError(`Error en ítem #${i + 1} (${it.name}): Cantidad o costo inválido.`, 'Error de Validación')
      return
    }
    if (!it.expiration_date) {
      toast.showWarning(`Lote Incompleto en #${i + 1} (${it.name}): Ingrese la Fecha de Vencimiento obligatoriamente.`, 'Fecha Requerida')
      return
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
    toast.showError('Error interno al registrar la compra', 'Error')
  } finally {
    isLoading.value = false
  }
}

definePage({ meta: { permission: 'listar_compra' } })
</script>

<template>
  <div class="compras-container pa-2 pa-sm-4">
    <!-- Input oculto para archivo Excel -->
    <input
      type="file"
      ref="excelFileInput"
      accept=".xlsx, .xls, .csv"
      style="display: none"
      @change="handleExcelFile"
    />

    <VRow class="ma-0">
      <VCol cols="12" class="pa-0">
        <VCard elevation="3" class="rounded-xl border">
          <!-- CABECERA PRINCIPAL -->
          <VCardTitle class="py-4 px-4 px-sm-6 bg-primary text-white d-flex justify-space-between align-center flex-wrap gap-2">
            <div class="d-flex align-center">
              <VIcon icon="ri-truck-line" size="28" class="me-3" />
              <div>
                <h3 class="text-h6 font-weight-bold text-white mb-0">Insertar Compra / Nota de Ingreso</h3>
                <span class="text-caption text-white text-opacity-80">Gestión de reabastecimiento, inventario y registro de lotes</span>
              </div>
            </div>

            <!-- BOTONES DE ACCIÓN RÁPIDA SUPERIOR -->
            <div class="d-flex align-center gap-2 flex-wrap">
              <VBtn
                color="white"
                variant="tonal"
                size="small"
                prepend-icon="ri-download-2-line"
                class="font-weight-bold"
                @click="downloadExcelTemplate"
              >
                Plantilla Excel
              </VBtn>

              <VBtn
                color="success"
                variant="flat"
                size="small"
                prepend-icon="ri-file-excel-2-line"
                class="font-weight-bold text-white"
                :loading="isImportingExcel"
                @click="triggerExcelUpload"
              >
                Importar Excel
              </VBtn>

              <VBtn
                color="secondary"
                variant="tonal"
                size="small"
                prepend-icon="ri-qr-code-line"
                class="font-weight-bold d-sm-none"
                @click="startScanner"
              >
                Escanear
              </VBtn>
            </div>
          </VCardTitle>

          <VCardText class="pt-5 px-3 px-sm-6 pb-6">
            <VForm @submit.prevent="submitPurchase">
              <!-- DATOS GENERALES DE LA COMPRA -->
              <VRow class="mb-4">
                <VCol cols="12" sm="6" md="3">
                  <VSelect
                    v-model="formData.reason"
                    label="Motivo de Ingreso *"
                    :items="[
                      { title: 'Compra Regular', value: 'COMPRA' },
                      { title: 'Inventario Inicial', value: 'INVENTARIO_INICIAL' }
                    ]"
                    item-title="title"
                    item-value="value"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="ri-file-transfer-line"
                    required
                  />
                </VCol>

                <VCol cols="12" sm="6" md="3">
                  <VSelect
                    v-model="formData.supplier_id"
                    label="Proveedor *"
                    :items="suppliers"
                    item-title="name"
                    item-value="id"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="ri-building-4-line"
                    :disabled="formData.reason === 'INVENTARIO_INICIAL'"
                    :required="formData.reason === 'COMPRA'"
                    clearable
                  />
                </VCol>

                <VCol cols="12" sm="6" md="3">
                  <VTextField
                    v-model="formData.invoice_number"
                    label="N° Factura / Nota Física"
                    placeholder="Ej. F-98234"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="ri-file-list-3-line"
                  />
                </VCol>

                <VCol cols="12" sm="6" md="3">
                  <VTextField
                    v-model="formData.purchase_date"
                    type="date"
                    label="Fecha de Ingreso *"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="ri-calendar-line"
                    required
                  />
                </VCol>
              </VRow>

              <VDivider class="my-4" />

              <!-- BARRA DE BÚSQUEDA Y HERRAMIENTAS -->
              <div class="search-toolbar-card pa-3 rounded-xl border bg-surface mb-5 shadow-xs">
                <VRow align="center" class="ma-0">
                  <VCol cols="12" md="7" class="pa-1">
                    <VAutocomplete
                      v-model="productSearch"
                      v-model:search="searchQuery"
                      :loading="searchLoading"
                      :items="searchProducts"
                      item-title="name"
                      item-value="id"
                      return-object
                      placeholder="Buscar por medicamento, principio activo o escanear código de barras..."
                      prepend-inner-icon="ri-search-line"
                      variant="outlined"
                      density="comfortable"
                      hide-no-data
                      hide-details
                      autofocus
                      class="bg-white rounded-lg"
                      @keydown.enter="handleSearchEnter"
                    >
                      <template #item="{ props, item }">
                        <VListItem
                          v-bind="props"
                          :title="item.raw.name"
                          :subtitle="`${item.raw.laboratory ? 'Lab: ' + item.raw.laboratory.name : 'Genérico'} | Código: ${item.raw.barcode || 'S/C'} | Stock Actual: ${item.raw.stock} unds`"
                        >
                          <template #append>
                            <span class="text-caption font-weight-bold text-success">Bs. {{ Number(item.raw.sale_price || 0).toFixed(2) }}</span>
                          </template>
                        </VListItem>
                      </template>
                    </VAutocomplete>
                  </VCol>

                  <VCol cols="12" md="5" class="pa-1 d-flex gap-2 justify-end flex-wrap">
                    <VBtn
                      color="primary"
                      variant="flat"
                      prepend-icon="ri-qr-code-line"
                      class="font-weight-bold"
                      @click="startScanner"
                    >
                      Escanear Celular (Cámara)
                    </VBtn>

                    <VBtn
                      v-if="formData.items.length > 0"
                      color="error"
                      variant="tonal"
                      size="default"
                      icon="ri-delete-bin-line"
                      title="Limpiar toda la lista"
                      @click="clearAllItems"
                    />
                  </VCol>
                </VRow>
              </div>

              <!-- VISTA MÓVIL (TARJETAS TOUCH) - visible en pantallas pequeñas (< 600px) -->
              <div class="d-block d-sm-none mb-4">
                <div v-if="formData.items.length === 0" class="text-center text-medium-emphasis py-8 border rounded-xl bg-grey-lighten-4">
                  <VIcon icon="ri-shopping-cart-2-line" size="40" class="mb-2 text-disabled" />
                  <div class="text-body-2 font-weight-medium">Utilice el buscador o la cámara para agregar productos a la compra.</div>
                </div>

                <div
                  v-for="(item, index) in formData.items"
                  :key="'card-' + index"
                  class="mobile-item-card border rounded-xl mb-3 overflow-hidden bg-surface elevation-1"
                >
                  <!-- Cabecera de la tarjeta -->
                  <div class="card-top-bar px-3 py-2 d-flex justify-space-between align-center bg-grey-lighten-4 border-b">
                    <div class="d-flex align-center">
                      <VChip size="x-small" color="primary" class="font-weight-bold me-2">#{{ index + 1 }}</VChip>
                      <span class="text-caption text-medium-emphasis font-weight-bold">{{ item.laboratory_name || 'Genérico' }}</span>
                    </div>
                    <IconBtn size="small" color="error" variant="text" @click="removeItem(index)">
                      <VIcon icon="ri-delete-bin-line" size="20" />
                    </IconBtn>
                  </div>

                  <!-- Cuerpo de la tarjeta -->
                  <div class="pa-3">
                    <div class="font-weight-bold text-body-2 mb-3 text-high-emphasis">{{ item.name }}</div>

                    <VRow density="compact" class="mb-2">
                      <VCol cols="6">
                        <VTextField
                          v-model="item.batch"
                          label="Lote (Opcional)"
                          placeholder="Ej. LOT-123"
                          density="compact"
                          variant="outlined"
                          hide-details
                        />
                      </VCol>
                      <VCol cols="6">
                        <VTextField
                          v-model="item.expiration_date"
                          type="date"
                          label="Vencimiento *"
                          density="compact"
                          variant="outlined"
                          required
                          hide-details
                        />
                      </VCol>
                    </VRow>

                    <VRow density="compact" class="mb-2">
                      <VCol cols="4">
                        <VTextField
                          v-model.number="item.quantity"
                          type="number"
                          label="Cantidad *"
                          min="1"
                          density="compact"
                          variant="outlined"
                          hide-details
                        />
                      </VCol>
                      <VCol cols="4">
                        <VTextField
                          v-model.number="item.unit_cost"
                          type="number"
                          step="0.01"
                          label="Costo Bs *"
                          density="compact"
                          variant="outlined"
                          hide-details
                          @input="item.sale_price = Number((item.unit_cost * 1.35).toFixed(2))"
                        />
                      </VCol>
                      <VCol cols="4">
                        <VTextField
                          v-model.number="item.sale_price"
                          type="number"
                          step="0.01"
                          label="P. Venta Bs"
                          density="compact"
                          variant="outlined"
                          hide-details
                        />
                      </VCol>
                    </VRow>
                  </div>

                  <!-- Pie con Subtotal -->
                  <div class="px-3 py-2 bg-green-lighten-5 border-t d-flex justify-space-between align-center">
                    <span class="text-caption font-weight-bold text-green-darken-3">Subtotal Ítem:</span>
                    <span class="text-subtitle-2 font-weight-black text-green-darken-4">Bs. {{ (Number(item.quantity || 0) * Number(item.unit_cost || 0)).toFixed(2) }}</span>
                  </div>
                </div>
              </div>

              <!-- VISTA EN TABLA FULL-WIDTH (DESKTOP & LAPTOP) - visible en >= 600px -->
              <div class="d-none d-sm-block mb-4">
                <div class="table-responsive-wrapper border rounded-xl overflow-hidden shadow-xs">
                  <VTable density="comfortable" class="full-width-table align-middle">
                    <thead>
                      <tr class="table-header-row bg-grey-lighten-4">
                        <th class="text-center font-weight-bold text-caption" style="width: 45px;">#</th>
                        <th class="text-left font-weight-bold text-caption" style="min-width: 250px;">Producto / Laboratorio</th>
                        <th class="text-left font-weight-bold text-caption" style="width: 140px;">Lote</th>
                        <th class="text-left font-weight-bold text-caption" style="width: 165px;">Vencimiento *</th>
                        <th class="text-center font-weight-bold text-caption" style="width: 110px;">Cantidad *</th>
                        <th class="text-center font-weight-bold text-caption" style="width: 130px;">Costo Unid. Bs *</th>
                        <th class="text-center font-weight-bold text-caption" style="width: 130px;">Precio Vta Bs</th>
                        <th class="text-right font-weight-bold text-caption" style="width: 130px;">Subtotal</th>
                        <th class="text-center" style="width: 50px;"></th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-if="formData.items.length === 0">
                        <td colspan="9" class="text-center text-medium-emphasis py-10">
                          <VIcon icon="ri-inbox-line" size="48" class="mb-2 text-disabled" />
                          <div class="text-body-1 font-weight-medium">Ningún medicamento anexado a la compra.</div>
                          <div class="text-caption text-medium-emphasis">Use el buscador superior, escanee con la cámara o importe un archivo Excel.</div>
                        </td>
                      </tr>

                      <tr v-for="(item, index) in formData.items" :key="'table-' + index" class="table-body-row">
                        <td class="text-center font-weight-bold text-caption text-medium-emphasis">
                          {{ index + 1 }}
                        </td>
                        <td>
                          <div class="font-weight-bold text-body-2 text-high-emphasis">{{ item.name }}</div>
                          <div class="text-caption product-lab">{{ item.laboratory_name || 'GENÉRICO' }}</div>
                        </td>
                        <td>
                          <VTextField
                            v-model="item.batch"
                            density="compact"
                            variant="outlined"
                            placeholder="Opcional"
                            hide-details
                            class="compact-cell-input"
                          />
                        </td>
                        <td>
                          <VTextField
                            v-model="item.expiration_date"
                            type="date"
                            density="compact"
                            variant="outlined"
                            required
                            hide-details
                            class="compact-cell-input"
                          />
                        </td>
                        <td>
                          <VTextField
                            v-model.number="item.quantity"
                            type="number"
                            min="1"
                            density="compact"
                            variant="outlined"
                            hide-details
                            class="compact-cell-input text-end"
                          />
                        </td>
                        <td>
                          <VTextField
                            v-model.number="item.unit_cost"
                            type="number"
                            step="0.01"
                            min="0"
                            density="compact"
                            variant="outlined"
                            hide-details
                            class="compact-cell-input text-end"
                            @input="item.sale_price = Number((item.unit_cost * 1.35).toFixed(2))"
                          />
                        </td>
                        <td>
                          <VTextField
                            v-model.number="item.sale_price"
                            type="number"
                            step="0.01"
                            min="0"
                            density="compact"
                            variant="outlined"
                            hide-details
                            class="compact-cell-input text-end"
                          />
                        </td>
                        <td class="text-right font-weight-black text-body-2 text-green-darken-3 bg-green-lighten-5">
                          Bs. {{ (Number(item.quantity || 0) * Number(item.unit_cost || 0)).toFixed(2) }}
                        </td>
                        <td class="text-center">
                          <IconBtn size="small" color="error" variant="tonal" @click="removeItem(index)" title="Eliminar fila">
                            <VIcon icon="ri-delete-bin-line" size="18" />
                          </IconBtn>
                        </td>
                      </tr>
                    </tbody>
                  </VTable>
                </div>
              </div>

              <!-- RESUMEN Y TOTALES -->
              <VCard variant="tonal" color="primary" class="pa-4 rounded-xl mt-4 mb-6">
                <VRow align="center" justify="space-between">
                  <VCol cols="12" sm="6">
                    <div class="d-flex align-center gap-4">
                      <div>
                        <span class="text-caption text-medium-emphasis">Total Ítems Distintos:</span>
                        <div class="text-h6 font-weight-bold">{{ formData.items.length }}</div>
                      </div>
                      <VDivider vertical style="height: 36px;" />
                      <div>
                        <span class="text-caption text-medium-emphasis">Total Unidades:</span>
                        <div class="text-h6 font-weight-bold">{{ totalUnits }}</div>
                      </div>
                    </div>
                  </VCol>

                  <VCol cols="12" sm="6" class="text-start text-sm-end">
                    <span class="text-caption text-medium-emphasis text-uppercase font-weight-bold">Costo Total Compra</span>
                    <div class="text-h4 font-weight-black text-primary">
                      Bs. {{ totalPurchase.toFixed(2) }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                      El Costo Promedio Ponderado (CPP) del inventario se recalculará al confirmar.
                    </div>
                  </VCol>
                </VRow>
              </VCard>

              <!-- BOTONES DE ACCIÓN FINAL -->
              <div class="d-flex flex-column flex-sm-row justify-end gap-3">
                <VBtn
                  color="secondary"
                  variant="outlined"
                  size="large"
                  class="font-weight-bold"
                  @click="router.push('/compras')"
                >
                  <VIcon start icon="ri-close-line" /> Descartar
                </VBtn>

                <VBtn
                  color="success"
                  type="submit"
                  size="large"
                  elevation="4"
                  :loading="isLoading"
                  :disabled="isLoading || formData.items.length === 0"
                  class="font-weight-bold text-white px-8"
                >
                  <VIcon start icon="ri-checkbox-circle-line" /> Procesar Compra y Sincronizar Stock
                </VBtn>
              </div>
            </VForm>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- DIALOG DE ESCANEO POR CÁMARA Y ASOCIACIÓN -->
    <VDialog v-model="isScannerModalOpen" max-width="600" persistent>
      <VCard class="rounded-xl overflow-hidden">
        <VCardTitle class="bg-primary text-white d-flex justify-space-between align-center py-3 px-4">
          <span class="d-flex align-center text-subtitle-1 font-weight-bold">
            <VIcon icon="ri-qr-code-line" class="me-2" /> Escanear Código de Barras
          </span>
          <VBtn icon="ri-close-line" variant="text" color="white" size="small" @click="stopScanner" />
        </VCardTitle>

        <VCardText class="px-3 px-sm-6 pt-5 pb-6 text-center">
          <!-- Estado 1: Escaneando -->
          <div v-show="!isAssociating">
            <p class="text-body-2 text-medium-emphasis mb-3">
              Enfoque el código de barras del medicamento con la cámara de su dispositivo
            </p>
            <div id="reader" style="width: 100%; max-width: 480px; margin: 0 auto; background: #fafafa; border-radius: 12px; overflow: hidden; min-height: 240px; border: 1px solid #e0e0e0;"></div>
            <div class="d-flex justify-center gap-2 mt-4">
              <VBtn color="warning" variant="tonal" size="small" v-if="isScanning" prepend-icon="ri-pause-line" @click="pauseScanner">
                Pausar Cámara
              </VBtn>
              <VBtn color="success" variant="tonal" size="small" v-else prepend-icon="ri-play-line" @click="resumeScanner">
                Reanudar Cámara
              </VBtn>
            </div>
          </div>

          <!-- Estado 2: Asociando código desconocido -->
          <div v-if="isAssociating" class="text-left pt-2">
            <VAlert type="warning" variant="tonal" class="mb-4 text-body-2 rounded-lg">
              El código escaneado <strong>"{{ scannedBarcode }}"</strong> no está registrado en el catálogo.
              Asócielo a un medicamento existente para continuar.
            </VAlert>

            <VAutocomplete
              v-model="selectedProductForAssociation"
              v-model:search="associateSearchQuery"
              :loading="associateSearchLoading"
              :items="associateProducts"
              item-title="name"
              item-value="id"
              return-object
              placeholder="Escriba el nombre del medicamento para vincular..."
              prepend-inner-icon="ri-search-line"
              variant="outlined"
              density="comfortable"
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

            <div class="d-flex justify-end gap-2 mt-4">
              <VBtn color="secondary" variant="outlined" @click="resumeScanner">Cancelar y Reanudar</VBtn>
              <VBtn color="primary" :disabled="!selectedProductForAssociation" :loading="isLoading" @click="associateBarcodeToProduct">
                Vincular y Agregar
              </VBtn>
            </div>
          </div>
        </VCardText>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.compras-container {
  max-width: 100%;
}

.table-responsive-wrapper {
  width: 100%;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

.full-width-table {
  min-width: 980px;
  width: 100%;
}

.table-header-row th {
  padding: 12px 14px !important;
  color: #4b5563 !important;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 2px solid #e5e7eb !important;
}

.table-body-row td {
  padding: 8px 10px !important;
  border-bottom: 1px solid #f3f4f6 !important;
}

.product-lab {
  color: #009688 !important;
  font-weight: 700;
  font-size: 0.72rem;
  letter-spacing: 0.5px;
}

.compact-cell-input :deep(.v-field__input) {
  padding-top: 6px !important;
  padding-bottom: 6px !important;
  font-size: 0.85rem !important;
}

.mobile-item-card {
  transition: all 0.2s ease;
}

.mobile-item-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08) !important;
}
</style>