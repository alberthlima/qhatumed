<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Http\Requests\StoreProductRequest;
use App\Http\Resources\ProductResource;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query()
            ->with(['supplier', 'laboratory', 'pharmaceuticalForm', 'activeBatches']);

        // Optional search by name or barcode
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('barcode', 'LIKE', "%{$search}%");
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
        // For show, we might want to include the active batches
        $product->loadMissing(['supplier', 'laboratory', 'pharmaceuticalForm', 'activeBatches']);
        
        return new ProductResource($product);
    }

    public function update(StoreProductRequest $request, Product $product)
    {
        $product->update($request->validated());
        
        return new ProductResource($product);
    }

    public function destroy(Product $product)
    {
        $product->update(['status' => false]);
        return response()->json(['message' => 'Product disabled successfully.']);
    }
}
