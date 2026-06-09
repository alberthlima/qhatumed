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

// CSV Import references
const csvInput = ref(null)
const importWarnings = ref([])
const showImportWarnings = ref(false)
const importSuccessMessage = ref('')
const showImportSuccess = ref(false)

const triggerCsvFile = () => {
  csvInput.value?.click()
}

const downloadCsvTemplate = async () => {
  try {
    isLoading.value = true
    const response = await $api('/v1/products?per_page=5', { method: 'GET' })
    const productsList = response.data?.data || response.data || []
    
    let csvContent = "Codigo_Barras,Cantidad,Costo_Unitario,Precio_Venta,Lote,Fecha_Vencimiento\n"
    
    if (productsList.length > 0) {
      productsList.forEach((product, idx) => {
        const barcode = product.barcode || `GENERIC00${idx + 1}`
        const cost = product.average_cost > 0 ? product.average_cost : 10.00
        const price = product.sale_price > 0 ? product.sale_price : Number((cost * 1.35).toFixed(2))
        const sampleDate = new Date()
        sampleDate.setFullYear(sampleDate.getFullYear() + 2) // Expiration in 2 years
        const dateStr = sampleDate.toISOString().slice(0, 10)
        
        csvContent += `"${barcode}",${10 * (idx + 1)},${cost},${price},"LOTE-EJ-${idx + 1}","${dateStr}"\n`
      })
    } else {
      csvContent += `"7501234567890",50,12.50,16.90,"LOTE-EJ-1","2028-12-31"\n`
      csvContent += `"7509876543210",100,5.20,7.00,"LOTE-EJ-2","2027-06-30"\n`
    }
    
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement("a")
    link.setAttribute("href", url)
    link.setAttribute("download", "plantilla_ejemplo_inventario.csv")
    link.style.visibility = 'hidden'
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  } catch (error) {
    toast.showError("Error al generar la plantilla de ejemplo.", "Error")
  } finally {
    isLoading.value = false
  }
}

const handleCsvUpload = async (event) => {
  const file = event.target.files[0]
  if (!file) return

  // Reset input value
  event.target.value = ''

  const reader = new FileReader()
  reader.onload = async (e) => {
    const text = e.target.result
    await parseAndAddCsv(text)
  }
  reader.readAsText(file, 'UTF-8')
}

