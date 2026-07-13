@extends('layouts.app')

@section('title', 'Waitlist')

@section('content')
  <div class="row g-3">
    <div class="col-xl-8">
      <div class="card">
        <div class="card-header"><h6 class="mb-0">Waiting patients</h6></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table align-middle mb-0 datatable">
              <thead class="table-light"><tr><th>Priority</th><th>Patient</th><th>Service</th><th>Provider pref.</th><th>Time pref.</th><th>Status</th><th></th></tr></thead>
              <tbody>
                @forelse ($entries as $e)
                  <tr>
                    <td>
                      <span class="badge bg-primary" title="{{ $reasons[$e->id] ?? '' }}">{{ $e->priority }}</span>
                    </td>
                    <td>{{ $e->patient->name }}</td>
                    <td>{{ $e->service->name ?? 'Any' }}</td>
                    <td>{{ $e->provider?->name ?? 'Any' }}</td>
                    <td>{{ ucfirst($e->time_pref ?? 'any') }}</td>
                    <td><span class="badge bg-light text-dark">{{ ucfirst($e->status) }}</span></td>
                    <td class="text-end">
                      <form method="POST" action="{{ route('waitlist.destroy', $e) }}" onsubmit="return confirm('Remove?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger">Remove</button>
                      </form>
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="7" class="text-center text-muted py-4">Waitlist is empty.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <div class="col-xl-4">
      <div class="card">
        <div class="card-header"><h6 class="mb-0">Add to waitlist</h6></div>
        <div class="card-body">
          <form method="POST" action="{{ route('waitlist.store') }}">
            @csrf
            <div class="mb-3">
              <label class="form-label">Patient</label>
              <select name="patient_id" class="form-select" required>
                <option value="">Select…</option>
                @foreach ($patients as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Service</label>
              <select name="service_id" class="form-select">
                <option value="">Any</option>
                @foreach ($services as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Preferred provider</label>
              <select name="provider_id" class="form-select">
                <option value="">Any</option>
                @foreach ($providers as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Time preference</label>
              <select name="time_pref" class="form-select">
                @foreach (['any', 'morning', 'afternoon', 'evening'] as $t)<option value="{{ $t }}">{{ ucfirst($t) }}</option>@endforeach
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Priority override</label>
              <input type="number" name="priority" class="form-control" min="0" max="100" placeholder="Leave blank to auto-compute">
              <div class="form-text">Defaults to a computed score from the patient's visit history, attendance, and referrals — hover a priority badge to see why. Set a number to override it.</div>
            </div>
            <button class="btn btn-primary w-100">Add</button>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection
