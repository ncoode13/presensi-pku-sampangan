<?php
namespace App\Http\Controllers\Api;

use App\Models\EmploymentStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EmploymentStatusController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = EmploymentStatus::all();
        return $this->respondSuccess($data);
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

                $data       = new EmploymentStatus();
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
    public function show(EmploymentStatus $employment_status)
    {
        return $this->respondSuccess($employment_status);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, EmploymentStatus $employment_status)
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
            return DB::transaction(function () use ($request, $employment_status) {

                $data       = $employment_status;
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
    public function destroy(EmploymentStatus $employment_status)
    {
        try {
            return DB::transaction(function () use ($employment_status) {

                $employment_status->delete();

                return $this->respondOk();
            });
        } catch (\Throwable $th) {
            return $this->respondError($th->getMessage());
        }
    }
}
