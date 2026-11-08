@extends('layouts.project')
@section('title', 'ResearchHub | Your Data')
@section('stage_key', 'data')
@php
    $accept = [
        'quantitative' => '.csv,.xlsx,.sav,.dta',
        'transcript' => '.docx,.pdf,.txt',
        'qualitative_codes' => '.csv,.xlsx',
    ];
    $help = [
        'quantitative' => ['One file with every response: one row per respondent and one column per question. Use the item codes from your questionnaire (for example PU1, PU2) as the column names in the first row.', 'CSV, Excel (.xlsx), SPSS (.sav) or Stata (.dta) · up to '.$limits['table']],
        'transcript' => ['One file per interview, written out in full. Remove names and other details that identify participants.', 'Word (.docx), PDF or plain text (.txt) · up to '.$limits['transcript'].' each'],
        'qualitative_codes' => ['Optional: if you have already coded the interviews, a table with the columns participant, theme, code and excerpt.', 'CSV or Excel (.xlsx) · up to '.$limits['table']],
    ];
@endphp
@section('stage')
@if (! $unlocked && ! $submitted)
<x-empty icon="lock" title="Data upload is not open yet">This step opens once your Chapters 1–3 and data collection instrument have been delivered. We will email you when they are ready.</x-empty>
@elseif ($submitted && ! $unlocked)
<section class="bg-surface-container-lowest border border-outline-variant p-stack-md rounded-xl">
<div class="flex items-center gap-3 mb-4">
<span class="material-symbols-outlined text-primary">inventory</span>
<h3 class="font-headline-sm text-headline-sm text-primary">Data submitted {{ $project->data_submitted_at?->format('M j, Y') }}</h3>
</div>
<p class="font-body-sm text-body-sm text-on-surface-variant mb-4">{{ $project->workflow_status->researcherMessage() }}</p>
<ul class="divide-y divide-outline-variant border border-outline-variant rounded-lg">
@foreach ($submittedFiles as $file)
<li class="px-4 py-3 flex items-center justify-between gap-4">
<div class="min-w-0"><p class="font-label-md text-label-md text-on-surface truncate">{{ $file->original_name }}</p><p class="text-[12px] text-on-surface-variant">{{ $kinds[$file->kind] ?? ucfirst($file->kind) }} · {{ $file->sizeForHumans() }}</p></div>
<a class="p-2 text-on-surface-variant hover:text-primary" href="{{ route('datasets.file', $file) }}" title="Download your file"><span class="material-symbols-outlined text-[20px]">download</span></a>
</li>
@endforeach
</ul>
</section>
@else
<div class="grid grid-cols-12 gap-gutter" data-data-step data-status-url="{{ route('datasets.status', $project) }}" data-upload-url="{{ route('datasets.store', $project) }}">
<div class="col-span-12 lg:col-span-8 space-y-stack-md">
<section class="bg-surface-container-lowest border border-outline-variant p-stack-md rounded-xl">
<h3 class="font-headline-sm text-headline-sm text-primary mb-2">Upload the data you collected</h3>
<p class="font-body-sm text-body-sm text-on-surface-variant">Collect your data with the instrument we delivered, then upload it below. Each file is checked as soon as it arrives. You can replace or remove files until you submit; submitting sends your data to our team for your complete thesis.</p>
</section>

@foreach ($kinds as $kind => $label)
<section class="bg-surface-container-lowest border border-outline-variant p-stack-md rounded-xl" data-kind-section="{{ $kind }}">
<div class="flex items-start justify-between gap-4 mb-3">
<div>
<h4 class="font-label-md text-label-md text-primary">{{ $label }}</h4>
<p class="font-body-sm text-body-sm text-on-surface-variant mt-1">{{ $help[$kind][0] }}</p>
<p class="text-[12px] text-on-surface-variant mt-1">{{ $help[$kind][1] }}</p>
</div>
</div>
<form action="{{ route('datasets.store', $project) }}" class="border-2 border-dashed border-outline-variant rounded-xl p-6 flex flex-col sm:flex-row items-center justify-between gap-4 hover:bg-surface-container-low transition-colors" data-upload-zone enctype="multipart/form-data" method="POST">
@csrf
<input name="kind" type="hidden" value="{{ $kind }}"/>
<div class="flex items-center gap-3 text-on-surface-variant">
<span class="material-symbols-outlined text-3xl">cloud_upload</span>
<span class="font-body-sm text-body-sm">Drag a file here, or</span>
</div>
<label class="px-5 py-2 bg-primary text-on-primary rounded-lg font-label-md text-label-md hover:bg-primary/90 cursor-pointer">{{ $kind === 'transcript' ? 'Choose files' : ($pending->contains('kind', $kind) ? 'Replace file' : 'Choose file') }}
<input accept="{{ $accept[$kind] }}" class="hidden" name="file" type="file" {{ $kind === 'transcript' ? 'multiple' : '' }}/>
</label>
<noscript><button class="px-4 py-2 border border-outline-variant rounded-lg" type="submit">Upload</button></noscript>
</form>
<div class="mt-3 space-y-2" data-progress-list></div>
<ul class="mt-3 divide-y divide-outline-variant border border-outline-variant rounded-lg {{ $pending->where('kind', $kind)->isEmpty() ? 'hidden' : '' }}" data-file-list="{{ $kind }}">
@foreach ($pending->where('kind', $kind) as $file)
@include('projects._dataset-row', ['file' => $file])
@endforeach
</ul>
</section>
@endforeach

