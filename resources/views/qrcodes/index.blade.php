@extends('layouts.app')

@section('title', 'QR Booking Codes')

@section('content')
  <div class="row g-3">
    <div class="col-xl-8">
      <x-card title="QR codes" bodyClass="p-0">
        <div class="table-responsive">
          <table class="table align-middle mb-0 datatable">
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
                    <form method="POST" action="{{ route('qrcodes.destroy', $qr) }}" class="d-inline" data-sas-confirm="Delete this QR code? Existing printed copies will stop working." data-sas-confirm-label="Delete">
                      @csrf @method('DELETE')
                      <button class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                  </td>
                </tr>
              @empty
                <x-empty-state colspan="6" icon="fi-rr-qrcode" title="No QR codes yet" description="Generate one to let patients book by scanning." />
              @endforelse
            </tbody>
          </table>
        </div>
      </x-card>
    </div>

    <div class="col-xl-4">
      <x-card title="Create a QR code">
        <form method="POST" action="{{ route('qrcodes.store') }}">
          @csrf
          <div class="mb-3">
            <x-form-field name="label" label="Label" :required="true" placeholder="e.g. Waiting-room poster" />
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
      </x-card>
    </div>
  </div>
@endsection
