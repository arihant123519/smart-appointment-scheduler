@extends('layouts.app')

@section('title', 'AI Scheduling Assistant')

@push('styles')
  <style>
    /* The hero card below carries the page title, so the generic layout
       toolbar heading (identical text) is redundant here — hide it.
       Scoped to this page only: this <style> block is only ever pushed
       into the document when ai/booking.blade.php is the active view. */
    .sas-page-toolbar { display: none; }

    /* ---------------------------------------------------------------------
       Hero
       ------------------------------------------------------------------ */
    .sas-ai-hero {
      background: #fff, var(--sas-gradient-mesh);
      background: var(--sas-gradient-mesh), #fff;
      border-radius: var(--sas-radius-2xl);
    }
    .sas-ai-hero__icon {
      width: 52px; height: 52px; border-radius: var(--sas-radius-lg); flex-shrink: 0;
      background: var(--sas-gradient-brand-soft); color: var(--sas-primary-700);
      display: grid; place-items: center; font-size: 1.4rem;
    }
    .sas-ai-hero__title { font-size: 2.25rem; font-weight: 800; letter-spacing: -.02em; color: var(--sas-gray-900); }
    .sas-ai-hero__subtitle { font-size: var(--sas-fs-base); color: var(--sas-gray-600); max-width: 46ch; }
    .sas-ai-safety {
      display: inline-flex; align-items: flex-start; gap: .65rem;
      background: var(--sas-gray-25); border: 1px solid var(--sas-gray-200); border-radius: var(--sas-radius-lg);
      padding: .65rem .9rem; max-width: 100%;
    }
    .sas-ai-safety__icon {
      width: 30px; height: 30px; border-radius: var(--sas-radius-sm); flex-shrink: 0;
      background: var(--sas-success-subtle); color: var(--sas-success-emphasis);
      display: grid; place-items: center; font-size: .85rem;
    }
    .sas-ai-safety__title { font-weight: 700; font-size: var(--sas-fs-sm); color: var(--sas-gray-900); }
    .sas-ai-safety__text { font-size: var(--sas-fs-xs); color: var(--sas-gray-500); }

    /* Mascot illustration — pure CSS/SVG, no external asset dependency */
    .sas-ai-mascot { position: relative; width: 100%; height: 210px; display: flex; align-items: center; justify-content: center; }
    .sas-ai-mascot__glow {
      position: absolute; inset: 8%; border-radius: 50%;
      background: radial-gradient(circle at 50% 45%, var(--sas-primary-100) 0%, rgba(219,234,254,.4) 45%, transparent 72%);
    }
    .sas-ai-mascot__bot { width: 140px; height: 140px; position: relative; z-index: 1; filter: drop-shadow(0 14px 22px rgba(37,99,235,.18)); }
    .sas-ai-mascot__spark { position: absolute; color: var(--sas-primary-200); font-size: .8rem; animation: sasAiTwinkle 2.4s ease-in-out infinite; }
    .sas-ai-mascot__spark--1 { top: 14%; left: 2%; animation-delay: .2s; }
    .sas-ai-mascot__spark--2 { top: 2%; left: 40%; font-size: .6rem; animation-delay: 1.1s; }
    @keyframes sasAiTwinkle { 0%, 100% { opacity: .25; transform: scale(.8); } 50% { opacity: 1; transform: scale(1); } }
    .sas-ai-mascot__bubble {
      position: absolute; width: 42px; height: 42px; border-radius: var(--sas-radius-md);
      background: #fff; border: 1px solid var(--sas-gray-200); box-shadow: var(--sas-shadow-md);
      display: grid; place-items: center; font-size: 1.05rem; animation: sasAiFloat 3.6s ease-in-out infinite;
    }
    .sas-ai-mascot__bubble--1 { top: 2%;  left: 6%;  background: #F5F3FF; border-color: #EDE9FE; color: #7C3AED; animation-delay: 0s; }
    .sas-ai-mascot__bubble--2 { top: 10%; right: 0; background: var(--sas-success-subtle); border-color: #D1FAE5; color: var(--sas-success-emphasis); animation-delay: .5s; }
    .sas-ai-mascot__bubble--3 { bottom: 8%; right: 10%; color: var(--sas-primary-600); animation-delay: 1s; }
    @keyframes sasAiFloat { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }
    @media (prefers-reduced-motion: reduce) { .sas-ai-mascot__bubble, .sas-ai-mascot__spark { animation: none; } }

    /* ---------------------------------------------------------------------
       Tabs — underline segmented control
       ------------------------------------------------------------------ */
    .sas-ai-tabs { border-bottom: 1px solid var(--sas-gray-200); gap: 1.25rem; }
    .sas-ai-tabs .nav-link {
      border: 0; border-bottom: 2px solid transparent; border-radius: 0; background: transparent;
      color: var(--sas-gray-600); font-weight: 600; font-size: var(--sas-fs-sm);
      padding: .75rem .1rem; margin-bottom: -1px; display: flex; align-items: center; gap: .45rem;
      transition: color .15s var(--sas-ease), border-color .15s var(--sas-ease);
    }
    .sas-ai-tabs .nav-link:hover { color: var(--sas-primary-700); }
    .sas-ai-tabs .nav-link.active { color: var(--sas-primary-600); border-bottom-color: var(--sas-primary-600); }
    .sas-ai-tabs .nav-link i { font-size: 1rem; }

    /* ---------------------------------------------------------------------
       Chat panel — one continuous surface: message log, suggestions, trust
       chips, composer and footnote all live inside a single card so the
       experience reads as one workspace rather than stacked widgets.
       ------------------------------------------------------------------ */
    .sas-chat-panel { padding: var(--sas-space-6) var(--sas-space-5); }
    @media (min-width: 992px) { .sas-chat-panel { padding: var(--sas-space-6); } }
    #chatLog { scroll-behavior: smooth; }
    .sas-msg { display: flex; gap: .6rem; max-width: 88%; }
    .sas-msg.user { align-self: flex-end; flex-direction: row-reverse; }
    .sas-msg__col { display: flex; flex-direction: column; gap: .25rem; min-width: 0; }
    .sas-msg.user .sas-msg__col { align-items: flex-end; }
    .sas-msg__avatar {
      width: 34px; height: 34px; border-radius: 50%; flex: 0 0 34px; display: grid; place-items: center;
      font-size: .95rem; color: #fff; overflow: hidden;
    }
    .sas-msg.bot .sas-msg__avatar { background: var(--sas-gray-100); padding: 4px; }
    .sas-msg.bot .sas-msg__avatar svg { width: 100%; height: 100%; }
    .sas-msg.user .sas-msg__avatar { background: var(--sas-gray-900); }
    .sas-msg__bubble { padding: .6rem .9rem; border-radius: var(--sas-radius-lg); line-height: 1.5; font-size: var(--sas-fs-base); }
    .sas-msg.bot .sas-msg__bubble { background: var(--sas-gray-50); border: 1px solid var(--sas-gray-100); border-top-left-radius: .3rem; color: var(--sas-gray-800); }
    .sas-msg.user .sas-msg__bubble { background: var(--sas-primary); color: #fff; border-top-right-radius: .3rem; }
    .sas-msg__time { font-size: var(--sas-fs-xs); color: var(--sas-gray-400); padding: 0 .2rem; }

    .sas-typing span { display: inline-block; width: 6px; height: 6px; margin: 0 1px; background: var(--sas-gray-400);
      border-radius: 50%; animation: sasAiBounce 1.2s infinite ease-in-out both; }
    .sas-typing span:nth-child(2) { animation-delay: .15s; } .sas-typing span:nth-child(3) { animation-delay: .3s; }
    @keyframes sasAiBounce { 0%, 80%, 100% { transform: scale(.6); opacity: .5; } 40% { transform: scale(1); opacity: 1; } }
    @media (prefers-reduced-motion: reduce) { .sas-typing span { animation: none; } }

    /* Suggestion tiles — icon + two-line label (action / detail) */
    .sas-suggest-tile {
      display: flex; align-items: center; gap: .7rem; width: 100%; text-align: left;
      background: #fff; border: 1px solid var(--sas-gray-200); border-radius: var(--sas-radius-lg);
      padding: .75rem .9rem; transition: border-color .15s var(--sas-ease), box-shadow .15s var(--sas-ease), transform .15s var(--sas-ease);
    }
    .sas-suggest-tile:hover { border-color: var(--sas-primary-300); box-shadow: var(--sas-shadow-sm); transform: translateY(-1px); }
    .sas-suggest-tile:focus-visible { outline: 2px solid var(--sas-primary-500); outline-offset: 2px; }
    .sas-suggest-tile__title { display: block; font-weight: 700; font-size: var(--sas-fs-sm); line-height: 1.3; }
    .sas-suggest-tile__detail { display: block; font-size: var(--sas-fs-xs); color: var(--sas-gray-500); margin-top: .1rem; }
    @media (prefers-reduced-motion: reduce) { .sas-suggest-tile { transition: none; } .sas-suggest-tile:hover { transform: none; } }

    /* Trust / capability chips */
    .sas-feature-pill {
      display: inline-flex; align-items: center; gap: .4rem; border: 1px solid var(--sas-gray-200);
      border-radius: 999px; padding: .35rem .8rem .35rem .6rem; font-size: var(--sas-fs-xs); font-weight: 600; color: var(--sas-gray-600);
    }
    .sas-feature-pill i { color: var(--sas-primary-500); font-size: .95rem; }

    /* Composer — ChatGPT-style box: input on top, toolbar row below */
    .sas-composer-box {
      display: flex; align-items: center; gap: .75rem;
      border: 1px solid var(--sas-gray-300); border-radius: var(--sas-radius-lg);
      padding: .7rem .8rem .6rem 1rem; background: #fff;
      transition: border-color .15s var(--sas-ease), box-shadow .15s var(--sas-ease);
    }
    .sas-composer-box:focus-within { border-color: var(--sas-primary-400); box-shadow: 0 0 0 .2rem var(--sas-primary-100); }
    .sas-composer-box__main { flex: 1; display: flex; flex-direction: column; gap: .4rem; min-width: 0; }
    .sas-composer-box__input { border: 0; outline: none; background: transparent; font-size: var(--sas-fs-base); padding: 0; width: 100%; }
    .sas-composer-box__input::placeholder { color: var(--sas-gray-400); }
    .sas-composer-box__attach {
      align-self: flex-start; border: 0; background: transparent; color: var(--sas-gray-400);
      display: inline-flex; align-items: center; padding: 0; transition: color .15s var(--sas-ease);
    }
    .sas-composer-box__attach:hover:not(:disabled) { color: var(--sas-gray-600); }
    .sas-composer-box__attach:disabled { cursor: not-allowed; opacity: .6; }
    .sas-composer-box__actions { display: flex; align-items: center; gap: .5rem; flex-shrink: 0; }
    .sas-composer__icon-btn {
      width: 38px; height: 38px; border-radius: 50%; border: 0; background: var(--sas-gray-100); color: var(--sas-gray-500);
      display: grid; place-items: center; flex-shrink: 0; transition: background-color .15s var(--sas-ease), color .15s var(--sas-ease);
    }
    .sas-composer__icon-btn:hover:not(:disabled) { background: var(--sas-gray-200); color: var(--sas-gray-700); }
    .sas-composer__icon-btn:disabled { opacity: .4; cursor: not-allowed; }
    .sas-composer__icon-btn.is-active { background: var(--sas-primary-50); color: var(--sas-primary-600); }
    .sas-send-btn {
      width: 38px; height: 38px; border-radius: 50%; flex-shrink: 0;
      display: grid; place-items: center; padding: 0; box-shadow: var(--sas-shadow-brand);
    }
    .sas-chat-footnote {
      display: flex; align-items: center; justify-content: center; gap: .4rem; color: var(--sas-gray-400); font-size: var(--sas-fs-xs);
      border-top: 1px solid var(--sas-gray-100); padding-top: var(--sas-space-4); margin-top: var(--sas-space-4);
    }

    /* Reused elsewhere for a lighter-weight text composer bar (Quick parse / Symptom helper) */
    .sas-composer { display: flex; align-items: center; gap: .6rem; }
    .sas-composer__bar {
      flex: 1; display: flex; align-items: center; gap: .35rem; background: #fff;
      border: 1px solid var(--sas-gray-300); border-radius: 999px; padding: .3rem .4rem .3rem .5rem;
      transition: border-color .15s var(--sas-ease), box-shadow .15s var(--sas-ease);
    }
    .sas-composer__bar:focus-within { border-color: var(--sas-primary-400); box-shadow: 0 0 0 .2rem var(--sas-primary-100); }
    .sas-composer__input { flex: 1; border: 0; outline: none; background: transparent; font-size: var(--sas-fs-base); padding: .4rem .2rem; min-width: 0; }
    .sas-composer__input::placeholder { color: var(--sas-gray-400); }

    /* ---------------------------------------------------------------------
       Result cards (quick parse / symptom helper) — built client-side
       ------------------------------------------------------------------ */
    .sas-result-card__header { display: flex; align-items: flex-start; gap: .75rem; margin-bottom: 1rem; }
    .sas-result-card__title { font-weight: 700; font-size: var(--sas-fs-lg); color: var(--sas-gray-900); }
    .sas-result-card__subtitle { font-size: var(--sas-fs-xs); color: var(--sas-gray-500); }
    .sas-result-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: .9rem 1.25rem; margin: 0 0 1rem; }
    .sas-result-grid dt { font-size: var(--sas-fs-xs); font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--sas-gray-400); margin-bottom: .2rem; }
    .sas-result-grid dd { font-size: var(--sas-fs-sm); font-weight: 600; color: var(--sas-gray-800); margin: 0; }
    .sas-provider-chip {
      display: inline-flex; align-items: center; gap: .4rem; background: var(--sas-gray-50); border: 1px solid var(--sas-gray-200);
      border-radius: 999px; padding: .2rem .65rem .2rem .2rem; font-size: var(--sas-fs-xs); font-weight: 600; color: var(--sas-gray-700);
    }
    .sas-provider-chip__avatar {
      width: 20px; height: 20px; border-radius: 50%; background: var(--sas-primary-100); color: var(--sas-primary-700);
      display: grid; place-items: center; font-size: .6rem; font-weight: 700; flex-shrink: 0;
    }
    .sas-reasoning { margin-top: 1rem; border-top: 1px solid var(--sas-gray-100); padding-top: .75rem; }
    .sas-reasoning summary {
      cursor: pointer; font-size: var(--sas-fs-xs); font-weight: 700; color: var(--sas-gray-500);
      display: inline-flex; align-items: center; gap: .35rem; list-style: none;
    }
    .sas-reasoning summary::-webkit-details-marker { display: none; }
    .sas-reasoning summary::before { content: '\25B8'; display: inline-block; transition: transform .15s var(--sas-ease); font-size: .8em; }
    .sas-reasoning[open] summary::before { transform: rotate(90deg); }
    .sas-reasoning p { margin: .5rem 0 0; font-size: var(--sas-fs-sm); color: var(--sas-gray-600); font-style: italic; }

    .sas-inline-alert { display: flex; align-items: center; gap: .5rem; background: var(--sas-danger-subtle); color: var(--sas-danger-emphasis);
      border-radius: var(--sas-radius-md); padding: .6rem .8rem; font-size: var(--sas-fs-sm); font-weight: 500; }
    .sas-inline-skeleton { display: flex; flex-direction: column; gap: .5rem; padding: .25rem 0; }
  </style>
@endpush

@section('content')
  <div class="row justify-content-center"><div class="col-xl-9">

    {{-- Hero header --}}
    <div class="card sas-ai-hero mb-4">
      <div class="card-body p-4 p-lg-5">
        <div class="row align-items-center g-4">
          <div class="col-lg-8">
            <div class="d-flex align-items-start gap-3 mb-3">
              <span class="sas-ai-hero__icon"><i class="fi fi-rr-sparkles"></i></span>
              <div>
                <h1 class="sas-ai-hero__title mb-1">AI Scheduling Assistant</h1>
                <p class="sas-ai-hero__subtitle mb-0">Book, reschedule, or find the right specialist — in plain language.</p>
              </div>
            </div>
            <div class="sas-ai-safety">
              <span class="sas-ai-safety__icon"><i class="fi fi-rr-shield-check"></i></span>
              <div>
                <div class="sas-ai-safety__title">{{ $enabled ? 'AI provider: '.$provider : 'Rule-based engine' }}</div>
                <div class="sas-ai-safety__text">AI is assistive only — you confirm every slot.</div>
              </div>
            </div>
          </div>
          <div class="col-lg-4 d-none d-lg-block">
            <div class="sas-ai-mascot" aria-hidden="true">
              <div class="sas-ai-mascot__glow"></div>
              <i class="fi fi-rr-sparkles sas-ai-mascot__spark sas-ai-mascot__spark--1"></i>
              <i class="fi fi-rr-sparkles sas-ai-mascot__spark sas-ai-mascot__spark--2"></i>
              <x-ai-bot-svg class="sas-ai-mascot__bot" :detailed="true" />
              <span class="sas-ai-mascot__bubble sas-ai-mascot__bubble--1"><i class="fi fi-rr-calendar-check"></i></span>
              <span class="sas-ai-mascot__bubble sas-ai-mascot__bubble--2"><i class="fi fi-rr-users"></i></span>
              <span class="sas-ai-mascot__bubble sas-ai-mascot__bubble--3"><i class="fi fi-rr-check"></i></span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <ul class="nav sas-ai-tabs mb-4" id="aiTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="tab-chat-btn" data-bs-toggle="tab" data-bs-target="#tab-chat" type="button" role="tab" aria-controls="tab-chat" aria-selected="true">
          <i class="fi fi-rr-comment-dots" aria-hidden="true"></i> Chat assistant
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-parse-btn" data-bs-toggle="tab" data-bs-target="#tab-parse" type="button" role="tab" aria-controls="tab-parse" aria-selected="false">
          <i class="fi fi-rr-bolt" aria-hidden="true"></i> Quick parse
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-symptom-btn" data-bs-toggle="tab" data-bs-target="#tab-symptom" type="button" role="tab" aria-controls="tab-symptom" aria-selected="false">
          <i class="fi fi-rr-stethoscope" aria-hidden="true"></i> Symptom helper
        </button>
      </li>
    </ul>

    <div class="tab-content" id="aiTabsContent">
      {{-- Conversational chatbot --------------------------------------------- --}}
      <div class="tab-pane fade show active" id="tab-chat" role="tabpanel" aria-labelledby="tab-chat-btn" tabindex="0">
        <div class="card sas-chat-panel">
          <div id="chatLog" class="d-flex flex-column gap-3 mb-4" style="min-height:120px;max-height:440px;overflow-y:auto;" aria-live="polite" aria-label="Conversation with the AI assistant">
            <div class="sas-msg bot">
              <div class="sas-msg__avatar"><x-ai-bot-svg /></div>
              <div class="sas-msg__col">
                <div class="sas-msg__bubble">Hi! I'm your AI scheduling assistant. How can I help you today?</div>
                <span class="sas-msg__time" data-sas-now></span>
              </div>
            </div>
          </div>

          <div class="row g-3 mb-3" id="chatSuggestions">
            <div class="col-md-4">
              <button type="button" class="sas-suggest-tile" data-fill="I need a dentist next Tuesday afternoon">
                <span class="sas-icon-tile bg-primary-subtle text-primary"><i class="fi fi-rr-tooth" aria-hidden="true"></i></span>
                <span>
                  <span class="sas-suggest-tile__title text-primary">Book a dentist</span>
                  <span class="sas-suggest-tile__detail">next Tuesday afternoon</span>
                </span>
              </button>
            </div>
            <div class="col-md-4">
              <button type="button" class="sas-suggest-tile" data-fill="Reschedule my appointment to next week">
                <span class="sas-icon-tile bg-success-subtle text-success"><i class="fi fi-rr-calendar-check" aria-hidden="true"></i></span>
                <span>
                  <span class="sas-suggest-tile__title text-success">Reschedule</span>
                  <span class="sas-suggest-tile__detail">my appointment</span>
                </span>
              </button>
            </div>
            <div class="col-md-4">
              <button type="button" class="sas-suggest-tile" data-fill="Book a therapy session this Friday morning">
                <span class="sas-icon-tile" style="background:#F5F3FF;color:#7C3AED"><i class="fi fi-rr-brain" aria-hidden="true"></i></span>
                <span>
                  <span class="sas-suggest-tile__title" style="color:#7C3AED">Find a therapist</span>
                  <span class="sas-suggest-tile__detail">this Friday morning</span>
                </span>
              </button>
            </div>
          </div>

          <div class="d-flex flex-wrap gap-2 mb-4">
            <span class="sas-feature-pill"><i class="fi fi-rr-sparkles" aria-hidden="true"></i>Understands natural language</span>
            <span class="sas-feature-pill"><i class="fi fi-rr-calendar-check" aria-hidden="true"></i>Checks real-time availability</span>
            <span class="sas-feature-pill"><i class="fi fi-rr-users" aria-hidden="true"></i>Suggests best-matched providers</span>
            <span class="sas-feature-pill"><i class="fi fi-rr-shield-check" aria-hidden="true"></i>You confirm every step</span>
          </div>

          <div class="sas-composer-box">
            <div class="sas-composer-box__main">
              <input type="text" id="chatInput" class="sas-composer-box__input" placeholder="Type a request in plain language, e.g. &quot;book a dentist next Tuesday afternoon&quot; …" aria-label="Message the AI assistant">
              <button type="button" class="sas-composer-box__attach" id="chatAttach" disabled title="Attachments coming soon" aria-label="Attach a file (coming soon)">
                <i class="fi fi-rr-clip" aria-hidden="true"></i>
              </button>
            </div>
            <div class="sas-composer-box__actions">
              <button type="button" class="sas-composer__icon-btn" id="chatVoice" title="Speak your request" aria-label="Use voice input" aria-pressed="false">
                <i class="fi fi-rr-microphone" aria-hidden="true"></i>
              </button>
              <button class="btn btn-primary sas-send-btn" id="chatSend" title="Send" aria-label="Send message"><i class="fi fi-rr-paper-plane" aria-hidden="true"></i></button>
            </div>
          </div>
          <p class="sas-chat-footnote mb-0"><i class="fi fi-rr-lock" aria-hidden="true"></i> Your data is secure. AI responses are suggestions — you confirm every action.</p>
        </div>
      </div>

      {{-- One-shot quick parse ----------------------------------------------- --}}
      <div class="tab-pane fade" id="tab-parse" role="tabpanel" aria-labelledby="tab-parse-btn" tabindex="0">
        <x-card>
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="sas-icon-tile bg-warning-subtle text-warning"><i class="fi fi-rr-bolt" aria-hidden="true"></i></span>
            <p class="text-muted mb-0">Type a request in plain language, e.g. <em>"book a dentist next Tuesday afternoon"</em>.</p>
          </div>
          <div class="sas-composer mb-3">
            <div class="sas-composer__bar">
              <input type="text" id="nlInput" class="sas-composer__input ps-2" placeholder="book a therapy session next Monday morning" aria-label="Describe your booking request">
            </div>
            <button class="btn btn-primary sas-send-btn" id="nlGo" title="Interpret" aria-label="Interpret request"><i class="fi fi-rr-arrow-right" aria-hidden="true"></i></button>
          </div>
          <div id="nlResult" aria-live="polite"></div>
        </x-card>
      </div>

      {{-- Symptom routing ----------------------------------------------------- --}}
      <div class="tab-pane fade" id="tab-symptom" role="tabpanel" aria-labelledby="tab-symptom-btn" tabindex="0">
        <x-card>
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="sas-icon-tile bg-info-subtle text-info"><i class="fi fi-rr-stethoscope" aria-hidden="true"></i></span>
            <p class="text-muted mb-0">Describe your symptoms and we'll suggest the right kind of specialist.
              <span class="text-danger fw-semibold">Informational only — not a diagnosis.</span></p>
          </div>
          <div class="sas-composer mb-3">
            <div class="sas-composer__bar">
              <input type="text" id="symInput" class="sas-composer__input ps-2" placeholder="e.g. sore throat and fever for 3 days" aria-label="Describe your symptoms">
            </div>
            <button class="btn btn-primary sas-send-btn" id="symGo" title="Check" aria-label="Check symptoms"><i class="fi fi-rr-search" aria-hidden="true"></i></button>
          </div>
          <div id="symResult" aria-live="polite"></div>
        </x-card>
      </div>
    </div>
  </div></div>
@endsection

@push('scripts')
<script>
  const CSRF = document.querySelector('meta[name=csrf-token]').content;
  const BOOK_URL = '{{ route('booking.create') }}';
  const esc = s => (s || '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  const now = () => new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
  document.querySelectorAll('[data-sas-now]').forEach(el => el.textContent = now());

  function providerChips(providers) {
    if (!providers || !providers.length) return '';
    return '<div class="d-flex flex-wrap gap-2 mb-3">' + providers.map(p => {
      const initials = esc(p.name).split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
      return '<span class="sas-provider-chip"><span class="sas-provider-chip__avatar">' + initials + '</span>' + esc(p.name) + '</span>';
    }).join('') + '</div>';
  }

  function skeleton() {
    return '<div class="sas-inline-skeleton">' +
      '<div class="sas-skeleton" style="height:14px;width:40%"></div>' +
      '<div class="sas-skeleton" style="height:14px;width:70%"></div>' +
      '<div class="sas-skeleton" style="height:14px;width:55%"></div>' +
      '</div>';
  }

  function inlineError(message) {
    return '<div class="sas-inline-alert"><i class="fi fi-rr-triangle-warning" aria-hidden="true"></i>' + esc(message) + '</div>';
  }

  // Compact bot-face glyph for dynamically-appended messages — mirrors the
  // ai-bot-svg Blade component (solid fill instead of a gradient, since a
  // JS-built defs/gradient id would collide across repeated messages).
  const BOT_AVATAR_SVG = '<svg viewBox="0 0 160 160" role="img" aria-hidden="true" focusable="false">' +
    '<rect x="32" y="28" width="96" height="98" rx="32" fill="#ffffff" stroke="#E2E8F0" stroke-width="2"/>' +
    '<path d="M42 64 a38 38 0 0 1 76 0" fill="none" stroke="#2563EB" stroke-width="7" stroke-linecap="round"/>' +
    '<rect x="28" y="58" width="15" height="32" rx="7" fill="#2563EB"/>' +
    '<rect x="117" y="58" width="15" height="32" rx="7" fill="#2563EB"/>' +
    '<rect x="52" y="58" width="56" height="42" rx="18" fill="#2563EB"/>' +
    '<path d="M64 76 q6 8 12 0" fill="none" stroke="#ffffff" stroke-width="3.5" stroke-linecap="round"/>' +
    '<path d="M84 76 q6 8 12 0" fill="none" stroke="#ffffff" stroke-width="3.5" stroke-linecap="round"/>' +
    '</svg>';

  // --- Chat assistant -------------------------------------------------------
  const chatLog = document.getElementById('chatLog');
  const history = [];
  const TYPING = '<span class="sas-typing"><span></span><span></span><span></span></span>';
  function bubble(text, who, isHtml) {
    const wrap = document.createElement('div');
    wrap.className = 'sas-msg ' + (who === 'user' ? 'user' : 'bot');
    const avatar = who === 'user'
      ? '<div class="sas-msg__avatar"><i class="fi fi-rr-user" aria-hidden="true"></i></div>'
      : '<div class="sas-msg__avatar">' + BOT_AVATAR_SVG + '</div>';
    wrap.innerHTML = avatar +
      '<div class="sas-msg__col"><div class="sas-msg__bubble">' + (isHtml ? text : esc(text)) + '</div>' +
      '<span class="sas-msg__time">' + now() + '</span></div>';
    chatLog.appendChild(wrap);
    chatLog.scrollTop = chatLog.scrollHeight;
    return wrap.querySelector('.sas-msg__bubble');
  }
  function sendChat() {
    const input = document.getElementById('chatInput');
    const text = input.value.trim();
    if (!text) return;
    bubble(text, 'user');
    history.push({ role: 'user', content: text });
    input.value = '';
    const thinking = bubble(TYPING, 'bot', true);
    fetch('{{ route('ai.chat') }}', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
      body: JSON.stringify({ messages: history }),
    }).then(r => r.json()).then(data => {
      thinking.innerHTML = esc(data.reply);
      history.push({ role: 'assistant', content: data.reply });
      if (data.booking_link) {
        let html = '<div class="sas-chat-action-card">';
        html += providerChips(data.providers);
        html += '<a class="btn btn-sm btn-primary" href="' + data.booking_link + '"><i class="fi fi-rr-arrow-right me-1"></i>Continue to booking</a>';
        html += '</div>';
        bubble(html, 'bot', true);
      }
    }).catch(() => thinking.innerHTML = '<span class="text-danger">Sorry, something went wrong. Please try again.</span>');
  }
  document.getElementById('chatSend').addEventListener('click', sendChat);
  document.getElementById('chatInput').addEventListener('keydown', e => { if (e.key === 'Enter') sendChat(); });

  // Suggestion tiles → fill input and send
  document.querySelectorAll('#chatSuggestions .sas-suggest-tile').forEach(function (tile) {
    tile.addEventListener('click', function () {
      document.getElementById('chatInput').value = tile.dataset.fill;
      sendChat();
    });
  });

  // Voice input — pure front-end, graceful fallback where unsupported.
  // Fills the composer only; the user still reviews and presses Send.
  (function () {
    const btn = document.getElementById('chatVoice');
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRecognition) {
      btn.disabled = true;
      btn.title = 'Voice input is not supported in this browser';
      return;
    }
    const recognizer = new SpeechRecognition();
    recognizer.lang = 'en-US';
    recognizer.interimResults = false;
    recognizer.maxAlternatives = 1;
    let listening = false;
    recognizer.addEventListener('result', e => {
      const text = e.results[0][0].transcript;
      const input = document.getElementById('chatInput');
      input.value = (input.value ? input.value + ' ' : '') + text;
      input.focus();
    });
    recognizer.addEventListener('end', () => { listening = false; btn.classList.remove('is-active'); btn.setAttribute('aria-pressed', 'false'); });
    recognizer.addEventListener('error', () => { listening = false; btn.classList.remove('is-active'); btn.setAttribute('aria-pressed', 'false'); });
    btn.addEventListener('click', () => {
      if (listening) { recognizer.stop(); return; }
      listening = true;
      btn.classList.add('is-active');
      btn.setAttribute('aria-pressed', 'true');
      recognizer.start();
    });
  })();

  // --- Quick parse ----------------------------------------------------------
  document.getElementById('nlGo').addEventListener('click', function () {
    const text = document.getElementById('nlInput').value.trim();
    const box = document.getElementById('nlResult');
    if (!text) return;
    box.innerHTML = skeleton();
    fetch('{{ route('ai.parse') }}', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
      body: JSON.stringify({ text }),
    }).then(r => r.json()).then(data => {
      const i = data.intent;
      let html = '<div class="sas-result-card">';
      html += '<div class="sas-result-card__header">';
      html += '<span class="sas-icon-tile bg-primary-subtle text-primary"><i class="fi fi-rr-check-circle" aria-hidden="true"></i></span>';
      html += '<div><div class="sas-result-card__title">Interpreted request</div><div class="sas-result-card__subtitle">Review before continuing to booking</div></div>';
      html += '</div>';
      html += '<dl class="sas-result-grid">';
      html += '<div><dt>Service</dt><dd>' + esc(data.service ? data.service.name : (i.specialty || '—')) + '</dd></div>';
      html += '<div><dt>Date</dt><dd>' + esc(i.date || 'Any') + '</dd></div>';
      html += '<div><dt>Time of day</dt><dd>' + esc(i.period || 'Any') + '</dd></div>';
      html += '<div><dt>Urgency</dt><dd><span class="badge badge-light-' + (i.urgency === 'urgent' ? 'danger' : 'secondary') + '">' + esc(i.urgency || 'Routine') + '</span></dd></div>';
      html += '</dl>';
      html += providerChips(data.providers);
      const params = new URLSearchParams();
      if (data.service) params.set('service', data.service.id);
      if (i.date) params.set('date', i.date);
      html += '<a class="btn btn-sm btn-primary" href="' + BOOK_URL + '?' + params.toString() + '"><i class="fi fi-rr-arrow-right me-1"></i>Continue to booking</a>';
      if (i.note) {
        html += '<details class="sas-reasoning"><summary>How I understood this</summary><p>' + esc(i.note) + '</p></details>';
      }
      html += '</div>';
      box.innerHTML = html;
    }).catch(() => box.innerHTML = inlineError('Could not interpret that. Try rephrasing.'));
  });
  document.getElementById('nlInput').addEventListener('keydown', e => { if (e.key === 'Enter') document.getElementById('nlGo').click(); });

  // --- Symptom helper -------------------------------------------------------
  document.getElementById('symGo').addEventListener('click', function () {
    const text = document.getElementById('symInput').value.trim();
    const box = document.getElementById('symResult');
    if (!text) return;
    box.innerHTML = skeleton();
    fetch('{{ route('ai.symptoms') }}', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
      body: JSON.stringify({ text }),
    }).then(r => r.json()).then(data => {
      const colors = { urgent: 'danger', soon: 'warning', routine: 'success' };
      const labels = { urgent: 'Urgent — act now', soon: 'See a clinician soon', routine: 'Routine' };
      const uc = colors[data.urgency] || 'secondary';
      let html = '<div class="sas-result-card">';

      html += '<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">';
      html += '<div class="sas-result-card__title" style="font-size:var(--sas-fs-base)">Suggested specialty: <span class="text-primary">' + esc(data.specialty || '—') + '</span></div>';
      html += '<span class="badge badge-light-' + uc + ' px-3 py-2"><i class="fi fi-rr-siren-on me-1" aria-hidden="true"></i>' + esc(labels[data.urgency] || data.urgency) + '</span>';
      html += '</div>';

      if (data.why_serious) {
        html += '<div class="d-flex gap-2 mb-3">';
        html += '<span class="sas-icon-tile bg-' + uc + '-subtle text-' + uc + '" style="width:30px;height:30px;font-size:.85rem"><i class="fi fi-rr-triangle-warning" aria-hidden="true"></i></span>';
        html += '<div><div class="fw-semibold small text-uppercase text-muted">Why this matters</div>' + esc(data.why_serious) + '</div>';
        html += '</div>';
      }

      if (data.can_lead_to) {
        html += '<div class="d-flex gap-2 mb-3">';
        html += '<span class="sas-icon-tile bg-' + uc + '-subtle text-' + uc + '" style="width:30px;height:30px;font-size:.85rem"><i class="fi fi-rr-arrow-trend-up" aria-hidden="true"></i></span>';
        html += '<div><div class="fw-semibold small text-uppercase text-muted">If left untreated, it can lead to</div>' + esc(data.can_lead_to) + '</div>';
        html += '</div>';
      }

      if (data.urgency === 'urgent') {
        html += '<div class="alert alert-danger d-flex align-items-center gap-2 py-2 mb-3">';
        html += '<i class="fi fi-rr-ambulance" aria-hidden="true"></i><div class="small mb-0">If symptoms are severe or worsening, seek emergency care immediately — don\'t wait for an appointment.</div>';
        html += '</div>';
      }

      if (data.advice) html += '<p class="small text-muted fst-italic">' + esc(data.advice) + '</p>';

      html += providerChips(data.providers);
      html += '<a class="btn btn-sm btn-primary" href="' + BOOK_URL + '"><i class="fi fi-rr-calendar-plus me-1" aria-hidden="true"></i>Book an appointment</a>';
      html += '</div>';
      box.innerHTML = html;
    }).catch(() => box.innerHTML = inlineError('Could not check those symptoms. Try rephrasing.'));
  });
  document.getElementById('symInput').addEventListener('keydown', e => { if (e.key === 'Enter') document.getElementById('symGo').click(); });
</script>
@endpush
