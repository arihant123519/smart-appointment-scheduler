@extends('layouts.app')

@section('title', 'QR Booking Codes')

@push('styles')
  <style>
    .sas-page-toolbar { display: none; }

    .sas-qr-header__icon {
      width: 52px; height: 52px; border-radius: var(--sas-radius-lg); flex-shrink: 0;
      background: var(--sas-primary-50); color: var(--sas-primary-700);
      display: grid; place-items: center; font-size: 1.4rem;
    }
    .sas-qr-header__title { font-size: 2.25rem; font-weight: 800; letter-spacing: -.02em; color: var(--sas-gray-900); }
    .sas-qr-header__subtitle { font-size: var(--sas-fs-base); color: var(--sas-gray-500); }

    .sas-qr-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: .85rem; padding: var(--sas-space-4) var(--sas-space-5); border-bottom: 1px solid var(--sas-gray-100); }
    .sas-qr-toolbar__length select { border-radius: var(--sas-radius-md); }
    .sas-qr-toolbar__search { margin-left: auto; }
    .sas-qr-toolbar__search input { border: 1px solid var(--sas-gray-200); border-radius: var(--sas-radius-md); padding: .55rem .9rem; font-size: var(--sas-fs-sm); min-width: 220px; }
    .sas-qr-toolbar__search input:focus { outline: none; border-color: var(--sas-primary-400); box-shadow: 0 0 0 .2rem var(--sas-primary-100); }
    #qrTable_wrapper > .row:first-child { display: none; }
    #qrTable_wrapper > .row:last-child { padding: var(--sas-space-4) var(--sas-space-5); margin: 0; align-items: center; }

    .sas-qr-thumb { width: 64px; height: 64px; padding: 6px; border: 1px solid var(--sas-gray-200); border-radius: var(--sas-radius-md); background: #fff; }
    .sas-qr-thumb img { width: 100%; height: 100%; display: block; }
    #qrTable .sas-qr-label { font-weight: 700; color: var(--sas-gray-900); }
    #qrTable .sas-qr-url { display: inline-flex; align-items: center; gap: .35rem; font-size: var(--sas-fs-xs); color: var(--sas-gray-500); text-decoration: none; word-break: break-all; }
    #qrTable .sas-qr-url:hover { color: var(--sas-primary-700); }
    #qrTable .btn-icon-square { width: 34px; height: 34px; border-radius: var(--sas-radius-md); border: 1px solid var(--sas-gray-200); background: #fff; color: var(--sas-gray-600); }
    #qrTable .btn-icon-square:hover { background: var(--sas-gray-50); }

    #qrCreatePanel { transition: opacity .15s var(--sas-ease); }
    .sas-qr-preview-box {
      border: 1px dashed var(--sas-gray-200); border-radius: var(--sas-radius-lg); background: var(--sas-gray-25);
      display: grid; place-items: center; padding: var(--sas-space-5); text-align: center;
    }
    .sas-qr-preview-box i { font-size: 2rem; color: var(--sas-gray-300); margin-bottom: .5rem; display: block; }
  </style>
@endpush

@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div class="d-flex align-items-start gap-3">
      <span class="sas-qr-header__icon"><i class="fi fi-rr-qrcode" aria-hidden="true"></i></span>
      <div>
        <h1 class="sas-qr-header__title mb-1">QR Booking Codes</h1>
        <p class="sas-qr-header__subtitle mb-0">Manage QR codes that direct patients to book appointments.</p>
      </div>
    </div>
    <button type="button" class="btn btn-primary btn-lg" id="qrCreateToggleBtn"><i class="fi fi-rr-plus me-1" aria-hidden="true"></i> Create QR Code</button>
  </div>

  <div class="row g-3">
    <div class="col-xl-8" id="qrListCol">
      <x-card bodyClass="p-0">
        <div class="sas-qr-toolbar">
          <span class="sas-qr-toolbar__length" id="qrLengthSlot"></span>
          <span class="sas-qr-toolbar__search" id="qrSearchSlot"></span>
        </div>
        <div class="table-responsive">
          <table id="qrTable" class="table align-middle mb-0 datatable">
            <thead class="table-light"><tr><th>Code</th><th>Label</th><th>Service</th><th>Scans</th><th>Bookings</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
              @forelse ($codes as $qr)
                <tr>
                  <td><span class="sas-qr-thumb"><img src="{{ $qr->image_url }}" alt="QR code for {{ $qr->label }}"></span></td>
                  <td>
                    <div class="sas-qr-label">QR code for {{ $qr->label }}</div>
                    <a href="{{ $qr->redeem_url }}" target="_blank" rel="noopener" class="sas-qr-url">
                      {{ $qr->redeem_url }} <i class="fi fi-rr-arrow-up-right-from-square" aria-hidden="true"></i>
                    </a>
                  </td>
                  <td>
                    @if ($qr->service)
                      <span class="badge" style="background:{{ $qr->service->color }}22;color:{{ $qr->service->color }}">{{ $qr->service->name }}</span>
                    @else
                      <span class="text-muted small">Any service</span>
                    @endif
                  </td>
                  <td data-order="{{ $qr->scans_count }}">{{ $qr->scans_count }}</td>
                  <td data-order="{{ $qr->bookings_count }}">
                    {{ $qr->bookings_count }}
                    @if ($qr->scans_count > 0)
                      <span class="text-success small fw-semibold">({{ round($qr->bookings_count / $qr->scans_count * 100) }}%)</span>
                    @endif
                  </td>
                  <td class="text-end">
                    <div class="dropdown sas-dropdown-actions">
                      <button type="button" class="btn btn-sm btn-icon-square" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions for {{ $qr->label }}">
                        <i class="fi fi-rr-menu-dots-vertical"></i>
                      </button>
                      <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="{{ $qr->redeem_url }}" target="_blank" rel="noopener"><i class="fi fi-rr-arrow-up-right-from-square"></i> Open booking link</a></li>
                        <li><a class="dropdown-item" href="{{ $qr->image_url }}" download="qr-{{ \Illuminate\Support\Str::slug($qr->label) }}.png"><i class="fi fi-rr-download"></i> Download PNG</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                          <form method="POST" action="{{ route('qrcodes.destroy', $qr) }}" data-sas-confirm="Delete this QR code? Existing printed copies will stop working." data-sas-confirm-label="Delete">
                            @csrf @method('DELETE')
                            <button type="submit" class="dropdown-item text-danger"><i class="fi fi-rr-trash"></i> Delete</button>
                          </form>
                        </li>
                      </ul>
                    </div>
                  </td>
                </tr>
              @empty
                <x-empty-state colspan="6" icon="fi-rr-qrcode" title="No QR campaigns yet." description="Generate one to let patients book by scanning.">
                  <button type="button" class="btn btn-sm btn-primary" id="qrEmptyCreateBtn"><i class="fi fi-rr-plus me-1"></i> Create QR Code</button>
                </x-empty-state>
              @endforelse
            </tbody>
          </table>
        </div>
      </x-card>
    </div>

    <div class="col-xl-4" id="qrCreatePanel">
      <x-card>
        <div class="d-flex align-items-start justify-content-between mb-1">
          <div>
            <h2 class="mb-1" style="font-size:var(--sas-fs-lg);font-weight:700">Create a QR code</h2>
            <p class="text-muted small mb-0">e.g. Waiting-room poster</p>
          </div>
          <button type="button" class="btn btn-sm btn-light btn-icon" id="qrCreateCloseBtn" aria-label="Close"><i class="fi fi-rr-cross"></i></button>
        </div>
        <form method="POST" action="{{ route('qrcodes.store') }}" class="mt-3">
          @csrf
          <div class="mb-3">
            <x-form-field name="label" label="Label" :required="true" placeholder="e.g. Waiting-room poster" />
            <div class="form-text">This label is for internal use only.</div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="qrService">Service</label>
            <select name="service_id" id="qrService" class="form-select">
              <option value="">Any service</option>
              @foreach ($services as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
            </select>
            <div class="form-text">Deep-links straight into booking this service, skipping the picker.</div>
          </div>

          <div class="mb-3">
            <div class="fw-semibold small mb-2">QR Code Preview</div>
            <div class="sas-qr-preview-box">
              <i class="fi fi-rr-qrcode" aria-hidden="true"></i>
              <div class="fw-semibold small mb-1">No QR code generated yet</div>
              <div class="text-muted small mb-0">Fill in the details to generate a preview.</div>
            </div>
          </div>

          <button class="btn btn-primary w-100"><i class="fi fi-rr-qrcode me-1" aria-hidden="true"></i> Create QR Code</button>
        </form>
      </x-card>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      const panel = document.getElementById('qrCreatePanel');
      const listCol = document.getElementById('qrListCol');
      const toggleBtn = document.getElementById('qrCreateToggleBtn');
      const closeBtn = document.getElementById('qrCreateCloseBtn');
      const emptyBtn = document.getElementById('qrEmptyCreateBtn');

      function setOpen(open) {
        panel.classList.toggle('d-none', !open);
        listCol.classList.toggle('col-xl-8', open);
        listCol.classList.toggle('col-xl-12', !open);
      }
      toggleBtn.addEventListener('click', () => setOpen(panel.classList.contains('d-none')));
      closeBtn.addEventListener('click', () => setOpen(false));
      if (emptyBtn) emptyBtn.addEventListener('click', () => setOpen(true));

      if (typeof window.jQuery === 'undefined' || !jQuery.fn.DataTable) return;
      const waitForTable = setInterval(function () {
        if (!jQuery.fn.DataTable.isDataTable('#qrTable')) return;
        clearInterval(waitForTable);

        // Move DataTables' own real length + search controls into the
        // unified toolbar row instead of rebuilding fakes.
        const lengthWrap = document.querySelector('#qrTable_wrapper .dataTables_length');
        const lengthSlot = document.getElementById('qrLengthSlot');
        if (lengthWrap && lengthSlot) lengthSlot.appendChild(lengthWrap);
        const filterWrap = document.querySelector('#qrTable_wrapper .dataTables_filter');
        const searchSlot = document.getElementById('qrSearchSlot');
        if (filterWrap && searchSlot) searchSlot.appendChild(filterWrap);
      }, 50);
    })();
  </script>
@endpush
