@extends('layouts.app')

@section('body')
    <header class="page-header">
        <div class="page-title">
            <h1>Notes</h1>
            <p>{{ count($notes) }} note{{ count($notes) === 1 ? '' : 's' }}</p>
        </div>
    </header>

    <div class="content-grid">
        <section class="panel">
            <h2>Saved notes</h2>
            <div class="stack">
                @forelse ($notes as $note)
                    <div class="row-item">
                        <span class="row-item-header">
                            <h3>{{ $note['name'] ?? 'Untitled note' }}</h3>
                            <span class="small muted">{{ $note['date'] ?? '' }} {{ $note['time'] ?? '' }}</span>
                        </span>
                        <span class="small muted">{{ $note['description'] ?? '' }}</span>
                        <p>{{ $note['content'] ?? '' }}</p>
                        <form class="inline-form" method="post" action="{{ route('notes.destroy', $note['id'] ?? '') }}">
                            @csrf
                            @method('delete')
                            <button class="button-danger" type="submit">Delete</button>
                        </form>
                    </div>
                @empty
                    <div class="empty-state">No notes yet.</div>
                @endforelse
            </div>
        </section>

        <section class="panel">
            <h2>Create note</h2>
            <form method="post" action="{{ route('notes.store') }}">
                @csrf
                <label>
                    Name
                    <input name="name" value="{{ old('name') }}" required>
                </label>
                <label>
                    Description
                    <input name="description" value="{{ old('description') }}">
                </label>
                <label>
                    Content
                    <textarea name="content">{{ old('content') }}</textarea>
                </label>
                <button type="submit">Save note</button>
            </form>
        </section>
    </div>
@endsection
