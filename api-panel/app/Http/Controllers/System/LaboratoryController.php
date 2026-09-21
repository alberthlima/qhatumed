<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\Laboratory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
            'status' => 'nullable|boolean',
            'image' => 'nullable|image|max:2048'
        ]);

        $data = [
            'name' => $validated['name'],
            'status' => $request->has('status') ? $request->boolean('status') : true
        ];

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('laboratories', 'public');
        }

        $item = Laboratory::create($data);
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
            'status' => 'nullable|boolean',
            'image' => 'nullable|image|max:2048'
        ]);

        $data = ['name' => $validated['name']];
        if ($request->has('status')) {
            $data['status'] = $request->boolean('status');
        }

        if ($request->hasFile('image')) {
            if ($laboratory->image_path && Storage::disk('public')->exists($laboratory->image_path)) {
                Storage::disk('public')->delete($laboratory->image_path);
            }
            $data['image_path'] = $request->file('image')->store('laboratories', 'public');
        }

        $laboratory->update($data);
        return response()->json($laboratory);
    }

    public function destroy(Laboratory $laboratory)
    {
        $laboratory->update(['status' => false]);
        return response()->json(['message' => 'Laboratory disabled']);
    }
}