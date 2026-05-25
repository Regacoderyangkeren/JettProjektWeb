@extends('layouts.app')

@section('body')
    <header class="page-header">
        <div class="page-title">
            <h1>Inbox</h1>
            <p>{{ count($items) + count($connectionItems) }} item{{ count($items) + count($connectionItems) === 1 ? '' : 's' }}</p>
        </div>
    </header>

    <div class="content-grid">
        <section class="panel">
            <h2>Team and task inbox</h2>
            <div class="stack">
                @forelse ($items as $item)
                    @include('inbox.partials.item', ['item' => $item, 'box' => 'inbox'])
                @empty
                    <div class="empty-state">Inbox is empty.</div>
                @endforelse
            </div>
        </section>

        <section class="panel">
            <h2>Connection inbox</h2>
            <div class="stack">
                @forelse ($connectionItems as $item)
                    @include('inbox.partials.item', ['item' => $item, 'box' => 'connectionInbox'])
                @empty
                    <div class="empty-state">Connection inbox is empty.</div>
                @endforelse
            </div>
        </section>
    </div>
@endsection
