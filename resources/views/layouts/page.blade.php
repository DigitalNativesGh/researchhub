{{-- App page with the standard content container (used by the new pages). --}}
@extends('layouts.app')

@section('main')
<div class="max-w-container-max mx-auto space-y-stack-md">
@include('partials.flash')
@yield('content')
</div>
@endsection
