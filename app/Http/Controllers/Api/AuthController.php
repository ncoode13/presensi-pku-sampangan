<?php

namespace App\Http\Controllers\Api;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{

    public function login(Request $request)
    {
        $request->validate([
            'email'       => ['required', 'email'],
            'password'    => ['required'],
            'remember_me' => ['boolean'],
        ]);

        try {

            if (! Auth::attempt($request->only('email', 'password'))) {
                return $this->respondError('Email / Password salah!');
            }


            $user = Auth::user();

            $user->load([
                'employee' => function ($query) {
                    $query->select('id', 'user_id', 'shift_id', 'avatar');
                },
                'employee.shift'
            ]);


            $rawShift = $user->getTodayShift();


            $cleanShift = null;
            if ($rawShift) {
                $cleanShift = [
                    'id'         => $rawShift->id,
                    'name'       => $rawShift->name,
                    'start_time' => $rawShift->start_time,
                    'end_time'   => $rawShift->end_time,
                ];
            }

            $cleanUser = [
                'id'            => $user->id,
                'name'          => $user->name,
                'username'      => $user->username,
                'role'          => $user->role,
                'avatar'        => $user->employee ? $user->employee->avatar : null,
                'current_shift' => $cleanShift,
            ];

            $expiration = $request->boolean('remember_me')
                ? now()->addMonth()
                : now()->addMinutes(60);

            $token = $user->createToken('authToken', ['*'], $expiration)->plainTextToken;

            return $this->respondSuccess([

                'user'       => $cleanUser,
                'token_type' => 'Bearer',
                'token'      => $token,
                'expires_at' => $expiration->toDateTimeString(),
            ]);
        } catch (\Throwable $th) {
            return $this->respondError($th->getMessage());
        }
    }

    public function logout(Request $request)
    {
        try {
            auth()->user()->currentAccessToken()->delete();
            return $this->respondOk();
        } catch (\Throwable $th) {
            return $this->respondError($th->getMessage());
        }
    }
}
