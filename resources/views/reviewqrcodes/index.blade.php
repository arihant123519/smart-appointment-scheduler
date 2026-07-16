@extends('layouts.app')

@section('title', 'Review QR Codes')

@section('content')
  <div class="row g-3">
    <div class="col-xl-8">
      @if ($codes->isEmpty())
        <x-card>
          <x-empty-state icon="fi-rr-qrcode" title="No review QR codes yet" description="Generate one to let patients leave feedback without logging in — print it for the front desk or waiting room." />
        </x-card>
      @else
        <div class="row g-3">
          @foreach ($codes as $qr)
            <div class="col-md-6">
              <x-card bodyClass="d-flex flex-column h-100">
                <div class="d-flex align-items-start gap-3 mb-3">
                  <div class="border p-1 bg-white flex-shrink-0" style="border-radius:var(--sas-radius-md)">
                    <img src="{{ $qr->image_url }}" alt="QR code for {{ $qr->label }}" width="88" height="88" style="display:block">
                  </div>
                  <div style="min-width:0">
                    <div class="fw-bold text-truncate">{{ $qr->label }}</div>
                    <div class="small text-muted text-break">{{ $qr->submit_url }}</div>
                  </div>
                </div>

                <div class="d-flex gap-2 mb-3">
                  <div class="flex-fill text-center py-2" style="background:var(--sas-gray-50);border-radius:var(--sas-radius-md)">
                    <div class="h5 mb-0 fw-bold">{{ $qr->scans_count }}</div>
                    <div class="text-muted small">Scans</div>
                  </div>
                  <div class="flex-fill text-center py-2" style="background:var(--sas-gray-50);border-radius:var(--sas-radius-md)">
                    <div class="h5 mb-0 fw-bold">{{ $qr->submissions_count }}</div>
                    <div class="text-muted small">Submissions</div>
                  </div>
                </div>

                <div class="d-flex gap-2 mt-auto">
                  <a href="{{ $qr->image_url }}" download="review-qr-{{ \Illuminate\Support\Str::slug($qr->label) }}.png" class="btn btn-light-secondary btn-sm flex-fill">
                    <i class="fi fi-rr-download me-1"></i> Download
                  </a>
                  <form method="POST" action="{{ route('reviewqrcodes.destroy', $qr) }}" data-sas-confirm="Delete this review QR code? Existing printed copies will stop working." data-sas-confirm-label="Delete">
                    @csrf @method('DELETE')
                    <button class="btn btn-light-danger btn-sm btn-icon"><i class="fi fi-rr-trash"></i></button>
                  </form>
                </div>
              </x-card>
            </div>
          @endforeach
        </div>
      @endif
    </div>

    <div class="col-xl-4">
      <x-card title="Create a review QR code">
        <p class="text-muted small mb-3">Print it at the front desk, on receipts, or in the waiting room so patients can leave feedback in seconds — no login required.</p>
        <form method="POST" action="{{ route('reviewqrcodes.store') }}">
          @csrf
          <div class="mb-3">
            <x-form-field name="label" label="Label" :required="true" placeholder="e.g. Front-desk counter card" />
          </div>
          <button class="btn btn-primary w-100"><i class="fi fi-rr-qrcode me-1"></i> Generate QR code</button>
        </form>
      </x-card>
    </div>
  </div>
@endsection
