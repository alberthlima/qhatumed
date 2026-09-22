<?php

namespace App\Http\Controllers;

use App\Models\Dispenser;
use Illuminate\Http\Request;

class DispenserController extends Controller
{
    public function index(Request $request)
    {
        $query = Dispenser::query();
        
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        return response()->json($query->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'status' => 'nullable|integer'
        ]);

        $dispenser = Dispenser::create([
            'name' => mb_strtoupper(trim($validated['name']), 'UTF-8'),
            'status' => $request->has('status') ? (int)$request->status : 1
        ]);

        return response()->json($dispenser, 201);
    }

    public function show(Dispenser $dispenser)
    {
        return response()->json($dispenser);
    }

    public function update(Request $request, Dispenser $dispenser)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'status' => 'sometimes|integer'
        ]);

        $data = [];
        if ($request->has('name')) {
            $data['name'] = mb_strtoupper(trim($request->name), 'UTF-8');
        }
        if ($request->has('status')) {
            $data['status'] = (int)$request->status;
        }

        $dispenser->update($data);
        return response()->json($dispenser);
    }

    public function destroy(Dispenser $dispenser)
    {
        $dispenser->status = 0;
        $dispenser->save();
        return response()->json(['message' => 'Dispenser inactivated'], 200);
    }

    public function enable(Dispenser $dispenser)
    {
        $dispenser->status = 1;
        $dispenser->save();
        return response()->json(['message' => 'Dispenser activated', 'data' => $dispenser], 200);
    }
}