<?php

use App\Enums\DegreeLevel;
use App\Http\Controllers\PaymentController;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.paystack.secret_key' => 'sk_test_secret', 'services.paystack.channels' => ['card', 'mobile_money']]);
    $this->user = User::factory()->create(['email' => 'ama.mensah@ug.edu.gh']);
    $this->actingAs($this->user);
});

/** A Paystack verify/webhook transaction for a payment. */
function paystackTransaction(Payment $payment, array $overrides = []): array
{
    return array_merge([
        'id' => 4099260516, 'status' => 'success', 'reference' => $payment->reference, 'amount' => $payment->amount,
        'currency' => $payment->currency, 'channel' => 'mobile_money', 'gateway_response' => 'Approved', 'paid_at' => now()->toIso8601String(),
        'authorization' => ['channel' => 'mobile_money', 'bank' => 'MTN', 'card_type' => '', 'last4' => 'X'],
    ], $overrides);
}

it('shows prices on the research type step', function () {
    $this->get(route('projects.new'))->assertOk()
        ->assertSee('$200')->assertSee('$400')->assertSee('$450')->assertSee('$500')->assertSee('$1,500')
        ->assertSee('Systematic Review')->assertSee('$100')->assertSee('Research Paper')->assertSee('$150')
        ->assertSee('I need data collection done on my behalf');
});

it('requires payment before the details step', function () {
    $this->post(route('projects.new.store'), ['degree_level' => 'phd'])->assertRedirect(route('projects.payment'));

    $this->get(route('projects.create'))->assertRedirect(route('projects.payment'));
    $this->get(route('title-assistant'))->assertRedirect(route('projects.payment'));
    $this->get(route('projects.payment'))->assertOk()->assertSee('PhD Thesis')->assertSee('$1,500')->assertSee('GHS 17,250')->assertSee('1 USD = 11.5 GHS')->assertSee('Mobile Money')->assertSee('Mastercard');

    $this->post(route('projects.store'), ['title' => 'A sufficiently long project title', 'discipline' => 'Education', 'degree_level' => 'phd', 'research_type' => 'mixed', 'citation_style' => 'apa7'])
        ->assertRedirect(route('projects.payment'));
    expect(Project::count())->toBe(0);
});

it('pays on Paystack and continues to the details step', function () {
    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/abc123', 'reference' => 'x']]),
    ]);

    $this->post(route('projects.new.store'), ['degree_level' => 'mba']);
    $this->post(route('projects.payment.store'))->assertRedirect('https://checkout.paystack.com/abc123');

    $payment = Payment::sole();
    expect($payment->status)->toBe('pending')->and($payment->amount)->toBe(460000)->and($payment->currency)->toBe('GHS')
        ->and($payment->price_amount)->toBe(40000)->and($payment->price_currency)->toBe('USD')->and($payment->exchange_rate)->toBe(11.5);

    Http::assertSent(fn (Request $r) => $r->url() === 'https://api.paystack.co/transaction/initialize'
        && $r->hasHeader('Authorization', 'Bearer sk_test_secret')
        && $r['amount'] === 460000 && $r['email'] === 'ama.mensah@ug.edu.gh' && $r['channels'] === ['card', 'mobile_money']
        && $r['callback_url'] === route('payments.callback') && $r['reference'] === $payment->reference);

    Http::fake(["api.paystack.co/transaction/verify/{$payment->reference}" => Http::response(['status' => true, 'data' => paystackTransaction($payment)])]);

    $this->get(route('payments.callback', ['reference' => $payment->reference]))
        ->assertRedirect(route('projects.create'))->assertSessionHas('status');

    $payment->refresh();
    expect($payment->isSuccessful())->toBeTrue()->and($payment->methodLabel())->toBe('Mobile Money (MTN)');
    $this->get(route('projects.create'))->assertOk()->assertSee('Paid · $400 (GHS 4,600)');

    // The payment is used by exactly one project.
    $details = ['title' => 'Strategic agility in Ghanaian SMEs', 'discipline' => 'Business & Economics', 'degree_level' => 'mba', 'research_type' => 'quantitative', 'citation_style' => 'apa7'];
    $this->post(route('projects.store'), $details)->assertRedirect();
    $project = Project::sole();
    expect($payment->fresh()->project_id)->toBe($project->id)->and($payment->fresh()->consumed_at)->not->toBeNull();

    $this->post(route('projects.store'), $details)->assertRedirect(route('projects.payment'));
    expect(Project::count())->toBe(1);

    // Deleting the project does not give the credit back.
    $this->delete(route('projects.destroy', $project));
    expect($this->user->canStartProject(DegreeLevel::Mba))->toBeFalse();
});

