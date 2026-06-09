<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use Illuminate\Http\Request;

class CashMovementController extends Controller
{
    // List movements for a given session
    public function index($sessionId)
    {
        $movements = CashMovement::where('cash_register_session_id', $sessionId)
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json($movements);
    }

    // Store a manual movement (Ingreso or Egreso manual)
    public function store(Request $request)
    {
        $request->validate([
            'cash_register_session_id' => 'required|exists:cash_register_sessions,id',
            'type' => 'required|in:IN,OUT',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
            'payment_method' => 'nullable|string'
        ]);

        $session = CashRegisterSession::findOrFail($request->cash_register_session_id);
        
        if ($session->status !== 'OPEN') {
            return response()->json(['error' => 'Cannot add movements to a closed session.'], 403);
        }

        $movement = CashMovement::create([
            'cash_register_session_id' => $session->id,
            'user_id' => auth()->id() ?? 1,
            'type' => $request->type,
            'amount' => $request->amount,
            'payment_method' => $request->payment_method ?? 'EFECTIVO',
            'description' => $request->description
        ]);

        return response()->json($movement, 201);
    }

    // Global history: filter by type (IN/OUT), register, date
    public function historial(Request $request)
    {
        $query = CashMovement::with(['session.cashRegister', 'session.user', 'user'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('cash_register_id')) {
            $query->whereHas('session', function ($q) use ($request) {
                $q->where('cash_register_id', $request->cash_register_id);
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $movements = $query->paginate($request->per_page ?? 50);

        return response()->json($movements);
    }
}
