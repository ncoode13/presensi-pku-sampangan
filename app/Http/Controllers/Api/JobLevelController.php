<?php
namespace App\Http\Controllers\Api;

use App\Models\JobLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class JobLevelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = JobLevel::all();
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

                $data       = new JobLevel();
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
    public function show(JobLevel $job_level)
    {
        return $this->respondSuccess($job_level);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, JobLevel $job_level)
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
            return DB::transaction(function () use ($request, $job_level) {

                $data       = $job_level;
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
    public function destroy(JobLevel $job_level)
    {
        try {
            return DB::transaction(function () use ($job_level) {

                $job_level->delete();

                return $this->respondOk();
            });
        } catch (\Throwable $th) {
            return $this->respondError($th->getMessage());
        }
    }
}
