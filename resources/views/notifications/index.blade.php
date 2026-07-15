@extends('layouts.app')

@section('title', 'Notifications')

@section('page_actions')
  <form method="POST" action="{{ route('notifications.readAll') }}">
    @csrf
    <button class="btn btn-light btn-sm">Mark all read</button>
  </form>
@endsection

@section('content')
  <x-card bodyClass="p-0">
    <ul class="list-group list-group-flush">
      @forelse ($notifications as $n)
        <li class="list-group-item d-flex align-items-center gap-3 {{ $n->read_at ? '' : 'bg-light' }}">
          <span class="sas-stat__icon bg-primary-subtle text-primary" style="width:40px;height:40px;font-size:1rem">
            <i class="fi {{ $n->data['icon'] ?? 'fi-rr-bell' }}"></i>
          </span>
          <div class="flex-grow-1">
            <div class="fw-semibold">{{ $n->data['title'] ?? 'Notification' }}</div>
            <small class="text-muted">{{ $n->data['body'] ?? '' }}</small>
          </div>
          <small class="text-muted">{{ $n->created_at->diffForHumans() }}</small>
          <a href="{{ route('notifications.read', $n->id) }}" class="btn btn-sm btn-light">Open</a>
        </li>
      @empty
        <li class="list-group-item p-0">
          <x-empty-state icon="fi-rr-inbox" title="No notifications" description="You're all caught up." />
        </li>
      @endforelse
    </ul>
    <x-slot:footer>{{ $notifications->links() }}</x-slot:footer>
  </x-card>
@endsection
