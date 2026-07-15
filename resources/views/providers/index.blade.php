@extends('layouts.app')

@section('title', 'Providers')

@section('page_actions')
  <a href="{{ route('providers.create') }}" class="btn btn-primary"><i class="fi fi-rr-plus me-1"></i> Add Provider</a>
@endsection

@section('content')
  <div class="row g-3 sas-stagger">
    @forelse ($providers as $p)
      <div class="col-xl-4 col-md-6">
        <x-card class="sas-card-hover h-100">
          <div class="d-flex align-items-center gap-3 mb-3">
            <img src="{{ $p->user->avatar_url }}" class="rounded-circle" width="52" height="52" alt="">
            <div>
              <h6 class="mb-0">{{ $p->name }}</h6>
              <small class="text-muted">{{ $p->specialty }} · {{ $p->credentials }}</small>
            </div>
            <x-badge-status class="ms-auto" :color="$p->is_active ? 'success' : 'secondary'" :label="$p->is_active ? 'Active' : 'Inactive'" :icon="$p->is_active ? 'fi-rr-check' : 'fi-rr-minus'" />
          </div>
          <div class="d-flex flex-wrap gap-1 mb-3">
            @foreach ($p->services as $s)
              <span class="badge badge-light-secondary">{{ $s->name }}</span>
            @endforeach
          </div>
          <div class="d-flex justify-content-between align-items-center">
            <small class="text-muted">{{ $p->appointments_count }} appointments</small>
            <div>
              <a href="{{ route('providers.show', $p) }}" class="btn btn-sm btn-light">View</a>
              <a href="{{ route('providers.edit', $p) }}" class="btn btn-sm btn-light">Edit</a>
            </div>
          </div>
        </x-card>
      </div>
    @empty
      <div class="col-12">
        <x-card>
          <x-empty-state icon="fi-rr-user-md" title="No providers yet" description="Add your first provider to start scheduling." />
        </x-card>
      </div>
    @endforelse
  </div>
@endsection
