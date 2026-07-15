@php
  $r = fn ($pattern) => request()->routeIs($pattern) ? 'active' : '';
  $sasClinic = auth()->user()?->clinic;
  // A section stays expanded (ignoring any stored collapsed state) if the
  // current page lives inside it, so navigating never hides the active link.
  $sasOpen = fn (array $patterns) => collect($patterns)->contains(fn ($p) => request()->routeIs($p)) ? '1' : '0';
@endphp
<aside class="sas-sidebar" id="sasSidebar">
  <a href="{{ route('dashboard') }}" class="sas-sidebar__brand">
    <img src="{{ $sasClinic?->logo_url ?? asset('assets/images/logo.svg') }}" alt="logo" onerror="this.style.display='none'">
    <span>{{ $sasClinic?->name ?? 'Scheduler' }}</span>
  </a>

  <div class="sas-sidebar__identity">
    <img src="{{ auth()->user()->avatar_url }}" alt="" class="sas-avatar sas-avatar-sm" width="32" height="32">
    <div class="sas-sidebar__identity-text">
      <div class="sas-sidebar__identity-name">{{ auth()->user()->name }}</div>
      <div class="sas-sidebar__identity-role">{{ auth()->user()->roles->first()?->name ? ucwords(str_replace('_', ' ', auth()->user()->roles->first()->name)) : 'User' }}</div>
    </div>
  </div>

  <div class="px-3 pt-3">
    <button type="button" class="sas-nav__search" data-sas-cmdk-open>
      <i class="fi fi-rr-search"></i> Quick find
      <span class="sas-nav__kbd">Ctrl K</span>
    </button>
  </div>

  <nav class="sas-nav">
    <a href="{{ route('dashboard') }}" class="sas-nav__link {{ $r('dashboard') }}">
      <i class="fi fi-rr-apps"></i> Dashboard
    </a>

    @if (auth()->user()->hasRole('patient') && ! auth()->user()->hasAnyRole(['front_desk', 'provider', 'clinic_admin', 'system_admin', 'billing']))
      <a href="{{ route('booking.create') }}" class="sas-nav__link {{ $r('booking.*') }}">
        <i class="fi fi-rr-calendar-plus"></i> Book Appointment
      </a>
    @endif

    <a href="{{ route('ai.booking') }}" class="sas-nav__link {{ $r('ai.*') }}">
      <i class="fi fi-rr-comment-alt"></i> AI Assistant
    </a>

    @canany(['view appointments', 'manage appointments'])
      @php $open = $sasOpen(['calendar', 'appointments.*', 'waitlist.*', 'walkins.*', 'announcements.*', 'flows.*']); @endphp
      <button type="button" class="sas-nav__section sas-nav__section--toggle" data-bs-toggle="collapse" data-bs-target="#navSecScheduling" data-sas-section="scheduling" aria-expanded="{{ $open === '1' ? 'true' : 'false' }}">
        Scheduling <i class="fi fi-rr-angle-small-down sas-nav__chevron"></i>
      </button>
      <div class="collapse {{ $open === '1' ? 'show' : '' }}" id="navSecScheduling" data-sas-force-open="{{ $open }}">
        <a href="{{ route('calendar') }}" class="sas-nav__link {{ $r('calendar') }}">
          <i class="fi fi-rr-calendar"></i> Calendar
        </a>
        <a href="{{ route('appointments.index') }}" class="sas-nav__link {{ $r('appointments.*') }}">
          <i class="fi fi-rr-clock"></i> Appointments
        </a>
        @can('manage waitlist')
          <a href="{{ route('waitlist.index') }}" class="sas-nav__link {{ $r('waitlist.*') }}">
            <i class="fi fi-rr-list"></i> Waitlist
          </a>
        @endcan
        @can('manage walk_in_queue')
          <a href="{{ route('walkins.index') }}" class="sas-nav__link {{ $r('walkins.*') }}">
            <i class="fi fi-rr-users"></i> Walk-in Queue
          </a>
        @endcan
        @can('manage reminders')
          <a href="{{ route('announcements.index') }}" class="sas-nav__link {{ $r('announcements.*') }}">
            <i class="fi fi-rr-megaphone"></i> Broadcast
          </a>
        @endcan
        @can('manage flows')
          <a href="{{ route('flows.index') }}" class="sas-nav__link {{ $r('flows.*') }}">
            <i class="fi fi-brands-whatsapp"></i> WhatsApp Flows
          </a>
        @endcan
      </div>
    @endcanany

    @canany(['manage patients', 'manage providers', 'manage services'])
      @php $open = $sasOpen(['patients.*', 'referrals.*', 'providers.*', 'services.*', 'qrcodes.*']); @endphp
      <button type="button" class="sas-nav__section sas-nav__section--toggle" data-bs-toggle="collapse" data-bs-target="#navSecManagement" data-sas-section="management" aria-expanded="{{ $open === '1' ? 'true' : 'false' }}">
        Management <i class="fi fi-rr-angle-small-down sas-nav__chevron"></i>
      </button>
      <div class="collapse {{ $open === '1' ? 'show' : '' }}" id="navSecManagement" data-sas-force-open="{{ $open }}">
        @can('manage patients')
          <a href="{{ route('patients.index') }}" class="sas-nav__link {{ $r('patients.*') }}">
            <i class="fi fi-rr-users"></i> Patients
          </a>
          <a href="{{ route('referrals.index') }}" class="sas-nav__link {{ $r('referrals.*') }}">
            <i class="fi fi-rr-share"></i> Referrals
          </a>
        @endcan
        @can('manage providers')
          <a href="{{ route('providers.index') }}" class="sas-nav__link {{ $r('providers.*') }}">
            <i class="fi fi-rr-user-md"></i> Providers
          </a>
        @endcan
        @can('manage services')
          <a href="{{ route('services.index') }}" class="sas-nav__link {{ $r('services.*') }}">
            <i class="fi fi-rr-symbol"></i> Services
          </a>
          <a href="{{ route('qrcodes.index') }}" class="sas-nav__link {{ $r('qrcodes.*') }}">
            <i class="fi fi-rr-qrcode"></i> QR Codes
          </a>
        @endcan
      </div>
    @endcanany

    @canany(['view reports', 'view billing'])
      @php $open = $sasOpen(['reports.*', 'reviews.*', 'payments.*']); @endphp
      <button type="button" class="sas-nav__section sas-nav__section--toggle" data-bs-toggle="collapse" data-bs-target="#navSecInsights" data-sas-section="insights" aria-expanded="{{ $open === '1' ? 'true' : 'false' }}">
        Insights <i class="fi fi-rr-angle-small-down sas-nav__chevron"></i>
      </button>
      <div class="collapse {{ $open === '1' ? 'show' : '' }}" id="navSecInsights" data-sas-force-open="{{ $open }}">
        @can('view reports')
          <a href="{{ route('reports.index') }}" class="sas-nav__link {{ $r('reports.*') }}">
            <i class="fi fi-rr-chart-pie-alt"></i> Reports
          </a>
          <a href="{{ route('reviews.index') }}" class="sas-nav__link {{ $r('reviews.index') }}">
            <i class="fi fi-rr-star"></i> Reviews
          </a>
        @endcan
        @can('view billing')
          <a href="{{ route('payments.index') }}" class="sas-nav__link {{ $r('payments.*') }}">
            <i class="fi fi-rr-usd-circle"></i> Billing
          </a>
        @endcan
      </div>
    @endcanany

    @canany(['manage users', 'manage roles', 'manage clinics', 'view audit logs', 'manage settings'])
      @php $open = $sasOpen(['users.*', 'roles.*', 'clinics.*', 'audit.*', 'settings.*']); @endphp
      <button type="button" class="sas-nav__section sas-nav__section--toggle" data-bs-toggle="collapse" data-bs-target="#navSecAdministration" data-sas-section="administration" aria-expanded="{{ $open === '1' ? 'true' : 'false' }}">
        Administration <i class="fi fi-rr-angle-small-down sas-nav__chevron"></i>
      </button>
      <div class="collapse {{ $open === '1' ? 'show' : '' }}" id="navSecAdministration" data-sas-force-open="{{ $open }}">
        @can('manage users')
          <a href="{{ route('users.index') }}" class="sas-nav__link {{ $r('users.*') }}">
            <i class="fi fi-rr-users-alt"></i> Users
          </a>
        @endcan
        @can('manage roles')
          <a href="{{ route('roles.index') }}" class="sas-nav__link {{ $r('roles.*') }}">
            <i class="fi fi-rr-shield-check"></i> Roles &amp; Permissions
          </a>
        @endcan
        @can('manage clinics')
          <a href="{{ route('clinics.index') }}" class="sas-nav__link {{ $r('clinics.*') }}">
            <i class="fi fi-rr-building"></i> Clinics
          </a>
        @endcan
        @can('view audit logs')
          <a href="{{ route('audit.index') }}" class="sas-nav__link {{ $r('audit.*') }}">
            <i class="fi fi-rr-document"></i> Audit Logs
          </a>
        @endcan
        @can('manage settings')
          <a href="{{ route('settings.integrations.edit') }}" class="sas-nav__link {{ $r('settings.*') }}">
            <i class="fi fi-rr-settings"></i> Integrations
          </a>
        @endcan
      </div>
    @endcanany

    <div class="sas-nav__section">Account</div>
    <a href="{{ route('notifications.index') }}" class="sas-nav__link {{ $r('notifications.*') }}">
      <i class="fi fi-rr-bell"></i> Notifications
      @php $unread = auth()->user()->unreadNotifications()->count(); @endphp
      @if ($unread)<span class="badge bg-danger">{{ $unread }}</span>@endif
    </a>
    <a href="{{ route('profile.edit') }}" class="sas-nav__link {{ $r('profile.*') }}">
      <i class="fi fi-rr-user"></i> My Profile
    </a>
    <form method="POST" action="{{ route('logout') }}">
      @csrf
      <button type="submit" class="sas-nav__link border-0 bg-transparent w-100 text-danger">
        <i class="fi fi-rr-exit"></i> Log Out
      </button>
    </form>
  </nav>
