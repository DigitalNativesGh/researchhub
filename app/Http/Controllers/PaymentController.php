<?php

namespace App\Http\Controllers;

use App\Enums\DegreeLevel;
use App\Models\Payment;
use App\Services\Payments\Paystack;
use App\Services\Payments\PaystackException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Wizard step 2: pay for the chosen research type on Paystack's hosted checkout (card or Mobile Money)
 * before the title and details step. A payment only counts once Paystack confirms it, either through the
 * verify call when the student returns or through the signed webhook.
 */
class PaymentController extends Controller
{
    public function show(Request $request, Paystack $paystack): View|RedirectResponse
    {
        $level = $this->level($request);
        if (! $level) {
            return redirect()->route('projects.new');
        }
        if ($request->user()->canStartProject($level)) {
            return redirect()->route('projects.create');
        }

        return view('projects.payment', [
            'level' => $level,
            'price' => Payment::priceFor($level),
            'currency' => Payment::currency(),
            'configured' => $paystack->configured(),
            'channels' => config('services.paystack.channels'),
        ]);
    }

    public function store(Request $request, Paystack $paystack): RedirectResponse
    {
        $level = $this->level($request);
        if (! $level) {
            return redirect()->route('projects.new');
        }

        $user = $request->user();
        if ($user->canStartProject($level)) {
            return redirect()->route('projects.create');
        }

        // Paystack emails the receipt, so it refuses reserved domains such as the demo accounts' ".test".
        if (! self::payableEmail($user->email)) {
            return back()->with('error', self::EMAIL_MESSAGE);
        }

        $payment = $user->payments()->create([
            'degree_level' => $level,
            'amount' => (int) round(Payment::priceFor($level) * 100),
            'currency' => Payment::currency(),
            'reference' => Payment::newReference(),
            'status' => 'pending',
        ]);

        try {
            $url = $paystack->initialize($payment, $user->email, route('payments.callback'), [
                'degree_level' => $level->value,
                'custom_fields' => [
                    ['display_name' => 'Research type', 'variable_name' => 'research_type', 'value' => $level->label()],
                    ['display_name' => 'Customer', 'variable_name' => 'customer_name', 'value' => $user->name],
                ],
            ]);
        } catch (PaystackException $e) {
            $payment->forceFill(['status' => 'failed', 'gateway_response' => $e->getMessage()])->save();

            return back()->with('error', str_contains(strtolower($e->getMessage()), 'email') ? self::EMAIL_MESSAGE : $e->getMessage());
        }

        return redirect()->away($url);
    }

    /** Paystack sends the student back here after checkout. */
    public function callback(Request $request, Paystack $paystack): RedirectResponse
    {
        $reference = (string) ($request->query('reference') ?: $request->query('trxref'));
        $payment = Payment::where('reference', $reference)->where('user_id', $request->user()->id)->first();

        if (! $payment) {
            return redirect()->route('projects.new')->with('error', 'We could not find that payment. If you were charged, contact support with your Paystack receipt.');
        }

        try {
            $paid = $payment->isSuccessful() || $payment->applyPaystack($paystack->verify($reference));
        } catch (PaystackException $e) {
            return redirect()->route('projects.payment')->with('error', 'We could not confirm your payment yet: '.$e->getMessage().' If you were charged, refresh this page in a minute.');
        }

        $request->session()->put('wizard.degree_level', $payment->degree_level->value);

        if (! $paid) {
            return redirect()->route('projects.payment')->with('error', 'The payment was not completed'.($payment->gateway_response ? ' ('.$payment->gateway_response.')' : '').'. You have not been charged; please try again.');
        }

        return redirect()->route('projects.create')->with('status', "Payment of {$payment->displayAmount()} received. Now give your project a title and details.");
    }

    /** Signed server-to-server notification; confirms payments even if the student closes the tab. */
    public function webhook(Request $request, Paystack $paystack): JsonResponse
    {
        if (! $paystack->validSignature($request->getContent(), $request->header('x-paystack-signature'))) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $event = $request->json('event');
        $data = (array) $request->json('data');

        if ($event === 'charge.success' && ($payment = Payment::where('reference', $data['reference'] ?? '')->first())) {
            $payment->applyPaystack($data);
        } elseif ($event === 'charge.success') {
            Log::warning('Paystack webhook for an unknown reference', ['reference' => $data['reference'] ?? null]);
        }

        return response()->json(['received' => true]);
    }

    private const EMAIL_MESSAGE = 'Paystack needs a real email address to send your receipt, and the one on your account cannot receive email. Update it under Profile, then try again.';

    /** Reserved and local-only domains (RFC 2606/6761) that Paystack rejects. */
    public static function payableEmail(string $email): bool
    {
        $domain = strtolower((string) substr(strrchr($email, '@') ?: '', 1));

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false
            && str_contains($domain, '.')
            && ! preg_match('/(^|\.)(test|example|invalid|localhost|local)$/', $domain)
            && ! in_array($domain, ['example.com', 'example.net', 'example.org'], true);
    }

    private function level(Request $request): ?DegreeLevel
    {
        return DegreeLevel::tryFrom((string) $request->session()->get('wizard.degree_level'));
    }
}
