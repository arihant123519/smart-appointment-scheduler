@extends('layouts.app')

@section('title', 'Edit Announcement')

@section('page_actions')
  <a href="{{ route('announcements.index') }}" class="btn btn-light"><i class="fi fi-rr-arrow-left me-1"></i> Back</a>
@endsection

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
          <div class="mb-3"><label class="form-label">Audience</label>
            <select name="audience" class="form-select">
              @foreach ($audiences as $key => $label)
                <option value="{{ $key }}" @selected(old('audience', $announcement->audience) === $key)>{{ $label }}</option>
              @endforeach
            </select>
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
    })();
  </script>
@endsection
