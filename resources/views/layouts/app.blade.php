<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'JettProjekt') }}</title>
        <style>
            :root {
                color-scheme: light;
                --bg: #f7f8fb;
                --ink: #172033;
                --muted: #647084;
                --line: #dfe4ec;
                --panel: #ffffff;
                --accent: #2563eb;
                --accent-dark: #1d4ed8;
                --danger: #b42318;
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                min-height: 100vh;
                background: var(--bg);
                color: var(--ink);
                font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                letter-spacing: 0;
            }

            a {
                color: var(--accent);
                text-decoration: none;
            }

            .page {
                min-height: 100vh;
                display: grid;
                place-items: center;
                padding: 32px 16px;
            }

            .shell {
                width: min(100%, 420px);
                background: var(--panel);
                border: 1px solid var(--line);
                border-radius: 8px;
                padding: 28px;
                box-shadow: 0 18px 50px rgba(23, 32, 51, 0.08);
            }

            .dashboard {
                width: min(100%, 880px);
            }

            .topbar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                margin-bottom: 24px;
            }

            h1 {
                margin: 0 0 8px;
                font-size: 28px;
                line-height: 1.15;
                letter-spacing: 0;
            }

            p {
                margin: 0;
                color: var(--muted);
                line-height: 1.55;
            }

            form {
                margin-top: 22px;
                display: grid;
                gap: 14px;
            }

            label {
                display: grid;
                gap: 7px;
                color: #324055;
                font-size: 14px;
                font-weight: 650;
            }

            input {
                width: 100%;
                min-height: 44px;
                border: 1px solid var(--line);
                border-radius: 6px;
                padding: 10px 12px;
                color: var(--ink);
                font: inherit;
            }

            input:focus {
                border-color: var(--accent);
                outline: 3px solid rgba(37, 99, 235, 0.12);
            }

            button {
                min-height: 44px;
                border: 0;
                border-radius: 6px;
                background: var(--accent);
                color: #ffffff;
                cursor: pointer;
                font: inherit;
                font-weight: 700;
                padding: 10px 14px;
            }

            button:hover {
                background: var(--accent-dark);
            }

            .link-row {
                margin-top: 18px;
                font-size: 14px;
            }

            .errors {
                margin: 0 0 18px;
                padding: 12px 14px;
                border-radius: 6px;
                background: #fff1f0;
                color: var(--danger);
                font-size: 14px;
                line-height: 1.45;
            }

            .profile-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                gap: 12px;
                margin-top: 20px;
            }

            .profile-item {
                border: 1px solid var(--line);
                border-radius: 8px;
                padding: 14px;
                background: #fbfcff;
            }

            .profile-item strong {
                display: block;
                margin-bottom: 6px;
                color: #324055;
                font-size: 13px;
            }

            .logout-form {
                margin: 0;
            }
        </style>
    </head>
    <body>
        @yield('body')
    </body>
</html>
