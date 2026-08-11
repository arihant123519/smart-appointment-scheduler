@extends('layouts.app')

@section('title', $patient->name.' — Patient History')

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
            <dt class="col-5 text-muted">DOB</dt><dd class="col-7">{{ $patient->date_of_birth?->format('M j, Y') ?? '—' }}{{ $patient->date_of_birth ? ' ('.$patient->date_of_birth->age.' yrs)' : '' }}</dd>
            <dt class="col-5 text-muted">Gender</dt><dd class="col-7">{{ ucfirst($patient->gender ?? '—') }}</dd>
            <dt class="col-5 text-muted">Address</dt><dd class="col-7">{{ $patient->address ?? '—' }}</dd>
          </dl>
        </div>
      </x-card>
    </div>
    <div class="col-xl-8">
      <x-card bodyClass="p-0">
        <ul class="nav nav-tabs px-3 pt-2" id="historyTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-appointments-btn" data-bs-toggle="tab" data-bs-target="#tab-appointments" type="button" role="tab">Appointments</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-consultations-btn" data-bs-toggle="tab" data-bs-target="#tab-consultations" type="button" role="tab">Consultations</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-prescriptions-btn" data-bs-toggle="tab" data-bs-target="#tab-prescriptions" type="button" role="tab">Prescriptions</button>
          </li>
        </ul>
        <div class="tab-content">
          <div class="tab-pane fade show active" id="tab-appointments" role="tabpanel">
            <div class="table-responsive">
              <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th>When</th><th>Provider</th><th>Service</th><th>Reason</th><th>Status</th></tr></thead>
                <tbody>
                  @forelse ($patient->appointments as $a)
                    <tr onclick="window.location='{{ route('appointments.show', $a) }}'" style="cursor:pointer">
                      <td>{{ $a->start_at->format('M j, Y g:i A') }}</td>
                      <td>{{ $a->provider->name }}</td>
                      <td>{{ $a->service->name ?? '—' }}</td>
                      <td class="small text-muted">{{ $a->reason ?? '—' }}</td>
                      <td><x-badge-status :color="$a->status_color" :label="$a->status_label" /></td>
                    </tr>
                  @empty
                    <x-empty-state colspan="5" icon="fi-rr-calendar" title="No appointments" />
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>

          <div class="tab-pane fade" id="tab-consultations" role="tabpanel">
            <div class="table-responsive">
              <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th>Date</th><th>Provider</th><th>Chief complaint</th><th>Diagnosis</th><th>Status</th><th></th></tr></thead>
                <tbody>
                  @forelse ($patient->consultations as $c)
                    <tr>
                      <td>{{ $c->created_at->format('M j, Y') }}</td>
                      <td>{{ $c->provider->name }}</td>
                      <td class="small text-muted">{{ \Illuminate\Support\Str::limit($c->chief_complaint, 40) ?: '—' }}</td>
                      <td class="small text-muted">{{ \Illuminate\Support\Str::limit($c->diagnosis, 40) ?: '—' }}</td>
                      <td>
                        @if ($c->is_finalized)
                          <x-badge-status color="success" label="Finalized" />
                        @else
                          <x-badge-status color="warning" label="Draft" />
                        @endif
                      </td>
                      <td class="text-end"><a href="{{ route('consultations.edit', $c->appointment_id) }}" class="btn btn-sm btn-light">Open</a></td>
                    </tr>
                  @empty
                    <x-empty-state colspan="6" icon="fi-rr-stethoscope" title="No consultations recorded" />
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>

          <div class="tab-pane fade" id="tab-prescriptions" role="tabpanel">
            <div class="table-responsive">
              <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th>Date</th><th>Provider</th><th>Medicines</th><th></th></tr></thead>
                <tbody>
                  @forelse ($patient->consultations->pluck('prescription')->filter() as $rx)
                    <tr>
                      <td>{{ ($rx->issued_at ?? $rx->created_at)->format('M j, Y') }}</td>
                      <td>{{ $rx->provider->name }}</td>
                      <td class="small text-muted">{{ $rx->items->pluck('medicine_name')->implode(', ') ?: '—' }}</td>
                      <td class="text-end"><a href="{{ route('prescriptions.pdf', $rx->appointment_id) }}" target="_blank" class="btn btn-sm btn-light"><i class="fi fi-rr-print me-1"></i> View</a></td>
                    </tr>
                  @empty
                    <x-empty-state colspan="4" icon="fi-rr-prescription" title="No prescriptions recorded" />
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </x-card>
    </div>
  </div>
@endsection
