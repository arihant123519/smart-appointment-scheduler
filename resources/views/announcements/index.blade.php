@extends('layouts.app')

@section('title', 'Broadcast Messaging')

@section('content')
  <div class="row g-3">
    <div class="col-xl-5">
      <div class="card">
        <div class="card-header"><h6 class="mb-0">Send an announcement</h6></div>
        <div class="card-body">
          <form method="POST" action="{{ route('announcements.store') }}">
            @csrf
            <div class="mb-3"><label class="form-label">Title</label><input type="text" name="title" class="form-control" value="{{ old('title') }}" required></div>
            <div class="mb-3"><label class="form-label">Message</label><textarea name="body" class="form-control" rows="4" required>{{ old('body') }}</textarea></div>
            <div class="mb-3"><label class="form-label">Audience</label>
              <select name="audience" class="form-select">
                @foreach ($audiences as $key => $label)
                  <option value="{{ $key }}" @selected(old('audience') === $key)>{{ $label }}</option>
                @endforeach
              </select>
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
            <div class="border rounded p-3 mb-3 bg-light">
              <div class="d-flex align-items-center gap-2 mb-2">
                <i class="fi fi-brands-whatsapp"></i><strong class="small">WhatsApp template</strong>
                <span class="text-muted small">(required if WhatsApp is selected)</span>
              </div>
              <div class="row g-2">
                <div class="col-md-7">
                  <label class="form-label small">Gupshup Template ID</label>
                  <input type="text" name="wa_template_id" class="form-control form-control-sm" value="{{ old('wa_template_id') }}" placeholder="e.g. 3515c95f-f515-45c1-8b0c-04141e8d858d">
                  @error('wa_template_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
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
                  <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="bt-add-var"><i class="fi fi-rr-plus me-1"></i> Add variable</button>
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
            })();
          </script>
        </div>
      </div>
    </div>
    <div class="col-xl-7">
      <div class="card"><div class="card-header"><h6 class="mb-0">Sent announcements</h6></div>
        <div class="card-body p-0">
          <table class="table align-middle mb-0">
            <thead class="table-light"><tr><th>When</th><th>Title</th><th>Audience</th><th>Channel</th><th>Recipients</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
              @forelse ($announcements as $a)
                <tr>
                  <td>
                    @if ($a->status === 'scheduled')
                      <span class="badge bg-warning text-dark">Scheduled</span><br>
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
                          onsubmit="return confirm('{{ $a->status === 'scheduled' ? 'Cancel this scheduled announcement?' : 'Delete this announcement?' }}');">
                      @csrf @method('DELETE')
                      <button class="btn btn-sm btn-outline-danger" title="{{ $a->status === 'scheduled' ? 'Cancel' : 'Delete' }}"><i class="fi fi-rr-trash"></i></button>
                    </form>
                  </td>
                </tr>
              @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No announcements sent yet.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
@endsection
