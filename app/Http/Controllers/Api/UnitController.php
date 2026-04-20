<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller as ApiController;
use App\Models\Unit;
use Illuminate\Http\Request;

class UnitController extends ApiController
{
    public function index()
    {
        $units = Unit::select('id', 'name')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $units
        ]);
    }
}
