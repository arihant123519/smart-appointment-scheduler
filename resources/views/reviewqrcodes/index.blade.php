@extends('layouts.app')

@section('title', 'Review QR Codes')

@push('styles')
  <style>
    .sas-page-toolbar { display: none; }

    .sas-rqr-header__icon {
      width: 52px; height: 52px; border-radius: var(--sas-radius-lg); flex-shrink: 0;
      background: var(--sas-primary-50); color: var(--sas-primary-700);
      display: grid; place-items: center; font-size: 1.4rem;
    }
    .sas-rqr-header__title { font-size: 2.25rem; font-weight: 800; letter-spacing: -.02em; color: var(--sas-gray-900); }
    .sas-rqr-header__subtitle { font-size: var(--sas-fs-base); color: var(--sas-gray-500); }

    .sas-rqr-empty { min-height: 620px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: var(--sas-space-8); }
    .sas-rqr-empty__art { position: relative; width: 220px; height: 220px; margin-bottom: var(--sas-space-6); display: grid; place-items: center; }
    .sas-rqr-empty__glow { position: absolute; inset: 0; border-radius: 50%; background: radial-gradient(circle at 50% 45%, var(--sas-primary-100) 0%, rgba(219,234,254,.35) 45%, transparent 72%); }
    .sas-rqr-empty__card { position: relative; z-index: 1; width: 148px; height: 148px; background: #fff; border-radius: var(--sas-radius-lg); box-shadow: var(--sas-shadow-lg); display: grid; place-items: center; }
    .sas-rqr-empty__card i { font-size: 4rem; color: var(--sas-primary-600); }
    .sas-rqr-empty__title { font-size: var(--sas-fs-2xl); font-weight: 800; color: var(--sas-gray-900); margin-bottom: .6rem; }
    .sas-rqr-empty__desc { color: var(--sas-gray-500); max-width: 46ch; margin: 0 auto 1.75rem; }

    .sas-rqr-preview-box {
      border: 1px dashed var(--sas-gray-200); border-radius: var(--sas-radius-lg); background: var(--sas-gray-25);
      display: grid; place-items: center; padding: var(--sas-space-5); text-align: center;
    }
    .sas-rqr-preview-box img { width: 100%; max-width: 220px; }
    .sas-rqr-preview-box i { font-size: 2rem; color: var(--sas-gray-300); margin-bottom: .5rem; display: block; }
    .sas-rqr-info { display: flex; gap: .65rem; background: var(--sas-primary-50); border: 1px solid var(--sas-primary-100); border-radius: var(--sas-radius-lg); padding: .9rem 1rem; font-size: var(--sas-fs-xs); color: var(--sas-primary-800); }
    .sas-rqr-info i { color: var(--sas-primary-600); flex-shrink: 0; margin-top: .1rem; }
  </style>
@endpush

@section('content')
  <div class="d-flex align-items-start gap-3 mb-4">
    <span class="sas-rqr-header__icon"><i class="fi fi-rr-qrcode" aria-hidden="true"></i></span>
    <div>
      <h1 class="sas-rqr-header__title mb-1">Review QR Codes</h1>
      <p class="sas-rqr-header__subtitle mb-0">Create and manage QR codes that let patients leave reviews without logging in.</p>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-xl-8">
      @if ($codes->isEmpty())
        <x-card>
          <div class="sas-rqr-empty">
            <div class="sas-rqr-empty__art" aria-hidden="true">
              <div class="sas-rqr-empty__glow"></div>
              <div class="sas-rqr-empty__card"><i class="fi fi-rr-qrcode"></i></div>
            </div>
            <h2 class="sas-rqr-empty__title">No review QR codes yet</h2>
            <p class="sas-rqr-empty__desc">Generate one to let patients leave feedback without logging in — print it for the front desk or waiting room so reviews come in within seconds of checkout.</p>
            <button type="button" class="btn btn-primary btn-lg" id="rqrEmptyFocusBtn"><i class="fi fi-rr-plus me-1" aria-hidden="true"></i> Create Your First Review QR Code</button>
          </div>
        </x-card>
      @else
        <div class="row g-3 sas-stagger">
          @foreach ($codes as $qr)
            <div class="col-md-6">
              <x-card class="sas-card-hover" bodyClass="d-flex flex-column h-100">
                <div class="d-flex align-items-start gap-3 mb-3">
                  <div class="border p-1 bg-white flex-shrink-0" style="border-radius:var(--sas-radius-md)">
                    <img src="{{ $qr->image_url }}" alt="QR code for {{ $qr->label }}" width="88" height="88" style="display:block">
                  </div>
                  <div style="min-width:0">
                    <div class="fw-bold text-truncate">{{ $qr->label }}</div>
                    <a href="{{ $qr->submit_url }}" target="_blank" rel="noopener" class="small text-muted text-break text-decoration-none">{{ $qr->submit_url }} <i class="fi fi-rr-arrow-up-right-from-square" aria-hidden="true"></i></a>
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
                    <button class="btn btn-light-danger btn-sm btn-icon" aria-label="Delete"><i class="fi fi-rr-trash"></i></button>
                  </form>
                </div>
              </x-card>
            </div>
          @endforeach
        </div>
      @endif
    </div>

    <div class="col-xl-4">
      <x-card>
        <h2 class="mb-1" style="font-size:var(--sas-fs-lg);font-weight:700">Create a review QR code</h2>
        <p class="text-muted small mb-3">Print it at the front desk, on receipts, or in the waiting room so patients can leave feedback in seconds — no login required.</p>
        <form method="POST" action="{{ route('reviewqrcodes.store') }}">
          @csrf
          <div class="mb-3">
            <x-form-field name="label" label="Label" :required="true" placeholder="e.g. Front Desk, Receipt, Waiting Room" />
          </div>

          <div class="mb-3">
            <div class="fw-semibold small mb-2">Preview</div>
            <div class="sas-rqr-preview-box">
              <i class="fi fi-rr-qrcode" aria-hidden="true"></i>
              <div class="fw-semibold small mb-1">No QR code generated yet</div>
              <div class="text-muted small mb-0">Fill in a label to generate a preview.</div>
            </div>
          </div>

          <div class="sas-rqr-info mb-3">
            <i class="fi fi-rr-info" aria-hidden="true"></i>
            <div>This QR code will open the review page where patients can leave feedback instantly without signing in.</div>
          </div>

          <button class="btn btn-primary w-100" id="rqrSubmitBtn"><i class="fi fi-rr-qrcode me-1"></i> Generate QR code</button>
        </form>
      </x-card>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      const focusBtn = document.getElementById('rqrEmptyFocusBtn');
      const labelInput = document.getElementById('label');
      if (focusBtn && labelInput) {
        focusBtn.addEventListener('click', function () {
          labelInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
          setTimeout(() => labelInput.focus(), 300);
        });
      }
    })();
  </script>
@endpush
