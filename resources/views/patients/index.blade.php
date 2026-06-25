@extends('layouts.app')

@section('title', 'Patients')

@section('page_actions')
  <a href="{{ route('patients.create') }}" class="btn btn-primary"><i class="fi fi-rr-plus me-1"></i> Add Patient</a>
@endsection

@section('content')
  <div class="card mb-3">
    <div class="card-body">
      <form method="GET" class="d-flex gap-2" style="max-width:420px">
        <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Search name, email or phone">
        <button class="btn btn-primary">Search</button>
      </form>
    </div>
  </div>
  <div class="card">
    <div class="card-body p-0">
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
                  <span class="badge bg-{{ $p->is_active ? 'success' : 'secondary' }}-subtle text-{{ $p->is_active ? 'success' : 'secondary' }}">{{ $p->is_active ? 'Active' : 'Inactive' }}</span>
                </td>
                <td class="text-end">
                  <a href="{{ route('patients.show', $p) }}" class="btn btn-sm btn-light">View</a>
                  <a href="{{ route('patients.edit', $p) }}" class="btn btn-sm btn-light">Edit</a>
                </td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center text-muted py-4">No patients found.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
@endsection
