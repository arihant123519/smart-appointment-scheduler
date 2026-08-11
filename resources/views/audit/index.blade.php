@extends('layouts.app')

@section('title', 'Audit Logs')

@section('page_icon')<i class="fi fi-rr-shield-check"></i>@endsection
@section('page_title', 'Audit Logs')
@section('page_description', "Track system activity and user actions for security, compliance and accountability.")

@section('page_actions')
  <button type="button" class="btn btn-light-secondary btn-sm fw-semibold" id="auditExportBtn">
    <i class="fi fi-rr-download me-1"></i> Export
  </button>
  <button type="button" class="btn btn-light-secondary btn-sm fw-semibold d-lg-none" id="auditFiltersToggle" aria-expanded="false" aria-controls="auditToolbar">
    <i class="fi fi-rr-settings-sliders me-1"></i> Filters
  </button>
@endsection

@section('content')
  @php
    // Everything below is derived, in the view, from the up-to-500 most
    // recent logs the controller already loads — no new queries, no
    // controller changes. A 30-day window (matching the date-range filter
    // that's pre-applied below) keeps the KPI cards, their vs-previous-30-days
    // deltas, and the table's default row count all telling the same story.
    $now = now();
    $cutoff30 = $now->copy()->subDays(30)->startOfDay();
    $cutoff60 = $now->copy()->subDays(60)->startOfDay();

    $bucketOf = function ($log) {
      if (str_starts_with($log->action, 'login')) return 'logins';
      if ($log->entity === 'Appointment') return 'appointments';
      if ($log->entity === 'Payment') return 'payments';
      if ($log->entity === 'PatientDocument') return 'documents';
      return 'data_changes';
    };

    $actionColor = function (string $action) {
      if (str_starts_with($action, 'login')) return 'info';
      if (in_array($action, ['created', 'document_drafted', 'role_created', 'consultation_saved', 'checked_in', 'intake_completed', 'walk_in_joined', 'confirmed_via_reminder', 'deposit_confirmed', 'document_sent'], true)) return 'success';
      if (in_array($action, ['updated', 'status_changed', 'reason_updated', 'rescheduled_drag', 'consultation_finalized', 'walk_in_status_changed'], true)) return 'warning';
      if (in_array($action, ['deleted', 'cancelled_via_reminder', 'role_deleted', 'walk_in_removed', 'document_approve_failed', 'deposit_forfeited_no_show', 'deposit_forfeited_late_cancel', 'deposit_abandoned_released_slot'], true)) return 'danger';
      if (in_array($action, ['payment_charged', 'payment_refunded', 'deposit_auto_refunded'], true)) return 'accent';
      return 'secondary';
    };

    $inWindow = fn ($log, $from, $to) => $log->created_at >= $from && $log->created_at < $to;
    $recent = $logs->filter(fn ($l) => $inWindow($l, $cutoff30, $now->copy()->addMinute()));
    $prior = $logs->filter(fn ($l) => $inWindow($l, $cutoff60, $cutoff30));

    $countIn = fn ($coll, $bucket) => $bucket ? $coll->filter(fn ($l) => $bucketOf($l) === $bucket)->count() : $coll->count();

    $kpiDefs = [
      ['key' => null, 'label' => 'Total Events', 'icon' => 'fi-rr-list-check', 'bg' => 'bg-accent-subtle', 'fg' => 'text-accent', 'color' => '#7c3aed'],
      ['key' => 'logins', 'label' => 'Logins', 'icon' => 'fi-rr-user', 'bg' => 'bg-info-subtle', 'fg' => 'text-info', 'color' => '#2563eb'],
      ['key' => 'data_changes', 'label' => 'Data Changes', 'icon' => 'fi-rr-database', 'bg' => 'bg-success-subtle', 'fg' => 'text-success', 'color' => '#17c653'],
      ['key' => 'appointments', 'label' => 'Appointments', 'icon' => 'fi-rr-calendar', 'bg' => 'bg-warning-subtle', 'fg' => 'text-warning', 'color' => '#f59e0b'],
      ['key' => 'payments', 'label' => 'Payments', 'icon' => 'fi-rr-credit-card', 'bg' => 'bg-danger-subtle', 'fg' => 'text-danger', 'color' => '#f1416c'],
      ['key' => 'documents', 'label' => 'Documents', 'icon' => 'fi-rr-document', 'bg' => 'bg-accent-subtle', 'fg' => 'text-accent', 'color' => '#7c3aed'],
    ];

    // 14 daily buckets per KPI for the sparkline, newest last.
    $sparkFor = function ($bucket) use ($logs, $bucketOf) {
      $days = [];
      for ($i = 13; $i >= 0; $i--) {
        $day = today()->subDays($i);
        $days[] = $logs->filter(fn ($l) => $l->created_at->isSameDay($day) && ($bucket === null || $bucketOf($l) === $bucket))->count();
      }
      return $days;
    };
  @endphp

  {{-- KPI row --}}
  <div class="row g-3 mb-4">
    @foreach ($kpiDefs as $k)
      @php
        $value = $countIn($recent, $k['key']);
        $prevValue = $countIn($prior, $k['key']);
        $delta = $prevValue > 0 ? round((($value - $prevValue) / $prevValue) * 100) : ($value > 0 ? 100 : null);
      @endphp
      <div class="col-xl-2 col-md-4 col-6">
        <x-stat-widget :label="$k['label']" :value="$value" :icon="$k['icon']" :bg="$k['bg']" :fg="$k['fg']"
          :sparkId="'spark_'.($k['key'] ?? 'total')" :sparkColor="$k['color']" :sparkSeries="$sparkFor($k['key'])"
          @if($delta !== null)
            :delta="abs($delta).'%'" :deltaUp="$delta >= 0" deltaLabel="vs last 30 days"
          @else
            caption="Last 30 days"
          @endif
        />
      </div>
    @endforeach
  </div>

  <x-card bodyClass="p-0">
    {{-- Filter toolbar — entirely client-side, filters the rows already
         rendered below via the table's existing DataTables instance. No
         request round-trip, no new backend endpoint. --}}
    <div class="sas-table-toolbar" id="auditToolbar">
      <select class="form-select form-select-sm" id="auditPageLen" aria-label="Entries per page">
        <option value="10">10</option>
        <option value="25">25</option>
        <option value="50">50</option>
        <option value="100">100</option>
      </select>

      <div class="sas-table-toolbar__filters">
        <select class="form-select form-select-sm" id="auditFilterAction" aria-label="Filter by action">
          <option value="">All Actions</option>
          <option value="logins">Logins</option>
          <option value="data_changes">Data Changes</option>
          <option value="appointments">Appointments</option>
          <option value="payments">Payments</option>
          <option value="documents">Documents</option>
        </select>

        <select class="form-select form-select-sm" id="auditFilterUser" aria-label="Filter by user">
          <option value="">All Users</option>
          @foreach ($logs->pluck('user')->filter()->unique('id')->sortBy('name') as $u)
            <option value="{{ $u->id }}">{{ $u->name }}</option>
          @endforeach
        </select>

        <select class="form-select form-select-sm" id="auditFilterEntity" aria-label="Filter by entity">
          <option value="">All Entities</option>
          @foreach ($logs->pluck('entity')->filter()->unique()->sort() as $e)
            <option value="{{ $e }}">{{ $e }}</option>
          @endforeach
        </select>

        <button type="button" class="sas-daterange-btn" id="auditDateRangeBtn">
          <i class="fi fi-rr-calendar"></i>
          <span id="auditDateRangeLabel">{{ $cutoff30->format('M j') }} &ndash; {{ $now->format('M j, Y') }}</span>
        </button>
        <input type="text" id="auditDateRangeInput" class="d-none">
      </div>

      <div class="sas-table-toolbar__search">
        <i class="fi fi-rr-search"></i>
        <input type="text" class="form-control form-control-sm" id="auditSearch" placeholder="Search logs...">
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 datatable" id="auditTable">
        <thead>
          <tr>
            <th>When</th>
            <th>User</th>
            <th>Action</th>
            <th>Entity</th>
            <th>IP Address</th>
            <th class="text-end">Details</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($logs as $log)
            <tr
              data-bucket="{{ $bucketOf($log) }}"
              data-user-id="{{ $log->user_id }}"
              data-entity="{{ $log->entity }}"
              data-timestamp="{{ $log->created_at->timestamp }}"
            >
              <td class="text-nowrap">
                <span class="d-inline-flex align-items-center gap-2 text-muted">
                  <i class="fi fi-rr-clock" style="font-size:.8rem"></i>
                  {{ $log->created_at->format('M j, Y g:i A') }}
                </span>
              </td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  @if ($log->user)
                    <img src="{{ $log->user->avatar_url }}" class="sas-avatar sas-avatar-sm" alt="">
                  @else
                    <span class="sas-avatar sas-avatar-sm bg-light text-muted"><i class="fi fi-rr-settings" style="font-size:.8rem"></i></span>
                  @endif
                  <div style="min-width:0">
                    <div class="fw-semibold text-truncate" style="max-width:160px">{{ $log->user->name ?? 'System' }}</div>
                    <small class="text-muted">{{ $log->user?->roles->first()?->name ? ucwords(str_replace('_', ' ', $log->user->roles->first()->name)) : '—' }}</small>
                  </div>
                </div>
              </td>
              <td><x-badge-status :color="$actionColor($log->action)" :label="ucwords(str_replace('_', ' ', $log->action))" /></td>
              <td class="text-muted">{{ $log->entity ?? '—' }}{{ $log->entity_id ? ' #'.$log->entity_id : '' }}</td>
              <td class="text-muted small font-monospace">{{ $log->ip ?? '—' }}</td>
              <td class="text-end">
                <div class="dropdown sas-dropdown-actions">
                  <button type="button" class="btn btn-sm btn-icon-square" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Details for this event">
                    <i class="fi fi-rr-menu-dots-vertical"></i>
                  </button>
                  <div class="dropdown-menu dropdown-menu-end p-3" style="width:300px">
                    <div class="sas-event-detail__row"><span>Action</span><strong>{{ ucwords(str_replace('_', ' ', $log->action)) }}</strong></div>
                    <div class="sas-event-detail__row"><span>Entity</span><strong>{{ $log->entity ?? '—' }}{{ $log->entity_id ? ' #'.$log->entity_id : '' }}</strong></div>
                    <div class="sas-event-detail__row"><span>User</span><strong>{{ $log->user->name ?? 'System' }}</strong></div>
                    <div class="sas-event-detail__row"><span>IP address</span><strong class="font-monospace">{{ $log->ip ?? '—' }}</strong></div>
                    @if ($log->user_agent)
                      <div class="sas-event-detail__row"><span>Device</span><strong class="text-truncate" style="max-width:160px">{{ $log->user_agent }}</strong></div>
                    @endif
                    @if (is_array($log->after) && count($log->after))
                      <hr class="my-2">
                      <div class="text-muted small fw-semibold mb-1">Changed fields</div>
                      @foreach (array_slice($log->after, 0, 6, true) as $field => $value)
                        @continue(is_array($value))
                        <div class="sas-event-detail__row">
                          <span>{{ ucwords(str_replace('_', ' ', $field)) }}</span>
                          <strong class="text-truncate" style="max-width:150px">{{ is_bool($value) ? ($value ? 'Yes' : 'No') : $value }}</strong>
                        </div>
                      @endforeach
                      @if (count($log->after) > 6)
                        <div class="text-muted small mt-1">+{{ count($log->after) - 6 }} more field{{ count($log->after) - 6 === 1 ? '' : 's' }}</div>
                      @endif
                    @endif
                  </div>
                </div>
              </td>
            </tr>
          @empty
            <x-empty-state colspan="6" icon="fi-rr-list-check" title="No audit entries yet" description="Sensitive actions will show up here as they happen." />
          @endforelse
        </tbody>
      </table>
    </div>
  </x-card>
