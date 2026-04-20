<?php
namespace App\Http\Controllers\Api;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = Department::all();
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

                $data       = new Department();
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
    public function show(Department $department)
    {
        return $this->respondSuccess($department);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Department $department)
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
            return DB::transaction(function () use ($request, $department) {

                $data       = $department;
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
    public function destroy(Department $department)
    {
        try {
            return DB::transaction(function () use ($department) {

                $department->delete();

                return $this->respondOk();
            });
        } catch (\Throwable $th) {
            return $this->respondError($th->getMessage());
        }
    }
}