it('does not unlock the wizard for failed or mismatched payments', function (array $overrides) {
    $payment = $this->user->payments()->create(['degree_level' => 'mphil', 'amount' => 80000, 'currency' => 'GHS', 'reference' => 'RH-TEST1', 'status' => 'pending']);
    $this->withSession(['wizard' => ['degree_level' => 'mphil']]);

    Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(['status' => true, 'data' => paystackTransaction($payment, $overrides)])]);

    $this->get(route('payments.callback', ['reference' => 'RH-TEST1']))->assertRedirect(route('projects.payment'))->assertSessionHas('error');
    expect($payment->fresh()->isSuccessful())->toBeFalse();
    $this->get(route('projects.create'))->assertRedirect(route('projects.payment'));
})->with([
    'declined' => [['status' => 'failed', 'gateway_response' => 'Declined']],
    'abandoned' => [['status' => 'abandoned']],
    'underpaid' => [['amount' => 100]],
    'wrong currency' => [['currency' => 'NGN']],
]);

it("ignores another user's payment reference", function () {
    $other = User::factory()->create();
    $other->payments()->create(['degree_level' => 'mphil', 'amount' => 80000, 'currency' => 'GHS', 'reference' => 'RH-OTHER', 'status' => 'pending']);
    Http::fake();

    $this->get(route('payments.callback', ['reference' => 'RH-OTHER']))->assertRedirect(route('projects.new'));
    Http::assertNothingSent();
});

it('confirms payments from a signed webhook', function () {
    $payment = $this->user->payments()->create(['degree_level' => 'phd', 'amount' => 150000, 'currency' => 'GHS', 'reference' => 'RH-HOOK', 'status' => 'pending']);
    $body = json_encode(['event' => 'charge.success', 'data' => paystackTransaction($payment, ['channel' => 'card', 'authorization' => ['card_type' => 'visa ', 'last4' => '4081']])]);
    auth()->logout();

    $this->call('POST', route('webhooks.paystack'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => 'forged'], $body)->assertUnauthorized();
    expect($payment->fresh()->isSuccessful())->toBeFalse();

    $this->call('POST', route('webhooks.paystack'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, 'sk_test_secret')], $body)->assertOk();
    expect($payment->fresh()->isSuccessful())->toBeTrue()->and($payment->fresh()->methodLabel())->toBe('Visa •••• 4081');

    // Paid while away: the wizard skips straight past payment.
    $this->actingAs($this->user)->post(route('projects.new.store'), ['degree_level' => 'phd'])->assertRedirect(route('projects.create'));
});