@endsection

@push('styles')
  <style>
    .sas-event-detail__row { display: flex; justify-content: space-between; gap: .75rem; font-size: .8rem; padding: .2rem 0; }
    .sas-event-detail__row span { color: var(--sas-gray-500); }
    .sas-event-detail__row strong { color: var(--sas-gray-800); font-weight: 600; text-align: right; }
  </style>
@endpush

@push('scripts')
  {{-- flatpickr is already loaded globally in layouts/app.blade.php; only
       ApexCharts is page-specific (each page opts in, same as the dashboard). --}}
  <script src="{{ asset('assets/libs/apexcharts/apexcharts.min.js') }}"></script>
  <script>
    (function () {
      if (typeof window.jQuery === 'undefined' || !jQuery.fn.DataTable) return;

      jQuery(function ($) {
        // The global auto-init in layouts/app.blade.php already turned
        // #auditTable into a DataTable on page load — grab that same
        // instance rather than re-initializing it.
        const table = $('#auditTable').DataTable();
        const nowTs = {{ $now->timestamp }};
        let range = { from: {{ $cutoff30->timestamp }}, to: nowTs };

        // ---- Entries per page ----
        $('#auditPageLen').on('change', function () { table.page.len(parseInt(this.value, 10)).draw(); });

        // ---- Free-text search ----
        $('#auditSearch').on('input', function () { table.search(this.value).draw(); });

        // ---- Category / user / entity / date-range filter (combined) ----
        $.fn.dataTable.ext.search.push(function (settings, data, rowIdx, rowData, counter) {
          if (settings.nTable.id !== 'auditTable') return true;
          const row = table.row(rowIdx).node();
          if (!row) return true;
          const bucket = row.getAttribute('data-bucket');
          const userId = row.getAttribute('data-user-id');
          const entity = row.getAttribute('data-entity');
          const ts = parseInt(row.getAttribute('data-timestamp'), 10);

          const actionFilter = $('#auditFilterAction').val();
          const userFilter = $('#auditFilterUser').val();
          const entityFilter = $('#auditFilterEntity').val();

          if (actionFilter && bucket !== actionFilter) return false;
          if (userFilter && userId !== userFilter) return false;
          if (entityFilter && entity !== entityFilter) return false;
          if (ts < range.from || ts > range.to) return false;
          return true;
        });

        $('#auditFilterAction, #auditFilterUser, #auditFilterEntity').on('change', function () { table.draw(); });

        // ---- Date range (flatpickr) ----
        const dateInput = flatpickr('#auditDateRangeInput', {
          mode: 'range',
          dateFormat: 'Y-m-d',
          defaultDate: [new Date(range.from * 1000), new Date(nowTs * 1000)],
          onClose: function (selectedDates) {
            if (selectedDates.length !== 2) return;
            const endOfDay = new Date(selectedDates[1]);
            endOfDay.setHours(23, 59, 59, 999);
            range.from = Math.floor(selectedDates[0].getTime() / 1000);
            range.to = Math.floor(endOfDay.getTime() / 1000);
            const fmt = (d) => d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            document.getElementById('auditDateRangeLabel').textContent = fmt(selectedDates[0]) + ' – ' + fmt(selectedDates[1]);
            table.draw();
          },
        });
        document.getElementById('auditDateRangeBtn').addEventListener('click', function () { dateInput.open(); });

        // Apply the default 30-day window immediately (KPIs above already
        // assume it, so the table and the cards agree on first paint).
        table.draw();

        // ---- Export (client-side CSV of whatever's currently filtered) ----
        document.getElementById('auditExportBtn').addEventListener('click', function () {
          const rows = table.rows({ search: 'applied' }).nodes();
          const header = ['When', 'User', 'Action', 'Entity', 'IP Address'];
          const lines = [header.join(',')];
          $(rows).each(function () {
            const cells = $(this).find('td');
            const csvCell = (i) => '"' + $(cells[i]).text().trim().replace(/\s+/g, ' ').replace(/"/g, '""') + '"';
            lines.push([csvCell(0), csvCell(1), csvCell(2), csvCell(3), csvCell(4)].join(','));
          });
          const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
          const url = URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = 'audit-log-' + new Date().toISOString().slice(0, 10) + '.csv';
          document.body.appendChild(a);
          a.click();
          a.remove();
          URL.revokeObjectURL(url);
        });

        // ---- Mobile filter drawer toggle ----
        const toggleBtn = document.getElementById('auditFiltersToggle');
        const toolbar = document.getElementById('auditToolbar');
        if (toggleBtn && toolbar) {
          toolbar.classList.add('d-none', 'd-lg-flex');
          toggleBtn.addEventListener('click', function () {
            const open = toolbar.classList.toggle('d-none') === false;
            toggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
          });
        }
      });
    })();
  </script>
@endpush
