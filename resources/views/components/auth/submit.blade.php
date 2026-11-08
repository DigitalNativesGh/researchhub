@props(['icon' => 'arrow_forward'])
<button class="w-full bg-primary text-on-primary py-4 rounded-lg font-label-md text-label-md flex items-center justify-center gap-2 hover:bg-primary-container transition-all active:scale-[0.98]" type="submit">
<span>{{ $slot }}</span>
<span class="material-symbols-outlined text-[20px]">{{ $icon }}</span>
</button>
