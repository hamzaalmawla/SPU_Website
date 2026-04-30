<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Two-Factor Authentication</title>
</head>
<body>
    <h1>Two-Factor Authentication</h1>
    <p>Please enter your authentication code to continue.</p>

    @if ($errors->any())
        <div role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('admin.two-factor.verify') }}">
        @csrf

        <label for="code">Authentication Code</label>
        <input
            id="code"
            name="code"
            type="text"
            inputmode="numeric"
            autocomplete="one-time-code"
            placeholder="Enter 6-digit code or recovery code"
            required
            autofocus
        >

        <button type="submit">Verify</button>
    </form>
</body>
</html>