it('adds the data collection add-on to the price and records it on the project', function () {
    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/dc1', 'reference' => 'x']]),
    ]);

    $this->post(route('projects.new.store'), ['degree_level' => 'phd', 'data_collection' => '1'])->assertRedirect(route('projects.payment'));
    $this->get(route('projects.payment'))->assertOk()->assertSee('Data collection on your behalf')->assertSee('$200')->assertSee('$1,700')->assertSee('Pay GHS 19,550');

    $this->post(route('projects.payment.store'))->assertRedirect('https://checkout.paystack.com/dc1');
    $payment = Payment::sole();
    expect($payment->amount)->toBe(1955000)->and($payment->data_collection)->toBeTrue();
    Http::assertSent(fn (Request $r) => $r['amount'] === 1955000);

    Http::fake(["api.paystack.co/transaction/verify/{$payment->reference}" => Http::response(['status' => true, 'data' => paystackTransaction($payment)])]);
    $this->get(route('payments.callback', ['reference' => $payment->reference]))->assertRedirect(route('projects.create'));
    $this->get(route('projects.create'))->assertOk()->assertSee('Includes data collection by our team');

    $this->post(route('projects.store'), ['title' => 'Smartphone use and learning outcomes in Ghana', 'discipline' => 'Education', 'degree_level' => 'phd', 'research_type' => 'quantitative', 'citation_style' => 'apa7'])->assertRedirect();
    expect(Project::sole()->data_collection)->toBeTrue()->and($payment->fresh()->project_id)->toBe(Project::sole()->id);
});

it('does not use a credit paid without data collection for a project with it', function () {
    $this->user->payments()->create(['degree_level' => 'msc', 'amount' => 45000, 'currency' => 'GHS', 'reference' => 'RH-MSC', 'status' => 'success']);

    $this->post(route('projects.new.store'), ['degree_level' => 'msc', 'data_collection' => '1'])->assertRedirect(route('projects.payment'));
    $this->get(route('projects.payment'))->assertOk()->assertSee('Pay GHS 6,325');

    $this->post(route('projects.new.store'), ['degree_level' => 'msc', 'data_collection' => '0'])->assertRedirect(route('projects.create'));
});

it('reports Paystack errors and missing keys', function () {
    Http::fake(['api.paystack.co/*' => Http::response(['status' => false, 'message' => 'Currency not supported by merchant'], 400)]);
    $this->withSession(['wizard' => ['degree_level' => 'mba']]);

    $this->from(route('projects.payment'))->post(route('projects.payment.store'))->assertRedirect(route('projects.payment'))->assertSessionHas('error', 'Currency not supported by merchant');
    expect(Payment::sole()->status)->toBe('failed');

    config(['services.paystack.secret_key' => null]);
    $this->get(route('projects.payment'))->assertOk()->assertSee('Online payments are not set up yet');
});

it('lets administrators set prices, including free', function () {
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    $this->actingAs($admin)->put(route('admin.settings.update'), [
        'registration_open' => '1', 'announcement_level' => 'info', 'title_suggestions_per_hour' => 20, 'paper_max_count' => 40, 'min_sources' => 1,
        'exchange_rate' => 11.5, 'price_undergraduate' => 0, 'price_mba' => 650.5, 'price_msc' => 450, 'price_mphil' => 800, 'price_phd' => 1500, 'price_systematic_review' => 100, 'price_research_paper' => 150,
        'data_collection_undergraduate' => 0, 'data_collection_mba' => 100, 'data_collection_msc' => 100, 'data_collection_mphil' => 150, 'data_collection_phd' => 200, 'data_collection_systematic_review' => 50, 'data_collection_research_paper' => 50,
    ])->assertSessionHasNoErrors();

    expect(Payment::priceFor(DegreeLevel::Mba))->toBe(650.5);

    $this->actingAs($this->user)->get(route('projects.new'))->assertSee('$650.50')->assertSee('Free');
    $this->post(route('projects.new.store'), ['degree_level' => 'undergraduate'])->assertRedirect(route('projects.create'));
});

