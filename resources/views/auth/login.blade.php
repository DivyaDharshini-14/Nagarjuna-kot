<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in | Nagarjuna KOT</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; background: #f7f3ed; color: #27221e; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
        .card { width: min(100%, 420px); padding: 36px; border: 1px solid #e5ddd2; border-radius: 18px; background: #fff; box-shadow: 0 20px 50px rgba(62, 45, 28, .09); }
        .eyebrow { margin: 0 0 8px; color: #b54824; font-size: 12px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; }
        h1 { margin: 0; font-size: 28px; }
        .subtitle { margin: 8px 0 28px; color: #71675d; }
        label { display: block; margin: 16px 0 7px; font-size: 14px; font-weight: 700; }
        input[type=email], input[type=password] { width: 100%; padding: 12px 14px; border: 1px solid #cabfb2; border-radius: 9px; font: inherit; }
        input:focus { outline: 3px solid rgba(181, 72, 36, .16); border-color: #b54824; }
        .remember { display: flex; align-items: center; gap: 8px; margin: 18px 0; color: #5f564d; font-size: 14px; }
        button { width: 100%; padding: 13px 18px; border: 0; border-radius: 9px; background: #9f3d20; color: #fff; font: inherit; font-weight: 800; cursor: pointer; }
        button:hover { background: #7f2e17; }
        .error { margin: 8px 0 0; color: #b42318; font-size: 13px; }
    </style>
</head>
<body>
    <main class="card">
        <p class="eyebrow">Nagarjuna</p>
        <h1>KOT Management</h1>
        <p class="subtitle">Sign in with your staff account.</p>

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <label for="email">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
            @error('email')
                <p class="error">{{ $message }}</p>
            @enderror

            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
            @error('password')
                <p class="error">{{ $message }}</p>
            @enderror

            <label class="remember">
                <input name="remember" type="checkbox" value="1" @checked(old('remember'))>
                Keep me signed in
            </label>

            <button type="submit">Sign in</button>
        </form>
    </main>
</body>
</html>
