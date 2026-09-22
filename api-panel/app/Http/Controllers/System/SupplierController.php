<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $query = Supplier::query();
        if ($request->filled('search')) {
            $query->where('name', 'LIKE', '%' . $request->search . '%')
                  ->orWhere('contact', 'LIKE', '%' . $request->search . '%');
        }
        return response()->json($query->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact' => 'nullable|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'status' => 'nullable|boolean'
        ]);

        $data = [
            'name' => mb_strtoupper(trim($validated['name']), 'UTF-8'),
            'contact' => $request->contact ?? $request->contact_name ?? null,
            'phone' => $validated['phone'] ?? null,
            'status' => $request->has('status') ? $request->boolean('status') : true
        ];

        $item = Supplier::create($data);
        return response()->json($item, 201);
    }

    public function show(Supplier $supplier)
    {
        return response()->json($supplier);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact' => 'nullable|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'status' => 'nullable|boolean'
        ]);

        $data = [
            'name' => mb_strtoupper(trim($validated['name']), 'UTF-8'),
            'contact' => $request->contact ?? $request->contact_name ?? null,
            'phone' => $validated['phone'] ?? null,
        ];
        if ($request->has('status')) {
            $data['status'] = $request->boolean('status');
        }

        $supplier->update($data);
        return response()->json($supplier);
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->update(['status' => false]);
        return response()->json(['message' => 'Supplier disabled']);
    }

    public function enable(Supplier $supplier)
    {
        $supplier->update(['status' => true]);
        return response()->json(['message' => 'Supplier enabled', 'data' => $supplier]);
    }
}