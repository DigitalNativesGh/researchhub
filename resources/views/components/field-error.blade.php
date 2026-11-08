@props(['name'])
@error($name)
<p class="mt-2 flex items-center gap-1 text-[12px] font-medium text-error"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p>
@enderror
