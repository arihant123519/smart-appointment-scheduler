@extends('layouts.app')

@section('title', 'Waitlist')

@section('content')
  <div class="row g-3">
    <div class="col-xl-8">
      <x-card title="Waiting patients" bodyClass="p-0">
        <div class="table-responsive">
          <table class="table align-middle mb-0 datatable">
            <thead class="table-light"><tr><th>Priority</th><th>Patient</th><th>Service</th><th>Provider pref.</th><th>Time pref.</th><th>Status</th><th></th></tr></thead>
            <tbody>
              @forelse ($entries as $e)
                <tr>
                  <td>
                    <x-badge-status color="primary" :label="$e->priority" title="{{ $reasons[$e->id] ?? '' }}" />
                  </td>
                  <td>{{ $e->patient->name }}</td>
                  <td>{{ $e->service->name ?? 'Any' }}</td>
                  <td>{{ $e->provider?->name ?? 'Any' }}</td>
                  <td>{{ ucfirst($e->time_pref ?? 'any') }}</td>
                  <td><x-badge-status color="secondary" :label="ucfirst($e->status)" /></td>
                  <td class="text-end">
                    <form method="POST" action="{{ route('waitlist.destroy', $e) }}" data-sas-confirm="Remove from waitlist?" data-sas-confirm-label="Remove">
                      @csrf @method('DELETE')
                      <button class="btn btn-sm btn-outline-danger">Remove</button>
                    </form>
                  </td>
                </tr>
              @empty
                <x-empty-state colspan="7" icon="fi-rr-list-check" title="Waitlist is empty" description="Add a patient below when a slot is full." />
              @endforelse
            </tbody>
          </table>
        </div>
      </x-card>
    </div>
    <div class="col-xl-4">
      <x-card title="Add to waitlist">
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
      </x-card>
    </div>
  </div>
@endsection
