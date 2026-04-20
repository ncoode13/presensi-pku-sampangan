<?php

namespace App\Http\Controllers\Api;

use App\Models\Relationship;
use Illuminate\Http\Request;

class RelationshipController extends Controller
{

    public function index()
    {
        $relationships = Relationship::all();

        return $this->respondSuccess($relationships, 'Data hubungan berhasil diambil');
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
    public function show(Relationship $relationship)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Relationship $relationship)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Relationship $relationship)
    {
        //
    }
}
