<?php

namespace App\Http\Controllers;

use App\Models\SourceInterval;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataloggerController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $devices = auth()->user()->devices()->with('sources')->orderBy('device_tag')->get();
        $logs = $this->query($devices->pluck('id')->all(), $filters)
            ->with(['source.device', 'source.parameters'])
            ->latest('bucket_start')->paginate(20)->withQueryString();

        return view('datalogger.index', compact('devices', 'logs', 'filters'));
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $deviceIds = auth()->user()->devices()->pluck('devices.id')->all();

        return response()->streamDownload(function () use ($deviceIds, $filters): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['gateway', 'source', 'bucket_start', 'last_recorded_at', 'sample_count', 'parameter', 'unit', 'average', 'min', 'max', 'last', 'quality']);
            $this->query($deviceIds, $filters)->with(['source.device', 'source.parameters'])
                ->orderBy('id')->chunkById(200, function ($intervals) use ($output): void {
                    foreach ($intervals as $interval) {
                        $parameters = $interval->source->parameters->keyBy('key');
                        foreach ($interval->metrics as $key => $metric) {
                            $count = $metric['count'] ?? 0;
                            fputcsv($output, [
                                $this->csvText($interval->source->device->device_tag),
                                $this->csvText($interval->source->name),
                                $interval->bucket_start->toIso8601String(),
                                $interval->last_recorded_at->toIso8601String(),
                                $interval->sample_count,
                                $this->csvText($parameters->get($key)?->label ?? $key),
                                $this->csvText($parameters->get($key)?->unit),
                                $count > 0 ? $metric['sum'] / $count : null,
                                $metric['min'] ?? null,
                                $metric['max'] ?? null,
                                $metric['last'] ?? null,
                                $this->csvText($metric['quality'] ?? null),
                            ]);
                        }
                    }
                });
            fclose($output);
        }, 'pump-monitoring-intervals-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'device_id' => ['nullable', 'integer'],
            'source_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
    }

    private function query(array $deviceIds, array $filters)
    {
        return SourceInterval::query()
            ->whereHas('source', function ($query) use ($deviceIds, $filters): void {
                $query->whereIn('device_id', $deviceIds);
                if (! empty($filters['device_id'])) {
                    $query->where('device_id', $filters['device_id']);
                }
            })
            ->when(! empty($filters['source_id']), fn ($query) => $query->where('data_source_id', $filters['source_id']))
            ->when(! empty($filters['from']), fn ($query) => $query->whereDate('bucket_start', '>=', $filters['from']))
            ->when(! empty($filters['to']), fn ($query) => $query->whereDate('bucket_start', '<=', $filters['to']));
    }

    private function csvText(?string $value): ?string
    {
        return $value !== null && preg_match('/^[\s]*[=+@-]/u', $value) ? "'".$value : $value;
    }
}
