@extends('layouts.app')

@section('title', 'WhatsApp Flows')

@php
  // Everything below is a real, read-only computation — same pattern used on
  // the Calendar/Appointments/Waitlist/Walk-in pages. No fabricated metrics:
  // there is no "paused" flow status or "messages delivered" concept the
  // mockup's text brief assumed, so those are intentionally left out (see
  // the summary for what was actually available to compute honestly).
  $flowIds = $flows->pluck('id');
  $totalFlows = $flows->count();
  $activeFlows = $flows->where('status', 'active')->count();
  $archivedFlows = $flows->where('status', 'archived')->count();
  $totalConversations = $flows->sum('conversations_count');

  $uniquePatients = $flowIds->isNotEmpty()
    ? \App\Models\WhatsappConversation::whereIn('whatsapp_flow_id', $flowIds)->whereNotNull('patient_id')->distinct('patient_id')->count('patient_id')
    : 0;

  $recentConversations = $flowIds->isNotEmpty()
    ? \App\Models\WhatsappConversation::whereIn('whatsapp_flow_id', $flowIds)->where('started_at', '>=', now()->subDays(30))->get(['status'])
    : collect();
  $successRate = $recentConversations->isNotEmpty()
    ? (int) round($recentConversations->where('status', 'completed')->count() / $recentConversations->count() * 100)
    : null;
@endphp

@push('styles')
  <style>
    .sas-flow-header__icon {
      width: 52px; height: 52px; border-radius: var(--sas-radius-lg); flex-shrink: 0;
      background: var(--sas-primary-50); color: var(--sas-primary-700);
      display: grid; place-items: center; font-size: 1.4rem;
    }
    .sas-flow-header__title { font-size: 2.25rem; font-weight: 800; letter-spacing: -.02em; color: var(--sas-gray-900); }
    .sas-flow-header__subtitle { font-size: var(--sas-fs-base); color: var(--sas-gray-500); }

    #flowsTable_wrapper > .row:first-child { padding: var(--sas-space-3) var(--sas-space-5); margin: 0; align-items: center; border-bottom: 1px solid var(--sas-gray-100); }
    #flowsTable_wrapper .dataTables_length select { border-radius: var(--sas-radius-md); }
    #flowsTable_wrapper .dataTables_filter input { margin-left: 0 !important; min-width: 200px; }
    #flowsTable_wrapper > .row:last-child { padding: var(--sas-space-4) var(--sas-space-5); margin: 0; align-items: center; }

    .sas-flow-filter-btn {
      border: 1px solid var(--sas-gray-200); background: #fff; color: var(--sas-gray-600); font-weight: 600; font-size: var(--sas-fs-sm);
      border-radius: var(--sas-radius-md); padding: .55rem 1rem; display: inline-flex; align-items: center; gap: .4rem;
    }
    .sas-flow-filter-btn:hover { background: var(--sas-gray-50); }
    .sas-flow-filter-btn.has-active { border-color: var(--sas-primary-400); color: var(--sas-primary-600); background: var(--sas-primary-50); }

    #flowsTable .sas-flow-name { display: flex; align-items: center; gap: .65rem; }
    #flowsTable .sas-flow-name__icon { width: 34px; height: 34px; border-radius: var(--sas-radius-md); background: var(--sas-success-subtle); color: var(--sas-success-emphasis); display: grid; place-items: center; flex-shrink: 0; }
    #flowsTable .sas-flow-name__text { font-weight: 700; color: var(--sas-gray-900); }
    #flowsTable .sas-flow-trigger { display: inline-flex; align-items: center; gap: .4rem; font-size: var(--sas-fs-sm); color: var(--sas-gray-700); }
    #flowsTable .sas-flow-conversations__count { font-weight: 700; color: var(--sas-gray-900); display: block; }
    #flowsTable .sas-flow-conversations__link { font-size: var(--sas-fs-xs); }
    #flowsTable .sas-flow-updated__date { color: var(--sas-gray-800); font-size: var(--sas-fs-sm); }
    #flowsTable .sas-flow-updated__time { color: var(--sas-gray-400); font-size: var(--sas-fs-xs); }
    #flowsTable .btn-icon-square { width: 34px; height: 34px; border-radius: var(--sas-radius-md); border: 1px solid var(--sas-gray-200); background: #fff; color: var(--sas-gray-600); }
    #flowsTable .btn-icon-square:hover { background: var(--sas-gray-50); }
  </style>
