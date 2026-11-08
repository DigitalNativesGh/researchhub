@if (session('status'))
<x-alert icon="check_circle" kind="success" :title="session('status')"/>
@endif
@if (session('error'))
<x-alert icon="error" kind="error" :title="session('error')"/>
@endif
@if ($errors->any() && ! ($hideErrorSummary ?? false))
<x-alert icon="error" kind="error" title="Please fix the following">
<ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
</x-alert>
@endif
