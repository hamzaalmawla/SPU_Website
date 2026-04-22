<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login</title>
</head>
<body>
    <h1>Admin Login</h1>

    @if ($errors->any())
        <div role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('admin.login.attempt') }}">
        @csrf

        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus>

        <label for="password">Password</label>
        <input id="password" name="password" type="password" required>

        <label for="remember">
            <input id="remember" name="remember" type="checkbox" value="1">
            Remember me
        </label>

        <button type="submit">Sign in</button>
    </form>
</body>
</html>
