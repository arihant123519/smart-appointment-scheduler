@extends('layouts.app')

@section('title', 'Broadcast Messaging')

@section('content')
  <div class="row g-3">
    <div class="col-xl-5">
      <div class="card">
        <div class="card-header"><h6 class="mb-0">Send an announcement</h6></div>
        <div class="card-body">
          <form method="POST" action="{{ route('announcements.store') }}">
            @csrf
            <div class="mb-3"><label class="form-label">Title</label><input type="text" name="title" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Message</label><textarea name="body" class="form-control" rows="4" required></textarea></div>
            <div class="mb-3"><label class="form-label">Channel</label>
              <select name="channel" class="form-select">
                <option value="email">Email</option>
                <option value="whatsapp">WhatsApp</option>
              </select>
            </div>
            <button class="btn btn-primary w-100">Send to all patients</button>
            <small class="text-muted d-block mt-2">Goes to every active patient in your clinic.</small>
          </form>
        </div>
      </div>
    </div>
    <div class="col-xl-7">
      <div class="card"><div class="card-header"><h6 class="mb-0">Sent announcements</h6></div>
        <div class="card-body p-0">
          <table class="table align-middle mb-0">
            <thead class="table-light"><tr><th>Sent</th><th>Title</th><th>Channel</th><th>Recipients</th></tr></thead>
            <tbody>
              @forelse ($announcements as $a)
                <tr>
                  <td>{{ $a->sent_at?->format('M j, Y g:i A') ?? '—' }}</td>
                  <td>{{ $a->title }}</td>
                  <td>{{ ucfirst($a->channel) }}</td>
                  <td>{{ $a->recipients_count }}</td>
                </tr>
              @empty
                <tr><td colspan="4" class="text-center text-muted py-4">No announcements sent yet.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
@endsection
