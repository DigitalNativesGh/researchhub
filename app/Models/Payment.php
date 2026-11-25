<?php

namespace App\Models;

use App\Enums\DegreeLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Payment extends Model
{
    public const SUCCESS = 'success';

    protected $fillable = [
        'user_id', 'project_id', 'degree_level', 'data_collection', 'amount', 'currency', 'price_amount', 'price_currency', 'exchange_rate', 'reference', 'status', 'channel', 'card_type',
        'last4', 'gateway_response', 'paystack_id', 'paid_at', 'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'degree_level' => DegreeLevel::class,
            'data_collection' => 'boolean',
            'amount' => 'integer',
            'price_amount' => 'integer',
            'exchange_rate' => 'float',
            'paystack_id' => 'integer',
            'paid_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** Price of a research type in the major currency unit, from settings (config overridden by admins). */
    public static function priceFor(DegreeLevel $level): float
    {
        return (float) config("researchhub.payments.prices.{$level->value}", 0);
    }

    /** Price of the optional data collection add-on for a research type. */
    public static function dataCollectionPriceFor(DegreeLevel $level): float
    {
        return (float) config("researchhub.payments.data_collection.{$level->value}", 0);
    }

    /** What the researcher pays to start a project: the research type, plus data collection if chosen. */
    public static function totalFor(DegreeLevel $level, bool $dataCollection = false): float
    {
        return self::priceFor($level) + ($dataCollection ? self::dataCollectionPriceFor($level) : 0);
    }

    /** The currency Paystack charges in (billing), e.g. GHS. Revenue and payment amounts are in this currency. */
    public static function currency(): string
    {
        return strtoupper((string) config('researchhub.payments.currency', 'GHS'));
    }

    /** The currency prices are set and shown in, e.g. USD. Converted to the billing currency at checkout. */
    public static function priceCurrency(): string
    {
        return strtoupper((string) config('researchhub.payments.price_currency', 'USD'));
    }

    /** Units of the billing currency per one unit of the price currency (e.g. 11.5 GHS per USD). */
    public static function exchangeRate(): float
    {
        return self::priceCurrency() === self::currency() ? 1.0 : (float) config('researchhub.payments.exchange_rate', 1);
    }

    /** A price (price currency) converted to the amount charged (billing currency), rounded to the minor unit. */
    public static function toBilling(float $price): float
    {
        return round($price * self::exchangeRate(), 2);
    }

    public static function format(float $amount, ?string $currency = null): string
    {
        $currency ??= self::currency();
        $number = number_format($amount, fmod($amount, 1) ? 2 : 0);

        return $currency === 'USD' ? '$'.$number : $currency.' '.$number;
    }

    /** A price in the price currency, e.g. "$200". */
    public static function formatPrice(float $amount): string
    {
        return self::format($amount, self::priceCurrency());
    }

    public static function formatRate(): string
    {
        return '1 '.self::priceCurrency().' = '.rtrim(rtrim(number_format(self::exchangeRate(), 4), '0'), '.').' '.self::currency();
    }

    public static function newReference(): string
    {
        return 'RH-'.Str::upper(Str::random(16));
    }

    /** Successful payments not yet used for a project. */
    public function scopeUnused(Builder $query): Builder
    {
        return $query->where('status', self::SUCCESS)->whereNull('consumed_at');
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::SUCCESS;
    }

    public function amountMajor(): float
    {
        return $this->amount / 100;
    }

    public function displayAmount(): string
    {
        return self::format($this->amountMajor(), $this->currency);
    }

    /** The price the researcher saw (e.g. "$200"), when it differs from the billing currency. */
    public function priceDisplay(): ?string
    {
        return $this->price_amount !== null && $this->price_currency && $this->price_currency !== $this->currency
            ? self::format($this->price_amount / 100, $this->price_currency)
            : null;
    }

    /** "$200 (GHS 2,300)", or just the charged amount. */
    public function fullAmount(): string
    {
        return ($price = $this->priceDisplay()) ? "{$price} ({$this->displayAmount()})" : $this->displayAmount();
    }

    /** "Mobile Money (MTN)", "Visa •••• 4081" */
    public function methodLabel(): string
    {
        return match ($this->channel) {
            'mobile_money' => 'Mobile Money'.($this->card_type ? ' ('.Str::upper($this->card_type).')' : ''),
            'card' => trim(Str::ucfirst(trim((string) $this->card_type)) ?: 'Card').($this->last4 ? ' •••• '.$this->last4 : ''),
            null => '—',
            default => Str::headline($this->channel),
        };
    }

    /**
     * Apply a verified Paystack transaction (from the verify endpoint or a webhook). Marks the payment
     * successful only when Paystack reports success for exactly the amount and currency we asked for.
     * Returns true when the payment is (now) successful. Safe to call more than once.
     */
    public function applyPaystack(array $data): bool
    {
        if ($this->isSuccessful()) {
            return true;
        }

        $paid = ($data['status'] ?? null) === 'success'
            && (int) ($data['amount'] ?? -1) === $this->amount
            && strtoupper((string) ($data['currency'] ?? '')) === $this->currency;

        $auth = $data['authorization'] ?? [];

        $this->forceFill([
            'status' => $paid ? self::SUCCESS : (in_array($data['status'] ?? null, ['failed', 'abandoned', 'reversed'], true) ? $data['status'] : ($this->status ?: 'pending')),
            'channel' => $data['channel'] ?? $this->channel,
            // Card brand (visa, mastercard) for cards; the network (MTN, Vodafone, AirtelTigo) for Mobile Money.
            'card_type' => (($data['channel'] ?? null) === 'mobile_money' ? ($auth['bank'] ?? null) : trim((string) ($auth['card_type'] ?? ''))) ?: $this->card_type,
            'last4' => $auth['last4'] ?? $this->last4,
            'gateway_response' => isset($data['gateway_response']) ? Str::limit((string) $data['gateway_response'], 250, '') : $this->gateway_response,
            'paystack_id' => $data['id'] ?? $this->paystack_id,
            'paid_at' => $paid ? ($data['paid_at'] ?? now()) : null,
        ])->save();

        if (! $paid && ($data['status'] ?? null) === 'success') {
            report(new \RuntimeException("Paystack payment {$this->reference} succeeded with an unexpected amount or currency."));
        }

        return $paid;
    }
}
