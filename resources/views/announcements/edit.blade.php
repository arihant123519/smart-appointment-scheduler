@extends('layouts.app')

@section('title', 'Edit Announcement')

@section('page_actions')
  <a href="{{ route('announcements.index') }}" class="btn btn-light"><i class="fi fi-rr-arrow-left me-1"></i> Back</a>
@endsection

@push('styles')
  <style>
    .sas-user-picker { border: 1px solid var(--sas-gray-200, #E2E8F0); border-radius: var(--sas-radius-lg, 12px); overflow: hidden; }
    .sas-user-picker__search { display: flex; align-items: center; gap: .5rem; padding: .65rem .85rem; border-bottom: 1px solid var(--sas-gray-200, #E2E8F0); background: var(--sas-gray-50, #F8FAFC); }
    .sas-user-picker__search i { color: var(--sas-gray-400, #94A3B8); font-size: .8rem; }
    .sas-user-picker__search input { border: 0; background: transparent; flex: 1; font-size: 13px; outline: none; }
    .sas-user-picker__list { max-height: 320px; overflow-y: auto; }
    .sas-user-picker__row { display: flex; align-items: center; gap: .65rem; padding: .55rem .85rem; cursor: pointer; border-bottom: 1px solid var(--sas-gray-100, #F1F5F9); transition: background .15s; margin: 0; }
    .sas-user-picker__row:last-child { border-bottom: 0; }
    .sas-user-picker__row:hover { background: var(--sas-gray-50, #F8FAFC); }
    .sas-user-picker__row input[type="checkbox"] { flex: 0 0 auto; }
    .sas-user-picker__avatar { width: 26px; height: 26px; border-radius: 8px; background: var(--sas-primary-100, #DBEAFE); color: var(--sas-primary-700, #1D4ED8); font-size: 11px; font-weight: 600; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
    .sas-user-picker__body { display: flex; flex-direction: column; min-width: 0; flex: 1; }
    .sas-user-picker__name { font-size: 13px; font-weight: 500; color: var(--sas-gray-900, #0F172A); }
    .sas-user-picker__meta { font-size: 11px; color: var(--sas-gray-400, #94A3B8); }
    .sas-user-picker__role { margin-left: auto; flex: 0 0 auto; }
    .sas-filter-section__label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--sas-gray-500, #64748B); margin-bottom: .65rem; }
    .sas-audience-modal { display: flex; align-items: flex-start; gap: 1.25rem; }
    .sas-audience-modal__filters { flex: 0 0 250px; padding-right: 1.25rem; border-right: 1px solid var(--sas-gray-200, #E2E8F0); }
    .sas-audience-modal__filters .form-label { font-size: 12px; font-weight: 500; color: var(--sas-gray-700, #334155); margin-bottom: .3rem; }
    .sas-audience-modal__hint { font-size: 12px; color: var(--sas-gray-400, #94A3B8); margin-bottom: 1rem; }
    .sas-audience-modal__users { flex: 1 1 auto; min-width: 0; }
    .sas-user-picker__roles { display: flex; flex-wrap: wrap; gap: .4rem; padding: .6rem .85rem; border-bottom: 1px solid var(--sas-gray-200, #E2E8F0); background: var(--sas-gray-50, #F8FAFC); }
    .sas-user-picker__bulk { display: flex; align-items: center; gap: .5rem; padding: .5rem .85rem; border-bottom: 1px solid var(--sas-gray-200, #E2E8F0); background: var(--sas-gray-50, #F8FAFC); font-size: 12px; font-weight: 500; color: var(--sas-gray-700, #334155); }
    .sas-user-picker__bulk small { font-weight: 400; color: var(--sas-gray-400, #94A3B8); }
    .sas-role-chip { display: inline-flex; align-items: center; padding: .28rem .6rem; border-radius: 6px; font-size: 11px; font-weight: 600; letter-spacing: .02em; border: 1px solid var(--sas-gray-200, #E2E8F0); color: var(--sas-gray-500, #64748B); background: #fff; cursor: pointer; transition: all .15s; }
    .sas-role-chip:hover { background: var(--sas-gray-100, #F1F5F9); }
    .sas-role-chip.active { background: var(--sas-primary-600, #2563EB); border-color: var(--sas-primary-600, #2563EB); color: #fff; }
    @media (max-width: 767.98px) {
      .sas-audience-modal { flex-direction: column; }
      .sas-audience-modal__filters { flex: none; width: 100%; border-right: 0; border-bottom: 1px solid var(--sas-gray-200, #E2E8F0); padding-right: 0; padding-bottom: 1rem; }
    }
  </style>
@endpush

@section('content')
  <div class="row g-3 justify-content-center">
    <div class="col-xl-6">
      <x-card>
        @php $channels = old('channels', explode(',', $announcement->channel)); @endphp

        <div class="alert alert-{{ $announcement->status === 'scheduled' ? 'warning' : 'secondary' }} py-2 small mb-3">
          @if ($announcement->status === 'scheduled')
            <i class="fi fi-rr-clock me-1"></i> Currently <strong>scheduled</strong> for {{ $announcement->send_at?->format('M j, Y g:i A') }}.
          @else
            <i class="fi fi-rr-check me-1"></i> Already <strong>sent</strong>{{ $announcement->sent_at ? ' on '.$announcement->sent_at->format('M j, Y g:i A') : '' }} to {{ $announcement->recipients_count }} recipient(s). Editing here updates the record; set a new "Send in" offset below to resend.
          @endif
        </div>

        <form method="POST" action="{{ route('announcements.update', $announcement) }}">
          @csrf @method('PUT')

          <x-form-field name="title" label="Title" :value="old('title', $announcement->title)" :required="true" />

          <div class="mb-3 mt-3"><label class="form-label">Message</label>
            <textarea name="body" class="form-control @error('body') is-invalid @enderror" rows="4" required>{{ old('body', $announcement->body) }}</textarea>
            @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          @php $f = old('filters', $announcement->filters ?? []); @endphp
          <input type="hidden" name="audience" value="custom">
          <div class="mb-3"><label class="form-label">Audience</label>
            <button type="button" id="configureFiltersBtn" class="btn btn-outline-secondary w-100 text-start" data-bs-toggle="modal" data-bs-target="#broadcastFilters">
              <i class="fi fi-rr-settings-sliders me-1"></i> Configure audience filters
            </button>
          </div>

          <div class="mb-3">
            <label class="form-label">Channel</label>
            <div class="d-flex gap-4">
              <div class="form-check">
                <input type="checkbox" class="form-check-input" id="ch-email" name="channels[]" value="email" @checked(in_array('email', $channels))>
                <label class="form-check-label" for="ch-email"><i class="fi fi-rr-envelope me-1"></i> Email</label>
              </div>
              <div class="form-check">
                <input type="checkbox" class="form-check-input" id="ch-wa" name="channels[]" value="whatsapp" @checked(in_array('whatsapp', $channels))>
                <label class="form-check-label" for="ch-wa"><i class="fi fi-brands-whatsapp me-1"></i> WhatsApp</label>
              </div>
            </div>
            @error('channels')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
          </div>

          {{-- WhatsApp template carried by this announcement --}}
          <div class="border rounded-3 p-3 mb-3 bg-light">
            <div class="d-flex align-items-center gap-2 mb-2">
              <i class="fi fi-brands-whatsapp"></i><strong class="small">WhatsApp template</strong>
              <span class="text-muted small">(required if WhatsApp is selected)</span>
            </div>
            <div class="row g-2">
              <div class="col-md-7">
                <x-form-field name="wa_template_id" label="Gupshup Template ID" :value="old('wa_template_id', $announcement->wa_template_id)" class="form-control-sm" placeholder="e.g. 3515c95f-f515-45c1-8b0c-04141e8d858d" />
              </div>
              <div class="col-md-5">
                <label class="form-label small">Namespace <span class="text-muted">(optional)</span></label>
                <input type="text" name="wa_namespace" class="form-control form-control-sm" value="{{ old('wa_namespace', $announcement->wa_namespace) }}" placeholder="optional">
              </div>
              <div class="col-12">
                <label class="form-label small mb-1">Variables — map each <code>@{{n}}</code> to a field, in order</label>
                <div id="bt-vars" class="d-flex flex-column gap-2">
                  @foreach (old('wa_variables', $announcement->wa_variables ?: ['message']) as $vi => $token)
                    <div class="input-group input-group-sm bt-var">
                      <span class="input-group-text bt-var-num" style="min-width:48px">{{ '{'.'{'.($vi + 1).'}'.'}' }}</span>
                      <select name="wa_variables[]" class="form-select">
                        @foreach ($tokens as $tk => $tl)
                          <option value="{{ $tk }}" @selected($token === $tk)>{{ $tl }}</option>
                        @endforeach
                      </select>
                      <button type="button" class="btn btn-outline-danger bt-remove-var" title="Remove">&times;</button>
                    </div>
                  @endforeach
                </div>
                <button type="button" class="btn btn-sm btn-light-secondary mt-2" id="bt-add-var"><i class="fi fi-rr-plus me-1"></i> Add variable</button>
              </div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Reschedule — Send in <span class="text-muted small">(optional; blank = keep current)</span></label>
            <div class="input-group">
              <input type="number" name="delay_value" id="delay-value" class="form-control" min="1" max="24" value="{{ old('delay_value') }}" placeholder="e.g. 2">
              <select name="delay_unit" id="delay-unit" class="form-select" style="max-width:170px">
                <option value="hours" @selected(old('delay_unit', 'hours') === 'hours')>Hours (max 24)</option>
                <option value="days" @selected(old('delay_unit') === 'days')>Days (max 30)</option>
              </select>
            </div>
            @error('delay_value')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
          </div>

          <button class="btn btn-primary"><i class="fi fi-rr-disk me-1"></i> Save changes</button>

          <x-modal id="broadcastFilters" title="Custom audience filters" size="xl">
            <div class="sas-audience-modal">
              <div class="sas-audience-modal__filters">
                <div class="sas-filter-section__label">Match by appointment</div>
                <p class="sas-audience-modal__hint">Narrows recipients to patients from matching appointments. Leave blank to pick recipients by role instead.</p>
                <div class="mb-3">
                  <label class="form-label">Service</label>
                  <select name="filters[service_id]" class="form-select form-select-sm sas-audience-filter" data-key="service_id">
                    <option value="">Any</option>
                    @foreach ($filterServices as $s)
                      <option value="{{ $s->id }}" @selected(($f['service_id'] ?? null) == $s->id)>{{ $s->name }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="mb-3">
                  <label class="form-label">Provider</label>
                  <select name="filters[provider_id]" class="form-select form-select-sm sas-audience-filter" data-key="provider_id">
                    <option value="">Any</option>
                    @foreach ($filterProviders as $p)
                      <option value="{{ $p->id }}" @selected(($f['provider_id'] ?? null) == $p->id)>{{ $p->name }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="mb-3">
                  <label class="form-label">Appointment status</label>
                  <select name="filters[status]" class="form-select form-select-sm sas-audience-filter" data-key="status">
                    <option value="">Any</option>
                    @foreach (\App\Models\Appointment::STATUSES as $key => $label)
                      <option value="{{ $key }}" @selected(($f['status'] ?? null) === $key)>{{ $label }}</option>
                    @endforeach
                  </select>
                </div>
                @if ($filterClinics->isNotEmpty())
                  <div class="mb-3">
                    <label class="form-label">Clinic</label>
                    <select name="filters[clinic_id]" class="form-select form-select-sm sas-audience-filter" data-key="clinic_id">
                      <option value="">Any</option>
                      @foreach ($filterClinics as $c)
                        <option value="{{ $c->id }}" @selected(($f['clinic_id'] ?? null) == $c->id)>{{ $c->name }}</option>
                      @endforeach
                    </select>
                  </div>
                @endif
                <div class="row g-2 mb-3">
                  <div class="col-6">
                    <label class="form-label">Risk min %</label>
                    <input type="number" name="filters[risk_min]" class="form-control form-control-sm sas-audience-filter" data-key="risk_min" min="0" max="100" value="{{ $f['risk_min'] ?? '' }}">
                  </div>
                  <div class="col-6">
                    <label class="form-label">Risk max %</label>
                    <input type="number" name="filters[risk_max]" class="form-control form-control-sm sas-audience-filter" data-key="risk_max" min="0" max="100" value="{{ $f['risk_max'] ?? '' }}">
                  </div>
                </div>
                <div class="mb-3">
                  <label class="form-label">Exact appointment date</label>
                  <input type="date" name="filters[date]" class="form-control form-control-sm sas-audience-filter" data-key="date" value="{{ $f['date'] ?? '' }}">
                </div>
                <div class="row g-2">
                  <div class="col-6">
                    <label class="form-label">Date from</label>
                    <input type="date" name="filters[date_from]" class="form-control form-control-sm sas-audience-filter" data-key="date_from" value="{{ $f['date_from'] ?? '' }}">
                  </div>
                  <div class="col-6">
                    <label class="form-label">Date to</label>
                    <input type="date" name="filters[date_to]" class="form-control form-control-sm sas-audience-filter" data-key="date_to" value="{{ $f['date_to'] ?? '' }}">
                  </div>
                </div>
              </div>

              <div class="sas-audience-modal__users">
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <div class="sas-filter-section__label mb-0">Recipients</div>
                  <span class="text-muted small" id="userPickerStatus"></span>
                </div>
                @php $oldUserIds = $f['user_ids'] ?? []; @endphp
                @php $roleColors = ['patient' => 'info', 'provider' => 'success', 'billing' => 'warning', 'front_desk' => 'secondary', 'clinic_admin' => 'primary', 'system_admin' => 'dark']; @endphp
                <div class="sas-user-picker">
                  <div class="sas-user-picker__search">
                    <i class="fi fi-rr-search"></i>
                    <input type="text" id="userPickerSearch" placeholder="Search by name or phone…" autocomplete="off">
                  </div>
                  @if ($filterRoleOptions->count() > 1)
                    <div class="sas-user-picker__roles" id="userPickerRoleTabs">
                      <button type="button" class="sas-role-chip active" data-role="all">All</button>
                      @foreach ($filterRoleOptions as $r)
                        <button type="button" class="sas-role-chip" data-role="{{ $r }}">{{ ucwords(str_replace('_', ' ', $r)) }}</button>
                      @endforeach
                    </div>
                  @endif
                  <div class="sas-user-picker__bulk">
                    <input type="checkbox" id="userPickerSelectAll">
                    <label for="userPickerSelectAll">Select all</label>
                    <small>— filtered patients are included automatically; use this to also add/remove others by hand</small>
                  </div>
                  <div class="sas-user-picker__list" id="userPickerList" data-preview-url="{{ route('announcements.previewAudience') }}">
                    @forelse ($filterUsers as $u)
                      @php $uRole = $u->roles->first()?->name; @endphp
                      <label class="sas-user-picker__row" data-role="{{ $uRole }}">
                        <input type="checkbox" name="filters[user_ids][]" value="{{ $u->id }}" @checked(in_array($u->id, $oldUserIds))>
                        <span class="sas-user-picker__avatar">{{ strtoupper(mb_substr($u->name, 0, 1)) }}</span>
                        <span class="sas-user-picker__body">
                          <span class="sas-user-picker__name">{{ $u->name }}</span>
                          @if ($u->phone)<span class="sas-user-picker__meta">{{ $u->phone }}</span>@endif
                        </span>
                        @if ($uRole)
                          <span class="badge bg-{{ $roleColors[$uRole] ?? 'secondary' }}-subtle text-{{ $roleColors[$uRole] ?? 'secondary' }} sas-user-picker__role">{{ ucwords(str_replace('_', ' ', $uRole)) }}</span>
                        @endif
                      </label>
                    @empty
                      <div class="text-muted small text-center py-4">No users found for this audience.</div>
                    @endforelse
                  </div>
                </div>
              </div>
            </div>

            <x-slot:footer>
              <span class="text-muted small me-auto" id="userPickerCount">0 recipients selected</span>
              <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
            </x-slot:footer>
          </x-modal>
        </form>

        <template id="bt-var-tpl">
          <div class="input-group input-group-sm bt-var">
            <span class="input-group-text bt-var-num" style="min-width:48px"></span>
            <select name="wa_variables[]" class="form-select">
              @foreach ($tokens as $tk => $tl)
                <option value="{{ $tk }}">{{ $tl }}</option>
              @endforeach
            </select>
            <button type="button" class="btn btn-outline-danger bt-remove-var" title="Remove">&times;</button>
          </div>
        </template>
      </x-card>
    </div>
  </div>

  <script>
    (function () {
      // Variable add/remove + renumber
      const wrap = document.getElementById('bt-vars');
      const lb = String.fromCharCode(123, 123), rb = String.fromCharCode(125, 125);
      function renumber() {
        wrap.querySelectorAll('.bt-var').forEach(function (row, i) {
          const n = row.querySelector('.bt-var-num');
          if (n) n.textContent = lb + (i + 1) + rb;
        });
      }
      document.getElementById('bt-add-var').addEventListener('click', function () {
        wrap.insertAdjacentHTML('beforeend', document.getElementById('bt-var-tpl').innerHTML);
        renumber();
      });
      wrap.addEventListener('click', function (e) {
        const rm = e.target.closest('.bt-remove-var');
        if (rm) { rm.closest('.bt-var').remove(); renumber(); }
      });

      // "Send in" unit cap
      const unit = document.getElementById('delay-unit');
      const val = document.getElementById('delay-value');
      function syncMax() {
        val.max = unit.value === 'days' ? 30 : 24;
        if (val.value && Number(val.value) > Number(val.max)) val.value = val.max;
      }
      unit.addEventListener('change', syncMax);
      syncMax();

      // Patient picker: live search, selected count, and live re-filtering
      // of the list itself as the appointment-match fields above change.
      const filtersBtn = document.getElementById('configureFiltersBtn');
      const pickerList = document.getElementById('userPickerList');
      if (pickerList) {
        const search = document.getElementById('userPickerSearch');
        const roleTabs = document.getElementById('userPickerRoleTabs');
        const selectAll = document.getElementById('userPickerSelectAll');
        const count = document.getElementById('userPickerCount');
        const status = document.getElementById('userPickerStatus');
        const previewUrl = pickerList.dataset.previewUrl;
        const selected = new Set();
        const roleColors = { patient: 'info', provider: 'success', billing: 'warning', front_desk: 'secondary', clinic_admin: 'primary', system_admin: 'dark' };
        let activeRole = 'all';

        function esc(str) {
          const d = document.createElement('div');
          d.textContent = str == null ? '' : String(str);
          return d.innerHTML;
        }

        function roleLabel(role) {
          return role.replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
        }

        function visibleRows() {
          return Array.from(pickerList.querySelectorAll('.sas-user-picker__row')).filter(function (r) { return r.style.display !== 'none'; });
        }

        function collectSelected() {
          pickerList.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
            if (cb.checked) selected.add(cb.value); else selected.delete(cb.value);
          });
        }

        function updateCount() {
          const n = selected.size;
          if (count) count.textContent = n + ' recipients selected';
          filtersBtn.innerHTML = '<i class="fi fi-rr-settings-sliders me-1"></i> Configure audience filters' + (n ? ' (' + n + ' recipients)' : '');
        }

        function syncSelectAllState() {
          if (!selectAll) return;
          const boxes = visibleRows().map(function (r) { return r.querySelector('input[type="checkbox"]'); });
          const checkedCount = boxes.filter(function (cb) { return cb.checked; }).length;
          selectAll.checked = boxes.length > 0 && checkedCount === boxes.length;
          selectAll.indeterminate = checkedCount > 0 && checkedCount < boxes.length;
        }

        function onSelectionChange() {
          collectSelected();
          updateCount();
          syncSelectAllState();
        }

        function applyFilters() {
          const q = (search && search.value.trim().toLowerCase()) || '';
          pickerList.querySelectorAll('.sas-user-picker__row').forEach(function (r) {
            const name = r.querySelector('.sas-user-picker__name').textContent.toLowerCase();
            const metaEl = r.querySelector('.sas-user-picker__meta');
            const meta = metaEl ? metaEl.textContent.toLowerCase() : '';
            const matchesSearch = !q || name.includes(q) || meta.includes(q);
            const matchesRole = activeRole === 'all' || r.dataset.role === activeRole;
            r.style.display = (matchesSearch && matchesRole) ? '' : 'none';
          });
          syncSelectAllState();
        }

        function renderRows(users) {
          if (!users.length) {
            pickerList.innerHTML = '<div class="text-muted small text-center py-4">No users match these filters.</div>';
            syncSelectAllState();
            return;
          }
          pickerList.innerHTML = users.map(function (u) {
            const initial = esc((u.name || '?').charAt(0).toUpperCase());
            const checked = selected.has(String(u.id)) ? ' checked' : '';
            const meta = u.phone ? '<span class="sas-user-picker__meta">' + esc(u.phone) + '</span>' : '';
            const role = u.role || '';
            const roleColor = roleColors[role] || 'secondary';
            const roleBadge = role ? '<span class="badge bg-' + roleColor + '-subtle text-' + roleColor + ' sas-user-picker__role">' + esc(roleLabel(role)) + '</span>' : '';
            return '<label class="sas-user-picker__row" data-role="' + esc(role) + '">'
              + '<input type="checkbox" name="filters[user_ids][]" value="' + u.id + '"' + checked + '>'
              + '<span class="sas-user-picker__avatar">' + initial + '</span>'
              + '<span class="sas-user-picker__body"><span class="sas-user-picker__name">' + esc(u.name) + '</span>' + meta + '</span>'
              + roleBadge
              + '</label>';
          }).join('');
          pickerList.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
            cb.addEventListener('change', onSelectionChange);
          });
          applyFilters();
        }

        let debounceTimer = null;
        function refreshList() {
          collectSelected();
          const params = new URLSearchParams();
          document.querySelectorAll('.sas-audience-filter').forEach(function (el) {
            if (el.value) params.set(el.dataset.key, el.value);
          });
          const hasFilters = Array.from(params.keys()).length > 0;
          if (status) status.textContent = 'Loading…';
          fetch(previewUrl + '?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
              if (status) status.textContent = '';
              if (!data) return;
              const users = data.users || [];
              // Appointment-matched patients are sent regardless of checkbox
              // state (the server re-resolves them from the same filters at
              // send time) — pre-check them so the list reflects that.
              if (hasFilters) users.forEach(function (u) { selected.add(String(u.id)); });
              renderRows(users);
              updateCount();
            })
            .catch(function () { if (status) status.textContent = 'Could not load users.'; });
        }

        document.querySelectorAll('.sas-audience-filter').forEach(function (el) {
          el.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(refreshList, 350);
          });
        });

        pickerList.addEventListener('change', function (e) {
          if (e.target.matches('input[type="checkbox"]')) onSelectionChange();
        });
        search && search.addEventListener('input', applyFilters);
        if (roleTabs) {
          roleTabs.addEventListener('click', function (e) {
            const btn = e.target.closest('.sas-role-chip');
            if (!btn) return;
            roleTabs.querySelectorAll('.sas-role-chip').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            activeRole = btn.dataset.role;
            applyFilters();
          });
        }
        if (selectAll) {
          selectAll.addEventListener('change', function () {
            visibleRows().forEach(function (r) {
              r.querySelector('input[type="checkbox"]').checked = selectAll.checked;
            });
            onSelectionChange();
          });
        }

        onSelectionChange();

        // Edit form reopened with filters already saved — reflect the
        // matching list (and auto-check) right away instead of waiting
        // for the user to touch a field.
        const hasPrefilledFilters = Array.from(document.querySelectorAll('.sas-audience-filter')).some(function (el) { return el.value; });
        if (hasPrefilledFilters) refreshList();
      }
    })();
  </script>
@endsection
