@extends('layouts.app')

@section('body')
    <main class="page">
        <section class="shell">
            <h1>Buat akun</h1>
            <p>Data ini akan disimpan ke Firebase Auth dan koleksi users.</p>

            @if ($errors->any())
                <div class="errors">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="post" action="{{ route('register.store') }}">
                @csrf
                <label>
                    First name
                    <input type="text" name="firstName" value="{{ old('firstName') }}" autocomplete="given-name" required>
                </label>

                <label>
                    Last name
                    <input type="text" name="lastName" value="{{ old('lastName') }}" autocomplete="family-name">
                </label>

                <label>
                    Alias
                    <input type="text" name="alias" value="{{ old('alias') }}" autocomplete="nickname">
                </label>

                <label>
                    Email
                    <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
                </label>

                <label>
                    Password
                    <input type="password" name="password" autocomplete="new-password" required>
                </label>

                <label>
                    Confirm password
                    <input type="password" name="password_confirmation" autocomplete="new-password" required>
                </label>

                <button type="submit">Daftar</button>
            </form>

            <p class="link-row">
                Sudah punya akun? <a href="{{ route('login') }}">Masuk</a>
            </p>
        </section>
    </main>
@endsection