it('charges in cedis at the exchange rate the administrator sets', function () {
    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/fx', 'reference' => 'x']]),
    ]);
    expect(Payment::exchangeRate())->toBe(11.5);

    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();
    $this->actingAs($admin)->get(route('admin.settings'))->assertOk()->assertSee('Exchange rate (GHS per 1 USD)');
    $this->put(route('admin.settings.update'), array_merge(
        collect(\App\Models\Setting::values())->filter(fn ($v) => $v !== null)->map(fn ($v) => is_bool($v) ? (int) $v : $v)->all(),
        ['exchange_rate' => 12.25],
    ))->assertSessionHasNoErrors();
    expect(Payment::exchangeRate())->toBe(12.25);

    // MBA at $400: GHS 4,900 at the new rate.
    $this->actingAs($this->user)->post(route('projects.new.store'), ['degree_level' => 'mba']);
    $this->get(route('projects.payment'))->assertOk()->assertSee('$400')->assertSee('Pay GHS 4,900')->assertSee('1 USD = 12.25 GHS');
    $this->post(route('projects.payment.store'));

    $payment = Payment::sole();
    expect($payment->amount)->toBe(490000)->and($payment->exchange_rate)->toBe(12.25)->and($payment->fullAmount())->toBe('$400 (GHS 4,900)');
    Http::assertSent(fn (Request $r) => $r['amount'] === 490000 && $r['currency'] === 'GHS');
});

it('keeps the paid research type fixed on edit', function () {
    $project = Project::factory()->for($this->user)->create();

    $this->put(route('projects.update', $project), [
        'title' => $project->title, 'discipline' => 'Education', 'degree_level' => 'phd', 'research_type' => 'quantitative', 'citation_style' => 'apa7',
    ])->assertSessionHasErrors('degree_level');

    expect($project->fresh()->degree_level->value)->toBe('mphil');
});

it('shows payments to administrators and re-checks pending ones', function () {
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();
    paidFor($this->user, 'mphil');
    $pending = $this->user->payments()->create(['degree_level' => 'phd', 'amount' => 150000, 'currency' => 'GHS', 'reference' => 'RH-LATE', 'status' => 'pending']);

    $this->actingAs($admin)->get(route('admin.payments.index'))->assertOk()->assertSee('RH-LATE')->assertSee('Unused credit');
    $this->get(route('admin.dashboard'))->assertOk()->assertSee('Revenue this month');
    $this->get(route('admin.users.show', $this->user))->assertOk()->assertSee('RH-LATE');
    $this->get(route('admin.payments.export'))->assertOk();

    Http::fake(['api.paystack.co/transaction/verify/RH-LATE' => Http::response(['status' => true, 'data' => paystackTransaction($pending)])]);
    $this->post(route('admin.payments.verify', $pending))->assertSessionHas('status');
    expect($pending->fresh()->isSuccessful())->toBeTrue();
});

it('explains that Paystack needs a deliverable email before trying to pay', function () {
    Http::fake();
    $user = User::factory()->create(['email' => 'admin@researchhub.com']);
    $this->actingAs($user)->withSession(['wizard' => ['degree_level' => 'mba']]);

    $this->get(route('projects.payment'))->assertOk()->assertSee('cannot receive receipts');
    $this->from(route('projects.payment'))->post(route('projects.payment.store'))
        ->assertRedirect(route('projects.payment'))->assertSessionHas('error', fn ($m) => str_contains($m, 'real email address'));

    Http::assertNothingSent();
    expect(Payment::count())->toBe(0);
});

it('turns a Paystack email rejection into a clear message', function () {
    Http::fake(['api.paystack.co/*' => Http::response(['status' => false, 'message' => 'Invalid Email Address Passed'], 400)]);
    $this->withSession(['wizard' => ['degree_level' => 'mba']]);

    $this->from(route('projects.payment'))->post(route('projects.payment.store'))
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'Update it under Profile'));
});

it('recognises which addresses Paystack accepts', function (string $email, bool $ok) {
    expect(PaymentController::payableEmail($email))->toBe($ok);
})->with([
    ['ops@efosusu.com', true], ['ama.mensah@ug.edu.gh', true],
    ['admin@researchhub.com', false], ['someone@example.com', false], ['me@localhost', false], ['x@site.local', false],
]);
