<?php

namespace App\Http\Controllers;

use App\Jobs\PublishValveCommand;
use App\Models\Device;
use App\Models\DeviceCommand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceCommandController extends Controller
{
    public function storeValveCommand(Request $request, Device $device): JsonResponse
    {
        $this->authorizeOperation($device);

        $validated = $request->validate([
            'value' => ['required', 'boolean'],
        ]);

        DeviceCommand::where('device_id', $device->id)
            ->whereIn('status', ['queued', 'sent'])
            ->update(['status' => 'expired', 'updated_at' => now()]);

        $command = DeviceCommand::create([
            'device_id' => $device->id,
            'command_type' => 'valve',
            'command_key' => 'valve_state',
            'command_value' => (string) (int) $validated['value'],
            'status' => 'queued',
        ]);

        PublishValveCommand::dispatch($command->id)->afterCommit();

        return response()->json([
            'message' => 'Valve command queued.',
            'data' => $this->commandData($device, $command),
        ], 202);
    }

    public function showValveCommand(Device $device, DeviceCommand $command): JsonResponse
    {
        $this->authorizeOperation($device);
        abort_unless($command->device_id === $device->id && $command->command_key === 'valve_state', 404);

        $queueExpired = $command->status === 'queued'
            && $command->created_at->addSeconds(config('mqtt.valve.queue_timeout'))->isPast();
        $ackExpired = $command->status === 'sent' && $command->expires_at?->isPast();

        if ($queueExpired || $ackExpired) {
            $command->update(['status' => 'expired']);
        }

        $device->load('latestData');

        return response()->json(['data' => $this->commandData($device, $command->fresh())]);
    }

    private function authorizeOperation(Device $device): void
    {
        $role = auth()->user()->devices()
            ->where('devices.id', $device->id)
            ->first()?->pivot?->role;

        abort_unless(
            in_array($role, ['owner', 'operator'], true),
            403,
            'Anda tidak punya izin untuk mengoperasikan device ini.'
        );
    }

    /** @return array<string, mixed> */
    private function commandData(Device $device, DeviceCommand $command): array
    {
        return [
            'id' => $command->id,
            'status' => $command->status,
            'requested_value' => (bool) $command->command_value,
            'actual_value' => $device->latestData?->valve_state,
            'expires_at' => $command->expires_at?->toISOString(),
            'message' => $command->error_message,
        ];
    }
}
