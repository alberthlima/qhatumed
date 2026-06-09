import xlrd

xls_path = r'c:\xampp\htdocs\farmacia_misalud\farmacia2026\producto.xls'
print("Cargando XLS...")
book = xlrd.open_workbook(xls_path)
sheet = book.sheet_by_index(0)

print(f"Hojas en el libro: {book.nsheets}")
print(f"Nombre de la primera hoja: {sheet.name}")
print(f"Dimensiones: {sheet.nrows} filas, {sheet.ncols} columnas")

print("\n--- Cabecera (Fila 0) ---")
headers = [sheet.cell_value(0, col) for col in range(sheet.ncols)]
print(headers)

print("\n--- Primeras 10 Filas de Datos ---")
for r in range(1, min(11, sheet.nrows)):
    row = [sheet.cell_value(r, col) for col in range(sheet.ncols)]
    print(f"Fila {r}: {row}")
