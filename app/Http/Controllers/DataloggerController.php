<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\TelemetryDaily;
// models
use App\Models\TelemetryHourly;
use App\Models\TelemetryMonthly;
use App\Models\TelemetryRaw;
use App\Models\TelemetryYearly;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DataloggerController extends Controller
{
    protected function getModel(string $type)
    {
        return match ($type) {
            'hourly' => TelemetryHourly::class,
            'daily' => TelemetryDaily::class,
            'monthly' => TelemetryMonthly::class,
            'yearly' => TelemetryYearly::class,
            default => TelemetryRaw::class,
        };
    }

    protected function getTimeColumn(string $type): string
    {
        return $type === 'raw' ? 'recorded_at' : 'period_start';
    }

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'type' => ['nullable', 'in:raw,hourly,daily,monthly,yearly'],
            'device_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $type = $validated['type'] ?? 'raw';
        $deviceIds = auth()->user()->devices()->pluck('devices.id');

        $modelClass = $this->getModel($type);
        $timeColumn = $this->getTimeColumn($type);

        $query = $modelClass::with('device')->whereIn('device_id', $deviceIds);

        // 🔽 filter device
        if ($request->device_id) {
            $query->where('device_id', $request->device_id);
        }

        // 🔽 filter tanggal (adaptif)
        if ($request->from && $request->to) {
            $query->whereBetween($timeColumn, [
                $request->from,
                $request->to,
            ]);
        }

        // 🔽 alias waktu jadi "time"
        $query->addSelect('*')->addSelect([
            'time' => $timeColumn,
        ]);

        $logs = $query
            ->orderByDesc($timeColumn)
            ->paginate(20)
            ->withQueryString();

        $devices = auth()->user()->devices()->orderBy('device_tag')->get();

        return view('datalogger.index', [
            'logs' => $logs,
            'type' => $type,
            'devices' => $devices,
        ]);
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'type' => ['nullable', 'in:raw,hourly,daily,monthly,yearly'],
            'device_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $type = $validated['type'] ?? 'raw';
        $deviceIds = auth()->user()->devices()->pluck('devices.id');

        $modelClass = $this->getModel($type);
        $timeColumn = $this->getTimeColumn($type);

        $query = $modelClass::with('device')->whereIn('device_id', $deviceIds);

        // 🔽 filter device
        if ($request->device_id) {
            $query->where('device_id', $request->device_id);
        }

        // 🔽 filter tanggal
        if ($request->from && $request->to) {
            $query->whereBetween($timeColumn, [
                $request->from,
                $request->to,
            ]);
        }

        $data = $query->orderByDesc($timeColumn)->get();

        // ❗ HANDLE DATA KOSONG
        if ($data->isEmpty()) {
            return back()->with('error', "Data $type belum tersedia untuk diexport.");
        }

        $filename = 'datalogger_'.$type.'_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($data, $type, $timeColumn) {

            $handle = fopen('php://output', 'w');

            // ========================
            // HEADER CSV
            // ========================
            if ($type === 'raw') {

                fputcsv($handle, [
                    'Waktu',
                    'Device',
                    'Samples',
                    'AVG pH', 'MIN pH', 'MAX pH',
                    'AVG Turbidity', 'MIN Turbidity', 'MAX Turbidity',
                    'AVG Sample Flow (L/min)', 'MIN Sample Flow', 'MAX Sample Flow',
                    'Usage (L)',
                    'Sensor Distance (cm)',
                    'Water Height (cm)',
                    'Level (%)',
                    'Signal (dBm)',
                ]);

                foreach ($data as $row) {
                    fputcsv($handle, [
                        optional($row->$timeColumn)->format('Y-m-d H:i:s'),
                        $row->device->device_tag ?? '-',
                        $row->sample_count ?? 1,
                        $row->ph,
                        $row->raw_payload['metrics']['ph']['min'] ?? $row->ph,
                        $row->raw_payload['metrics']['ph']['max'] ?? $row->ph,
                        $row->turbidity,
                        $row->raw_payload['metrics']['turbidity']['min'] ?? $row->turbidity,
                        $row->raw_payload['metrics']['turbidity']['max'] ?? $row->turbidity,
                        $row->sample_flow,
                        $row->raw_payload['metrics']['sample_flow']['min'] ?? $row->sample_flow,
                        $row->raw_payload['metrics']['sample_flow']['max'] ?? $row->sample_flow,
                        $row->usage_liter,
                        $row->level_distance_cm,
                        $row->level_cm,
                        $row->level_percent,
                        $row->signal_strength,
                    ]);
                }

            } else {

                // AGGREGATED
                fputcsv($handle, [
                    'Period Start',
                    'Period End',
                    'Device',

                    'AVG pH', 'MIN pH', 'MAX pH',
                    'AVG Turbidity', 'MIN Turbidity', 'MAX Turbidity',
                    'AVG Sample Flow (L/min)', 'MIN Sample Flow (L/min)', 'MAX Sample Flow (L/min)',
                    'Usage (L)',

                    'AVG Level (%)', 'MIN Level (%)', 'MAX Level (%)',

                    'AVG Signal', 'MIN Signal', 'MAX Signal',
                ]);

                foreach ($data as $row) {
                    fputcsv($handle, [
                        $row->period_start,
                        $row->period_end,
                        $row->device->device_tag ?? '-',

                        $row->avg_ph, $row->min_ph, $row->max_ph,
                        $row->avg_turbidity, $row->min_turbidity, $row->max_turbidity,
                        $row->avg_sample_flow, $row->min_sample_flow, $row->max_sample_flow,
                        $row->usage_liter,

                        $row->avg_level_percent, $row->min_level_percent, $row->max_level_percent,

                        $row->avg_signal_strength, $row->min_signal_strength, $row->max_signal_strength,
                    ]);
                }
            }

            fclose($handle);

        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
