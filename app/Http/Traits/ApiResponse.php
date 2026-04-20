<?php

namespace App\Http\Traits;

use Error;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait ApiResponse
{
    public function respondOk($message = 'Success')
    {
        return response(['success' => true, 'message' => $message, 'data' => null, 'code' => 200]);
    }

    public function respondSuccess($data = null, $message = 'Success')
    {

        // if ((is_array($data) && empty($data)) or ($data instanceof Collection && $data->isEmpty()) or $data === null) {
        //     return $this->respondError('Data tidak ditemukan', Response::HTTP_NOT_FOUND);
        // }

        return response([
            'success' => true,
            'message' => $message,
            'data'    => $data,
            'code'    => 200
        ], 200);
    }

    public function respondError($message = null, $code_error = 400)
    {

        if ($message instanceof PostTooLargeException) {
            return $this->respondError("Ukuran file terlalu besar, max " . ini_get("upload_max_filesize") . "B", Response::HTTP_BAD_REQUEST);
        }
        if ($message instanceof AuthenticationException) {
            return $this->respondError('Token tidak valid atau kadaluarsa, login kembali', Response::HTTP_UNAUTHORIZED);
        }
        if ($message instanceof ThrottleRequestsException) {
            return $this->respondError($message->getMessage(), Response::HTTP_TOO_MANY_REQUESTS);
        }
        if ($message instanceof ModelNotFoundException) {
            return $this->respondError('Data tidak ditemukan', Response::HTTP_NOT_FOUND);
        }
        if ($message instanceof ValidationException) {
            return $this->respondError($message->validator->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if ($message instanceof QueryException) {
            return $this->respondError($message->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        if ($message instanceof HttpResponseException) {
            return $this->respondError($message->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        if ($message instanceof Error) {
            return $this->respondError($message->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        if ($message instanceof AuthorizationException) {
            return $this->respondError($message->getMessage(), Response::HTTP_FORBIDDEN);
        }
        if ($message instanceof NotFoundHttpException) {
            return $this->respondError('url tidak ditemukan', Response::HTTP_NOT_FOUND);
        }

        if (is_null($message)) {
            return response(['success' => false, 'message' => 'Error', 'data' => null, 'code' => $code_error], $code_error);
        }

        if (is_array($message)) {
            return response(['success' => false] + $message + ['data' => null, 'code' => $code_error], $code_error);
        }

        return response(['success' => false, 'message' => $message, 'data' => null, 'code' => $code_error], $code_error);
    }
}
