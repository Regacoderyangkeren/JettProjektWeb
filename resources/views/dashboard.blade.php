@extends('layouts.app')

@section('body')
    <main class="page">
        <section class="shell dashboard">
            <div class="topbar">
                <div>
                    <h1>Dashboard</h1>
                    <p>{{ $profile['email'] ?? 'Signed in' }}</p>
                </div>

                <form class="logout-form" method="post" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit">Logout</button>
                </form>
            </div>

            <div class="profile-grid">
                <div class="profile-item">
                    <strong>Name</strong>
                    {{ trim(($profile['firstName'] ?? '').' '.($profile['lastName'] ?? '')) ?: '-' }}
                </div>
                <div class="profile-item">
                    <strong>Alias</strong>
                    {{ $profile['alias'] ?? '-' }}
                </div>
                <div class="profile-item">
                    <strong>Status</strong>
                    {{ $profile['status'] ?? 'ONLINE' }}
                </div>
            </div>
        </section>
    </main>
@endsection
