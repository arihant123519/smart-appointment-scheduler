@extends('layouts.app')

@section('title', 'Patients')

@section('page_actions')
  <a href="{{ route('patients.create') }}" class="btn btn-primary"><i class="fi fi-rr-plus me-1"></i> Add Patient</a>
@endsection

@section('content')
  <x-card bodyClass="p-3" class="mb-3">
    <form method="GET" class="d-flex gap-2" style="max-width:420px">
      <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Search name, email or phone">
      <button class="btn btn-primary">Search</button>
    </form>
  </x-card>
  <x-card bodyClass="p-0">
    <div class="table-responsive p-3">
      <table class="table table-hover align-middle mb-0 datatable">
        <thead class="table-light"><tr><th>Name</th><th>Email</th><th>Phone</th><th>Visits</th><th>Status</th><th></th></tr></thead>
        <tbody>
          @forelse ($patients as $p)
            <tr>
              <td class="d-flex align-items-center gap-2">
                <img src="{{ $p->avatar_url }}" class="rounded-circle" width="34" height="34" alt="">
                <span class="fw-semibold">{{ $p->name }}</span>
              </td>
              <td>{{ $p->email }}</td>
              <td>{{ $p->phone ?? '—' }}</td>
              <td>{{ $p->appointments_count }}</td>
              <td>
                <x-badge-status :color="$p->is_active ? 'success' : 'secondary'" :label="$p->is_active ? 'Active' : 'Inactive'" :icon="$p->is_active ? 'fi-rr-check' : 'fi-rr-minus'" />
              </td>
              <td class="text-end">
                <a href="{{ route('patients.show', $p) }}" class="btn btn-sm btn-light">View</a>
                <a href="{{ route('patients.edit', $p) }}" class="btn btn-sm btn-light">Edit</a>
              </td>
            </tr>
          @empty
            <x-empty-state colspan="6" icon="fi-rr-users" title="No patients found" description="Try a different search, or add a new patient." />
          @endforelse
        </tbody>
      </table>
    </div>
  </x-card>
@endsection
