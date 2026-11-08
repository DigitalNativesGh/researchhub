<label class="group inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-surface-container-low hover:bg-surface-container text-on-surface-variant font-label-sm text-label-sm transition-all cursor-pointer has-[:checked]:bg-primary has-[:checked]:text-on-primary has-[:checked]:shadow-sm">
<input checked class="sr-only" name="keywords[]" type="checkbox" value="{{ $keyword }}"/>
<span class="material-symbols-outlined text-[14px] group-has-[:checked]:hidden">add</span>
<span>{{ $keyword }}</span>
<span class="material-symbols-outlined text-[14px] hidden group-has-[:checked]:inline">close</span>
</label>
