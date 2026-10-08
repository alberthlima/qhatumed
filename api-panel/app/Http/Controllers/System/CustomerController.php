<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            // Tipos 1 (CI) y 5 (NIT) son estrictamente numéricos conforme a normativa del SIN
            'document_number' => [
                'nullable', 'string', 'max:50',
                Rule::when(
                    in_array((int) $request->input('type_document_id', 1), [1, 5], true),
                    ['regex:/^\d+$/']
                ),
            ],
            'complement' => 'nullable|string|max:10',
            'phone' => ['nullable', 'string', 'max:25', 'regex:/^(\+)?\d+$/'],
            'address' => 'nullable|string|max:200',
            'email' => 'nullable|string|max:100',
            'status' => 'boolean'
        ], [
            'document_number.regex' => 'El campo Número de Documento es estrictamente numérico para documentos tipo CI o NIT.',
            'phone.regex' => 'El campo Celular / WhatsApp debe contener únicamente dígitos numéricos.',
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
            // Tipos 1 (CI) y 5 (NIT) son estrictamente numéricos conforme a normativa del SIN
            'document_number' => [
                'nullable', 'string', 'max:50',
                Rule::when(
                    in_array((int) $request->input('type_document_id', 1), [1, 5], true),
                    ['regex:/^\d+$/']
                ),
            ],
            'complement' => 'nullable|string|max:10',
            'phone' => ['nullable', 'string', 'max:25', 'regex:/^(\+)?\d+$/'],
            'address' => 'nullable|string|max:200',
            'email' => 'nullable|string|max:100',
            'status' => 'boolean'
        ], [
            'document_number.regex' => 'El campo Número de Documento es estrictamente numérico para documentos tipo CI o NIT.',
            'phone.regex' => 'El campo Celular / WhatsApp debe contener únicamente dígitos numéricos.',
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
