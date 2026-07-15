<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="theme-color" content="#5955D1">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'Sign In') | {{ config('app.name') }}</title>
  <link rel="icon" type="image/png" href="{{ asset('assets/images/favicon.png') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com/">
  <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400..800;1,400..800&amp;display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/2.6.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
  <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/sas-ui.css') }}">
  <style>
    body { background: var(--sas-gray-50); }
    .sas-auth-shell { min-height: 100vh; display: flex; }
    .sas-auth-card .card { box-shadow: var(--sas-shadow-lg); border-radius: var(--sas-radius-xl); }
    .sas-auth-brand {
      flex: 0 0 44%;
      max-width: 560px;
      color: #fff;
      position: relative;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding: 3rem;
      background: linear-gradient(145deg, var(--sas-primary-500) 0%, #7b78e0 55%, #9b7be0 100%);
    }
    .sas-auth-brand::after {
      content: ''; position: absolute; right: -60px; top: -60px;
      width: 280px; height: 280px; border-radius: 50%; background: rgba(255,255,255,.10);
    }
    .sas-auth-brand::before {
      content: ''; position: absolute; left: -80px; bottom: -100px;
      width: 320px; height: 320px; border-radius: 50%; background: rgba(255,255,255,.08);
    }
    .sas-auth-brand > * { position: relative; z-index: 1; }
    .sas-auth-brand__logo { display: flex; align-items: center; gap: .65rem; font-weight: 700; font-size: 1.15rem; }
    .sas-auth-brand__logo img { height: 30px; }
    .sas-auth-feature { display: flex; align-items: flex-start; gap: .75rem; margin-bottom: 1.1rem; }
    .sas-auth-feature i {
      width: 34px; height: 34px; flex: 0 0 34px; border-radius: var(--sas-radius-md);
      background: rgba(255,255,255,.16); display: flex; align-items: center; justify-content: center;
    }
    .sas-auth-panel { flex: 1; display: flex; align-items: center; justify-content: center; padding: 2rem 1rem; }
    .sas-auth-card { width: 100%; max-width: 420px; }
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
        <h2 class="fw-bold mb-3">Run a calmer, fuller schedule.</h2>
        <div class="sas-auth-feature">
          <i class="fi fi-rr-calendar-clock"></i>
          <div>
            <div class="fw-semibold">Smart scheduling</div>
            <div class="text-white-50 small">Calendar, waitlist, and walk-ins in one view.</div>
          </div>
        </div>
        <div class="sas-auth-feature">
          <i class="fi fi-rr-bell"></i>
          <div>
            <div class="fw-semibold">Fewer no-shows</div>
            <div class="text-white-50 small">Automated reminders across email and WhatsApp.</div>
          </div>
        </div>
        <div class="sas-auth-feature">
          <i class="fi fi-rr-chart-pie-alt"></i>
          <div>
            <div class="fw-semibold">Insights that matter</div>
            <div class="text-white-50 small">Fill rate, no-show risk, and channel performance at a glance.</div>
          </div>
        </div>
      </div>

      <div class="text-white-50 small">&copy; {{ date('Y') }} {{ config('app.name') }}</div>
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
