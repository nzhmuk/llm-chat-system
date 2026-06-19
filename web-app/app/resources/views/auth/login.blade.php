<!DOCTYPE html>
<html>
<head>
    <title>Login - MU-TH-UR 6000</title>

    @vite(['resources/css/app.css'])
</head>
<body>

<header>
    <h1>MU-TH-UR / 6000</h1>
</header>

<main style="padding:20px;">

<form method="POST" action="{{ route('login') }}">
    @csrf

    <div>
        <label>Email</label><br>
        <input type="email" name="email" required autofocus>
    </div>

    <br>

    <div>
        <label>Password</label><br>
        <input type="password" name="password" required>
    </div>

    <br>

    <button type="submit">LOGIN</button>
</form>

</main>

</body>
</html>