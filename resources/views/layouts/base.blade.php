<!DOCTYPE html>

<html class="@yield('html_class', 'light')" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<title>@yield('title', 'ResearchHub')</title>
@if (\App\Support\Assets::useCdn())
@include('partials.tailwind-cdn')
@else
@vite('resources/css/app.css')
@endif
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
@if (\App\Support\Assets::useCdn())
<style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            display: inline-block;
            vertical-align: middle;
        }
        body { font-family: 'Inter', sans-serif; }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; height: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #e1e3e4; border-radius: 10px; }
        .ambient-shadow { box-shadow: 0 10px 40px -10px rgba(4, 22, 50, 0.08); }
        .input-ring:focus { outline: none; border-color: #041632; box-shadow: 0 0 0 2px rgba(4, 22, 50, 0.1); }
        [x-cloak] { display: none !important; }
    </style>
@endif
@stack('styles')
<script defer src="{{ asset('js/researchhub.js') }}"></script>
</head>
@yield('body')
</html>
