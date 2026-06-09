<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use Illuminate\Http\Request;

class CompanySettingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    /**
     * Display the company settings.
     */
    public function show()
    {
        $settings = CompanySetting::first();
        if (!$settings) {
            $settings = CompanySetting::create([
                'siat_environment' => 2,
                'siat_modality' => 2, // Computarizada por defecto para farmacia
                'siat_branch_code' => 0,
                'siat_pos_code' => 0
            ]);
        }
        return response()->json($settings);
    }

    /**
     * Update the company settings.
     */
    public function update(Request $request)
    {
        $settings = CompanySetting::first();
        if (!$settings) {
            $settings = new CompanySetting();
        }

        $settings->fill($request->all());
        $settings->save();

        return response()->json([
            'message' => 'Configuración actualizada correctamente',
            'data' => $settings
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CompanySetting $companySetting)
    {
        //
    }
}
