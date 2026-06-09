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
        $request->validate([
            'name' => 'required|string|max:255',
            'status' => 'nullable|integer'
        ]);

        $dispenser = Dispenser::create($request->all());
        return response()->json($dispenser, 201);
    }

    public function show(Dispenser $dispenser)
    {
        return response()->json($dispenser);
    }

    public function update(Request $request, Dispenser $dispenser)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'status' => 'sometimes|integer'
        ]);

        $dispenser->update($request->all());
        return response()->json($dispenser);
    }

    public function destroy(Dispenser $dispenser)
    {
        // soft delete or generic logical delete usually done via status
        $dispenser->status = 0;
        $dispenser->save();
        return response()->json(['message' => 'Dispenser inactivated'], 200);
    }
}
