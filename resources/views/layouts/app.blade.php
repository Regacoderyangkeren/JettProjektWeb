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
                --success: #14804a;
                --warning: #a16207;
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

            .app-frame {
                min-height: 100vh;
                display: grid;
                grid-template-columns: 240px minmax(0, 1fr);
            }

            .sidebar {
                min-height: 100vh;
                border-right: 1px solid var(--line);
                background: #ffffff;
                padding: 22px 16px;
                position: sticky;
                top: 0;
            }

            .brand {
                display: block;
                margin-bottom: 24px;
                color: var(--ink);
                font-size: 18px;
                font-weight: 800;
            }

            .nav-list {
                display: grid;
                gap: 6px;
            }

            .nav-list a {
                display: flex;
                align-items: center;
                min-height: 40px;
                padding: 8px 10px;
                border-radius: 6px;
                color: #324055;
                font-weight: 650;
            }

            .nav-list a.active,
            .nav-list a:hover {
                background: #edf3ff;
                color: var(--accent-dark);
            }

            .workspace {
                min-width: 0;
                padding: 28px;
            }

            .page-header {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 18px;
                margin-bottom: 22px;
            }

            .page-title {
                min-width: 0;
            }

            .page-title h1 {
                margin-bottom: 6px;
            }

            .content-grid {
                display: grid;
                grid-template-columns: minmax(0, 1.5fr) minmax(320px, 0.8fr);
                gap: 18px;
                align-items: start;
            }

            .panel {
                background: #ffffff;
                border: 1px solid var(--line);
                border-radius: 8px;
                padding: 18px;
            }

            .panel + .panel {
                margin-top: 18px;
            }

            .panel h2 {
                margin: 0 0 14px;
                font-size: 18px;
                letter-spacing: 0;
            }

            .summary-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
                gap: 12px;
            }

            .metric {
                border: 1px solid var(--line);
                border-radius: 8px;
                padding: 14px;
                background: #ffffff;
            }

            .metric strong {
                display: block;
                margin-bottom: 8px;
                font-size: 24px;
                line-height: 1;
            }

            .metric span {
                color: var(--muted);
                font-size: 13px;
                font-weight: 650;
            }

            .stack {
                display: grid;
                gap: 10px;
            }

            .row-item {
                display: grid;
                gap: 6px;
                border: 1px solid var(--line);
                border-radius: 8px;
                padding: 14px;
                background: #fbfcff;
            }

            .row-item-header {
                display: flex;
                justify-content: space-between;
                gap: 12px;
            }

            .row-item h3 {
                margin: 0;
                color: var(--ink);
                font-size: 15px;
            }

            .muted {
                color: var(--muted);
            }

            .small {
                font-size: 13px;
            }

            .badge {
                display: inline-flex;
                align-items: center;
                min-height: 24px;
                border-radius: 999px;
                padding: 3px 9px;
                background: #eef2f7;
                color: #475467;
                font-size: 12px;
                font-weight: 750;
                white-space: nowrap;
            }

            .badge.active,
            .badge.done,
            .badge.approved {
                background: #e9f8ef;
                color: var(--success);
            }

            .badge.completed,
            .badge.high,
            .badge.review {
                background: #fff7df;
                color: var(--warning);
            }

            .badge.archived,
            .badge.cancelled,
            .badge.disapproved {
                background: #fff1f0;
                color: var(--danger);
            }

            .tag-badges {
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
                margin-top: 5px;
            }

            .tag-badge {
                display: inline-flex;
                align-items: center;
                min-height: 22px;
                border: 1px solid;
                border-radius: 999px;
                padding: 2px 8px;
                background: #ffffff;
                font-size: 12px;
                font-weight: 700;
            }

            .person-row {
                display: grid;
                grid-template-columns: 44px minmax(0, 1fr);
                gap: 12px;
                align-items: center;
            }

            .avatar {
                width: 44px;
                height: 44px;
                border-radius: 999px;
                background: #dfe7f2;
                color: #324055;
                display: grid;
                place-items: center;
                font-weight: 800;
                position: relative;
                overflow: hidden;
            }

            .avatar img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }

            .status-dot {
                position: absolute;
                right: 1px;
                top: 1px;
                width: 13px;
                height: 13px;
                border: 2px solid #ffffff;
                border-radius: 999px;
                background: #98a2b3;
            }

            .status-dot.online {
                background: #16a34a;
            }

            .status-dot.standby {
                background: #2563eb;
            }

            .status-dot.offline {
                background: #98a2b3;
            }

            .toolbar {
                display: flex;
                align-items: center;
                justify-content: flex-end;
                gap: 8px;
                flex-wrap: wrap;
            }

            .action-link {
                display: inline-flex;
                align-items: center;
                min-height: 44px;
                padding: 10px 14px;
                border: 1px solid var(--line);
                border-radius: 6px;
                background: #ffffff;
                color: var(--accent);
                font-weight: 700;
            }

            .action-link:hover {
                background: #edf3ff;
            }

            .chat-panel {
                max-width: 920px;
            }

            .chat-stream {
                min-height: 320px;
                max-height: min(62vh, 640px);
                overflow-y: auto;
                display: flex;
                flex-direction: column;
                gap: 10px;
                padding: 4px 2px 16px;
            }

            .chat-message {
                align-self: flex-start;
                width: min(78%, 600px);
                padding: 11px 13px;
                border: 1px solid var(--line);
                border-radius: 8px;
                background: #fbfcff;
            }

            .chat-message.own {
                align-self: flex-end;
                border-color: #bfd4ff;
                background: #edf3ff;
            }

            .chat-message-head {
                display: flex;
                justify-content: space-between;
                gap: 12px;
                margin-bottom: 5px;
                font-size: 12px;
                color: var(--muted);
            }

            .chat-message-head strong {
                color: #324055;
            }

            .chat-message p {
                color: var(--ink);
                overflow-wrap: anywhere;
                white-space: pre-wrap;
            }

            .chat-compose {
                padding-top: 16px;
                border-top: 1px solid var(--line);
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

            .form-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap: 12px;
            }

            .form-grid .full {
                grid-column: 1 / -1;
            }

            .inline-form,
            .logout-form {
                margin: 0;
                display: inline-flex;
                gap: 8px;
                align-items: center;
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

            textarea,
            select {
                width: 100%;
                min-height: 44px;
                border: 1px solid var(--line);
                border-radius: 6px;
                padding: 10px 12px;
                color: var(--ink);
                font: inherit;
                background: #ffffff;
            }

            textarea {
                min-height: 96px;
                resize: vertical;
            }

            input:focus,
            textarea:focus,
            select:focus {
                border-color: var(--accent);
                outline: 3px solid rgba(37, 99, 235, 0.12);
            }

            select[multiple] {
                min-height: 116px;
            }

            .color-field {
                padding: 5px;
                cursor: pointer;
            }

            .checkbox-fieldset {
                margin: 0;
                border: 1px solid var(--line);
                border-radius: 6px;
                padding: 11px 12px 12px;
            }

            .checkbox-fieldset legend {
                padding: 0 5px;
                color: #324055;
                font-size: 13px;
                font-weight: 650;
            }

            .checkbox-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
                gap: 8px 12px;
            }

            .checkbox-row {
                display: flex;
                align-items: center;
                gap: 8px;
                min-height: 30px;
                font-weight: 500;
            }

            .checkbox-row input[type="checkbox"] {
                width: 18px;
                min-height: 18px;
                margin: 0;
            }

            .tag-editor-list {
                margin-top: 18px;
            }

            .tag-edit-form {
                margin-top: 0;
            }

            .tag-system-row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 10px;
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

            .button-secondary {
                background: #eef2f7;
                color: #324055;
            }

            .button-secondary:hover {
                background: #dfe7f2;
            }

            .button-danger {
                background: var(--danger);
            }

            .button-danger:hover {
                background: #8f1b13;
            }

            .button-ghost {
                background: transparent;
                color: var(--accent);
                border: 1px solid var(--line);
            }

            .button-ghost:hover {
                background: #edf3ff;
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

            .notice {
                margin: 0 0 18px;
                padding: 12px 14px;
                border-radius: 6px;
                background: #e9f8ef;
                color: var(--success);
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

            .empty-state {
                padding: 22px;
                border: 1px dashed var(--line);
                border-radius: 8px;
                color: var(--muted);
                text-align: center;
            }

            @media (max-width: 860px) {
                .app-frame {
                    grid-template-columns: 1fr;
                }

                .sidebar {
                    min-height: auto;
                    position: static;
                    border-right: 0;
                    border-bottom: 1px solid var(--line);
                }

                .nav-list {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }

                .workspace {
                    padding: 20px 16px;
                }

                .content-grid {
                    grid-template-columns: 1fr;
                }

                .page-header {
                    display: grid;
                }

                .chat-message {
                    width: min(92%, 600px);
                }
            }
        </style>
    </head>
    <body>
        @if (session()->has('firebase.uid') && ! request()->routeIs('login', 'register'))
            <div class="app-frame">
                <aside class="sidebar">
                    <a class="brand" href="{{ route('dashboard') }}">JettProjekt</a>
                    <nav class="nav-list">
                        <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">Dashboard</a>
                        <a class="{{ request()->routeIs('projects.*') || request()->routeIs('tasks.*') ? 'active' : '' }}" href="{{ route('projects.index') }}">Projects</a>
                        <a class="{{ request()->routeIs('teams.*') ? 'active' : '' }}" href="{{ route('teams.index') }}">Teams</a>
                        <a class="{{ request()->routeIs('connections.*') ? 'active' : '' }}" href="{{ route('connections.index') }}">Connections</a>
                        <a class="{{ request()->routeIs('notes.*') ? 'active' : '' }}" href="{{ route('notes.index') }}">Notes</a>
                        <a class="{{ request()->routeIs('inbox.*') ? 'active' : '' }}" href="{{ route('inbox.index') }}">Inbox</a>
                    </nav>
                </aside>
                <main class="workspace">
                    @if (session('status'))
                        <div class="notice">{{ session('status') }}</div>
                    @endif

                    @if ($errors->any())
                        <div class="errors">{{ $errors->first() }}</div>
                    @endif

                    @yield('body')
                </main>
            </div>
        @else
            @yield('body')
        @endif
    </body>
</html>
