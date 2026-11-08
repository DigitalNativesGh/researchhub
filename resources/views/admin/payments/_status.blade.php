@php
    [$kind, $label] = match ($payment->status) {
        'success' => ['done', 'Paid'],
        'pending' => ['pending', 'Pending'],
        'abandoned' => ['pending', 'Abandoned'],
        default => ['failed', ucfirst($payment->status)],
    };
@endphp
<x-badge :kind="$kind">{{ $label }}</x-badge>