@if ($answering)
<section class="bg-surface-container-lowest border border-outline-variant p-stack-md rounded-xl">
<p class="font-body-sm text-body-sm text-on-surface">Our team asked for more information. Add any files they asked for here, then <a class="text-secondary hover:underline" href="{{ route('projects.show', $project) }}">send your reply from the Overview page</a>. Files you submitted earlier stay with our team.</p>
</section>
@else
<section class="bg-surface-container-lowest border border-outline-variant p-stack-md rounded-xl">
<form action="{{ route('data.submit', $project) }}" class="space-y-4" method="POST">
@csrf
<label class="flex items-start gap-3 cursor-pointer">
<input class="mt-1 w-5 h-5 rounded border-outline-variant text-primary" name="confirm" required type="checkbox" value="1"/>
<span class="font-body-sm text-body-sm text-on-surface">These are my final data files, collected with the delivered instrument. I understand they cannot be changed after I submit.</span>
</label>
<x-field-error name="confirm"/>
<button class="flex items-center gap-2 bg-primary text-on-primary px-8 py-3 rounded-lg font-label-md text-label-md hover:bg-primary/90 disabled:opacity-50" data-busy-text="Submitting…" data-submit-data type="submit"><span class="material-symbols-outlined">send</span>Submit my data</button>
</form>
</section>
@endif
</div>

<aside class="col-span-12 lg:col-span-4 space-y-stack-md">
<section class="bg-surface-container-lowest border border-outline-variant p-stack-md rounded-xl">
<h3 class="font-label-md text-label-md text-primary mb-3">Your instrument</h3>
@forelse ($instruments as $doc)
<a class="flex items-center gap-2 py-2 text-secondary hover:underline font-body-sm text-body-sm" href="{{ route('deliverables.download', [$project, $doc]) }}"><span class="material-symbols-outlined text-[18px]">download</span>{{ $doc->label }} (.{{ $doc->format }})</a>
@empty
<p class="font-body-sm text-body-sm text-on-surface-variant">Download it from the Overview page.</p>
@endforelse
</section>
<section class="bg-surface-container-low border border-outline-variant p-stack-md rounded-xl">
<h3 class="font-label-md text-label-md text-primary mb-2">Before you upload</h3>
<ul class="font-body-sm text-body-sm text-on-surface-variant space-y-2 list-disc pl-5">
<li>Use the instrument exactly as delivered, so your data matches its questions.</li>
<li>Remove names, phone numbers and other details that identify participants.</li>
<li>Keep the original files; you can download what you uploaded at any time.</li>
</ul>
</section>
</aside>
</div>
@endif
@endsection

