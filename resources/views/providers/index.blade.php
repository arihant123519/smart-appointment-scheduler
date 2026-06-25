@extends('layouts.app')

@section('title', 'Providers')

@section('page_actions')
  <a href="{{ route('providers.create') }}" class="btn btn-primary"><i class="fi fi-rr-plus me-1"></i> Add Provider</a>
@endsection

@section('content')
  <div class="row g-3">
    @forelse ($providers as $p)
      <div class="col-xl-4 col-md-6">
        <div class="card h-100">
          <div class="card-body">
            <div class="d-flex align-items-center gap-3 mb-3">
              <img src="{{ $p->user->avatar_url }}" class="rounded-circle" width="52" height="52" alt="">
              <div>
                <h6 class="mb-0">{{ $p->name }}</h6>
                <small class="text-muted">{{ $p->specialty }} · {{ $p->credentials }}</small>
              </div>
              <span class="badge bg-{{ $p->is_active ? 'success' : 'secondary' }}-subtle text-{{ $p->is_active ? 'success' : 'secondary' }} ms-auto">{{ $p->is_active ? 'Active' : 'Inactive' }}</span>
            </div>
            <div class="d-flex flex-wrap gap-1 mb-3">
              @foreach ($p->services as $s)
                <span class="badge bg-light text-dark">{{ $s->name }}</span>
              @endforeach
            </div>
            <div class="d-flex justify-content-between align-items-center">
              <small class="text-muted">{{ $p->appointments_count }} appointments</small>
              <div>
                <a href="{{ route('providers.show', $p) }}" class="btn btn-sm btn-light">View</a>
                <a href="{{ route('providers.edit', $p) }}" class="btn btn-sm btn-light">Edit</a>
              </div>
            </div>
          </div>
        </div>
      </div>
    @empty
      <div class="col-12"><div class="card"><div class="card-body text-center text-muted py-5">No providers yet.</div></div></div>
    @endforelse
  </div>
@endsection
