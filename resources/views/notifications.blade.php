@extends(auth()->user()->is_admin && request()->query('area') !== 'app' ? 'layouts.admin' : 'layouts.page')
@section('title', 'Notifications | ResearchHub')
@section('page_title', 'Notifications')
@section('nav', 'notifications')
@section('content')
<div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
<div>
<h2 class="font-headline-lg text-headline-lg text-primary">Notifications</h2>
<p class="font-body-md text-body-md text-on-surface-variant mt-1">Updates about your projects.</p>
</div>
@if ($notifications->total())
<form action="{{ route('notifications.read') }}" method="POST">@csrf
<button class="flex items-center gap-1 px-4 py-2 border border-outline-variant rounded-lg text-primary font-label-md text-label-md hover:bg-surface-container-low" type="submit"><span class="material-symbols-outlined text-[18px]">done_all</span>Mark all as read</button>
</form>
@endif
</div>
<section class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden">
@if ($notifications->isEmpty())
<div class="p-stack-md"><x-empty icon="notifications" title="No notifications yet">You will be told here (and by email, if you have email alerts on) when something needs you or is ready to download.</x-empty></div>
@else
<ul class="divide-y divide-outline-variant">
@foreach ($notifications as $n)
<li><a class="flex items-start gap-4 px-stack-md py-4 hover:bg-surface-container-low {{ $n->read_at ? '' : 'bg-secondary-container/20' }}" href="{{ route('notifications.open', $n->id) }}">
<span class="material-symbols-outlined text-primary mt-0.5">{{ $n->data['icon'] ?? 'notifications' }}</span>
<div class="flex-1 min-w-0">
<p class="font-label-md text-label-md text-on-surface {{ $n->read_at ? '' : 'font-bold' }}">{{ $n->data['title'] ?? 'Update' }}</p>
<p class="font-body-sm text-body-sm text-on-surface-variant">{{ $n->data['message'] ?? '' }}</p>
</div>
<span class="text-[12px] text-on-surface-variant whitespace-nowrap">{{ $n->created_at->diffForHumans() }}</span>
</a></li>
@endforeach
</ul>
<x-admin.pager :items="$notifications"/>
@endif
</section>
@endsection
