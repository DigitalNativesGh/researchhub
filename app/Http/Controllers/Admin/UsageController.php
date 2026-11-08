<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiCall;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Claude usage and estimated spend across every account. */
class UsageController extends Controller
{
    private const PERIODS = ['month' => 'This month', '30d' => 'Last 30 days', 'all' => 'All time'];

    public function __invoke(Request $request): View
    {
        [$query, $period] = $this->query($request);

        $totals = (clone $query)->selectRaw('count(*) as calls, coalesce(sum(cost), 0) as cost, coalesce(sum(input_tokens + cached_tokens + cache_write_tokens), 0) as input, coalesce(sum(cached_tokens), 0) as cached, coalesce(sum(output_tokens), 0) as output, sum(case when status = ? then 1 else 0 end) as errors', ['error'])->first();

        $group = fn (string $column) => (clone $query)->selectRaw("{$column} as name, count(*) as calls, coalesce(sum(cost), 0) as cost")->groupBy($column)->orderByDesc('cost')->get();

        return view('admin.usage', [
            'period' => $period,
            'periods' => self::PERIODS,
            'totals' => $totals,
            'byStage' => $group('stage'),
            'byModel' => $group('model'),
            'topUsers' => (clone $query)->whereNotNull('user_id')->selectRaw('user_id, count(*) as calls, coalesce(sum(cost), 0) as cost')->groupBy('user_id')->orderByDesc('cost')->limit(10)->with('user:id,name,email')->get(),
            'recent' => (clone $query)->with(['project:id,title', 'user:id,name'])->latest('id')->paginate(20)->withQueryString(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$query] = $this->query($request);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Time', 'User', 'Project', 'Stage', 'Model', 'Input tokens', 'Cached tokens', 'Cache write tokens', 'Output tokens', 'Cost (USD)', 'Duration (ms)', 'Status', 'Error'], escape: '');
            foreach ($query->with(['project:id,title', 'user:id,email'])->orderBy('id')->cursor() as $c) {
                fputcsv($out, [$c->created_at, $c->user?->email, $c->project?->title, $c->stage, $c->model, $c->input_tokens, $c->cached_tokens, $c->cache_write_tokens, $c->output_tokens, $c->cost, $c->duration_ms, $c->status, $c->error], escape: '');
            }
            fclose($out);
        }, 'platform-ai-usage.csv', ['Content-Type' => 'text/csv']);
    }

    private function query(Request $request): array
    {
        $period = array_key_exists($request->query('period'), self::PERIODS) ? $request->query('period') : 'month';

        $query = AiCall::query()
            ->when($period === 'month', fn ($q) => $q->where('created_at', '>=', now()->startOfMonth()))
            ->when($period === '30d', fn ($q) => $q->where('created_at', '>=', now()->subDays(30)));

        return [$query, $period];
    }
}
