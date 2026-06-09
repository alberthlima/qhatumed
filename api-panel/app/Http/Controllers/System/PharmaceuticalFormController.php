<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\PharmaceuticalForm;
use Illuminate\Http\Request;

class PharmaceuticalFormController extends Controller
{
    public function index(Request $request)
    {
        $query = PharmaceuticalForm::query();
        if ($request->filled('search')) {
            $query->where('name', 'LIKE', '%' . $request->search . '%');
        }
        return response()->json($query->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'status' => 'boolean'
        ]);

        $item = PharmaceuticalForm::create($validated);
        return response()->json($item, 201);
    }

    public function show(PharmaceuticalForm $pharmaceuticalForm)
    {
        return response()->json($pharmaceuticalForm);
    }

    public function update(Request $request, PharmaceuticalForm $pharmaceuticalForm)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'status' => 'boolean'
        ]);

        $pharmaceuticalForm->update($validated);
        return response()->json($pharmaceuticalForm);
    }

    public function destroy(PharmaceuticalForm $pharmaceuticalForm)
    {
        $pharmaceuticalForm->update(['status' => false]);
        return response()->json(['message' => 'Disabled']);
    }
}
