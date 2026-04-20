<?php

namespace App\Http\Controllers\Api;


use App\Http\Traits\ApiResponse;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Validator;

class Controller extends BaseController
{
    use ApiResponse;

    public function validateUploadFile($data)
    {

        $validator_file = Validator::make(
            $data,
            [
                'photo' => 'image|mimes:jpeg,png,jpg|max:2048',

            ],
            [
                'photo.image' => 'Format photo harus gambar',
                'photo.mimes' => 'Format gambar photo harus jpeg, png, jpg',
                'photo.max'   => 'Ukuran photo maksimal 2 MB',
            ]
        );

        if ($validator_file->fails()) {
            return $validator_file->errors()->first();
        }

        return false;
    }

    public function saveImageFile($request)
    {
        $path = null;

        if ($request->hasFile('photo')) {
            if (!$request->file('photo')->isValid()) {
                throw new \Exception("File image not valid", 1);
            }
            try {
                $random    = mt_rand(100000, 999999);
                $timestamp = round(microtime(true) * 1000);
                $status    = $request->input('status');
                $time      = str_replace(':', '', $request->input('time'));
                $userId    = $request->input('user_id');

                $extension = $request->file('photo')->getClientOriginalExtension();
                $fileName  = $time . '-' . $userId . '-' . $status . '-' . $timestamp . $random . '.' . $extension;

                $path = $request->file('photo')->storeAs('images/photo', $fileName, 'public');
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage(), 1);
            }
        }

        return $path;
    }
}
