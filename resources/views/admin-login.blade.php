<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login</title>
    <style>
        :root {
            --bg: #f3f4f6;
            --card: #ffffff;
            --text: #1f2937;
            --muted: #6b7280;
            --border: #e5e7eb;
            --primary: #4040c8;
            --primary-hover: #3333a8;
            --danger-bg: #fef2f2;
            --danger-text: #b91c1c;
            --danger-border: #fecaca;
            --info-bg: #eef2ff;
            --info-text: #3730a3;
            --info-border: #c7d2fe;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0f1117;
                --card: #1a1d27;
                --text: #e5e7eb;
                --muted: #9ca3af;
                --border: #2a2e3b;
                --danger-bg: #2a1517;
                --danger-text: #fca5a5;
                --danger-border: #5b1f24;
                --info-bg: #1b1d3a;
                --info-text: #c7d2fe;
                --info-border: #33366b;
            }
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: var(--bg);
            color: var(--text);
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        .card {
            width: 100%;
            max-width: 24rem;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            padding: 2rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.06);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin-bottom: 1.5rem;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .brand svg { color: var(--primary); }

        .alert {
            margin-bottom: 1rem;
            padding: 0.65rem 0.85rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            border: 1px solid;
        }

        .alert-info {
            background: var(--info-bg);
            color: var(--info-text);
            border-color: var(--info-border);
        }

        .alert-error {
            background: var(--danger-bg);
            color: var(--danger-text);
            border-color: var(--danger-border);
        }

        label {
            display: block;
            margin-bottom: 0.35rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .field { margin-bottom: 1rem; }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 0.65rem 0.8rem;
            font-size: 0.95rem;
            color: var(--text);
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
        }

        input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(64, 64, 200, 0.2);
        }

        button {
            width: 100%;
            margin-top: 0.5rem;
            padding: 0.7rem 1rem;
            font-size: 0.95rem;
            font-weight: 600;
            color: #fff;
            background: var(--primary);
            border: 0;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: background 0.15s;
        }

        button:hover { background: var(--primary-hover); }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="9"></circle>
                <circle cx="12" cy="12" r="3"></circle>
                <path d="M12 3v3M12 18v3M3 12h3M18 12h3"></path>
            </svg>
            <span>CDTM API monitoring</span>
        </div>

        @if (session('message'))
            <div class="alert alert-info">{{ session('message') }}</div>
        @endif

        @error('email')
            <div class="alert alert-error">{{ $message }}</div>
        @enderror

        <form method="POST" action="/admin/login">
            @csrf

            <div class="field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}"
                       placeholder="you@example.com" autocomplete="username" required autofocus>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password"
                       placeholder="••••••••" autocomplete="current-password" required>
            </div>

            <button type="submit">Log in</button>
        </form>
    </div>
</body>
</html>
