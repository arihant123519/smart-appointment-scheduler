<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="theme-color" content="#2563EB">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'Sign In') | {{ config('app.name') }}</title>
  <link rel="icon" type="image/png" href="{{ asset('assets/images/favicon.png') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com/">
  <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400..800;1,400..800&amp;display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/2.6.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
  <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/sas-ui.css') }}?v={{ filemtime(public_path('assets/css/sas-ui.css')) }}">
  <style>
    body { background: var(--sas-gray-25, #fff); }
    .sas-auth-shell { min-height: 100vh; display: flex; }

    /* Branding panel — light, airy, brand-accent-on-white rather than a
       solid brand-color fill, closer to how the app's own canvas reads. */
    .sas-auth-brand {
      flex: 0 0 46%;
      max-width: 620px;
      position: relative;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      justify-content: center;
      gap: 2.5rem;
      padding: 3.5rem 3rem;
      background:
        radial-gradient(ellipse 60% 50% at 100% 0%, var(--sas-primary-100), transparent 60%),
        var(--sas-gray-25, #fbfcfe);
      border-right: 1px solid var(--sas-gray-100);
    }
    .sas-auth-brand > * { position: relative; z-index: 1; }
    .sas-auth-brand__logo { display: flex; align-items: center; gap: .65rem; font-weight: 700; font-size: 1.15rem; color: var(--sas-gray-900); }
    .sas-auth-brand__logo img { height: 30px; }
    .sas-auth-brand__headline { font-size: 2.5rem; font-weight: 800; letter-spacing: -.02em; line-height: 1.15; color: var(--sas-gray-900); margin-bottom: 2rem; }
    .sas-auth-brand__headline em { font-style: normal; color: var(--sas-primary-600); }
    .sas-auth-feature { display: flex; align-items: flex-start; gap: .85rem; margin-bottom: 1.35rem; }
    .sas-auth-feature:last-child { margin-bottom: 0; }
    .sas-auth-feature i {
      width: 40px; height: 40px; flex: 0 0 40px; border-radius: var(--sas-radius-md);
      display: flex; align-items: center; justify-content: center; font-size: 1.1rem;
    }
    .sas-auth-feature .fw-semibold { color: var(--sas-gray-900); }
    .sas-auth-brand__footer { color: var(--sas-gray-400); }

    .sas-auth-panel { flex: 1; display: flex; align-items: center; justify-content: center; padding: 2rem 1rem; background: #fff; }
    .sas-auth-card { width: 100%; max-width: 440px; }
    .sas-auth-card .card { box-shadow: var(--sas-shadow-lg); border-radius: var(--sas-radius-xl); border: 1px solid var(--sas-gray-100); }
    @media (max-width: 991.98px) {
      .sas-auth-brand { display: none; }
    }
  </style>
  @stack('styles')
</head>
<body>
  <div class="sas-auth-shell">
    <aside class="sas-auth-brand">
      <div class="sas-auth-brand__logo">
        <img src="{{ asset('assets/images/logo.svg') }}" alt="" onerror="this.style.display='none'">
        {{ config('app.name') }}
      </div>

      <div>
        <h2 class="sas-auth-brand__headline">Run a calmer,<br><em>fuller schedule.</em></h2>
        <div class="sas-auth-feature">
          <i class="fi fi-rr-calendar-clock bg-primary-subtle text-primary"></i>
          <div>
            <div class="fw-semibold">Smart scheduling</div>
            <div class="text-muted small">Calendar, waitlist, and walk-ins in one view.</div>
          </div>
        </div>
        <div class="sas-auth-feature">
          <i class="fi fi-rr-bell bg-success-subtle text-success"></i>
          <div>
            <div class="fw-semibold">Fewer no-shows</div>
            <div class="text-muted small">Automated reminders across email and WhatsApp.</div>
          </div>
        </div>
        <div class="sas-auth-feature">
          <i class="fi fi-rr-chart-pie-alt bg-accent-subtle text-accent"></i>
          <div>
            <div class="fw-semibold">Insights that matter</div>
            <div class="text-muted small">Fill rate, no-show risk, and channel performance at a glance.</div>
          </div>
        </div>
      </div>

      @include('layouts.partials.auth-preview')

      <div class="sas-auth-brand__footer small">&copy; {{ date('Y') }} {{ config('app.name') }}</div>
    </aside>

    <div class="sas-auth-panel">
      <div class="sas-auth-card">
        <div class="d-lg-none text-center mb-4">
          <img src="{{ asset('assets/images/logo.svg') }}" alt="" height="32" onerror="this.style.display='none'">
          <h5 class="mt-2 mb-0">{{ config('app.name') }}</h5>
        </div>

        <div class="card sas-card p-4 p-sm-5">
          @if ($errors->any())
            <div class="alert alert-danger">
              <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
          @endif

          @yield('content')
        </div>
      </div>
    </div>
  </div>
  <script src="{{ asset('assets/libs/global/global.min.js') }}"></script>
  @stack('scripts')
</body>
</html>