@push('scripts')
<script>
(() => {
    const root = document.querySelector('[data-data-step]');
    if (!root) return;
    const token = document.querySelector('meta[name="csrf-token"]').content;
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const badges = {
        done: 'bg-tertiary-fixed text-on-tertiary-fixed', approval: 'bg-primary-fixed text-on-primary-fixed-variant',
        failed: 'bg-error-container text-on-error-container', progress: 'bg-secondary-container text-on-secondary-container', pending: 'bg-surface-container-high text-on-surface-variant',
    };

    function row(d) {
        const detail = d.rows !== null && d.rows !== undefined && d.status !== 'invalid' ? `${d.rows} rows · ${d.columns} columns · ` : '';
        const notes = [...d.errors.map((e) => `<li class="text-error">${esc(e)}</li>`), ...d.warnings.map((w) => `<li>${esc(w)}</li>`)].join('');
        return `<li class="px-4 py-3" data-dataset="${d.id}">
            <div class="flex items-center justify-between gap-4">
                <div class="min-w-0"><p class="font-label-md text-label-md text-on-surface truncate">${esc(d.name)}</p><p class="text-[12px] text-on-surface-variant">${detail}${esc(d.size)}</p></div>
                <div class="flex items-center gap-2 shrink-0">
                    <span class="${badges[d.badge] || badges.pending} px-3 py-1 rounded-full text-[12px] font-bold uppercase tracking-wider">${esc(d.label)}</span>
                    <button class="p-2 text-on-surface-variant hover:text-error" data-remove="${esc(d.delete_url)}" title="Remove" type="button"><span class="material-symbols-outlined text-[20px]">delete</span></button>
                </div>
            </div>
            ${notes ? `<ul class="mt-2 text-[12px] text-on-surface-variant list-disc pl-5 space-y-1">${notes}</ul>` : ''}
        </li>`;
    }

    function render(datasets) {
        root.querySelectorAll('[data-file-list]').forEach((list) => {
            const items = datasets.filter((d) => d.kind === list.dataset.fileList);
            list.innerHTML = items.map(row).join('');
            list.classList.toggle('hidden', items.length === 0);
        });
        const checking = datasets.some((d) => ['uploaded', 'validating'].includes(d.status));
        const submit = root.querySelector('[data-submit-data]');
        if (submit) submit.disabled = checking || datasets.length === 0;
        return checking;
    }

    let timer = null;
    function poll() {
        clearTimeout(timer);
        fetch(root.dataset.statusUrl, { headers: { Accept: 'application/json' } })
            .then((r) => r.json()).then((data) => { if (render(data.datasets)) timer = setTimeout(poll, 2500); })
            .catch(() => { timer = setTimeout(poll, 8000); });
    }

    function upload(zone, file) {
        const list = zone.parentElement.querySelector('[data-progress-list]');
        const item = document.createElement('div');
        item.innerHTML = `<div class="flex justify-between text-[12px] text-on-surface-variant mb-1"><span class="truncate">${esc(file.name)}</span><span data-pct>0%</span></div>
            <div class="w-full bg-surface-container-high rounded-full h-1.5"><div class="bg-primary h-1.5 rounded-full transition-all" data-bar style="width:0%"></div></div>`;
        list.appendChild(item);

        const form = new FormData(zone);
        form.set('file', file);
        const xhr = new XMLHttpRequest();
        xhr.open('POST', root.dataset.uploadUrl);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-CSRF-TOKEN', token);
        xhr.upload.onprogress = (e) => {
            if (!e.lengthComputable) return;
            const pct = Math.round((e.loaded / e.total) * 100);
            item.querySelector('[data-bar]').style.width = pct + '%';
            item.querySelector('[data-pct]').textContent = pct + '%';
        };
        xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                item.remove();
            } else {
                let message = 'Upload failed.';
                try { const body = JSON.parse(xhr.responseText); message = body.errors ? Object.values(body.errors).flat().join(' ') : (body.message || message); } catch (e) {}
                item.innerHTML = `<p class="text-[12px] text-error">${esc(file.name)}: ${esc(message)}</p>`;
            }
            poll();
        };
        xhr.onerror = () => { item.innerHTML = `<p class="text-[12px] text-error">${esc(file.name)}: the upload was interrupted. Please try again.</p>`; };
        xhr.send(form);
    }

    root.querySelectorAll('[data-upload-zone]').forEach((zone) => {
        const input = zone.querySelector('input[type=file]');
        const send = (files) => Array.from(files).forEach((f) => upload(zone, f));
        input.addEventListener('change', () => { send(input.files); input.value = ''; });
        zone.addEventListener('submit', (e) => { e.preventDefault(); send(input.files); });
        ['dragenter', 'dragover'].forEach((ev) => zone.addEventListener(ev, (e) => { e.preventDefault(); zone.classList.add('border-primary'); }));
        ['dragleave', 'drop'].forEach((ev) => zone.addEventListener(ev, (e) => { e.preventDefault(); zone.classList.remove('border-primary'); }));
        zone.addEventListener('drop', (e) => {
            const files = Array.from(e.dataTransfer?.files || []);
            send(input.multiple ? files : files.slice(0, 1));
        });
    });

    root.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-remove]');
        if (!btn || !confirm('Remove this file?')) return;
        fetch(btn.dataset.remove, { method: 'DELETE', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token } }).then(poll);
    });

    poll();
})();
</script>
@endpush
