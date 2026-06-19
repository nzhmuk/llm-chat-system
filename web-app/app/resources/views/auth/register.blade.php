<!DOCTYPE html>
<html>
<head>
    <title>Register - MU-TH-UR 6000</title>

    @vite(['resources/css/app.css'])
</head>
<body>

<header>
    <h1>MU-TH-UR / 6000</h1>
</header>

<main style="padding:20px; max-width:500px; margin:0 auto;">

    <div class="message assistant">
        CREATE NEW USER
    </div>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div>
            <label>NAME</label><br>
            <input type="text" name="name" value="{{ old('name') }}" required autofocus>
        </div>

        <br>

        <div>
            <label>EMAIL</label><br>
            <input type="email" name="email" value="{{ old('email') }}" required>
        </div>

        <br>

        <div>
            <label>PASSWORD</label><br>
            <input type="password" name="password" required>
        </div>

        <br>

        <div>
            <label>CONFIRM PASSWORD</label><br>
            <input type="password" name="password_confirmation" required>
        </div>

        <br>

        <button type="submit">REGISTER</button>
    </form>

    <br>

    <a href="{{ route('login') }}">ALREADY REGISTERED? LOGIN</a>

</main>

</body>
</html>