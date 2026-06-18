<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register - MU-TH-UR 6000</title>

    @vite(['resources/css/app.css'])
</head>
<body class="theme-muthur">

<header>
    <h1>MU-TH-UR / 6000</h1>
</header>

<main>
    <div class="mu-logo" role="img" aria-label="USCSS Nostromo"></div>

    <div class="mu-auth">
        <h2>CREATE NEW USER</h2>

        @if ($errors->any())
            <div class="mu-errors">REGISTRATION REJECTED.
@foreach ($errors->all() as $error){{ $error }}
@endforeach</div>
        @endif

        <form method="POST" action="{{ route('register') }}">
            @csrf

            <div class="mu-field">
                <label>NAME</label>
                <input type="text" name="name" value="{{ old('name') }}" required autofocus>
            </div>

            <div class="mu-field">
                <label>EMAIL</label>
                <input type="email" name="email" value="{{ old('email') }}" required>
            </div>

            <div class="mu-field">
                <label>PASSWORD</label>
                <input type="password" name="password" required>
            </div>

            <div class="mu-field">
                <label>CONFIRM PASSWORD</label>
                <input type="password" name="password_confirmation" required>
            </div>

            <button type="submit">REGISTER</button>
        </form>

        <br>

        <a href="{{ route('login') }}">ALREADY REGISTERED? LOGIN</a>
    </div>
</main>

</body>
</html>
