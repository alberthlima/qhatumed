<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Http\Requests\StoreProductRequest;
use App\Http\Resources\ProductResource;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query()
            ->with(['supplier', 'laboratory', 'pharmaceuticalForm', 'activeBatches', 'barcodes']);

        // Optional search by name or barcode (primary and alternates)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('barcode', 'LIKE', "%{$search}%")
                  ->orWhereHas('barcodes', function ($bq) use ($search) {
                      $bq->where('barcode', 'LIKE', "%{$search}%");
                  });
            });
        }

        // Apply sorting
        $sortField = $request->input('sort_by', 'id');
        $sortOrder = $request->input('sort_order', 'desc');
        $allowedFields = ['id', 'barcode', 'name', 'sale_price', 'stock'];
        
        if (in_array($sortField, $allowedFields)) {
            $query->orderBy($sortField, $sortOrder);
        } else {
            $query->orderBy('id', 'desc');
        }

        // Return paginated to prevent massive JSON payloads
        $products = $query->paginate($request->input('per_page', 20));
        
        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request)
    {
        $product = Product::create($request->validated());
        
        return new ProductResource($product->load(['supplier', 'laboratory']));
    }

    public function show(Product $product)
    {
        $product->loadMissing(['supplier', 'laboratory', 'pharmaceuticalForm', 'activeBatches', 'barcodes']);
        
        return new ProductResource($product);
    }

    public function update(StoreProductRequest $request, Product $product)
    {
        $product->update($request->validated());
        
        return new ProductResource($product);
    }

    /**
     * Asocia un nuevo código de barras al producto.
     * - Si el producto NO tiene código principal, lo asigna ahí.
     * - Siempre guarda el código en product_barcodes para trazabilidad completa.
     */
    public function associateBarcode(Request $request, Product $product)
    {
        $request->validate([
            'barcode' => 'required|string|max:100',
            'note'    => 'nullable|string|max:150',
        ]);

        $barcode = trim($request->barcode);

        // Verificar que el código no esté en uso por otro producto (tabla principal)
        $existsInMain = Product::where('barcode', $barcode)
            ->where('id', '!=', $product->id)
            ->exists();

        if ($existsInMain) {
            return response()->json([
                'message' => 'Este código de barras ya está asignado a otro producto.'
            ], 422);
        }

        // Verificar que el código no esté en la tabla de alternos de otro producto
        $existsInAlternate = ProductBarcode::where('barcode', $barcode)
            ->where('product_id', '!=', $product->id)
            ->exists();

        if ($existsInAlternate) {
            return response()->json([
                'message' => 'Este código de barras ya está registrado como alterno de otro producto.'
            ], 422);
        }

        // Si el producto aún no tiene código principal, asignárselo
        if (empty($product->barcode)) {
            $product->update(['barcode' => $barcode]);
        }

        // Guardar en la tabla de códigos alternos (evitar duplicado para este mismo producto)
        ProductBarcode::firstOrCreate(
            ['product_id' => $product->id, 'barcode' => $barcode],
            ['note' => $request->note]
        );

        $product->loadMissing(['supplier', 'laboratory', 'pharmaceuticalForm', 'barcodes']);

        return new ProductResource($product);
    }

    /**
     * Lista todos los códigos de barras registrados de un producto.
     * Útil para trazabilidad y consultas SQL desde reportes.
     */
    public function barcodes(Product $product)
    {
        return response()->json([
            'product_id'   => $product->id,
            'product_name' => $product->name,
            'primary_barcode' => $product->barcode,
            'alternate_barcodes' => $product->barcodes->map(fn ($b) => [
                'id'         => $b->id,
                'barcode'    => $b->barcode,
                'note'       => $b->note,
                'registered' => $b->created_at->format('d/m/Y'),
            ]),
        ]);
    }

    public function destroy(Product $product)
    {
        $product->update(['status' => false]);
        return response()->json(['message' => 'Product disabled successfully.']);
    }
}
