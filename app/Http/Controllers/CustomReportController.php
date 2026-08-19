<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Services\CustomReportBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomReportController extends Controller
{
    public function __invoke(Request $request, Agent $agent, CustomReportBuilderService $reports): JsonResponse
    {
        $this->authorize('view', $agent);

        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        return response()->json([
            'widgets' => $reports->build($agent, $validated['date_from'] ?? null, $validated['date_to'] ?? null),
        ]);
    }
}
