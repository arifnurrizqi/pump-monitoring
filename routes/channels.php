<?php

use App\Broadcasting\DeviceChannel;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('devices.{deviceId}', DeviceChannel::class, ['guards' => ['web']]);
