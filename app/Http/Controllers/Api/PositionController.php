<?php
namespace App\Http\Controllers\Api;

use App\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PositionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Position::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'name' => 'required',
            ], [
                'name.required' => 'Nama harus diisi.',
            ]
        );

        if ($validator->fails()) {
            $error = $validator->errors()->first();
            return $this->respondError($error);
        }

        try {
            return DB::transaction(function () use ($request) {

                $data       = new Position();
                $data->name = $request->name;
                $data->save();

                return $this->respondSuccess($data);
            });
        } catch (\Throwable $th) {
            return $this->respondError($th->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Position $position)
    {
        return $this->respondSuccess($position);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Position $position)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'name' => 'required',
            ], [
                'name.required' => 'Nama harus diisi.',
            ]
        );

        if ($validator->fails()) {
            $error = $validator->errors()->first();
            return $this->respondError($error);
        }

        try {
            return DB::transaction(function () use ($request, $position) {

                $data       = $position;
                $data->name = $request->name;
                $data->save();

                return $this->respondSuccess($data);
            });
        } catch (\Throwable $th) {
            return $this->respondError($th->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Position $position)
    {
        try {
            return DB::transaction(function () use ($position) {

                $position->delete();

                return $this->respondOk();
            });
        } catch (\Throwable $th) {
            return $this->respondError($th->getMessage());
        }
    }
}
