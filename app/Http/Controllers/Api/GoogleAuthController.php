<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Storage;

class GoogleAuthController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')
            ->stateless()
            ->redirect();
    }

    public function handleGoogleCallback()
    {
        $frontendUrl = config('services.frontend_url');

        try {
            $googleUser = Socialite::driver('google')
                ->stateless()
                ->user();

            $user = User::select('id', 'name', 'username', 'role', 'email')
                ->where('email', $googleUser->email)
                ->with([
                    'employee' => function ($query) {
                        $query->select('id', 'user_id', 'shift_id', 'avatar');
                    },
                    'employee.shift'
                ])
                ->first();

            if (!$user) {
                return redirect($frontendUrl . '/auth/google/callback?status=error&message=Email tidak terdaftar');
            }

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
            
            $avatarUrl = null;
            if ($user->employee && $user->employee->avatar) {
                $avatarUrl = Storage::url($user->employee->avatar);
            }

            $cleanUser = [
                'id'            => $user->id,
                'name'          => $user->name,
                'username'      => $user->username,
                'role'          => $user->role,
                'avatar'        => $avatarUrl, 
                'current_shift' => $cleanShift,
            ];

            $expiration = now()->addMinutes(config('session.lifetime', 60));

            $token = $user->createToken('api_token', ['*'], $expiration)->plainTextToken;

            $userDataJson = json_encode($cleanUser);
            $userDataEncoded = base64_encode($userDataJson);

            return redirect($frontendUrl . '/auth/google/callback?token=' . $token . '&u=' . $userDataEncoded . '&status=success');

        } catch (\Throwable $th) {
             return redirect($frontendUrl . '/auth/google/callback?status=error&message=Gagal Login: ' . $th->getMessage());
        }
    }
}