@extends('layouts.app')

@section('body')
    <main class="page">
        <section class="shell">
            <h1>JettProjekt</h1>
            <p>Masuk ke workspace kamu.</p>

            @if ($errors->any())
                <div class="errors">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="post" action="{{ route('login.store') }}">
                @csrf
                <label>
                    Email
                    <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
                </label>

                <label>
                    Password
                    <input type="password" name="password" autocomplete="current-password" required>
                </label>

                <button type="submit">Masuk</button>
            </form>

            <p class="link-row">
                Belum punya akun? <a href="{{ route('register') }}">Daftar</a>
            </p>
        </section>
    </main>
@endsection
