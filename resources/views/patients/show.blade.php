@extends('layouts.app')

@section('title', $patient->name)

@section('page_actions')
  <a href="{{ route('patients.edit', $patient) }}" class="btn btn-light"><i class="fi fi-rr-pencil me-1"></i> Edit</a>
@endsection

@section('content')
  <div class="row g-3">
    <div class="col-xl-4">
      <x-card>
        <div class="text-center">
          <img src="{{ $patient->avatar_url }}" class="rounded-circle mb-3" width="84" height="84" alt="">
          <h5 class="mb-0">{{ $patient->name }}</h5>
          <p class="text-muted">{{ $patient->email }}</p>
          <dl class="row text-start small mb-0">
            <dt class="col-5 text-muted">Phone</dt><dd class="col-7">{{ $patient->phone ?? '—' }}</dd>
            <dt class="col-5 text-muted">DOB</dt><dd class="col-7">{{ $patient->date_of_birth?->format('M j, Y') ?? '—' }}</dd>
            <dt class="col-5 text-muted">Gender</dt><dd class="col-7">{{ ucfirst($patient->gender ?? '—') }}</dd>
            <dt class="col-5 text-muted">Address</dt><dd class="col-7">{{ $patient->address ?? '—' }}</dd>
          </dl>
        </div>
      </x-card>
    </div>
    <div class="col-xl-8">
      <x-card title="Appointment history" bodyClass="p-0">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead class="table-light"><tr><th>When</th><th>Provider</th><th>Service</th><th>Status</th></tr></thead>
            <tbody>
              @forelse ($patient->appointments as $a)
                <tr onclick="window.location='{{ route('appointments.show', $a) }}'" style="cursor:pointer">
                  <td>{{ $a->start_at->format('M j, Y g:i A') }}</td>
                  <td>{{ $a->provider->name }}</td>
                  <td>{{ $a->service->name ?? '—' }}</td>
                  <td><x-badge-status :color="$a->status_color" :label="$a->status_label" /></td>
                </tr>
              @empty
                <x-empty-state colspan="4" icon="fi-rr-calendar" title="No appointments" />
              @endforelse
            </tbody>
          </table>
        </div>
      </x-card>
    </div>
  </div>
@endsection
