<?php

namespace App\Broadcasting;

use App\Models\User;

class DeviceChannel
{
    public function join(User $user, int $deviceId): bool
    {
        return $user->devices()->whereKey($deviceId)->exists();
    }
}
