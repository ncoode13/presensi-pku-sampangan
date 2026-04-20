<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $rawPassword;

    public function __construct(User $user, $rawPassword)
    {
        $this->user = $user;
        $this->rawPassword = $rawPassword;
    }
}