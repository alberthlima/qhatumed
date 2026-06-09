<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\Laboratory;
use Illuminate\Http\Request;

class LaboratoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Laboratory::query();
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

        $item = Laboratory::create($validated);
        return response()->json($item, 201);
    }

    public function show(Laboratory $laboratory)
    {
        return response()->json($laboratory);
    }

    public function update(Request $request, Laboratory $laboratory)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'status' => 'boolean'
        ]);

        $laboratory->update($validated);
        return response()->json($laboratory);
    }

    public function destroy(Laboratory $laboratory)
    {
        $laboratory->update(['status' => false]);
        return response()->json(['message' => 'Laboratory disabled']);
    }
}
