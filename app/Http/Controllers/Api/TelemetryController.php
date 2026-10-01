<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TelemetryIngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelemetryController extends Controller
{
    public function store(Request $request, TelemetryIngestionService $ingestion): JsonResponse
    {
        return $ingestion->ingest($request->all());
    }
}
