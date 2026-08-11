@extends('layouts.app')

@section('title', 'Broadcast Messaging')

@php
  // Read-only helper call (no DB/side-effects) — same realistic sample values
  // the backend's own test-send/preview already uses (WhatsappTemplate docblock).
  // Powers the client-side WhatsApp preview below; nothing here is fabricated.
  $waSampleContext = \App\Support\WhatsappTemplate::sampleContext();
@endphp

@push('styles')
  <style>
    .sas-page-toolbar { display: none; }

    .sas-bc-header__icon {
      width: 52px; height: 52px; border-radius: var(--sas-radius-lg); flex-shrink: 0;
      background: var(--sas-primary-50); color: var(--sas-primary-700);
      display: grid; place-items: center; font-size: 1.4rem;
    }
    .sas-bc-header__title { font-size: 2.25rem; font-weight: 800; letter-spacing: -.02em; color: var(--sas-gray-900); }
    .sas-bc-header__subtitle { font-size: var(--sas-fs-base); color: var(--sas-gray-500); }

    {{-- Matches .sas-outline-field__label (the Title field's own label) so
         every label on this form reads at the same weight/case — the
         previous uppercase-tracked treatment only applied to some labels
         and not others, which is what looked inconsistent. --}}
    .sas-bc-label { font-size: var(--sas-fs-sm); font-weight: 700; color: var(--sas-gray-900); margin-bottom: .4rem; display: block; }

    .sas-bc-message { position: relative; }
    .sas-bc-message textarea {
      width: 100%; border: 1px solid var(--sas-gray-200); border-radius: var(--sas-radius-lg); padding: .9rem 1rem;
      font-size: var(--sas-fs-base); resize: vertical; min-height: 160px; transition: border-color .15s var(--sas-ease), box-shadow .15s var(--sas-ease);
    }
    .sas-bc-message textarea:focus { outline: none; border-color: var(--sas-primary-400); box-shadow: 0 0 0 .2rem var(--sas-primary-100); }
    .sas-bc-message__count { text-align: right; font-size: var(--sas-fs-xs); color: var(--sas-gray-400); margin-top: .35rem; }
    .sas-bc-message__count.is-over { color: var(--sas-danger); font-weight: 700; }

    .sas-bc-channel { display: flex; align-items: center; gap: .55rem; border: 1px solid var(--sas-gray-200); border-radius: var(--sas-radius-md); padding: .6rem .85rem; flex: 1; cursor: pointer; transition: border-color .15s var(--sas-ease); }
    .sas-bc-channel:hover { border-color: var(--sas-gray-300); }
    .sas-bc-channel.is-selected { border-color: var(--sas-primary-400); }
    .sas-bc-channel input { flex-shrink: 0; }
    .sas-bc-channel i { color: var(--sas-gray-500); }
    .sas-bc-channel.is-selected i { color: var(--sas-primary-600); }
    .sas-bc-channel span { font-weight: 600; font-size: var(--sas-fs-sm); color: var(--sas-gray-800); }

    .sas-bc-info { display: flex; gap: .65rem; background: var(--sas-primary-50); border: 1px solid var(--sas-primary-100); border-radius: var(--sas-radius-lg); padding: .9rem 1rem; font-size: var(--sas-fs-xs); color: var(--sas-primary-800); }
    .sas-bc-info i { color: var(--sas-primary-600); flex-shrink: 0; margin-top: .1rem; }

    {{-- Each row: a fixed-size {{n}} chip + a proper Bootstrap select that's
         allowed to actually shrink (min-width:0 — without it, a flex child
         won't shrink below its content's natural width, so a long option
         label like "Appointment status (e.g. Confirmed)" was pushing the
         select past its column and the remove button along with it). --}}
    .sas-bc-var { display: flex; align-items: center; gap: .5rem; }
    .sas-bc-var__num { background: var(--sas-primary-50); color: var(--sas-primary-700); font-weight: 700; font-size: var(--sas-fs-xs); border-radius: var(--sas-radius-md); padding: .35rem .55rem; flex-shrink: 0; }
    .sas-bc-var select { flex: 1 1 auto; min-width: 0; }
    .sas-bc-var button { border: 1px solid var(--sas-gray-200); background: #fff; border-radius: var(--sas-radius-sm); width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; color: var(--sas-gray-400); flex-shrink: 0; }
    .sas-bc-var button:hover { color: var(--sas-danger); border-color: var(--sas-danger-subtle); background: var(--sas-danger-subtle); }

    .sas-bc-reference { background: var(--sas-gray-25); border: 1px solid var(--sas-gray-100); border-radius: var(--sas-radius-lg); padding: 1.1rem 1.2rem; }
    .sas-bc-reference__item { font-size: var(--sas-fs-sm); color: var(--sas-gray-700); padding: .55rem 0; }
    .sas-bc-reference__item + .sas-bc-reference__item { border-top: 1px solid var(--sas-gray-100); }

    #announcementsTable_wrapper > .row:first-child { padding: var(--sas-space-3) var(--sas-space-5); margin: 0; align-items: center; border-bottom: 1px solid var(--sas-gray-100); }
    #announcementsTable_wrapper > .row:last-child { padding: var(--sas-space-4) var(--sas-space-5); margin: 0; align-items: center; }
    #announcementsTable .btn-icon-square { width: 34px; height: 34px; border-radius: var(--sas-radius-md); border: 1px solid var(--sas-gray-200); background: #fff; }

    /* WhatsApp preview bubble */
    .sas-wa-preview { background: #E5DDD5; border-radius: var(--sas-radius-lg); padding: 1.5rem 1rem; }
    .sas-wa-preview__bubble { background: #fff; border-radius: var(--sas-radius-md); padding: .7rem .9rem; max-width: 320px; margin: 0 auto; box-shadow: 0 1px 2px rgba(0,0,0,.1); font-size: var(--sas-fs-sm); white-space: pre-wrap; }

    /* Existing audience-filter modal styling (unchanged) */
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
  <div class="d-flex align-items-start gap-3 mb-4">
    <span class="sas-bc-header__icon"><i class="fi fi-rr-megaphone" aria-hidden="true"></i></span>
    <div>
      <h1 class="sas-bc-header__title mb-1">Broadcast Messaging</h1>
      <p class="sas-bc-header__subtitle mb-0">Send announcements to patients via email and WhatsApp.</p>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-xl-7">
      <x-card>
        <x-slot:title>Send an announcement</x-slot:title>
        <form method="POST" action="{{ route('announcements.store') }}" id="broadcastForm">
          @csrf
          <div class="row g-3 mb-3">
            <div class="col-md-7">
              <x-form-field name="title" label="Title" :value="old('title')" :required="true" placeholder="Enter announcement title" />
            </div>
            <div class="col-md-5">
              <label class="sas-bc-label" for="audienceSelect">Audience</label>
              <select name="audience" id="audienceSelect" class="form-select">
                @foreach ($audiences as $key => $label)
                  <option value="{{ $key }}" @selected(old('audience', 'patients') === $key)>{{ $label }}</option>
                @endforeach
              </select>
            </div>
          </div>

          <div class="mb-3" id="configureFiltersWrap">
            <button type="button" id="configureFiltersBtn" class="btn btn-outline-secondary w-100 text-start" data-bs-toggle="modal" data-bs-target="#broadcastFilters">
              <i class="fi fi-rr-settings-sliders me-1"></i> Configure audience filters
            </button>
          </div>

          <div class="mb-3">
            <label class="sas-bc-label" for="body">Message: <span class="text-danger">*</span></label>
            <div class="sas-bc-message">
              <textarea name="body" id="body" required maxlength="5000" placeholder="Type your announcement message here…">{{ old('body') }}</textarea>
            </div>
            @error('body')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            <div class="sas-bc-message__count" id="bodyCount">0 / 5000 characters</div>
          </div>

          @php $oldChannels = old('channels', ['email']); @endphp
          <div class="mb-3">
            <label class="sas-bc-label">Channel</label>
            <div class="d-flex gap-3">
              <label class="sas-bc-channel" id="channelEmailWrap">
                <input type="checkbox" class="form-check-input m-0" id="ch-email" name="channels[]" value="email" @checked(in_array('email', $oldChannels))>
                <i class="fi fi-rr-envelope" aria-hidden="true"></i><span>Email</span>
              </label>
              <label class="sas-bc-channel" id="channelWaWrap">
                <input type="checkbox" class="form-check-input m-0" id="ch-wa" name="channels[]" value="whatsapp" @checked(in_array('whatsapp', $oldChannels))>
                <i class="fi fi-brands-whatsapp" aria-hidden="true"></i><span>WhatsApp</span>
              </label>
            </div>
            @error('channels')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
          </div>

          <div class="mb-3">
            <label class="sas-bc-label">Send in <span class="text-muted fw-normal">(optional — blank = send now)</span></label>
            <div class="input-group">
              <input type="number" name="delay_value" id="delay-value" class="form-control" min="1" max="24" value="{{ old('delay_value') }}" placeholder="0">
              <select name="delay_unit" id="delay-unit" class="form-select" style="max-width:170px">
                <option value="hours" @selected(old('delay_unit', 'hours') === 'hours')>Hours (max 24)</option>
                <option value="days" @selected(old('delay_unit') === 'days')>Days (max 30)</option>
              </select>
            </div>
            @error('delay_value')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
          </div>

          <div class="sas-bc-info mb-3">
            <i class="fi fi-rr-info" aria-hidden="true"></i>
            <div>Pick one or both channels. Each broadcast carries its own WhatsApp template.<br>Leave <strong>Send in</strong> blank to send now (max 24 hours / 30 days ahead).</div>
          </div>

          <div class="d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-light" id="broadcastPreviewBtn"><i class="fi fi-rr-eye me-1" aria-hidden="true"></i> Preview</button>
            <button type="submit" class="btn btn-primary"><i class="fi fi-rr-paper-plane me-1" aria-hidden="true"></i> Send Announcement</button>
          </div>

          {{-- WhatsApp template fields live visually in the right-hand panel
               but must stay inside this same <form> to submit together. --}}
          <div id="waTemplateFieldsSlot"></div>

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
                      <option value="{{ $s->id }}" @selected(old('filters.service_id') == $s->id)>{{ $s->name }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="mb-3">
                  <label class="form-label">Provider</label>
                  <select name="filters[provider_id]" class="form-select form-select-sm sas-audience-filter" data-key="provider_id">
                    <option value="">Any</option>
                    @foreach ($filterProviders as $p)
                      <option value="{{ $p->id }}" @selected(old('filters.provider_id') == $p->id)>{{ $p->name }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="mb-3">
                  <label class="form-label">Appointment status</label>
                  <select name="filters[status]" class="form-select form-select-sm sas-audience-filter" data-key="status">
                    <option value="">Any</option>
                    @foreach (\App\Models\Appointment::STATUSES as $key => $label)
                      <option value="{{ $key }}" @selected(old('filters.status') === $key)>{{ $label }}</option>
                    @endforeach
                  </select>
                </div>
                @if ($filterClinics->isNotEmpty())
                  <div class="mb-3">
                    <label class="form-label">Clinic</label>
                    <select name="filters[clinic_id]" class="form-select form-select-sm sas-audience-filter" data-key="clinic_id">
                      <option value="">Any</option>
                      @foreach ($filterClinics as $c)
                        <option value="{{ $c->id }}" @selected(old('filters.clinic_id') == $c->id)>{{ $c->name }}</option>
                      @endforeach
                    </select>
                  </div>
                @endif
                <div class="row g-2 mb-3">
                  <div class="col-6">
                    <x-outline-field name="filters[risk_min]" id="filterRiskMin" type="number" label="Risk min %" class="sas-audience-filter" data-key="risk_min" min="0" max="100" value="{{ old('filters.risk_min') }}" />
                  </div>
                  <div class="col-6">
                    <x-outline-field name="filters[risk_max]" id="filterRiskMax" type="number" label="Risk max %" class="sas-audience-filter" data-key="risk_max" min="0" max="100" value="{{ old('filters.risk_max') }}" />
                  </div>
                </div>
                <div class="mb-3">
                  <x-outline-field name="filters[date]" id="filterDate" type="date" label="Exact appointment date" class="sas-audience-filter" data-key="date" value="{{ old('filters.date') }}" />
                </div>
                <div class="row g-2">
                  <div class="col-6">
                    <x-outline-field name="filters[date_from]" id="filterDateFrom" type="date" label="Date from" class="sas-audience-filter" data-key="date_from" value="{{ old('filters.date_from') }}" />
                  </div>
                  <div class="col-6">
                    <x-outline-field name="filters[date_to]" id="filterDateTo" type="date" label="Date to" class="sas-audience-filter" data-key="date_to" value="{{ old('filters.date_to') }}" />
                  </div>
                </div>
              </div>

              <div class="sas-audience-modal__users">
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <div class="sas-filter-section__label mb-0">Recipients</div>
                  <span class="text-muted small" id="userPickerStatus"></span>
                </div>
                @php $oldUserIds = old('filters.user_ids', []); @endphp
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

          {{-- Live preview modal — client-side only, uses the real sample
               token values the backend's own test-send/preview relies on. --}}
          <x-modal id="broadcastPreview" title="Preview" size="lg">
            <ul class="nav nav-tabs mb-3" role="tablist">
              <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#previewEmail" type="button">Email</button></li>
              <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#previewWa" type="button">WhatsApp</button></li>
            </ul>
            <div class="tab-content">
              <div class="tab-pane fade show active" id="previewEmail">
                <div class="border rounded-3 p-3">
                  <div class="fw-bold mb-2" id="previewEmailTitle">—</div>
                  <div class="text-muted" id="previewEmailBody" style="white-space:pre-wrap">—</div>
                </div>
              </div>
              <div class="tab-pane fade" id="previewWa">
                <div class="sas-wa-preview">
                  <div class="sas-wa-preview__bubble" id="previewWaBubble">—</div>
                </div>
                <p class="text-muted small mt-2 mb-0">Shown with sample values (e.g. "{{ $waSampleContext['patient_name'] }}") in place of each <code>&#123;&#123;n&#125;&#125;</code> — real messages substitute the actual recipient and appointment details.</p>
              </div>
            </div>
          </x-modal>
        </form>
      </x-card>
    </div>

    <div class="col-xl-5">
      <x-card>
        <x-slot:title>WhatsApp template <span class="fw-normal text-muted small">(required if WhatsApp is selected)</span></x-slot:title>
        <div class="row g-3 mb-3">
          <div class="col-md-7">
            <x-form-field form="broadcastForm" name="wa_template_id" label="Gupshup Template ID" :value="old('wa_template_id')" placeholder="Enter Gupshup Template ID" />
          </div>
          <div class="col-md-5">
            <x-outline-field form="broadcastForm" name="wa_namespace" label="Namespace (optional)" value="{{ old('wa_namespace') }}" placeholder="Enter namespace (optional)" />
          </div>
        </div>

        <label class="sas-bc-label">Variables — map each <code>&#123;&#123;n&#125;&#125;</code> to a field, in order</label>
        <div class="row g-3">
          <div class="col-md-7">
            <div id="bt-vars" class="d-flex flex-column gap-2 mb-2">
              @foreach (old('wa_variables', $defaultVariables) as $vi => $token)
                <div class="sas-bc-var bt-var">
                  <span class="sas-bc-var__num bt-var-num">{{ '{'.'{'.($vi + 1).'}'.'}' }}</span>
                  <select form="broadcastForm" name="wa_variables[]" class="form-select form-select-sm">
                    @foreach ($tokens as $tk => $tl)
                      <option value="{{ $tk }}" @selected($token === $tk)>{{ $tl }}</option>
                    @endforeach
                  </select>
                  <button type="button" class="bt-remove-var" title="Remove" aria-label="Remove variable"><i class="fi fi-rr-cross" aria-hidden="true"></i></button>
                </div>
              @endforeach
            </div>
            <button type="button" class="btn btn-sm btn-light-secondary" id="bt-add-var"><i class="fi fi-rr-plus me-1" aria-hidden="true"></i> Add variable</button>
          </div>
          <div class="col-md-5">
            <div class="sas-bc-reference">
              @foreach ($tokens as $tk => $tl)
                <div class="sas-bc-reference__item">{{ $tl }}</div>
              @endforeach
            </div>
          </div>
        </div>
      </x-card>
    </div>
  </div>

  <x-card bodyClass="p-0" class="mt-3">
    <x-slot:title>Sent announcements</x-slot:title>
    <div class="table-responsive">
      <table id="announcementsTable" class="table align-middle mb-0 datatable">
        <thead class="table-light"><tr><th>When</th><th>Title</th><th>Audience</th><th>Channel</th><th>Recipients</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
          @forelse ($announcements as $a)
            <tr>
              <td>
                @if ($a->status === 'scheduled')
                  <x-badge-status color="warning" label="Scheduled" icon="fi-rr-clock" /><br>
                  <small class="text-muted">{{ $a->send_at?->format('M j, Y g:i A') }}</small>
                @else
                  {{ $a->sent_at?->format('M j, Y g:i A') ?? '—' }}
                @endif
              </td>
              <td>{{ $a->title }}</td>
              <td><small>{{ $a->audience_label }}</small></td>
              <td>{{ $a->channel_label }}</td>
              <td>{{ $a->status === 'scheduled' ? '—' : $a->recipients_count }}</td>
              <td class="text-end text-nowrap">
                <a href="{{ route('announcements.edit', $a) }}" class="btn btn-sm btn-icon-square" title="Edit" aria-label="Edit"><i class="fi fi-rr-edit"></i></a>
                <form method="POST" action="{{ route('announcements.destroy', $a) }}" class="d-inline"
                      data-sas-confirm="{{ $a->status === 'scheduled' ? 'Cancel this scheduled announcement?' : 'Delete this announcement?' }}"
                      data-sas-confirm-label="{{ $a->status === 'scheduled' ? 'Cancel' : 'Delete' }}">
                  @csrf @method('DELETE')
                  <button class="btn btn-sm btn-icon-square" title="{{ $a->status === 'scheduled' ? 'Cancel' : 'Delete' }}" aria-label="{{ $a->status === 'scheduled' ? 'Cancel' : 'Delete' }}"><i class="fi fi-rr-trash text-danger"></i></button>
                </form>
              </td>
            </tr>
          @empty
            <x-empty-state colspan="6" icon="fi-rr-megaphone" title="No announcements sent yet" description="Your sent announcements will appear here." />
          @endforelse
        </tbody>
      </table>
    </div>
  </x-card>

  <template id="bt-var-tpl">
    <div class="sas-bc-var bt-var">
      <span class="sas-bc-var__num bt-var-num"></span>
      <select form="broadcastForm" name="wa_variables[]" class="form-select form-select-sm">
        @foreach ($tokens as $tk => $tl)
          <option value="{{ $tk }}">{{ $tl }}</option>
        @endforeach
      </select>
      <button type="button" class="bt-remove-var" title="Remove" aria-label="Remove variable"><i class="fi fi-rr-cross" aria-hidden="true"></i></button>
    </div>
  </template>

  <script>
    (function () {
      const wrap = document.getElementById('bt-vars');
      if (!wrap) return;
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

      // "Send in" unit cap: 24 for hours, 30 for days.
      const unit = document.getElementById('delay-unit');
      const val = document.getElementById('delay-value');
      function syncMax() {
        val.max = unit.value === 'days' ? 30 : 24;
        if (val.value && Number(val.value) > Number(val.max)) val.value = val.max;
      }
      unit.addEventListener('change', syncMax);
      syncMax();

      // Audience select: the granular "Configure audience filters" picker only
      // applies when audience === 'custom' (matches AnnouncementController::store,
      // which ignores `filters` for every other audience value).
      const audienceSelect = document.getElementById('audienceSelect');
      const filtersWrap = document.getElementById('configureFiltersWrap');
      function syncAudienceUI() {
        filtersWrap.style.display = audienceSelect.value === 'custom' ? '' : 'none';
      }
      audienceSelect.addEventListener('change', syncAudienceUI);
      syncAudienceUI();

      // Channel toggle-cards (real checkboxes underneath — just a nicer hit target).
      ['channelEmailWrap', 'channelWaWrap'].forEach(function (id) {
        const label = document.getElementById(id);
        const input = label.querySelector('input');
        function sync() { label.classList.toggle('is-selected', input.checked); }
        input.addEventListener('change', sync);
        sync();
      });

      // Live character counter — 5000 is the real server-side limit
      // (AnnouncementController::validateAnnouncement), not a made-up cap.
      const body = document.getElementById('body');
      const count = document.getElementById('bodyCount');
      function syncCount() {
        const n = body.value.length;
        count.textContent = n + ' / 5000 characters';
        count.classList.toggle('is-over', n > 5000);
      }
      body.addEventListener('input', syncCount);
      syncCount();

      // Preview — entirely client-side, using the real WhatsappTemplate sample
      // context the backend's own test-send already relies on.
      const sample = @json($waSampleContext);
      document.getElementById('broadcastPreviewBtn').addEventListener('click', function () {
        const title = document.getElementById('title')?.value || '(untitled announcement)';
        const message = body.value || '(no message yet)';
        document.getElementById('previewEmailTitle').textContent = title;
        document.getElementById('previewEmailBody').textContent = message;

        let waText = message;
        wrap.querySelectorAll('.bt-var').forEach(function (row, i) {
          const token = row.querySelector('select').value;
          const value = token === 'message' ? message : (sample[token] || '');
          waText = waText.split(lb + (i + 1) + rb).join(value);
        });
        document.getElementById('previewWaBubble').textContent = waText;

        if (window.bootstrap && bootstrap.Modal) {
          bootstrap.Modal.getOrCreateInstance(document.getElementById('broadcastPreview')).show();
        }
      });

      // Patient picker: live search, selected count, and live re-filtering
      // of the list itself as the appointment-match fields above change.
      const filtersBtn = document.getElementById('configureFiltersBtn');
      const pickerList = document.getElementById('userPickerList');
      if (pickerList) {
        const search = document.getElementById('userPickerSearch');
        const roleTabs = document.getElementById('userPickerRoleTabs');
        const selectAll = document.getElementById('userPickerSelectAll');
        const pickerCount = document.getElementById('userPickerCount');
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
          if (pickerCount) pickerCount.textContent = n + ' recipients selected';
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
          const ids = new Set(users.map(function (u) { return String(u.id); }));
          Array.from(selected).forEach(function (id) { if (!ids.has(id)) selected.delete(id); });

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

        const hasPrefilledFilters = Array.from(document.querySelectorAll('.sas-audience-filter')).some(function (el) { return el.value; });
        if (hasPrefilledFilters) refreshList();
      }
    })();
  </script>
@endsection
