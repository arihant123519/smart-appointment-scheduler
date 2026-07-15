@extends('layouts.app')

@section('title', 'Clinics')

@section('page_actions')
  <a href="{{ route('clinics.create') }}" class="btn btn-primary"><i class="fi fi-rr-plus me-1"></i> Add Clinic</a>
@endsection

@section('content')
  <x-card bodyClass="p-0">
    <div class="table-responsive p-3">
      <table class="table table-hover align-middle mb-0 datatable">
        <thead class="table-light"><tr><th>Name</th><th>Location</th><th>Timezone</th><th>Providers</th><th>Services</th><th>Appts</th><th>Status</th><th></th></tr></thead>
        <tbody>
          @forelse ($clinics as $c)
            <tr>
              <td class="fw-semibold">{{ $c->name }}</td>
              <td>{{ collect([$c->city, $c->state, $c->country])->filter()->join(', ') ?: '—' }}</td>
              <td>{{ $c->timezone }}</td>
              <td>{{ $c->providers_count }}</td>
              <td>{{ $c->services_count }}</td>
              <td>{{ $c->appointments_count }}</td>
              <td><x-badge-status :color="$c->is_active ? 'success' : 'secondary'" :label="$c->is_active ? 'Active' : 'Inactive'" :icon="$c->is_active ? 'fi-rr-check' : 'fi-rr-minus'" /></td>
              <td class="text-end"><a href="{{ route('clinics.edit', $c) }}" class="btn btn-sm btn-light">Edit</a></td>
            </tr>
          @empty
            <x-empty-state colspan="8" icon="fi-rr-building" title="No clinics yet" description="Add your first clinic to get started." />
          @endforelse
        </tbody>
      </table>
    </div>
  </x-card>
@endsection
