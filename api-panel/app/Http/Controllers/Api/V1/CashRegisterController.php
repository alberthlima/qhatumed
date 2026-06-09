<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use App\Models\CashRegisterSession;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CashRegisterController extends Controller
{
    // List available registers
    public function index()
    {
        return response()->json(CashRegister::where('status', true)->get());
    }

    // Get current active session for user
    public function currentSession(Request $request)
    {
        $userId = auth()->id() ?? 1;
        $session = CashRegisterSession::where('user_id', $userId)
            ->where('status', 'OPEN')
            ->with('cashRegister')
            ->first();
            
        if (!$session) {
            return response()->json(['message' => 'No open session'], 404);
        }
        
        // Calculate current balances (total calculated by system - only cash!)
        $incomings = $session->movements()->where('type', 'IN')->where('payment_method', 'EFECTIVO')->sum('amount');
        $outgoings = $session->movements()->where('type', 'OUT')->where('payment_method', 'EFECTIVO')->sum('amount');
        $system_total = $session->opening_amount + $incomings - $outgoings;

        // Breakdown of digital/cash movements for the shift
        $cash_sales = $session->movements()
            ->where('type', 'IN')
            ->where('payment_method', 'EFECTIVO')
            ->whereNotNull('reference_id')
            ->sum('amount');
            
        $qr_sales = $session->movements()
            ->where('type', 'IN')
            ->where('payment_method', 'QR')
            ->sum('amount');
            
        $card_sales = $session->movements()
            ->where('type', 'IN')
            ->where('payment_method', 'TARJETA')
            ->sum('amount');
            
        $manual_in = $session->movements()
            ->where('type', 'IN')
            ->where('payment_method', 'EFECTIVO')
            ->whereNull('reference_id')
            ->sum('amount');
        
        return response()->json([
            'session' => $session,
            'calculated_balance' => $system_total,
            'details' => [
                'cash_sales' => round($cash_sales, 2),
                'qr_sales' => round($qr_sales, 2),
                'card_sales' => round($card_sales, 2),
                'manual_in' => round($manual_in, 2),
                'manual_out' => round($outgoings, 2),
                'opening_amount' => round($session->opening_amount, 2)
            ]
        ]);
    }

    // Open a new session
    public function openSession(Request $request)
    {
        $request->validate([
            'cash_register_id' => 'required|exists:cash_registers,id',
            'opening_amount' => 'required|numeric|min:0'
        ]);

        $userId = auth()->id() ?? 1;

        // Check if user already has an open session
        $existing = CashRegisterSession::where('user_id', $userId)
            ->where('status', 'OPEN')
            ->first();
            
        if ($existing) {
            return response()->json(['error' => 'You already have an open session in ' . $existing->cashRegister->name], 403);
        }

        $session = CashRegisterSession::create([
            'cash_register_id' => $request->cash_register_id,
            'user_id' => $userId,
            'opening_time' => now(),
            'opening_amount' => $request->opening_amount,
            'status' => 'OPEN'
        ]);

        return response()->json($session, 201);
    }

    // Blind close session
    public function closeSession(Request $request, $id)
    {
        $request->validate([
            'reported_amount' => 'required|numeric|min:0'
        ]);

        $session = CashRegisterSession::findOrFail($id);
        
        if ($session->status === 'CLOSED') {
            return response()->json(['error' => 'Session is already closed'], 400);
        }

        return DB::transaction(function() use ($session, $request) {
            $incomings = $session->movements()->where('type', 'IN')->where('payment_method', 'EFECTIVO')->sum('amount');
            $outgoings = $session->movements()->where('type', 'OUT')->where('payment_method', 'EFECTIVO')->sum('amount');
            
            $expected = $session->opening_amount + $incomings - $outgoings;
            $reported = $request->reported_amount;
            $discrepancy = $reported - $expected;

            $session->update([
                'closing_time' => now(),
                'closing_amount' => $reported,
                'discrepancy' => $discrepancy,
                'status' => 'CLOSED'
            ]);

            return response()->json([
                'message' => 'Caja Cerrada Exitosamente.',
                'session' => $session
            ]);
        });
    }

    // List all sessions (history)
    public function sessions(Request $request)
    {
        $sessions = CashRegisterSession::with(['cashRegister', 'user'])
            ->orderBy('id', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json($sessions);
    }
}
