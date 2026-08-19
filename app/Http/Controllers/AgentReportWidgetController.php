<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\AgentReportWidget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AgentReportWidgetController extends Controller
{
    public function store(Request $request, Agent $agent): RedirectResponse
    {
        $this->authorize('update', $agent);

        $data = $this->validated($request);
        $data['order'] = (int) $agent->reportWidgets()->max('order') + 1;

        $agent->reportWidgets()->create($data);

        return back()->with('success', 'Reporte personalizado creado.');
    }

    public function update(Request $request, Agent $agent, AgentReportWidget $reportWidget): RedirectResponse
    {
        $this->authorize('update', $agent);

        $reportWidget->update($this->validated($request));

        return back()->with('success', 'Reporte personalizado actualizado.');
    }

    public function destroy(Agent $agent, AgentReportWidget $reportWidget): RedirectResponse
    {
        $this->authorize('update', $agent);

        $reportWidget->delete();

        return back()->with('success', 'Reporte personalizado eliminado.');
    }

    public function reorder(Request $request, Agent $agent): RedirectResponse
    {
        $this->authorize('update', $agent);

        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        foreach ($validated['ids'] as $position => $id) {
            $agent->reportWidgets()->whereKey($id)->update(['order' => $position]);
        }

        return back()->with('success', 'Orden actualizado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'metric' => ['required', Rule::in([
                AgentReportWidget::METRIC_RANGE_BUCKETS,
                AgentReportWidget::METRIC_CLIENT_PROGRESS,
                AgentReportWidget::METRIC_TOPIC_PROGRESS,
                AgentReportWidget::METRIC_VALUE_COUNTS,
                AgentReportWidget::METRIC_CALLS,
            ])],
            'source' => ['required', Rule::in([
                AgentReportWidget::SOURCE_CUSTOM_FIELD,
                AgentReportWidget::SOURCE_CALLS,
            ])],
            'field_name' => ['nullable', 'string', 'max:120'],
            'chart_type' => ['required', Rule::in(['doughnut', 'bar', 'funnel', 'stat', 'progress'])],
            'config' => ['nullable', 'array'],
            'config.max' => ['nullable', 'numeric'],
            'config.ranges' => ['nullable', 'array'],
            'config.ranges.*.from' => ['nullable', 'numeric'],
            'config.ranges.*.to' => ['nullable', 'numeric'],
            'config.ranges.*.label' => ['nullable', 'string', 'max:120'],
            'config.ranges.*.color' => ['nullable', 'string', 'max:32'],
            'config.topics' => ['nullable', 'array'],
            'config.topics.*.field' => ['nullable', 'string', 'max:120'],
            'config.topics.*.label' => ['nullable', 'string', 'max:120'],
            'config.topics.*.max' => ['nullable', 'numeric'],
            'config.topics.*.color' => ['nullable', 'string', 'max:32'],
            'config.values' => ['nullable', 'array'],
            'config.values.*.value' => ['nullable', 'string', 'max:120'],
            'config.values.*.label' => ['nullable', 'string', 'max:120'],
            'config.values.*.color' => ['nullable', 'string', 'max:32'],
            'config.calls_metrics' => ['nullable', 'array'],
            'config.calls_metrics.*' => [Rule::in(['total', 'avg', 'distribution'])],
            'config.width' => ['nullable', Rule::in(['half', 'full'])],
        ]);
    }
}
