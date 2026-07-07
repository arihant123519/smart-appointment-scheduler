@php
  $r = fn ($pattern) => request()->routeIs($pattern) ? 'active' : '';
@endphp
<aside class="sas-sidebar" id="sasSidebar">
  <a href="{{ route('dashboard') }}" class="sas-sidebar__brand">
    <img src="{{ asset('assets/images/logo.svg') }}" alt="logo" onerror="this.style.display='none'">
    <span>Scheduler</span>
  </a>

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
      <div class="sas-nav__section">Scheduling</div>
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
    @endcanany

    @canany(['manage patients', 'manage providers', 'manage services'])
      <div class="sas-nav__section">Management</div>
      @can('manage patients')
        <a href="{{ route('patients.index') }}" class="sas-nav__link {{ $r('patients.*') }}">
          <i class="fi fi-rr-users"></i> Patients
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
      @endcan
    @endcanany

    @canany(['view reports', 'view billing'])
      <div class="sas-nav__section">Insights</div>
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
    @endcanany

    @canany(['manage users', 'manage roles', 'manage clinics', 'view audit logs', 'manage settings'])
      <div class="sas-nav__section">Administration</div>
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
        <i class="fi fi-sr-exit"></i> Log Out
      </button>
    </form>
  </nav>
</aside>
