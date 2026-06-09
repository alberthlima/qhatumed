<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = \App\Models\Customer::query();
        if ($request->filled('search')) {
            $query->where('name', 'LIKE', '%' . $request->search . '%')
                  ->orWhere('document_number', 'LIKE', '%' . $request->search . '%');
        }
        return response()->json($query->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        // Map frontend fields to backend DB columns
        if ($request->has('first_name')) {
            $request->merge(['name' => $request->first_name]);
        }
        if ($request->has('document_type')) {
            $request->merge(['type_document_id' => $request->document_type]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type_document_id' => 'nullable|integer',
            'document_number' => 'nullable|string|max:50',
            'complement' => 'nullable|string|max:10',
            'phone' => 'nullable|string|max:25',
            'address' => 'nullable|string|max:200',
            'email' => 'nullable|string|max:100',
            'status' => 'boolean'
        ]);

        $item = \App\Models\Customer::create($validated);
        return response()->json($item, 201);
    }

    public function show(Customer $customer)
    {
        return response()->json($customer);
    }

    public function update(Request $request, Customer $customer)
    {
        // Map frontend fields to backend DB columns
        if ($request->has('first_name')) {
            $request->merge(['name' => $request->first_name]);
        }
        if ($request->has('document_type')) {
            $request->merge(['type_document_id' => $request->document_type]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type_document_id' => 'nullable|integer',
            'document_number' => 'nullable|string|max:50',
            'complement' => 'nullable|string|max:10',
            'phone' => 'nullable|string|max:25',
            'address' => 'nullable|string|max:200',
            'email' => 'nullable|string|max:100',
            'status' => 'boolean'
        ]);

        $customer->update($validated);
        return response()->json($customer);
    }

    public function destroy(Customer $customer)
    {
        $customer->update(['status' => false]);
        return response()->json(['message' => 'Disabled']);
    }
}
