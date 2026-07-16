@extends('layouts.app')

@section('title', 'Services')

@section('page_actions')
  <a href="{{ route('services.create') }}" class="btn btn-primary"><i class="fi fi-rr-plus me-1"></i> Add Service</a>
@endsection

@section('content')
  <x-card bodyClass="p-0">
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
              <td>₹{{ number_format($s->price, 2) }}</td>
              <td>{{ $s->telehealth ? 'Yes' : 'No' }}</td>
              <td><x-badge-status :color="$s->is_active ? 'success' : 'secondary'" :label="$s->is_active ? 'Active' : 'Inactive'" /></td>
              <td class="text-end">
                <a href="{{ route('services.edit', $s) }}" class="btn btn-sm btn-light">Edit</a>
                <form method="POST" action="{{ route('services.destroy', $s) }}" class="d-inline" data-sas-confirm="Delete this service?" data-sas-confirm-label="Delete">
                  @csrf @method('DELETE')
                  <button class="btn btn-sm btn-outline-danger">Delete</button>
                </form>
              </td>
            </tr>
          @empty
            <x-empty-state colspan="8" icon="fi-rr-symbol" title="No services yet" description="Add your first service to start taking bookings." />
          @endforelse
        </tbody>
      </table>
    </div>
  </x-card>
@endsection
