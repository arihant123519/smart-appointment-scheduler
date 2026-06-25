@extends('layouts.app')

@section('title', 'Services')

@section('page_actions')
  <a href="{{ route('services.create') }}" class="btn btn-primary"><i class="fi fi-rr-plus me-1"></i> Add Service</a>
@endsection

@section('content')
  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive p-3">
        <table class="table table-hover align-middle mb-0 datatable">
          <thead class="table-light"><tr><th>Service</th><th>Specialty</th><th>Duration</th><th>Buffer</th><th>Price</th><th>Telehealth</th><th>Status</th><th></th></tr></thead>
          <tbody>
            @forelse ($services as $s)
              <tr>
                <td><span class="badge rounded-circle p-2 me-1" style="background:{{ $s->color }}"></span> {{ $s->name }}</td>
                <td>{{ $s->specialty ?? '—' }}</td>
                <td>{{ $s->duration }} min</td>
                <td>{{ $s->buffer }} min</td>
                <td>${{ number_format($s->price, 2) }}</td>
                <td>{{ $s->telehealth ? 'Yes' : 'No' }}</td>
                <td><span class="badge bg-{{ $s->is_active ? 'success' : 'secondary' }}-subtle text-{{ $s->is_active ? 'success' : 'secondary' }}">{{ $s->is_active ? 'Active' : 'Inactive' }}</span></td>
                <td class="text-end">
                  <a href="{{ route('services.edit', $s) }}" class="btn btn-sm btn-light">Edit</a>
                  <form method="POST" action="{{ route('services.destroy', $s) }}" class="d-inline" onsubmit="return confirm('Delete service?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                  </form>
                </td>
              </tr>
            @empty
              <tr><td colspan="8" class="text-center text-muted py-4">No services yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
@endsection
