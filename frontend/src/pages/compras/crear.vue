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

// ── CÁMARA / SCANNER QR & BARCODE (SOLO MÓVIL) ──

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

// Enter on search input (physical barcode gun support)
const handleSearchEnter = async () => {
  const query = (searchQuery.value || '').trim()
  if (!query) return

  const exactMatch = searchProducts.value.find(p => p.barcode === query || p.name.toLowerCase() === query.toLowerCase())
  if (exactMatch) {
    addProductToPurchase(exactMatch)
    productSearch.value = null
    searchQuery.value = ''
    playBeep()
    toast.showSuccess(`Producto "${exactMatch.name}" agregado.`, "Escaneado")
    return
  }

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
      const normalizedRow = {}
      for (const key of Object.keys(row)) {
        const cleanKey = key.trim().toLowerCase().replace(/\s+/g, '_')
        normalizedRow[cleanKey] = row[key]
      }

      const barcode = String(normalizedRow['codigo_barras'] || normalizedRow['barcode'] || normalizedRow['codigo'] || '').trim()
      const productName = String(normalizedRow['nombre_producto'] || normalizedRow['producto'] || normalizedRow['nombre'] || normalizedRow['descripcion'] || '').trim()
      const batch = String(normalizedRow['lote'] || normalizedRow['nro_lote'] || '').trim()
      
      let expirationDate = ''
      const rawDate = normalizedRow['fecha_vencimiento'] || normalizedRow['vencimiento'] || normalizedRow['fecha_expiracion'] || ''
      if (rawDate) {
        if (typeof rawDate === 'number') {
          const jsDate = new Date(Math.round((rawDate - 25569) * 86400 * 1000))
          expirationDate = jsDate.toISOString().slice(0, 10)
        } else if (String(rawDate).includes('/')) {
          const parts = String(rawDate).split('/')
          if (parts.length === 3) {
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

      let matchedProduct = null

      if (barcode) {
        matchedProduct = allCatalogProducts.value.find(p => p.barcode === barcode || (p.barcodes && p.barcodes.some(b => b.barcode === barcode)))
      }

      if (!matchedProduct && productName) {
        const pNameLower = productName.toLowerCase()
        matchedProduct = allCatalogProducts.value.find(p => p.name.toLowerCase() === pNameLower || p.name.toLowerCase().includes(pNameLower))
      }

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
  <div class="compras-container pa-2 pa-sm-3">
    <!-- Input oculto para archivo Excel -->
    <input
      type="file"
      ref="excelFileInput"
      accept=".xlsx, .xls, .csv"
      style="display: none"
      @change="handleExcelFile"
    />

    <VCard elevation="2" class="rounded-xl border">
      <!-- CABECERA PRINCIPAL -->
      <VCardTitle class="py-3 px-4 px-sm-6 bg-primary text-white d-flex justify-space-between align-center flex-wrap gap-2">
        <div class="d-flex align-center">
          <VIcon icon="ri-truck-line" size="24" class="me-2" />
          <span class="text-subtitle-1 font-weight-bold text-white">Insertar Compra / Nota de Ingreso</span>
        </div>

        <!-- BOTONES RÁPIDOS MÓVIL (SOLO EN PANTALLAS PEQUEÑAS < 960px) -->
        <div class="d-flex d-md-none align-center gap-2">
          <VBtn
            color="white"
            variant="tonal"
            size="small"
            prepend-icon="ri-download-2-line"
            class="font-weight-bold"
            @click="downloadExcelTemplate"
          >
            Plantilla
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
            Excel
          </VBtn>


        </div>
      </VCardTitle>

      <VCardText class="pt-4 px-3 px-sm-5 pb-5">
        <VForm @submit.prevent="submitPurchase">
          <!-- DATOS GENERALES DE LA COMPRA (GRID 2 COLS EN MD, 4 EN LG) -->
          <VRow class="mb-2" density="comfortable">
            <VCol cols="12" sm="6" lg="3">
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
                density="compact"
                prepend-inner-icon="ri-file-transfer-line"
                hide-details
                required
              />
            </VCol>

            <VCol cols="12" sm="6" lg="3">
              <VSelect
                v-model="formData.supplier_id"
                label="Proveedor *"
                :items="suppliers"
                item-title="name"
                item-value="id"
                variant="outlined"
                density="compact"
                prepend-inner-icon="ri-building-4-line"
                :disabled="formData.reason === 'INVENTARIO_INICIAL'"
                :required="formData.reason === 'COMPRA'"
                clearable
                hide-details
              />
            </VCol>

            <VCol cols="12" sm="6" lg="3">
              <VTextField
                v-model="formData.invoice_number"
                label="N° Factura / Nota Física"
                placeholder="Ej. F-98234"
                variant="outlined"
                density="compact"
                prepend-inner-icon="ri-file-list-3-line"
                hide-details
              />
            </VCol>

            <VCol cols="12" sm="6" lg="3">
              <VTextField
                v-model="formData.purchase_date"
                type="date"
                label="Fecha de Ingreso *"
                variant="outlined"
                density="compact"
                prepend-inner-icon="ri-calendar-line"
                hide-details
                required
              />
            </VCol>
          </VRow>

          <VDivider class="my-3" />

          <!-- BARRA DE BÚSQUEDA Y ACCIONES DESKTOP -->
          <div class="search-toolbar-box pa-2 rounded-lg border bg-surface mb-3">
            <VRow align="center" class="ma-0" density="compact">
              <!-- BUSCADOR PRINCIPAL (APROVECHA CASI TODO EL ESPACIO EN DESKTOP) -->
              <VCol cols="12" md="7" lg="8" class="pa-1">
                <VAutocomplete
                  v-model="productSearch"
                  v-model:search="searchQuery"
                  :loading="searchLoading"
                  :items="searchProducts"
                  item-title="name"
                  item-value="id"
                  return-object
                  placeholder="Escriba medicamento o escanee con pistola de barras..."
                  prepend-inner-icon="ri-search-line"
                  variant="outlined"
                  density="compact"
                  hide-no-data
                  hide-details
                  autofocus
                  class="rounded-lg"
                  @keydown.enter="handleSearchEnter"
                >
                  <template #item="{ props, item }">
                    <VListItem
                      v-bind="props"
                      :title="item.raw.name"
                      :subtitle="`${item.raw.laboratory ? 'Lab: ' + item.raw.laboratory.name : 'Genérico'} | Código: ${item.raw.barcode || 'S/C'} | Stock: ${item.raw.stock} unds`"
                    >
                      <template #append>
                        <span class="text-caption font-weight-bold text-success">Bs. {{ Number(item.raw.sale_price || 0).toFixed(2) }}</span>
                      </template>
                    </VListItem>
                  </template>
                </VAutocomplete>
              </VCol>

              <!-- BOTONES EXCEL EN DESKTOP (VISIBLES EN >= 960px) -->
              <VCol cols="12" md="5" lg="4" class="pa-1 d-none d-md-flex justify-end align-center gap-2">
                <VBtn
                  color="secondary"
                  variant="tonal"
                  size="small"
                  prepend-icon="ri-download-2-line"
                  class="font-weight-bold"
                  title="Descargar Plantilla Excel de compras"
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
                  title="Cargar archivo Excel con productos"
                  @click="triggerExcelUpload"
                >
                  Importar Excel
                </VBtn>

                <IconBtn
                  v-if="formData.items.length > 0"
                  size="small"
                  color="error"
                  variant="tonal"
                  title="Vaciar lista"
                  @click="clearAllItems"
                >
                  <VIcon icon="ri-delete-bin-line" size="18" />
                </IconBtn>
              </VCol>

              <!-- BOTÓN CÁMARA CELULAR EN MÓVIL (< 960px) -->
              <VCol cols="12" class="pa-1 d-flex d-md-none justify-space-between align-center">
                <VBtn
                  color="primary"
                  variant="flat"
                  block
                  prepend-icon="ri-qr-code-line"
                  class="font-weight-bold"
                  @click="startScanner"
                >
                  Escanear con Celular (Cámara)
                </VBtn>
              </VCol>
            </VRow>
          </div>

          <!-- VISTA MÓVIL (TARJETAS TOUCH) - visible en < 768px -->
          <div class="d-block d-md-none mb-3">
            <div v-if="formData.items.length === 0" class="text-center text-medium-emphasis py-6 border rounded-lg bg-grey-lighten-4">
              <VIcon icon="ri-shopping-cart-2-line" size="32" class="mb-1 text-disabled" />
              <div class="text-body-2 font-weight-medium">Busque productos o escanee con la cámara para agregar.</div>
            </div>

            <div
              v-for="(item, index) in formData.items"
              :key="'card-' + index"
              class="mobile-item-card border rounded-lg mb-2 overflow-hidden bg-surface elevation-1"
            >
              <div class="card-top-bar px-3 py-1 d-flex justify-space-between align-center bg-grey-lighten-4 border-b">
                <div class="d-flex align-center">
                  <VChip size="x-small" color="primary" class="font-weight-bold me-2">#{{ index + 1 }}</VChip>
                  <span class="text-caption text-medium-emphasis font-weight-bold">{{ item.laboratory_name || 'GENÉRICO' }}</span>
                </div>
                <IconBtn size="small" color="error" variant="text" @click="removeItem(index)">
                  <VIcon icon="ri-delete-bin-line" size="18" />
                </IconBtn>
              </div>

              <div class="pa-2">
                <div class="font-weight-bold text-body-2 mb-2 text-high-emphasis">{{ item.name }}</div>

                <VRow density="compact" class="mb-1">
                  <VCol cols="6">
                    <VTextField
                      v-model="item.batch"
                      label="Lote"
                      placeholder="Opcional"
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
                      class="date-input-clean"
                    />
                  </VCol>
                </VRow>

                <VRow density="compact">
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
                      label="Venta Bs"
                      density="compact"
                      variant="outlined"
                      hide-details
                    />
                  </VCol>
                </VRow>
              </div>

              <div class="px-3 py-1 bg-green-lighten-5 border-t d-flex justify-space-between align-center">
                <span class="text-caption font-weight-bold text-green-darken-3">Subtotal:</span>
                <span class="text-body-2 font-weight-black text-green-darken-4">Bs. {{ (Number(item.quantity || 0) * Number(item.unit_cost || 0)).toFixed(2) }}</span>
              </div>
            </div>
          </div>

          <!-- VISTA EN TABLA OPTIMIZADA (DESKTOP & 17"-19" MONITORS) - visible en >= 768px -->
          <div class="d-none d-md-block mb-3">
            <div class="table-compact-container border rounded-lg">
              <table class="compras-table">
                <thead>
                  <tr class="compras-th-row">
                    <th class="th-center" style="width: 38px;">#</th>
                    <th class="th-left" style="min-width: 170px;">Producto / Laboratorio</th>
                    <th class="th-left" style="width: 105px;">Lote</th>
                    <th class="th-left" style="width: 135px;">Vencimiento *</th>
                    <th class="th-center" style="width: 80px;">Cant. *</th>
                    <th class="th-center" style="width: 95px;">Costo Bs *</th>
                    <th class="th-center" style="width: 95px;">P. Venta Bs</th>
                    <th class="th-right" style="width: 105px;">Subtotal</th>
                    <th class="th-center" style="width: 42px;"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="formData.items.length === 0">
                    <td colspan="9" class="text-center text-medium-emphasis py-8">
                      <VIcon icon="ri-inbox-line" size="40" class="mb-1 text-disabled" />
                      <div class="text-body-2 font-weight-medium">Ningún medicamento anexado a la compra.</div>
                      <div class="text-caption text-medium-emphasis">Use el buscador superior o importe un archivo Excel.</div>
                    </td>
                  </tr>

                  <tr v-for="(item, index) in formData.items" :key="'table-' + index" class="compras-tr-row">
                    <td class="td-center font-weight-bold text-caption text-medium-emphasis">
                      {{ index + 1 }}
                    </td>
                    <td class="td-left">
                      <div class="product-name-cell">{{ item.name }}</div>
                      <div class="product-lab-cell">{{ item.laboratory_name || 'GENÉRICO' }}</div>
                    </td>
                    <td class="td-left">
                      <VTextField
                        v-model="item.batch"
                        density="compact"
                        variant="outlined"
                        placeholder="Opcional"
                        hide-details
                        class="cell-input"
                      />
                    </td>
                    <td class="td-left">
                      <VTextField
                        v-model="item.expiration_date"
                        type="date"
                        density="compact"
                        variant="outlined"
                        required
                        hide-details
                        class="cell-input date-cell-input"
                      />
                    </td>
                    <td class="td-center">
                      <VTextField
                        v-model.number="item.quantity"
                        type="number"
                        min="1"
                        density="compact"
                        variant="outlined"
                        hide-details
                        class="cell-input text-end"
                      />
                    </td>
                    <td class="td-center">
                      <VTextField
                        v-model.number="item.unit_cost"
                        type="number"
                        step="0.01"
                        min="0"
                        density="compact"
                        variant="outlined"
                        hide-details
                        class="cell-input text-end"
                        @input="item.sale_price = Number((item.unit_cost * 1.35).toFixed(2))"
                      />
                    </td>
                    <td class="td-center">
                      <VTextField
                        v-model.number="item.sale_price"
                        type="number"
                        step="0.01"
                        min="0"
                        density="compact"
                        variant="outlined"
                        hide-details
                        class="cell-input text-end"
                      />
                    </td>
                    <td class="td-right subtotal-cell">
                      Bs. {{ (Number(item.quantity || 0) * Number(item.unit_cost || 0)).toFixed(2) }}
                    </td>
                    <td class="td-center">
                      <IconBtn size="x-small" color="error" variant="tonal" @click="removeItem(index)" title="Eliminar">
                        <VIcon icon="ri-delete-bin-line" size="16" />
                      </IconBtn>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- RESUMEN COMPACTO DE TOTALES -->
          <VCard variant="tonal" color="primary" class="pa-3 rounded-xl mb-4">
            <VRow align="center" justify="space-between" density="compact">
              <VCol cols="12" sm="6" class="py-1">
                <div class="d-flex align-center gap-4">
                  <div>
                    <span class="text-caption text-medium-emphasis">Ítems:</span>
                    <span class="text-subtitle-1 font-weight-bold ms-2">{{ formData.items.length }}</span>
                  </div>
                  <VDivider vertical style="height: 24px;" />
                  <div>
                    <span class="text-caption text-medium-emphasis">Unidades:</span>
                    <span class="text-subtitle-1 font-weight-bold ms-2">{{ totalUnits }}</span>
                  </div>
                </div>
              </VCol>

              <VCol cols="12" sm="6" class="text-start text-sm-end py-1">
                <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis me-2">Costo Total Compra:</span>
                <span class="text-h5 font-weight-black text-primary">
                  Bs. {{ totalPurchase.toFixed(2) }}
                </span>
              </VCol>
            </VRow>
          </VCard>

          <!-- BOTONES DE ACCIÓN FINAL -->
          <div class="d-flex flex-column flex-sm-row justify-end gap-2">
            <VBtn
              color="secondary"
              variant="outlined"
              size="default"
              class="font-weight-bold"
              @click="router.push('/compras')"
            >
              <VIcon start icon="ri-close-line" /> Descartar
            </VBtn>

            <VBtn
              color="success"
              type="submit"
              size="default"
              elevation="3"
              :loading="isLoading"
              :disabled="isLoading || formData.items.length === 0"
              class="font-weight-bold text-white px-6"
            >
              <VIcon start icon="ri-checkbox-circle-line" /> Procesar Compra y Sincronizar Stock
            </VBtn>
          </div>
        </VForm>
      </VCardText>
    </VCard>

    <!-- DIALOG DE ESCANEO POR CÁMARA (SOLO MÓVIL) -->
    <VDialog v-model="isScannerModalOpen" max-width="500" persistent>
      <VCard class="rounded-xl overflow-hidden">
        <VCardTitle class="bg-primary text-white d-flex justify-space-between align-center py-3 px-4">
          <span class="d-flex align-center text-subtitle-1 font-weight-bold">
            <VIcon icon="ri-qr-code-line" class="me-2" /> Escanear Código de Barras
          </span>
          <VBtn icon="ri-close-line" variant="text" color="white" size="small" @click="stopScanner" />
        </VCardTitle>

        <VCardText class="px-3 px-sm-5 pt-4 pb-5 text-center">
          <div v-show="!isAssociating">
            <p class="text-caption text-medium-emphasis mb-2">
              Enfoque el código de barras con la cámara del celular
            </p>
            <div id="reader" style="width: 100%; max-width: 440px; margin: 0 auto; background: #fafafa; border-radius: 10px; overflow: hidden; min-height: 220px; border: 1px solid #e0e0e0;"></div>
            <div class="d-flex justify-center gap-2 mt-3">
              <VBtn color="warning" variant="tonal" size="small" v-if="isScanning" prepend-icon="ri-pause-line" @click="pauseScanner">
                Pausar
              </VBtn>
              <VBtn color="success" variant="tonal" size="small" v-else prepend-icon="ri-play-line" @click="resumeScanner">
                Reanudar
              </VBtn>
            </div>
          </div>

          <div v-if="isAssociating" class="text-left pt-2">
            <VAlert type="warning" variant="tonal" class="mb-3 text-caption rounded-lg">
              Código <strong>"{{ scannedBarcode }}"</strong> no registrado. Asócielo a un medicamento:
            </VAlert>

            <VAutocomplete
              v-model="selectedProductForAssociation"
              v-model:search="associateSearchQuery"
              :loading="associateSearchLoading"
              :items="associateProducts"
              item-title="name"
              item-value="id"
              return-object
              placeholder="Buscar medicamento..."
              prepend-inner-icon="ri-search-line"
              variant="outlined"
              density="compact"
              class="mb-3"
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
                  :subtitle="`${item.raw.laboratory ? 'Lab: ' + item.raw.laboratory.name : 'Genérico'} | Código: ${item.raw.barcode || 'S/C'}`"
                />
              </template>
            </VAutocomplete>

            <div class="d-flex justify-end gap-2 mt-3">
              <VBtn color="secondary" variant="outlined" size="small" @click="resumeScanner">Cancelar</VBtn>
              <VBtn color="primary" size="small" :disabled="!selectedProductForAssociation" :loading="isLoading" @click="associateBarcodeToProduct">
                Vincular
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

.table-compact-container {
  width: 100%;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

.compras-table {
  width: 100%;
  border-collapse: collapse;
  table-layout: auto;
}

.compras-th-row th {
  padding: 8px 6px !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  background-color: #f8fafc;
  border-bottom: 2px solid #e2e8f0;
  color: #475569;
  white-space: nowrap;
}

.compras-tr-row td {
  padding: 5px 4px !important;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
}

.th-left, .td-left { text-align: left; }
.th-center, .td-center { text-align: center; }
.th-right, .td-right { text-align: right; }

.product-name-cell {
  font-weight: 600;
  font-size: 0.82rem;
  line-height: 1.2;
  color: #1e293b;
  max-width: 240px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.product-lab-cell {
  color: #0d9488 !important; /* Emerald Teal */
  font-weight: 700;
  font-size: 0.7rem;
  letter-spacing: 0.4px;
}

.subtotal-cell {
  font-weight: 800;
  font-size: 0.82rem;
  color: #166534 !important;
  background-color: #f0fdf4 !important;
  padding-right: 8px !important;
  white-space: nowrap;
}

.cell-input :deep(.v-field__input) {
  padding: 4px 6px !important;
  min-height: 32px !important;
  height: 32px !important;
  font-size: 0.8rem !important;
}

.cell-input :deep(.v-field) {
  border-radius: 6px !important;
}

.date-cell-input :deep(input[type="date"]) {
  padding: 2px 2px !important;
  font-size: 0.78rem !important;
}

.mobile-item-card {
  transition: all 0.2s ease;
}

.mobile-item-card:hover {
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.06) !important;
}

/* Dark mode compatibility */
@media (prefers-color-scheme: dark) {
  .compras-th-row th {
    background-color: rgba(255, 255, 255, 0.04);
    border-bottom: 2px solid rgba(255, 255, 255, 0.1);
    color: #94a3b8;
  }
  .compras-tr-row td {
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
  }
  .product-name-cell {
    color: #e2e8f0;
  }
  .subtotal-cell {
    background-color: rgba(22, 101, 52, 0.2) !important;
    color: #4ade80 !important;
  }
}
</style>