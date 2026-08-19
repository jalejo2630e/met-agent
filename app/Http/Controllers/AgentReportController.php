<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Setting;
use App\Services\AgentOperationReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentReportController extends Controller
{
    public function __invoke(Request $request, Agent $agent, AgentOperationReportService $reports): JsonResponse
    {
        $this->authorize('view', $agent);

        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $report = $reports->build($agent, $validated['date_from'] ?? null, $validated['date_to'] ?? null);
        $report['default_email'] = Setting::get('contact_email');

        return response()->json($report);
    }
}
