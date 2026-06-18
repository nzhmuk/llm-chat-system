<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - MU-TH-UR 6000</title>

    @vite(['resources/css/app.css'])
</head>
<body class="theme-muthur">

<header>
    <h1>MU-TH-UR / 6000</h1>
</header>

<main>
    <div class="mu-logo" role="img" aria-label="USCSS Nostromo"></div>

    <div class="mu-auth">
        <h2>ACCESS TERMINAL</h2>

        @if ($errors->any())
            <div class="mu-errors">ACCESS DENIED.
@foreach ($errors->all() as $error){{ $error }}
@endforeach</div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="mu-field">
                <label>EMAIL</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus>
            </div>

            <div class="mu-field">
                <label>PASSWORD</label>
                <input type="password" name="password" required>
            </div>

            <button type="submit">LOGIN</button>
        </form>

        <br>

        <a href="{{ route('register') }}">NO CREDENTIALS? CREATE USER</a>
    </div>
</main>

</body>
</html>
