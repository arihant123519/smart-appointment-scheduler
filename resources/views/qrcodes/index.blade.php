@extends('layouts.app')

@section('title', 'QR Booking Codes')

@section('content')
  <div class="row g-3">
    <div class="col-xl-8">
      <div class="card">
        <div class="card-header"><h6 class="mb-0">QR codes</h6></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead class="table-light"><tr><th>Code</th><th>Label</th><th>Service</th><th>Scans</th><th>Bookings</th><th></th></tr></thead>
              <tbody>
                @forelse ($codes as $qr)
                  <tr>
                    <td><img src="{{ $qr->image_url }}" alt="QR code for {{ $qr->label }}" width="64" height="64"></td>
                    <td>
                      {{ $qr->label }}
                      <div class="small text-muted text-break">{{ $qr->redeem_url }}</div>
                    </td>
                    <td>{{ $qr->service->name ?? 'Any service' }}</td>
                    <td>{{ $qr->scans_count }}</td>
                    <td>
                      {{ $qr->bookings_count }}
                      @if ($qr->scans_count > 0)
                        <span class="text-muted small">({{ round($qr->bookings_count / $qr->scans_count * 100) }}%)</span>
                      @endif
                    </td>
                    <td class="text-end">
                      <a href="{{ $qr->image_url }}" download="qr-{{ \Illuminate\Support\Str::slug($qr->label) }}.png" class="btn btn-sm btn-outline-secondary">Download</a>
                      <form method="POST" action="{{ route('qrcodes.destroy', $qr) }}" class="d-inline" onsubmit="return confirm('Delete this QR code? Existing printed copies will stop working.')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                      </form>
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="6" class="text-center text-muted py-4">No QR codes yet.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-4">
      <div class="card">
        <div class="card-header"><h6 class="mb-0">Create a QR code</h6></div>
        <div class="card-body">
          <form method="POST" action="{{ route('qrcodes.store') }}">
            @csrf
            <div class="mb-3">
              <label class="form-label" for="qrLabel">Label <span class="text-danger">*</span></label>
              <input type="text" name="label" id="qrLabel" class="form-control" placeholder="e.g. Waiting-room poster" required>
            </div>
            <div class="mb-3">
              <label class="form-label" for="qrService">Service</label>
              <select name="service_id" id="qrService" class="form-select">
                <option value="">Any service</option>
                @foreach ($services as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
              </select>
              <div class="form-text">Deep-links straight into booking this service, skipping the picker.</div>
            </div>
            <button class="btn btn-primary w-100">Generate QR code</button>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection
