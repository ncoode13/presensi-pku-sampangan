<?php

namespace App\Listeners;

use App\Events\UserCreated;
use App\Mail\NewEmployeeCredentialMail;
use Illuminate\Contracts\Queue\ShouldQueue; 
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendWelcomeEmailNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(UserCreated $event): void
    {
        $user = $event->user;
        $password = $event->rawPassword;

        if ($user->email) {
            Mail::to($user->email)->send(new NewEmployeeCredentialMail($user, $password));
        }
    }
}