@endpush

@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div class="d-flex align-items-start gap-3">
      <span class="sas-flow-header__icon"><i class="fi fi-brands-whatsapp" aria-hidden="true"></i></span>
      <div>
        <h1 class="sas-flow-header__title mb-1">WhatsApp Flows</h1>
        <p class="sas-flow-header__subtitle mb-0">Manage and monitor automated WhatsApp conversation flows.</p>
      </div>
    </div>
    <div class="d-flex align-items-center gap-2">
      <a href="{{ route('settings.integrations.edit') }}" class="btn btn-light btn-lg"><i class="fi fi-rr-picture me-1" aria-hidden="true"></i> Templates</a>
      <a href="{{ route('flows.create') }}" class="btn btn-primary btn-lg"><i class="fi fi-rr-plus me-1" aria-hidden="true"></i> Create Flow</a>
    </div>
  </div>

  <div class="row g-3 mb-3 sas-stagger">
    <div class="col-6 col-xl-2">
      <x-stat-widget label="Total Flows" :value="$totalFlows" icon="fi-rr-comment-alt" bg="bg-primary-subtle" fg="text-primary" caption="All time" />
    </div>
    <div class="col-6 col-xl-2">
      <x-stat-widget label="Active Flows" :value="$activeFlows" icon="fi-rr-check-circle" bg="bg-success-subtle" fg="text-success" :caption="$totalFlows > 0 ? round($activeFlows / $totalFlows * 100).'%' : '—'" />
    </div>
    <div class="col-6 col-xl-2">
      {{-- The backend has no "paused" status (only draft/active/archived) —
           this reports the real one, not the mockup's fabricated label. --}}
      <x-stat-widget label="Archived Flows" :value="$archivedFlows" icon="fi-rr-pause" bg="bg-warning-subtle" fg="text-warning" :caption="$totalFlows > 0 ? round($archivedFlows / $totalFlows * 100).'%' : '—'" />
    </div>
    <div class="col-6 col-xl-2">
      <x-stat-widget label="Conversations" :value="$totalConversations" icon="fi-rr-comment-dots" bg="bg-info-subtle" fg="text-info" caption="All time" />
    </div>
    <div class="col-6 col-xl-2">
      <x-stat-widget label="Unique Patients" :value="$uniquePatients" icon="fi-rr-users-alt" bg="bg-primary-subtle" fg="text-primary" caption="All time" />
    </div>
    <div class="col-6 col-xl-2">
      <x-stat-widget label="Success Rate" :value="$successRate !== null ? $successRate.'%' : '—'" icon="fi-rr-arrow-trend-up" bg="bg-success-subtle" fg="text-success" caption="Last 30 days" />
    </div>
  </div>

  <x-card bodyClass="p-0">
    <div class="table-responsive">
      <table id="flowsTable" class="table align-middle mb-0 datatable">
        <thead>
          <tr><th>Name</th><th>Trigger</th><th>Status</th><th>Conversations</th><th>Last Updated</th><th class="text-end">Actions</th></tr>
        </thead>
        <tbody>
          @forelse ($flows as $flow)
            <tr data-status="{{ $flow->status }}">
              <td>
                <div class="sas-flow-name">
                  <span class="sas-flow-name__icon"><i class="fi fi-brands-whatsapp" aria-hidden="true"></i></span>
                  <span class="sas-flow-name__text">{{ $flow->name }}</span>
                </div>
              </td>
              <td>
                @if ($flow->trigger_event)
                  <span class="sas-flow-trigger"><i class="fi fi-rr-calendar text-primary" aria-hidden="true"></i> {{ $events[$flow->trigger_event] ?? $flow->trigger_event }}</span>
                @else
                  <span class="text-muted small">Not set</span>
                @endif
              </td>
              <td>
                @php $badge = ['draft' => 'secondary', 'active' => 'success', 'archived' => 'warning'][$flow->status] ?? 'secondary'; @endphp
                <x-badge-status :color="$badge" :label="ucfirst($flow->status)" />
              </td>
              <td>
                <span class="sas-flow-conversations__count">{{ $flow->conversations_count }}</span>
                <a href="{{ route('flows.conversations', $flow) }}" class="sas-flow-conversations__link">View conversations</a>
              </td>
              <td>
                <div class="sas-flow-updated__date">{{ $flow->updated_at->format('d M Y') }}</div>
                <div class="sas-flow-updated__time">{{ $flow->updated_at->format('g:i A') }}</div>
              </td>
              <td class="text-end">
                <div class="dropdown sas-dropdown-actions">
                  <button type="button" class="btn btn-sm btn-icon-square" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions for {{ $flow->name }}">
                    <i class="fi fi-rr-menu-dots-vertical"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('flows.edit', $flow) }}"><i class="fi fi-rr-edit"></i> Edit flow</a></li>
                    <li><a class="dropdown-item" href="{{ route('flows.conversations', $flow) }}"><i class="fi fi-rr-comment-dots"></i> View conversations</a></li>
                    @if ($flow->status !== 'active')
                      <li>
                        <form method="POST" action="{{ route('flows.activate', $flow) }}">
                          @csrf @method('PATCH')
                          <button type="submit" class="dropdown-item"><i class="fi fi-rr-check-circle"></i> Activate</button>
                        </form>
                      </li>
                    @endif
                    <li><hr class="dropdown-divider"></li>
                    <li>
                      <form method="POST" action="{{ route('flows.destroy', $flow) }}" data-sas-confirm="Delete this flow? Its past conversations stay on record." data-sas-confirm-label="Delete">
                        @csrf @method('DELETE')
                        <button type="submit" class="dropdown-item text-danger"><i class="fi fi-rr-trash"></i> Delete</button>
                      </form>
                    </li>
                  </ul>
                </div>
              </td>
            </tr>
          @empty
            <x-empty-state colspan="6" icon="fi-rr-comment-alt" title="Create your first automation" description="Build a flow to automate WhatsApp conversations like reschedule confirmations.">
              <a href="{{ route('flows.create') }}" class="btn btn-sm btn-primary"><i class="fi fi-rr-plus me-1"></i> New Flow</a>
            </x-empty-state>
          @endforelse
        </tbody>
      </table>
    </div>
  </x-card>