</aside>

{{-- Quick-find command palette (Ctrl/Cmd+K). Purely a client-side shortcut
     over the links already rendered above — no new permission surface, it
     only ever indexes what this user's own sidebar already shows them. --}}
<div class="modal fade" id="sasCmdPalette" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="p-2 border-bottom">
        <input type="text" class="form-control form-control-lg border-0" id="sasCmdInput" placeholder="Search pages…" autocomplete="off">
      </div>
      <div class="list-group list-group-flush" id="sasCmdResults" style="max-height:360px;overflow-y:auto"></div>
    </div>
  </div>
</div>

<script>
  (function () {
    const sidebar = document.getElementById('sasSidebar');

    // --- Collapsible nav sections, state persisted per user in localStorage.
    sidebar.querySelectorAll('[data-sas-section]').forEach(function (btn) {
      const key = 'sas_nav_collapsed_' + btn.dataset.sasSection;
      const target = document.querySelector(btn.getAttribute('data-bs-target'));
      if (!target) return;
      // The active section always stays open regardless of stored state.
      if (target.dataset.sasForceOpen !== '1' && localStorage.getItem(key) === '1') {
        target.classList.remove('show');
        btn.setAttribute('aria-expanded', 'false');
      }
      target.addEventListener('hidden.bs.collapse', () => localStorage.setItem(key, '1'));
      target.addEventListener('shown.bs.collapse', () => localStorage.setItem(key, '0'));
    });

    // --- Command palette --------------------------------------------------
    // Note: this script runs near the top of <body> (inside the sidebar
    // partial), before assets/libs/global/global.min.js (which provides
    // window.bootstrap) loads near the bottom. So the Bootstrap Modal
    // instance is resolved lazily, at the moment it's actually opened,
    // rather than up front here — by then bootstrap has always loaded.
    const openBtn = document.querySelector('[data-sas-cmdk-open]');
    const modalEl = document.getElementById('sasCmdPalette');
    const input = document.getElementById('sasCmdInput');
    const results = document.getElementById('sasCmdResults');
    if (!modalEl) return;

    function openPalette() {
      if (!window.bootstrap || !bootstrap.Modal) return;
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    function index() {
      return Array.from(sidebar.querySelectorAll('a.sas-nav__link[href]')).map(function (a) {
        return { label: a.textContent.trim().replace(/\s+/g, ' '), href: a.getAttribute('href'), icon: (a.querySelector('i') || {}).className || 'fi fi-rr-arrow-right' };
      });
    }

    function render(items) {
      results.innerHTML = '';
      if (!items.length) {
        results.innerHTML = '<div class="text-center text-muted small py-4">No matching pages</div>';
        return;
      }
      items.slice(0, 8).forEach(function (item, i) {
        const a = document.createElement('a');
        a.href = item.href;
        a.className = 'list-group-item list-group-item-action d-flex align-items-center gap-2' + (i === 0 ? ' active' : '');
        a.innerHTML = '<i class="' + item.icon + '"></i> ' + item.label;
        results.appendChild(a);
      });
    }

    function filter() {
      const q = input.value.trim().toLowerCase();
      const all = index();
      render(q ? all.filter(function (i) { return i.label.toLowerCase().includes(q); }) : all);
    }

    openBtn && openBtn.addEventListener('click', openPalette);
    document.addEventListener('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); openPalette(); }
    });
    modalEl.addEventListener('shown.bs.modal', function () { input.value = ''; filter(); input.focus(); });
    input.addEventListener('input', filter);
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        const first = results.querySelector('a');
        if (first) window.location = first.getAttribute('href');
      }
    });
  })();
</script>
