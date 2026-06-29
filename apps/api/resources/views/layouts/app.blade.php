<!DOCTYPE html>
@php($locale = app()->getLocale())
<html
    lang="{{ str_replace('_', '-', $locale) }}"
    dir="{{ in_array($locale, ['ar', 'fa', 'he', 'ur']) ? 'rtl' : 'ltr' }}"
    class="theme-dark"
>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="min-h-screen bg-bg-base text-text-primary antialiased">
        {{ $slot }}

        @livewireScripts
    </body>
</html>