@endsection

@push('scripts')
  <script>
    (function () {
      if (typeof window.jQuery === 'undefined' || !jQuery.fn.DataTable) return;
      const waitForTable = setInterval(function () {
        if (!jQuery.fn.DataTable.isDataTable('#flowsTable')) return;
        clearInterval(waitForTable);

        const table = jQuery('#flowsTable').DataTable();
        let statusSet = new Set();

        jQuery.fn.dataTable.ext.search.push(function (settings, data, rowIdx) {
          if (settings.nTable.id !== 'flowsTable') return true;
          if (!statusSet.size) return true;
          const row = table.row(rowIdx).node();
          return row ? statusSet.has(row.getAttribute('data-status')) : true;
        });

        const filterWrap = document.querySelector('#flowsTable_wrapper .dataTables_filter');
        if (!filterWrap) return;

        const wrap = document.createElement('div');
        wrap.className = 'dropdown';
        wrap.innerHTML =
          '<button type="button" class="sas-flow-filter-btn" id="flowStatusFilterBtn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Filter by status">' +
            '<i class="fi fi-rr-filter" aria-hidden="true"></i> Filters' +
          '</button>' +
          '<ul class="dropdown-menu dropdown-menu-end p-2" style="min-width:170px">' +
            ['draft', 'active', 'archived'].map(s =>
              '<li><label class="dropdown-item d-flex align-items-center gap-2 mb-0" style="cursor:pointer"><input type="checkbox" class="form-check-input mt-0 sas-flow-status-check" value="' + s + '"> ' + s.charAt(0).toUpperCase() + s.slice(1) + '</label></li>'
            ).join('') +
          '</ul>';
        filterWrap.appendChild(wrap);

        const filterBtnEl = document.getElementById('flowStatusFilterBtn');
        const checks = wrap.querySelectorAll('.sas-flow-status-check');
        checks.forEach(c => c.addEventListener('change', function () {
          statusSet = new Set(Array.from(checks).filter(x => x.checked).map(x => x.value));
          filterBtnEl.classList.toggle('has-active', statusSet.size > 0);
          table.draw();
        }));
      }, 50);
    })();
  </script>
@endpush
