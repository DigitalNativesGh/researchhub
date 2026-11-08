<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DegreeLevel;
use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\Payment;
use App\Services\Payments\Paystack;
use App\Services\Payments\PaystackException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Project payments taken through Paystack. */
class PaymentController extends Controller
{
    private const STATUSES = ['all' => 'All', 'success' => 'Paid', 'pending' => 'Pending', 'failed' => 'Failed', 'abandoned' => 'Abandoned'];

    public function index(Request $request): View
    {
        $query = $this->query($request);

        $paid = Payment::where('status', Payment::SUCCESS);

        return view('admin.payments.index', [
            'payments' => (clone $query)->with(['user:id,name,email', 'project:id,title'])->latest('id')->paginate(25)->withQueryString(),
            'statuses' => self::STATUSES,
            'status' => $this->status($request),
            'q' => trim((string) $request->query('q')),
            'totals' => [
                'month' => (clone $paid)->where('paid_at', '>=', now()->startOfMonth())->sum('amount') / 100,
                'total' => (clone $paid)->sum('amount') / 100,
                'count' => (clone $paid)->count(),
                'unused' => Payment::unused()->count(),
            ],
            'byLevel' => (clone $paid)->selectRaw('degree_level, count(*) as payments, sum(amount) as amount')->groupBy('degree_level')->get()
                ->keyBy(fn ($r) => $r->degree_level->value),
            'prices' => collect(DegreeLevel::cases())->mapWithKeys(fn ($l) => [$l->value => Payment::priceFor($l)]),
        ]);
    }

    /** Ask Paystack again, e.g. when a webhook was missed. */
    public function verify(Payment $payment, Paystack $paystack): RedirectResponse
    {
        try {
            $paid = $payment->applyPaystack($paystack->verify($payment->reference));
        } catch (PaystackException $e) {
            return back()->with('error', 'Paystack: '.$e->getMessage());
        }

        AdminLog::record('payment.verified', "Re-checked payment {$payment->reference} with Paystack: {$payment->status}", $payment);

        return back()->with('status', $paid ? "Payment {$payment->reference} is confirmed as paid." : "Paystack reports payment {$payment->reference} as {$payment->status}.");
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->query($request);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Reference', 'User', 'Email', 'Research type', 'Amount', 'Currency', 'Status', 'Method', 'Paid at', 'Project'], escape: '');
            foreach ($query->with(['user:id,name,email', 'project:id,title'])->orderBy('id')->cursor() as $p) {
                fputcsv($out, [$p->created_at, $p->reference, $p->user?->name, $p->user?->email, $p->degree_level->label(), number_format($p->amountMajor(), 2, '.', ''), $p->currency, $p->status, $p->methodLabel(), $p->paid_at, $p->project?->title], escape: '');
            }
            fclose($out);
        }, 'payments.csv', ['Content-Type' => 'text/csv']);
    }

    private function query(Request $request)
    {
        $status = $this->status($request);
        $q = trim((string) $request->query('q'));
        $like = '%'.mb_strtolower($q).'%';

        return Payment::query()
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->whereRaw('lower(reference) like ?', [$like])
                ->orWhereHas('user', fn ($u) => $u->whereRaw('lower(name) like ?', [$like])->orWhereRaw('lower(email) like ?', [$like]))));
    }

    private function status(Request $request): string
    {
        return array_key_exists($request->query('status'), self::STATUSES) ? $request->query('status') : 'all';
    }
}
