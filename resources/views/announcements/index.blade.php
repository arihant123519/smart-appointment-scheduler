@extends('layouts.app')

@section('title', 'Broadcast Messaging')

@section('content')
  <div class="row g-3">
    <div class="col-xl-5">
      <x-card>
        <x-slot:title>Send an announcement</x-slot:title>
        <form method="POST" action="{{ route('announcements.store') }}">
          @csrf
          <x-form-field name="title" label="Title" :value="old('title')" :required="true" />
          <div class="mb-3 mt-3"><label class="form-label">Message</label><textarea name="body" class="form-control @error('body') is-invalid @enderror" rows="4" required>{{ old('body') }}</textarea>
            @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="mb-3"><label class="form-label">Audience</label>
            <div class="input-group">
              <select name="audience" id="audienceSelect" class="form-select">
                @foreach ($audiences as $key => $label)
                  <option value="{{ $key }}" @selected(old('audience') === $key)>{{ $label }}</option>
                @endforeach
              </select>
              <button type="button" id="configureFiltersBtn" class="btn btn-outline-secondary d-none" data-bs-toggle="modal" data-bs-target="#broadcastFilters">
                <i class="fi fi-rr-settings-sliders me-1"></i> Configure filters
              </button>
            </div>
          </div>
          @php $oldChannels = old('channels', ['email']); @endphp
          <div class="mb-3">
            <label class="form-label">Channel</label>
            <div class="d-flex gap-4">
              <div class="form-check">
                <input type="checkbox" class="form-check-input" id="ch-email" name="channels[]" value="email" @checked(in_array('email', $oldChannels))>
                <label class="form-check-label" for="ch-email"><i class="fi fi-rr-envelope me-1"></i> Email</label>
              </div>
              <div class="form-check">
                <input type="checkbox" class="form-check-input" id="ch-wa" name="channels[]" value="whatsapp" @checked(in_array('whatsapp', $oldChannels))>
                <label class="form-check-label" for="ch-wa"><i class="fi fi-brands-whatsapp me-1"></i> WhatsApp</label>
              </div>
            </div>
            @error('channels')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
          </div>
          {{-- WhatsApp template — entered with the announcement (used when WhatsApp is a channel) --}}
          <div class="border rounded-3 p-3 mb-3 bg-light">
            <div class="d-flex align-items-center gap-2 mb-2">
              <i class="fi fi-brands-whatsapp"></i><strong class="small">WhatsApp template</strong>
              <span class="text-muted small">(required if WhatsApp is selected)</span>
            </div>
            <div class="row g-2">
              <div class="col-md-7">
                <x-form-field name="wa_template_id" label="Gupshup Template ID" :value="old('wa_template_id')" class="form-control-sm" placeholder="e.g. 3515c95f-f515-45c1-8b0c-04141e8d858d" />
              </div>
              <div class="col-md-5">
                <label class="form-label small">Namespace <span class="text-muted">(optional)</span></label>
                <input type="text" name="wa_namespace" class="form-control form-control-sm" value="{{ old('wa_namespace') }}" placeholder="optional">
              </div>
              <div class="col-12">
                <label class="form-label small mb-1">Variables — map each <code>@{{n}}</code> to a field, in order</label>
                <div id="bt-vars" class="d-flex flex-column gap-2">
                  @foreach (old('wa_variables', $defaultVariables) as $vi => $token)
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
            <label class="form-label">Send in <span class="text-muted small">(optional — blank = send now)</span></label>
            <div class="input-group">
              <input type="number" name="delay_value" id="delay-value" class="form-control" min="1" max="24" value="{{ old('delay_value') }}" placeholder="e.g. 2">
              <select name="delay_unit" id="delay-unit" class="form-select" style="max-width:170px">
                <option value="hours" @selected(old('delay_unit', 'hours') === 'hours')>Hours (max 24)</option>
                <option value="days" @selected(old('delay_unit') === 'days')>Days (max 30)</option>
              </select>
            </div>
            @error('delay_value')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
          </div>
          <button class="btn btn-primary w-100"><i class="fi fi-rr-paper-plane me-1"></i> Send / schedule announcement</button>
          <small class="text-muted d-block mt-2">Pick one or both channels. Each broadcast carries its own WhatsApp template. Leave <strong>Send in</strong> blank to send now (max 24 hours / 30 days ahead).</small>

          <x-modal id="broadcastFilters" title="Custom audience filters">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label small">Service</label>
                <select name="filters[service_id]" class="form-select">
                  <option value="">Any</option>
                  @foreach ($filterServices as $s)
                    <option value="{{ $s->id }}" @selected(old('filters.service_id') == $s->id)>{{ $s->name }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label small">Provider</label>
                <select name="filters[provider_id]" class="form-select">
                  <option value="">Any</option>
                  @foreach ($filterProviders as $p)
                    <option value="{{ $p->id }}" @selected(old('filters.provider_id') == $p->id)>{{ $p->name }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label small">Appointment status</label>
                <select name="filters[status]" class="form-select">
                  <option value="">Any</option>
                  @foreach (\App\Models\Appointment::STATUSES as $key => $label)
                    <option value="{{ $key }}" @selected(old('filters.status') === $key)>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              @if ($filterClinics->isNotEmpty())
                <div class="col-md-6">
                  <label class="form-label small">Clinic</label>
                  <select name="filters[clinic_id]" class="form-select">
                    <option value="">Any</option>
                    @foreach ($filterClinics as $c)
                      <option value="{{ $c->id }}" @selected(old('filters.clinic_id') == $c->id)>{{ $c->name }}</option>
                    @endforeach
                  </select>
                </div>
              @endif
              <div class="col-md-3">
                <label class="form-label small">No-show risk min %</label>
                <input type="number" name="filters[risk_min]" class="form-control" min="0" max="100" value="{{ old('filters.risk_min') }}">
              </div>
              <div class="col-md-3">
                <label class="form-label small">No-show risk max %</label>
                <input type="number" name="filters[risk_max]" class="form-control" min="0" max="100" value="{{ old('filters.risk_max') }}">
              </div>
              <div class="col-md-6">
                <label class="form-label small">Exact appointment date</label>
                <input type="date" name="filters[date]" class="form-control" value="{{ old('filters.date') }}">
              </div>
              <div class="col-md-3">
                <label class="form-label small">Appointment date from</label>
                <input type="date" name="filters[date_from]" class="form-control" value="{{ old('filters.date_from') }}">
              </div>
              <div class="col-md-3">
                <label class="form-label small">Appointment date to</label>
                <input type="date" name="filters[date_to]" class="form-control" value="{{ old('filters.date_to') }}">
              </div>
              <div class="col-12">
                <label class="form-label small">Or select specific patients</label>
                <select name="filters[user_ids][]" class="form-select" multiple size="6">
                  @foreach ($filterUsers as $u)
                    <option value="{{ $u->id }}" @selected(in_array($u->id, old('filters.user_ids', [])))>{{ $u->name }}</option>
                  @endforeach
                </select>
                <small class="text-muted">Selected patients are added to the audience in addition to whoever matches the filters above.</small>
              </div>
            </div>
            <x-slot:footer>
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

            // Show the "Configure filters" button only for the custom audience.
            const audienceSelect = document.getElementById('audienceSelect');
            const filtersBtn = document.getElementById('configureFiltersBtn');
            function syncFiltersBtn() {
              filtersBtn.classList.toggle('d-none', audienceSelect.value !== 'custom');
            }
            audienceSelect.addEventListener('change', syncFiltersBtn);
            syncFiltersBtn();
          })();
        </script>
      </x-card>
    </div>
    <div class="col-xl-7">
      <x-card bodyClass="p-0">
        <x-slot:title>Sent announcements</x-slot:title>
        <table class="table align-middle mb-0 datatable">
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
                  <a href="{{ route('announcements.edit', $a) }}" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="fi fi-rr-edit"></i></a>
                  <form method="POST" action="{{ route('announcements.destroy', $a) }}" class="d-inline"
                        data-sas-confirm="{{ $a->status === 'scheduled' ? 'Cancel this scheduled announcement?' : 'Delete this announcement?' }}"
                        data-sas-confirm-label="{{ $a->status === 'scheduled' ? 'Cancel' : 'Delete' }}">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger" title="{{ $a->status === 'scheduled' ? 'Cancel' : 'Delete' }}"><i class="fi fi-rr-trash"></i></button>
                  </form>
                </td>
              </tr>
            @empty
              <x-empty-state colspan="6" icon="fi-rr-megaphone" title="No announcements sent yet" />
            @endforelse
          </tbody>
        </table>
      </x-card>
    </div>
  </div>
@endsection
