<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'MU-TH-UR 6000') }}</title>

    @vite(['resources/css/app.css'])
</head>
<body class="theme-muthur">

<header class="mu-appbar">
    <h1>MU-TH-UR / 6000</h1>
    <nav class="mu-nav">
        <a href="{{ route('dashboard') }}">DASHBOARD</a>
        <a href="{{ route('chat.index') }}">TERMINAL</a>
        <a href="{{ route('profile.edit') }}">PROFILE</a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">LOGOUT</button>
        </form>
    </nav>
</header>

@isset($header)
    <div class="mu-pageheader">{{ $header }}</div>
@endisset

<main>
    {{ $slot }}
</main>

</body>
</html>
