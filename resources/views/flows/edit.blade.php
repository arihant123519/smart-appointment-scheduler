@extends('layouts.app')

@section('title', $flow->exists ? 'Edit Flow' : 'New Flow')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/libs/drawflow/drawflow.min.css') }}">
  <style>
    .flow-builder { display: flex; flex-direction: column; gap: 1rem; }
    .flow-canvas-wrap { display: flex; gap: 1rem; align-items: stretch; }
    .flow-palette { width: 196px; flex-shrink: 0; display: flex; flex-direction: column; gap: .5rem; }
    .flow-palette button { text-align: left; }
    .flow-stage { flex: 1 1 auto; min-width: 0; position: relative; }

    #drawflow {
      width: 100%; height: 600px; background: #eef1f5;
      border: 1px solid #dde3ea; border-radius: .6rem;
      background-image: radial-gradient(#c7d0da 1.4px, transparent 1.4px);
      background-size: 24px 24px;
    }

    .flow-zoom-controls {
      position: absolute; right: 14px; bottom: 14px; z-index: 5;
      display: flex; flex-direction: column; gap: 3px;
      background: #fff; border: 1px solid #dde3ea; border-radius: .5rem; padding: 4px;
      box-shadow: 0 2px 8px rgba(20,30,40,.1);
    }
    .flow-zoom-controls button {
      width: 30px; height: 30px; border: 0; background: transparent; border-radius: .35rem; color: #45525e;
    }
    .flow-zoom-controls button:hover { background: #f1f4f7; color: #0d6efd; }

    .flow-side-panel { width: 330px; flex-shrink: 0; }

    .flow-node-card {
      border-radius: 10px; overflow: hidden; min-width: 220px; max-width: 240px;
      box-shadow: 0 1px 2px rgba(20,30,40,.08), 0 4px 14px rgba(20,30,40,.06);
      border: 1px solid rgba(0,0,0,.06);
      transition: box-shadow .15s ease;
    }
    .drawflow-node:hover .flow-node-card { box-shadow: 0 2px 6px rgba(20,30,40,.14), 0 10px 22px rgba(20,30,40,.1); }
    .drawflow-node.selected .flow-node-card { outline: 2px solid #0d6efd; outline-offset: 1px; }

    .flow-node-card .fnc-head { padding: .5rem .65rem; display: flex; align-items: center; gap: .45rem; color: #fff; }
    .flow-node-card .fnc-head .fi { font-size: .85rem; opacity: .9; flex-shrink: 0; }
    .flow-node-card .fnc-titlewrap { min-width: 0; flex: 1 1 auto; }
    .flow-node-card .fnc-title { font-size: .8rem; font-weight: 700; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .flow-node-card .fnc-diamond { flex-shrink: 0; font-size: .6rem; opacity: .8; }
    .flow-node-card .fnc-body {
      padding: .5rem .65rem; font-size: .72rem; color: #4a5561; background: #fff;
      min-height: 2.4em; line-height: 1.35; overflow-wrap: anywhere;
    }
    .flow-node-card .fnc-body.fnc-empty { color: #9aa4ae; font-style: italic; }
    .flow-node-card .fnc-body .fnc-tpl-text {
      margin-top: .35rem; padding-top: .35rem; border-top: 1px dashed rgba(0,0,0,.14); color: #2c333a;
    }
    .flow-node-card .fnc-ports {
      padding: .1rem .65rem .55rem; font-size: .68rem; color: #4a5561; background: #fff;
      display: flex; flex-direction: column; align-items: flex-end; gap: 2px;
    }
    .flow-node-card .fnc-meta { padding: 0 .65rem .5rem; font-size: .68rem; color: #6c7680; background: #fff; }

    /* Editable-in-place bodies: click straight into the node and type — no
       need to open the side panel just to set the main message/question/text.
       The `:empty::before` placeholder disappears the instant you type. */
    .flow-node-card .fnc-body[contenteditable="true"] { cursor: text; outline: none; }
    .flow-node-card .fnc-body[contenteditable="true"]:hover { background: #fbfcfe; }
    .flow-node-card .fnc-body[contenteditable="true"]:focus { background: #fffdf3; }
    .flow-node-card .fnc-body[contenteditable="true"]:empty::before {
      content: attr(data-placeholder); color: #9aa4ae; font-style: italic;
    }

    .fnc-send_message .fnc-head { background: linear-gradient(135deg, #0d6efd, #4a92ff); }
    .fnc-branch_yes_no .fnc-head { background: linear-gradient(135deg, #6f42c1, #9163d6); }
    .fnc-capture_text .fnc-head { background: linear-gradient(135deg, #fd7e14, #ff9a45); }
    .fnc-action .fnc-head { background: linear-gradient(135deg, #198754, #35a873); }
    .fnc-end .fnc-head { background: linear-gradient(135deg, #495057, #6c757d); }
    .fnc-comment .fnc-head { background: linear-gradient(135deg, #f0ad4e, #ffce7a); }

    /* Comments/notes are purely visual (no ports, skipped by the flow engine)
       — a dashed outline marks them as "not part of the running logic". */
    .flow-node-card.fnc-comment { border: 1px dashed rgba(0,0,0,.28); box-shadow: none; }

    /* Shapes are user-chosen per step (side panel or palette), not fixed by
       type — pill is just the default for "End" steps, echoing a flowchart's
       start/end symbol. */
    .flow-node-card.fnc-shape-rectangle { border-radius: 3px; }
    .flow-node-card.fnc-shape-rounded { border-radius: 14px; }
    .flow-node-card.fnc-shape-pill { border-radius: 999px; }
    .flow-node-card.fnc-shape-pill .fnc-head { border-radius: 999px 999px 0 0; justify-content: center; }
    .flow-node-card.fnc-shape-pill .fnc-body, .flow-node-card.fnc-shape-pill .fnc-ports { text-align: center; border-radius: 0 0 999px 999px; }
    .flow-node-card.fnc-shape-ribbon { clip-path: polygon(0 0, calc(100% - 18px) 0, 100% 18px, 100% 100%, 18px 100%, 0 calc(100% - 18px)); }

    /* Oval/Circle: border-radius:50% on a wide card reads as an oval — the
       classic flowchart start/end ellipse. */
    .flow-node-card.fnc-shape-oval { border-radius: 50%; }

    /* Cloud: an organic, uneven blob — asymmetric per-corner radii, no
       clip-path needed, so nothing is ever harshly cut off. */
    .flow-node-card.fnc-shape-cloud { border-radius: 62% 38% 55% 45% / 50% 42% 58% 50%; }

    /* Octagon: shallow 14px corner cuts — a faceted look without deep clipping. */
    .flow-node-card.fnc-shape-octagon {
      clip-path: polygon(14px 0, calc(100% - 14px) 0, 100% 14px, 100% calc(100% - 14px), calc(100% - 14px) 100%, 14px 100%, 0 calc(100% - 14px), 0 14px);
    }

    /* Parallelogram: the classic flowchart input/output symbol — skewed sides. */
    .flow-node-card.fnc-shape-parallelogram { clip-path: polygon(12% 0, 100% 0, 88% 100%, 0 100%); }

    /* Diamond: the classic flowchart decision symbol. Pointed on all 4 sides,
       so it needs real breathing room — wider, taller, and heavily padded so
       text clears the point instead of being clipped by it. */
    .flow-node-card.fnc-shape-diamond {
      clip-path: polygon(50% 0, 100% 50%, 50% 100%, 0 50%);
      min-width: 240px; max-width: 300px; min-height: 130px;
    }

    .flow-node-card.fnc-shape-oval .fnc-head, .flow-node-card.fnc-shape-oval .fnc-body, .flow-node-card.fnc-shape-oval .fnc-ports,
    .flow-node-card.fnc-shape-cloud .fnc-head, .flow-node-card.fnc-shape-cloud .fnc-body, .flow-node-card.fnc-shape-cloud .fnc-ports {
      padding-left: 22px; padding-right: 22px; text-align: center;
    }
    .flow-node-card.fnc-shape-octagon .fnc-head, .flow-node-card.fnc-shape-octagon .fnc-body, .flow-node-card.fnc-shape-octagon .fnc-ports {
      padding-left: 20px; padding-right: 20px;
    }
    .flow-node-card.fnc-shape-parallelogram .fnc-head, .flow-node-card.fnc-shape-parallelogram .fnc-body, .flow-node-card.fnc-shape-parallelogram .fnc-ports {
      padding-left: 30px; padding-right: 30px;
    }
    .flow-node-card.fnc-shape-diamond .fnc-head, .flow-node-card.fnc-shape-diamond .fnc-body, .flow-node-card.fnc-shape-diamond .fnc-ports {
      padding-left: 40px; padding-right: 40px; text-align: center; justify-content: center;
    }
    .flow-node-card.fnc-shape-oval .fnc-ports, .flow-node-card.fnc-shape-cloud .fnc-ports, .flow-node-card.fnc-shape-diamond .fnc-ports {
      align-items: center;
    }

    /* Drawflow's own default CSS ships `.drawflow-node.selected{background:red}`
       as a demo placeholder. !important is used defensively here (nowhere else
       in this file) specifically to out-rank that vendor rule regardless of
       stylesheet load order. */
    .drawflow-node { background: transparent !important; padding: 0; border: none; }
    .drawflow-node.selected { background: transparent !important; }

    /* Connections: thicker, softer lines with a directional arrowhead. */
    .drawflow .connection .main-path { stroke: #94a3b8; stroke-width: 2.5; marker-end: url(#flow-arrowhead); }
    .drawflow .connection.selected .main-path { stroke: #0d6efd; }
    .drawflow .connection .point { stroke: #64748b; fill: #fff; }
    .drawflow .connection .point.selected, .drawflow .connection .point:hover { fill: #0d6efd; }

    .drawflow .output, .drawflow .input { background: #fff; border: 2px solid #64748b; height: 14px; width: 14px; }
    .drawflow .output:hover, .drawflow .input:hover { background: #0d6efd; border-color: #0d6efd; }
  </style>
@endpush

@section('content')
  <form id="flow-form" method="POST" action="{{ $flow->exists ? route('flows.update', $flow) : route('flows.store') }}">
    @csrf
    @if ($flow->exists) @method('PUT') @endif
    <input type="hidden" name="canvas_json" id="canvas_json_input">

    <div class="flow-builder">
      <div class="card">
        <div class="card-body d-flex flex-wrap gap-3 align-items-end">
          <div style="min-width:260px">
            <label class="form-label small mb-1">Flow name</label>
            <input type="text" name="name" class="form-control form-control-sm" value="{{ old('name', $flow->name) }}" required>
          </div>
          <div style="min-width:220px">
            <label class="form-label small mb-1">Runs when…</label>
            <select name="trigger_event" class="form-select form-select-sm">
              <option value="">— not wired to a trigger yet —</option>
              @foreach ($events as $key => $label)
                <option value="{{ $key }}" @selected(old('trigger_event', $flow->trigger_event) === $key)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="ms-auto d-flex align-items-center gap-2">
            @if ($flow->exists)
              @php $badge = ['draft' => 'secondary', 'active' => 'success', 'archived' => 'dark'][$flow->status] ?? 'secondary'; @endphp
              <span class="badge bg-{{ $badge }}">{{ ucfirst($flow->status) }}</span>
            @endif
            <a href="{{ route('flows.index') }}" class="btn btn-sm btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-sm btn-primary"><i class="fi fi-rr-disk me-1"></i> Save</button>
          </div>
          @error('canvas_json')<div class="text-danger small w-100">{{ $message }}</div>@enderror
        </div>
      </div>

      <div class="alert alert-secondary small mb-0">
        <i class="fi fi-rr-info me-1"></i>
        Add steps from the palette, drag from a node's right-hand dot to the next node's left-hand dot to connect them. Click straight into a step's body to type its message/question right there, or click the step to open the full side panel — including giving it its own <strong>step name</strong>, <strong>color</strong> and <strong>shape</strong>. A <strong>Yes/No branch</strong> node has two connectors on the right — the top one is <strong>Yes</strong>, the bottom one is <strong>No</strong>.
      </div>

      <div class="flow-canvas-wrap">
        <div class="flow-palette">
          <div class="mb-1">
            <label class="form-label small mb-1 fw-semibold">New step style</label>
            <div class="d-flex gap-1 mb-1">
              <input type="color" id="palette-color" class="form-control form-control-color form-control-sm p-1" style="width:38px;height:31px" value="#0d6efd" title="Color for new steps" disabled>
              <select id="palette-shape" class="form-select form-select-sm"></select>
            </div>
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="palette-color-auto" checked>
              <label class="form-check-label small" for="palette-color-auto">Auto color by type</label>
            </div>
          </div>
          <hr class="my-1">
          <button type="button" class="btn btn-outline-primary btn-sm" data-add="send_message"><i class="fi fi-rr-comment-alt me-1"></i> Send message</button>
          <button type="button" class="btn btn-outline-primary btn-sm" data-add="branch_yes_no"><i class="fi fi-rr-arrows-split-up-and-left me-1"></i> Yes / No branch</button>
          <button type="button" class="btn btn-outline-primary btn-sm" data-add="capture_text"><i class="fi fi-rr-edit me-1"></i> Capture reply</button>
          <button type="button" class="btn btn-outline-primary btn-sm" data-add="action"><i class="fi fi-rr-bolt me-1"></i> Take action</button>
          <button type="button" class="btn btn-outline-primary btn-sm" data-add="end"><i class="fi fi-rr-flag me-1"></i> End flow</button>
          <hr class="my-1">
          <div class="small text-muted mb-1">Annotation <span class="text-muted">(not run by the flow)</span></div>
          <button type="button" class="btn btn-outline-secondary btn-sm" data-add="comment"><i class="fi fi-rr-note me-1"></i> Note / Comment</button>
          <hr class="my-1">
          <div class="small text-muted">Click a node on the canvas to edit it. Drag its edge dots to connect steps.</div>
        </div>

        <div class="flow-stage">
          <div id="drawflow"></div>
          <div class="flow-zoom-controls">
            <button type="button" id="zoom-in" title="Zoom in"><i class="fi fi-rr-plus"></i></button>
            <button type="button" id="zoom-reset" title="Reset zoom"><i class="fi fi-rr-expand"></i></button>
            <button type="button" id="zoom-out" title="Zoom out"><i class="fi fi-rr-minus"></i></button>
          </div>
        </div>

        <div class="flow-side-panel">
          <div class="card h-100">
            <div class="card-header"><h6 class="mb-0" id="panel-title">Step details</h6></div>
            <div class="card-body" id="panel-body">
              <p class="text-muted small mb-0">Select a node on the canvas to edit it, or add one from the palette.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </form>
@endsection

@push('scripts')
  <script src="{{ asset('assets/libs/drawflow/drawflow.min.js') }}"></script>
  <script>
    (function () {
      const WHATSAPP_EVENTS = @json($events);
      const SAVED_EXPORT = @json($flow->canvas_json);
      const TEMPLATES_URL = @json(route('settings.integrations.edit').'#wa-templates');
      // Configured template bodies per event (from Settings → Integrations),
      // keyed by event, each a { body, variables } pair.
      const TEMPLATE_SECTIONS = @json($templateSections);
      // Built via char codes — NEVER written as literal double curly braces
      // anywhere in this file, including comments, so the Blade compiler never
      // mistakes this JS text for one of its own echo tags (it would try to
      // echo an undefined PHP constant and fatal the whole page).
      const LB = String.fromCharCode(123, 123), RB = String.fromCharCode(125, 125);
      const SEND_MESSAGE_PLACEHOLDER = 'Hi ' + LB + 'patient_name' + RB + ', does ' + LB + 'time' + RB + ' on ' + LB + 'date' + RB + ' still work?';

      /**
       * Converts a template's positional double-brace-1, double-brace-2, ...
       * body into the named double-brace-patient_name, double-brace-time, ...
       * form the flow's own plain-text token substitution understands
       * (see FlowEngine::tokenMap() server-side).
       */
      function templateToNamedText(section) {
        if (!section || !section.body) return '';
        let body = section.body;
        (section.variables || []).forEach(function (token, i) {
          const positional = LB + (i + 1) + RB; // e.g. index 0 -> position 1
          const named = LB + token + RB;        // e.g. "patient_name"
          body = body.split(positional).join(named);
        });

        return body;
      }

      const NODE_META = {
        send_message: { label: 'Send message', icon: 'fi-rr-comment-alt', inputs: 1, outputs: 1 },
        branch_yes_no: { label: 'Yes / No branch', icon: 'fi-rr-arrows-split-up-and-left', inputs: 1, outputs: 2 },
        capture_text: { label: 'Capture reply', icon: 'fi-rr-edit', inputs: 1, outputs: 1 },
        action: { label: 'Take action', icon: 'fi-rr-bolt', inputs: 1, outputs: 1 },
        end: { label: 'End flow', icon: 'fi-rr-flag', inputs: 1, outputs: 0 },
        comment: { label: 'Note', icon: 'fi-rr-note', inputs: 0, outputs: 0 },
      };

      /** Step types whose message can come from an approved WhatsApp template, not just plain text. */
      const TEMPLATE_CAPABLE_TYPES = ['send_message', 'branch_yes_no', 'capture_text'];
      const DEFAULT_MODE = { send_message: 'template', branch_yes_no: 'text', capture_text: 'text' };

      /** Falls back per-type so nodes saved before this field existed keep behaving as plain text. */
      function modeOf(type, data) {
        return (data && data.mode) || DEFAULT_MODE[type] || 'text';
      }

      const ACTION_LABELS = {
        confirm: 'Confirms the appointment',
        reschedule_to_captured: 'Reschedules to the captured time',
        cancel: 'Cancels the appointment',
        escalate: 'Hands off to staff',
      };

      const DEFAULT_COLORS = {
        send_message: '#0d6efd', branch_yes_no: '#6f42c1', capture_text: '#fd7e14', action: '#198754', end: '#495057',
        comment: '#f0ad4e',
      };

      const SHAPES = {
        rectangle: 'Rectangle', rounded: 'Rounded', pill: 'Pill / Stadium', ribbon: 'Ribbon',
        oval: 'Oval / Circle', cloud: 'Cloud', octagon: 'Octagon',
        diamond: 'Diamond (decision)', parallelogram: 'Parallelogram (input/output)',
      };

      /** The one freeform text field each type's node body can be edited inline for — omitted types (action) stay side-panel-only. */
      const PRIMARY_FIELD = {
        send_message: 'text', branch_yes_no: 'question', capture_text: 'question', end: 'outcome', comment: 'text',
      };

      const BODY_PLACEHOLDERS = {
        send_message: 'Click to type a message (or use the side panel)',
        branch_yes_no: 'Click to type the question to ask',
        capture_text: 'Click to type the question to ask',
        end: 'Click to set an outcome label',
        comment: 'Click to type a note',
      };

      function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
          return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
      }

      /** Small corner badges: ◆ for a decision (branch) step, ☁ for a cloud-shaped one — either, both, or neither. */
      function headBadges(type, shape) {
        let out = '';
        if (type === 'branch_yes_no') out += '◆';
        if (shape === 'cloud') out += '☁';

        return out;
      }

      const container = document.getElementById('drawflow');
      const editor = new Drawflow(container);
      editor.reroute = true;
      editor.reroute_fix_curvature = true;
      editor.curvature = 0.5;
      editor.start();

      // A directional arrowhead marker, referenced by every connection's line
      // (see the .main-path CSS rule) — appended once, outside Drawflow's own
      // internal structure so pan/zoom transforms never touch it.
      container.insertAdjacentHTML('beforeend',
        '<svg width="0" height="0" style="position:absolute"><defs>'
        + '<marker id="flow-arrowhead" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse">'
        + '<path d="M0,0 L10,5 L0,10 z" fill="#64748b"></path></marker></defs></svg>');

      if (SAVED_EXPORT && SAVED_EXPORT.drawflow) {
        editor.import(SAVED_EXPORT);
      }

      let addCount = Object.keys((editor.export().drawflow.Home.data || {})).length;

      /**
       * Non-editable, computed previews — for `action` (dropdown-driven) and a
       * `send_message` actively using a template. When the picked event has a
       * configured template body, its actual wording (converted to named
       * tokens) is shown right on the card, under the "Template: X" label.
       */
      function previewFor(type, data) {
        data = data || {};
        if (TEMPLATE_CAPABLE_TYPES.includes(type)) {
          const label = 'Template: ' + escapeHtml(WHATSAPP_EVENTS[data.event_key] || data.event_key);
          const section = TEMPLATE_SECTIONS[data.event_key];
          if (section) {
            const text = escapeHtml(templateToNamedText(section)).replace(/\n/g, '<br>');
            return label + '<div class="fnc-tpl-text">' + text + '</div>';
          }
          return label;
        }
        if (type === 'action') {
          return data.action ? escapeHtml(ACTION_LABELS[data.action] || data.action) : null;
        }
        return null;
      }

      /** A small read-only line under the editable body — only capture_text uses this today. */
      function metaFor(type, data) {
        if (type === 'capture_text' && data.variable) {
          return 'Saves as <code>' + escapeHtml(data.variable) + '</code>';
        }

        return null;
      }

      /**
       * Decides how a node's body renders: directly editable raw text (most
       * types), or a computed read-only preview (`action`, and `send_message`
       * once an approved template is actively selected for it).
       */
      function bodyContent(type, data) {
        if (TEMPLATE_CAPABLE_TYPES.includes(type) && modeOf(type, data) !== 'text' && data.event_key) {
          const preview = previewFor(type, data);

          return { editable: false, html: preview || 'Not configured yet', empty: !preview };
        }

        if (type === 'action') {
          const preview = previewFor(type, data);

          return { editable: false, html: preview || 'Not configured yet', empty: !preview };
        }

        const field = PRIMARY_FIELD[type];
        if (field) {
            return { editable: true, text: data[field] || '', placeholder: BODY_PLACEHOLDERS[type] || 'Not configured yet' };
        }

        return { editable: false, html: 'Not configured yet', empty: true };
      }

      function nodeHtml(type, data) {
        data = data || {};
        const meta = NODE_META[type];
        const label = data.label || meta.label;
        const headStyle = data.color ? ' style="background:' + escapeHtml(data.color) + '"' : '';
        const shape = data.shape || (type === 'end' ? 'pill' : 'rounded');
        const badges = headBadges(type, shape);

        const bc = bodyContent(type, data);
        let lower = bc.editable
          ? '<div class="fnc-body" contenteditable="true" data-placeholder="' + escapeHtml(bc.placeholder) + '">' + escapeHtml(bc.text) + '</div>'
          : '<div class="fnc-body' + (bc.empty ? ' fnc-empty' : '') + '">' + bc.html + '</div>';

        const metaLine = metaFor(type, data);
        if (metaLine) lower += '<div class="fnc-meta">' + metaLine + '</div>';

        if (type === 'branch_yes_no') {
          lower += '<div class="fnc-ports"><span>Yes ↗</span><span>No ↘</span></div>';
        }

        return '<div class="flow-node-card fnc-' + type + ' fnc-shape-' + shape + '">'
          + '<div class="fnc-head"' + headStyle + '><i class="fi ' + meta.icon + '"></i>'
          + '<div class="fnc-titlewrap"><div class="fnc-title">' + escapeHtml(label) + '</div></div>'
          + (badges ? '<span class="fnc-diamond">' + badges + '</span>' : '')
          + '</div>'
          + lower
          + '</div>';
      }

      function refreshNodeCard(nodeId, type, data) {
        const el = document.getElementById('node-' + nodeId);
        if (!el) return;
        const meta = NODE_META[type];

        const titleEl = el.querySelector('.fnc-title');
        if (titleEl) titleEl.textContent = data.label || meta.label;

        const headEl = el.querySelector('.fnc-head');
        if (headEl) headEl.style.background = data.color || '';

        const shape = data.shape || (type === 'end' ? 'pill' : 'rounded');
        const cardEl = el.querySelector('.flow-node-card');
        if (cardEl) {
          cardEl.className = cardEl.className.replace(/\bfnc-shape-\S+/, '').trim();
          cardEl.classList.add('fnc-shape-' + shape);
        }

        const badges = headBadges(type, shape);
        const badgeEl = el.querySelector('.fnc-diamond');
        if (badgeEl) {
          badgeEl.textContent = badges;
          badgeEl.style.display = badges ? '' : 'none';
        } else if (badges && headEl) {
          headEl.insertAdjacentHTML('beforeend', '<span class="fnc-diamond">' + badges + '</span>');
        }

        const bodyEl = el.querySelector('.fnc-body');
        if (bodyEl) {
          const bc = bodyContent(type, data);
          if (bc.editable) {
            // Don't clobber the DOM while the user is actively typing in it —
            // this branch only runs from the side panel or an import/refresh.
            if (document.activeElement !== bodyEl) {
              bodyEl.textContent = bc.text;
            }
            bodyEl.setAttribute('contenteditable', 'true');
            bodyEl.setAttribute('data-placeholder', bc.placeholder);
            bodyEl.classList.remove('fnc-empty');
          } else {
            bodyEl.removeAttribute('contenteditable');
            bodyEl.innerHTML = bc.html;
            bodyEl.classList.toggle('fnc-empty', !!bc.empty);
          }
        }

        const metaHtml = metaFor(type, data);
        const metaEl = el.querySelector('.fnc-meta');
        if (metaEl) {
          if (metaHtml) { metaEl.innerHTML = metaHtml; } else { metaEl.remove(); }
        } else if (metaHtml && bodyEl) {
          bodyEl.insertAdjacentHTML('afterend', '<div class="fnc-meta">' + metaHtml + '</div>');
        }
      }

      function defaultData(type) {
        const extra = {
          send_message: { mode: 'template', event_key: '', text: '' },
          branch_yes_no: { question: '', unclear_message: '', mode: 'text', event_key: '' },
          capture_text: { question: '', variable: '', mode: 'text', event_key: '' },
          action: { action: '', variable: '', escalate_reason: '' },
          end: { outcome: '' },
          comment: { text: '' },
        }[type];

        return Object.assign({
          label: NODE_META[type].label,
          color: '',
          shape: type === 'end' ? 'pill' : 'rounded',
        }, extra);
      }

      // ---- Inline (canvas) editing ----------------------------------------

      let currentPanelNodeId = null;

      function syncPanelField(nodeId, field, value) {
        if (currentPanelNodeId !== nodeId) return;
        const input = panelBody.querySelector('[data-field="' + field + '"]');
        if (input && document.activeElement !== input) {
          input.value = value;
        }
      }

      function wireInlineEdit(nodeId) {
        const el = document.getElementById('node-' + nodeId);
        if (!el) return;
        const body = el.querySelector('.fnc-body[contenteditable="true"]');
        if (!body || body.dataset.wired) return;
        body.dataset.wired = '1';

        // Stop Drawflow from starting a node-drag when the user is just
        // trying to click into the text to place a cursor.
        body.addEventListener('mousedown', function (e) { e.stopPropagation(); });

        // Keep these fields single-paragraph — Enter finishes editing rather
        // than inserting a nested <div>, which keeps textContent extraction clean.
        body.addEventListener('keydown', function (e) {
          if (e.key === 'Enter') { e.preventDefault(); body.blur(); }
        });

        body.addEventListener('input', function () {
          const node = editor.getNodeFromId(nodeId);
          if (!node) return;
          const field = PRIMARY_FIELD[node.name];
          if (!field) return;

          const current = node.data || {};
          current[field] = body.textContent.trim();
          editor.updateNodeDataFromId(nodeId, current);
          syncPanelField(nodeId, field, current[field]);
        });
      }

      Object.keys(editor.export().drawflow.Home.data || {}).forEach(function (id) {
        // Drawflow only stores each node's HTML snapshot from the moment it was
        // created — later panel/inline edits patch the live DOM but never that
        // stored snapshot, so a reload would otherwise redraw the stale (often
        // placeholder) markup even though the saved `data` is correct. Re-render
        // from the actual data now so the card always matches what was saved.
        const node = editor.getNodeFromId(id);
        if (node) refreshNodeCard(id, node.name, node.data || {});
        wireInlineEdit(id);
      });

      // ---- Palette style picker: set a shape/color once, then every step you
      // add next comes in already looking that way (still editable per-step
      // afterward via the side panel). --------------------------------------

      const paletteShapeSelect = document.getElementById('palette-shape');
      const paletteColorInput = document.getElementById('palette-color');
      const paletteColorAuto = document.getElementById('palette-color-auto');

      let paletteShapeOptions = '<option value="auto">Auto (by step type)</option>';
      for (const key in SHAPES) {
        paletteShapeOptions += '<option value="' + key + '">' + SHAPES[key] + '</option>';
      }
      paletteShapeSelect.innerHTML = paletteShapeOptions;

      function syncPaletteColorEnabled() { paletteColorInput.disabled = paletteColorAuto.checked; }
      paletteColorAuto.addEventListener('change', syncPaletteColorEnabled);
      syncPaletteColorEnabled();

      document.querySelectorAll('[data-add]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          const type = btn.dataset.add;
          const meta = NODE_META[type];
          const data = defaultData(type);

          if (paletteShapeSelect.value !== 'auto') data.shape = paletteShapeSelect.value;
          if (! paletteColorAuto.checked) data.color = paletteColorInput.value;

          const col = addCount % 4, row = Math.floor(addCount / 4);
          const x = 60 + col * 270, y = 60 + row * 190;
          addCount++;

          const id = editor.addNode(type, meta.inputs, meta.outputs, x, y, type, data, nodeHtml(type, data));
          wireInlineEdit(id);
          renderPanel(id);
        });
      });

      // ---- Zoom controls -------------------------------------------------

      document.getElementById('zoom-in').addEventListener('click', function () { editor.zoom_in(); });
      document.getElementById('zoom-out').addEventListener('click', function () { editor.zoom_out(); });
      document.getElementById('zoom-reset').addEventListener('click', function () { editor.zoom_reset(); });

      // ---- Side panel ---------------------------------------------------

      const panelTitle = document.getElementById('panel-title');
      const panelBody = document.getElementById('panel-body');

      function eventOptions(selected) {
        let html = '<option value="">— pick a template event —</option>';
        for (const key in WHATSAPP_EVENTS) {
          html += '<option value="' + key + '"' + (key === selected ? ' selected' : '') + '>' + WHATSAPP_EVENTS[key] + '</option>';
        }
        return html;
      }

      /** Shared "Message source / Template (event) / text" fields — used by send_message, branch_yes_no and capture_text. */
      function messageSourceFieldsHtml(type, data, textLabel, textPlaceholder) {
        const field = PRIMARY_FIELD[type];
        const mode = modeOf(type, data);

        return ''
          + '<div class="mb-2"><label class="form-label small mb-1">Message source</label>'
          + '<select class="form-select form-select-sm" data-field="mode">'
          + '<option value="template"' + (mode !== 'text' ? ' selected' : '') + '>Approved WhatsApp template</option>'
          + '<option value="text"' + (mode === 'text' ? ' selected' : '') + '>Plain text (only within a 24h reply window)</option>'
          + '</select></div>'
          + '<div class="mb-2">'
          + '<div class="d-flex justify-content-between align-items-center mb-1">'
          + '<label class="form-label small mb-0">Template (event)</label>'
          + '<a href="' + TEMPLATES_URL + '" target="_blank" rel="noopener" class="small text-decoration-none">'
          + '<i class="fi fi-rr-plus-small me-1"></i>Manage templates</a>'
          + '</div>'
          + '<select class="form-select form-select-sm" data-field="event_key">' + eventOptions(data.event_key || '') + '</select></div>'
          + '<div class="mb-2"><label class="form-label small mb-1">' + textLabel + ' <span class="text-muted">(used if no template, or mode = plain text — same text shown right on the step)</span></label>'
          + '<textarea class="form-control form-control-sm" rows="3" data-field="' + field + '" placeholder="' + textPlaceholder + '">' + escapeHtml(data[field] || '') + '</textarea></div>';
      }

      function renderPanel(nodeId) {
        const node = editor.getNodeFromId(nodeId);
        if (!node) return;
        const type = node.name;
        const data = node.data || {};
        const meta = NODE_META[type];
        currentPanelNodeId = nodeId;
        panelTitle.textContent = data.label || meta.label;

        const currentShape = data.shape || (type === 'end' ? 'pill' : 'rounded');
        let shapeOptions = '';
        for (const key in SHAPES) {
          shapeOptions += '<option value="' + key + '"' + (key === currentShape ? ' selected' : '') + '>' + SHAPES[key] + '</option>';
        }

        let html = ''
          + '<div class="mb-2"><label class="form-label small mb-1">Step name</label>'
          + '<input type="text" class="form-control form-control-sm" data-field="label" placeholder="' + escapeHtml(meta.label) + '" value="' + escapeHtml(data.label || '') + '"></div>'
          + '<div class="row g-2 mb-2">'
          + '<div class="col-6">'
          + '<label class="form-label small mb-1">Color</label>'
          + '<div class="d-flex align-items-center gap-1">'
          + '<input type="color" class="form-control form-control-color form-control-sm p-1" style="width:40px;height:31px" data-field="color" value="' + escapeHtml(data.color || DEFAULT_COLORS[type]) + '">'
          + '<button type="button" class="btn btn-sm btn-outline-secondary" id="panel-reset-color" title="Use the default color"><i class="fi fi-rr-refresh"></i></button>'
          + '</div></div>'
          + '<div class="col-6">'
          + '<label class="form-label small mb-1">Shape</label>'
          + '<select class="form-select form-select-sm" data-field="shape">' + shapeOptions + '</select>'
          + '</div>'
          + '</div>'
          + '<div class="small text-muted mb-3"><i class="fi ' + meta.icon + ' me-1"></i>' + meta.label + '</div>';

        if (type === 'send_message') {
          html += messageSourceFieldsHtml(type, data, 'Plain text', SEND_MESSAGE_PLACEHOLDER);
        } else if (type === 'branch_yes_no') {
          html += messageSourceFieldsHtml(type, data, 'Question to ask', SEND_MESSAGE_PLACEHOLDER)
            + '<div class="form-text mb-2">Sent once, the moment the flow reaches this step. Leave both blank (no template, no text) if an earlier "Send message" step already asked.</div>'
            + '<div class="mb-2"><label class="form-label small mb-1">If the reply is unclear, ask again with…</label>'
            + '<textarea class="form-control form-control-sm" rows="2" data-field="unclear_message" placeholder="Sorry, could you reply yes or no?">' + escapeHtml(data.unclear_message || '') + '</textarea></div>'
            + '<p class="small text-muted mb-0">Connect the top output (Yes) and bottom output (No) to the next steps.</p>';
        } else if (type === 'capture_text') {
          html += messageSourceFieldsHtml(type, data, 'Question to ask', 'What time works best for you?')
            + '<div class="form-text mb-2">Sent once, the moment the flow reaches this step. Leave both blank (no template, no text) if an earlier "Send message" step already asked.</div>'
            + '<div class="mb-2"><label class="form-label small mb-1">Store the reply as</label>'
            + '<input type="text" class="form-control form-control-sm" data-field="variable" placeholder="e.g. requested_time" value="' + escapeHtml(data.variable || '') + '"></div>'
            + '<p class="small text-muted mb-0">Pick this exact variable name in a "Take action" step\'s captured-variable field to use the reply.</p>';
        } else if (type === 'action') {
          html += ''
            + '<div class="mb-2"><label class="form-label small mb-1">Action</label>'
            + '<select class="form-select form-select-sm" data-field="action">'
            + ['', 'confirm', 'reschedule_to_captured', 'cancel', 'escalate'].map(function (a) {
                const labels = { '': '— choose —', confirm: 'Confirm the appointment', reschedule_to_captured: 'Reschedule to a captured time', cancel: 'Cancel the appointment', escalate: 'Hand off to staff' };
                return '<option value="' + a + '"' + (data.action === a ? ' selected' : '') + '>' + labels[a] + '</option>';
              }).join('')
            + '</select></div>'
            + '<div class="mb-2"><label class="form-label small mb-1">Captured variable <span class="text-muted">(for "reschedule to a captured time")</span></label>'
            + '<input type="text" class="form-control form-control-sm" data-field="variable" placeholder="e.g. requested_time" value="' + escapeHtml(data.variable || '') + '"></div>'
            + '<div class="mb-2"><label class="form-label small mb-1">Reason shown to staff <span class="text-muted">(for "hand off to staff")</span></label>'
            + '<input type="text" class="form-control form-control-sm" data-field="escalate_reason" value="' + escapeHtml(data.escalate_reason || '') + '"></div>';
        } else if (type === 'end') {
          html += ''
            + '<div class="mb-2"><label class="form-label small mb-1">Outcome label <span class="text-muted">(same text shown right on the step, and used for your own reporting)</span></label>'
            + '<input type="text" class="form-control form-control-sm" data-field="outcome" placeholder="e.g. rescheduled" value="' + escapeHtml(data.outcome || '') + '"></div>';
        } else if (type === 'comment') {
          html += ''
            + '<div class="mb-2"><label class="form-label small mb-1">Note text <span class="text-muted">(same text shown right on the step)</span></label>'
            + '<textarea class="form-control form-control-sm" rows="4" data-field="text" placeholder="e.g. Front desk double-checks this manually">' + escapeHtml(data.text || '') + '</textarea></div>'
            + '<p class="small text-muted mb-0">This is a visual note only — it has no connectors and is skipped entirely when the flow runs.</p>';
        }

        html += '<button type="button" class="btn btn-sm btn-outline-danger mt-2" id="panel-delete">Delete this step</button>';
        panelBody.innerHTML = html;

        function persist(field, value) {
          const current = editor.getNodeFromId(nodeId).data || {};
          current[field] = value;
          editor.updateNodeDataFromId(nodeId, current);
          refreshNodeCard(nodeId, type, current);
          if (field === 'label') panelTitle.textContent = value || meta.label;
        }

        panelBody.querySelectorAll('[data-field]').forEach(function (field) {
          field.addEventListener('input', function () { persist(field.dataset.field, field.value); });
          field.addEventListener('change', function () { persist(field.dataset.field, field.value); });
        });

        // Auto-fill the plain-text fallback from the picked template's real
        // wording (converted to named tokens) — only when the field is still
        // empty, so it never overwrites something you've already written.
        if (TEMPLATE_CAPABLE_TYPES.includes(type)) {
          const eventKeySelect = panelBody.querySelector('[data-field="event_key"]');
          const targetField = PRIMARY_FIELD[type];
          const textArea = panelBody.querySelector('[data-field="' + targetField + '"]');

          const fillFromTemplate = function () {
            const section = TEMPLATE_SECTIONS[eventKeySelect.value];
            if (section && textArea && !textArea.value.trim()) {
              const filled = templateToNamedText(section);
              textArea.value = filled;
              persist(targetField, filled);
            }
          };

          if (eventKeySelect) {
            eventKeySelect.addEventListener('change', fillFromTemplate);
            fillFromTemplate(); // covers reopening a step whose template was already picked
          }
        }

        const resetColor = document.getElementById('panel-reset-color');
        if (resetColor) {
          resetColor.addEventListener('click', function () {
            persist('color', '');
            const colorInput = panelBody.querySelector('[data-field="color"]');
            if (colorInput) colorInput.value = DEFAULT_COLORS[type];
          });
        }

        const del = document.getElementById('panel-delete');
        if (del) {
          del.addEventListener('click', function () {
            editor.removeNodeId('node-' + nodeId);
            resetPanel();
          });
        }
      }

      function resetPanel() {
        currentPanelNodeId = null;
        panelTitle.textContent = 'Step details';
        panelBody.innerHTML = '<p class="text-muted small mb-0">Select a node on the canvas to edit it, or add one from the palette.</p>';
      }

      editor.on('nodeSelected', function (id) { renderPanel(id); });
      editor.on('nodeUnselected', function () { resetPanel(); });
      editor.on('nodeRemoved', function () { resetPanel(); });

      // ---- Save -----------------------------------------------------------

      document.getElementById('flow-form').addEventListener('submit', function () {
        document.getElementById('canvas_json_input').value = JSON.stringify(editor.export());
      });
    })();
  </script>
@endpush
