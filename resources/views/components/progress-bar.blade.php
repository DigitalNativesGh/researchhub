@props(['value' => 0, 'color' => 'bg-primary'])
<div class="w-full bg-surface-container-high rounded-full h-1.5">
<div class="{{ $color }} h-1.5 rounded-full" style="width: {{ max(0, min(100, (int) $value)) }}%"></div>
</div>