const parseAndAddCsv = async (text) => {
  const lines = text.split(/\r?\n/)
  if (lines.length < 2) {
    toast.showWarning('El archivo CSV está vacío o no tiene el formato correcto.', 'Advertencia')
    return
  }

  const headerLine = lines[0].toLowerCase()
  const delimiter = headerLine.includes(';') ? ';' : ','
  const headers = headerLine.split(delimiter).map(h => h.trim().replace(/^["']|["']$/g, ''))

  const idxBarcode = headers.findIndex(h => h.includes('codigo') || h.includes('barcode') || h.includes('barras') || h.includes('cod'))
  const idxQty = headers.findIndex(h => h.includes('cant') || h.includes('quantity'))
  const idxCost = headers.findIndex(h => h.includes('cost') || h.includes('precio') || h.includes('compra'))
  const idxSalePrice = headers.findIndex(h => h.includes('venta') || h.includes('sale') || h.includes('precio_vta'))
  const idxBatch = headers.findIndex(h => h.includes('lote') || h.includes('batch'))
  const idxExp = headers.findIndex(h => h.includes('venc') || h.includes('exp') || h.includes('fecha'))

  if (idxBarcode === -1 || idxQty === -1 || idxCost === -1) {
    toast.showWarning('No se encontraron las columnas obligatorias en el CSV. El archivo debe contener al menos: Código de barras, Cantidad y Costo Unitario.', 'Advertencia')
    return
  }

  const cleanNumberString = (str) => {
    if (!str) return '';
    let clean = str.trim();
    if (clean.includes(',') && clean.includes('.')) {
      clean = clean.replace(/\./g, '');
    }
    clean = clean.replace(',', '.');
    return clean;
  }

  isLoading.value = true
  let addedCount = 0
  let errorMessages = []

  for (let i = 1; i < lines.length; i++) {
    const line = lines[i].trim()
    if (!line) continue

    const cols = line.split(delimiter).map(c => c.trim().replace(/^["']|["']$/g, ''))
    if (cols.length < Math.max(idxBarcode, idxQty, idxCost) + 1) continue

    const barcode = cols[idxBarcode]
    const quantity = parseInt(cols[idxQty])
    const unitCost = parseFloat(cleanNumberString(cols[idxCost]))
    const csvSalePrice = idxSalePrice !== -1 ? parseFloat(cleanNumberString(cols[idxSalePrice])) : NaN
    const batch = idxBatch !== -1 ? cols[idxBatch] : ''
    const expDate = idxExp !== -1 ? cols[idxExp] : ''

    if (!barcode) continue

    if (isNaN(quantity) || quantity < 1) {
      errorMessages.push(`Fila ${i + 1}: Cantidad inválida para el producto con código ${barcode}.`)
      continue
    }
    if (isNaN(unitCost) || unitCost < 0) {
      errorMessages.push(`Fila ${i + 1}: Costo inválido para el producto con código ${barcode}.`)
      continue
    }

    try {
      const response = await $api(`/v1/products?search=${barcode}`, { method: 'GET' })
      const productsList = response.data?.data || response.data || []
      const matchedProduct = productsList.find(p => p.barcode === barcode)

      if (!matchedProduct) {
        errorMessages.push(`Fila ${i + 1}: El código de barras "${barcode}" no está registrado en el catálogo.`)
        continue
      }

      const calculatedSalePrice = !isNaN(csvSalePrice) && csvSalePrice >= 0 
        ? csvSalePrice 
        : (matchedProduct.sale_price > 0 ? matchedProduct.sale_price : Number((unitCost * 1.35).toFixed(2)))

      const exists = formData.value.items.find(item => item.product_id === matchedProduct.id)
      if (exists) {
        exists.quantity += quantity
        exists.unit_cost = unitCost
        exists.sale_price = calculatedSalePrice
        if (batch) exists.batch = batch
        if (expDate) exists.expiration_date = expDate
      } else {
        formData.value.items.push({
          product_id: matchedProduct.id,
          name: matchedProduct.name,
          batch: batch || '',
          expiration_date: expDate || '',
          quantity: quantity,
          unit_cost: unitCost,
          sale_price: calculatedSalePrice
        })
      }
      addedCount++
    } catch (err) {
      errorMessages.push(`Fila ${i + 1}: Error de red al buscar el código ${barcode}.`)
    }
  }

  isLoading.value = false
  
  importWarnings.value = errorMessages
  showImportWarnings.value = errorMessages.length > 0
  
  importSuccessMessage.value = `Se importaron ${addedCount} productos exitosamente.`
  showImportSuccess.value = addedCount > 0
}

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
  <div class="pa-4">
    <VRow>
      <VCol cols="12" md="10" class="mx-auto">

        <VCard>
          <VCardTitle class="py-4 bg-primary text-white d-flex align-center">
            <VIcon icon="ri-truck-line" class="me-2" /> Insertar Compra / Nota de Ingreso
          </VCardTitle>

          <VCardText class="pt-6">
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

                <VCol cols="12" md="6" class="text-right">
                  <div class="d-flex justify-end gap-2 mb-1">
                    <VBtn
                      color="secondary"
                      variant="outlined"
                      prepend-icon="ri-download-line"
                      @click="downloadCsvTemplate"
                    >
                      Plantilla Ejemplo
                    </VBtn>
                    <VBtn
                      color="info"
                      variant="tonal"
                      prepend-icon="ri-file-excel-line"
                      @click="triggerCsvFile"
                    >
                      Importar desde CSV
                    </VBtn>
                  </div>
                  <input
                    ref="csvInput"
                    type="file"
                    accept=".csv"
                    class="d-none"
                    @change="handleCsvUpload"
                  />
                </VCol>
              </VRow>

              <!-- Éxito de Importación -->
              <VAlert
                v-if="showImportSuccess && importSuccessMessage"
                v-model="showImportSuccess"
                title="Importación Exitosa"
                type="success"
                variant="tonal"
                closable
                class="mb-4 text-left"
              >
                {{ importSuccessMessage }}
              </VAlert>

              <!-- Advertencias de Importación -->
              <VAlert
                v-if="showImportWarnings && importWarnings.length > 0"
                v-model="showImportWarnings"
                title="Errores / Advertencias de Importación CSV"
                type="error"
                variant="tonal"
                closable
                class="mb-4 text-left"
              >
                <ul class="pl-4 mt-2">
                  <li v-for="(warn, wIdx) in importWarnings" :key="wIdx" class="text-caption">{{ warn }}</li>
                </ul>
              </VAlert>

              <!-- DATA TABLE Lotes -->
              <VTable density="compact" class="border rounded align-middle">
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
                <VCol cols="12" class="text-end">
                    <div class="text-h6">Costo Total Compra: <span class="font-weight-black text-primary">Bs. {{ totalPurchase.toFixed(2) }}</span></div>
                   <div class="text-caption text-medium-emphasis">El Costo Promedio (CPP) del inventario se auto-recalculará al procesar.</div>
                </VCol>
              </VRow>

              <div class="d-flex justify-end gap-3 mt-6">
                <VBtn color="secondary" variant="outlined" @click="router.push('/compras')">Descartar</VBtn>
                <VBtn color="success" type="submit" :loading="isLoading" size="large"><VIcon start icon="ri-check-line" /> Procesar Compra y Sincronizar Stock</VBtn>
              </div>
            </VForm>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
