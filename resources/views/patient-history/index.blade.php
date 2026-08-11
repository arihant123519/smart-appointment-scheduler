@extends('layouts.app')

@section('title', 'My Patients')

@push('styles')
  <style>
    .sas-page-toolbar { display: none; }

    .sas-pat-header__icon {
      width: 52px; height: 52px; border-radius: var(--sas-radius-lg); flex-shrink: 0;
      background: var(--sas-primary-50); color: var(--sas-primary-700);
      display: grid; place-items: center; font-size: 1.4rem;
    }
    .sas-pat-header__title { font-size: 2.25rem; font-weight: 800; letter-spacing: -.02em; color: var(--sas-gray-900); }
    .sas-pat-header__subtitle { font-size: var(--sas-fs-base); color: var(--sas-gray-500); }

    .sas-pat-searchrow { padding: var(--sas-space-4) var(--sas-space-5); border-bottom: 1px solid var(--sas-gray-100); }
    .sas-pat-search { position: relative; max-width: 480px; }
    .sas-pat-search i { position: absolute; left: .9rem; top: 50%; transform: translateY(-50%); color: var(--sas-gray-400); }
    .sas-pat-search input {
      width: 100%; border: 1px solid var(--sas-gray-200); border-radius: var(--sas-radius-md); background: var(--sas-gray-50);
      padding: .6rem .9rem .6rem 2.4rem; font-size: var(--sas-fs-sm); transition: border-color .15s var(--sas-ease), background-color .15s var(--sas-ease);
    }
    .sas-pat-search input:focus { outline: none; background: #fff; border-color: var(--sas-primary-400); box-shadow: 0 0 0 .2rem var(--sas-primary-100); }

    /* DataTables' auto-injected length/filter row, scoped to this page's table only */
    #patHistoryTable_wrapper > .row:first-child { padding: var(--sas-space-3) var(--sas-space-5); margin: 0; align-items: center; border-bottom: 1px solid var(--sas-gray-100); }
    #patHistoryTable_wrapper .dataTables_length select { border-radius: var(--sas-radius-md); }
    #patHistoryTable_wrapper .dataTables_filter input { margin-left: 0 !important; min-width: 220px; }
    #patHistoryTable_wrapper > .row:last-child { padding: var(--sas-space-4) var(--sas-space-5); margin: 0; align-items: center; }

    #patHistoryTable tbody tr { cursor: pointer; }
    #patHistoryTable .btn-icon-square {
      width: 34px; height: 34px; border-radius: var(--sas-radius-md); border: 1px solid var(--sas-gray-200); background: #fff; color: var(--sas-gray-600);
    }
    #patHistoryTable .btn-icon-square:hover { background: var(--sas-gray-50); }
  </style>
@endpush

@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div class="d-flex align-items-start gap-3">
      <span class="sas-pat-header__icon" style="background: #c5deff;"><i class="fi fi-rr-users" aria-hidden="true"></i></span>
      <div>
        <h1 class="sas-pat-header__title mb-1">My Patients</h1>
        <p class="sas-pat-header__subtitle mb-0">Manage and view all registered patients.</p>
      </div>
    </div>
  </div>

  @php $totalPatients = $patients->count(); @endphp

  <x-card bodyClass="p-0">
    <form method="GET" class="sas-pat-searchrow">
      <div class="sas-pat-search">
        <i class="fi fi-rr-search" aria-hidden="true"></i>
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search name, email or phone…" aria-label="Search patients">
      </div>
      @if (request('q'))
        <div class="mt-2 text-muted small">
          {{ $totalPatients }} patient{{ $totalPatients === 1 ? '' : 's' }} matching &ldquo;{{ request('q') }}&rdquo;
          &nbsp;·&nbsp; <a href="{{ route('patient-history.index') }}" class="text-decoration-none">Clear</a>
        </div>
      @endif
    </form>

    <div class="table-responsive">
      <table id="patHistoryTable" class="table table-hover align-middle mb-0 datatable">
        <thead class="table-light"><tr><th>Name</th><th>Email</th><th>Phone</th><th>Visits</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
          @forelse ($patients as $p)
            <tr onclick="window.location='{{ route('patient-history.show', $p) }}'">
              <td>
                <div class="d-flex align-items-center gap-2">
                  <img src="{{ $p->avatar_url }}" class="sas-avatar sas-avatar-sm" alt="">
                  <span class="fw-semibold">{{ $p->name }}</span>
                </div>
              </td>
              <td class="text-muted">{{ $p->email }}</td>
              <td class="text-muted">{{ $p->phone ?? '—' }}</td>
              <td>{{ $p->appointments_count }}</td>
              <td class="text-end" onclick="event.stopPropagation()">
                <a class="btn btn-sm btn-icon-square" title="Actions for {{ $p->name }}" href="{{ route('patient-history.show', $p) }}"><i class="fi fi-rr-eye"></i></a></li>
              </td>
            </tr>
          @empty
            <x-empty-state colspan="5" icon="fi-rr-users" title="No patients yet" description="Patients you've had an appointment with will show up here." />
          @endforelse
        </tbody>
      </table>
    </div>
  </x-card>
@endsection
