<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="theme-color" content="#5955D1">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Dashboard') | {{ config('app.name') }}</title>

  <link rel="icon" type="image/png" href="{{ asset('assets/images/favicon.png') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com/">
  <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:ital,wght@0,400..700;1,400..700&amp;display=swap" rel="stylesheet">

  {{-- Icon fonts (CDN — local mirror webfonts were incomplete) --}}
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/2.6.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/2.6.0/uicons-solid-rounded/css/uicons-solid-rounded.css">
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/2.6.0/uicons-bold-rounded/css/uicons-bold-rounded.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">

  <link rel="stylesheet" href="{{ asset('assets/libs/flatpickr/flatpickr.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/libs/datatables/datatables.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/app-shell.css') }}">
  @stack('styles')
</head>
<body>
  <div class="sas-layout">
    @include('partials.sidebar')
    <div class="sas-backdrop" id="sasBackdrop"></div>

    <div class="sas-main">
      @include('partials.topbar')

      <main class="sas-content">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
          <div>
            <h1 class="sas-page-title">@yield('page_title', View::yieldContent('title', 'Dashboard'))</h1>
            @hasSection('breadcrumb')
              <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">@yield('breadcrumb')</ol>
              </nav>
            @endif
          </div>
          <div>@yield('page_actions')</div>
        </div>

        {{-- session success/error are surfaced as toasts (see bottom of layout) --}}
        @if ($errors->any())
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0 ps-3">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        @endif

        @yield('content')
      </main>

      <footer class="text-center text-muted small py-3 border-top">
        &copy; {{ date('Y') }} {{ config('app.name') }} &mdash; built on Laravel {{ app()->version() }}
      </footer>
    </div>
  </div>

  {{-- Global AI navigation assistant (floating) --}}
  @auth
    @include('partials.ai-assistant')
  @endauth

  {{-- Toast container --}}
  <div class="toast-container position-fixed top-0 end-0 p-3" id="sasToasts" style="z-index:1090"></div>

  @php
    // Build the list of toasts to pop on load.
    $sasToastPayload = [];
    if (session('success')) { $sasToastPayload[] = ['title' => 'Success', 'body' => session('success'), 'variant' => 'success']; }
    if (session('error')) { $sasToastPayload[] = ['title' => 'Heads up', 'body' => session('error'), 'variant' => 'danger']; }

    // Today's appointments for the current user (role-aware), shown once per session.
    $sasUser = auth()->user();
    $sasTodayToast = null;
    if ($sasUser) {
        if ($sasUser->hasRole('patient') && ! $sasUser->hasAnyRole(['front_desk','provider','clinic_admin','system_admin','billing'])) {
            $appt = \App\Models\Appointment::with('provider.user','service')->where('patient_id',$sasUser->id)->whereDate('start_at',today())->active()->orderBy('start_at')->first();
            if ($appt) { $sasTodayToast = ['title' => 'You have an appointment today', 'body' => $appt->start_at->format('g:i A').' with '.$appt->provider->name, 'variant' => 'primary']; }
        } elseif ($sasUser->provider) {
            $c = \App\Models\Appointment::where('provider_id',$sasUser->provider->id)->whereDate('start_at',today())->active()->count();
            if ($c) { $sasTodayToast = ['title' => "You have {$c} appointment(s) today", 'body' => 'Check your calendar for details.', 'variant' => 'primary']; }
        } elseif ($sasUser->can('view appointments')) {
            $c = \App\Models\Appointment::forCurrentClinic()->whereDate('start_at',today())->active()->count();
            if ($c) { $sasTodayToast = ['title' => "{$c} appointment(s) scheduled today", 'body' => 'See the dashboard for the full list.', 'variant' => 'primary']; }
        }
    }

    // Recent unread notifications for new-notification toasts.
    $sasUnread = $sasUser ? $sasUser->unreadNotifications()->latest()->limit(3)->get()->map(fn($n) => [
        'id' => $n->id,
        'title' => $n->data['title'] ?? 'Notification',
        'body' => $n->data['body'] ?? '',
    ])->values() : collect();
  @endphp
  <script>
    window.SAS_TOASTS = @json($sasToastPayload);
    window.SAS_TODAY_TOAST = @json($sasTodayToast);
    window.SAS_UNREAD = @json($sasUnread);
    window.SAS_IS_FRONTDESK = @json($sasUser ? $sasUser->hasRole('front_desk') : false);
    window.SAS_FEED_URL = @json($sasUser ? route('notifications.feed') : null);
  </script>

  <script src="{{ asset('assets/libs/global/global.min.js') }}"></script>
  <script src="{{ asset('assets/libs/flatpickr/flatpickr.min.js') }}"></script>
  <script src="{{ asset('assets/libs/datatables/datatables.min.js') }}"></script>
  <script>
    // Initialise DataTables on any table.datatable that actually has data rows.
    // (Tables showing only an empty "no records" colspan row are skipped so the
    //  friendly empty message is preserved and DataTables doesn't mis-count columns.)
    (function () {
      if (typeof window.jQuery === 'undefined' || !jQuery.fn.DataTable) return;
      jQuery(function ($) {
        $('table.datatable').each(function () {
          if ($(this).find('tbody td[colspan]').length) return;
          $(this).DataTable({
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            order: [],
            language: { search: '', searchPlaceholder: 'Search…' },
          });
        });
      });
    })();
  </script>
  <script>
    // --- Toast helper --------------------------------------------------------
    (function () {
      const container = document.getElementById('sasToasts');
      if (!container) return;

      window.sasToast = function (title, body, variant) {
        variant = variant || 'primary';
        const el = document.createElement('div');
        el.className = 'toast align-items-start border-0 shadow-sm mb-2';
        el.setAttribute('role', 'alert');
        el.innerHTML =
          '<div class="toast-header">' +
            '<span class="badge bg-' + variant + ' rounded-circle p-1 me-2">&nbsp;</span>' +
            '<strong class="me-auto">' + title + '</strong>' +
            '<small class="text-muted">now</small>' +
            '<button type="button" class="btn-close ms-2" data-bs-dismiss="toast"></button>' +
          '</div>' +
          (body ? '<div class="toast-body">' + body + '</div>' : '');
        container.appendChild(el);
        if (window.bootstrap && bootstrap.Toast) {
          const t = new bootstrap.Toast(el, { delay: 6000 });
          t.show();
          el.addEventListener('hidden.bs.toast', () => el.remove());
        } else {
          el.classList.add('show');
          setTimeout(() => el.remove(), 6000);
        }
      };

      // 1) Flash messages
      (window.SAS_TOASTS || []).forEach(t => sasToast(t.title, t.body, t.variant));

      // 2) Today's appointment — once per browser session
      if (window.SAS_TODAY_TOAST) {
        const stamp = 'sas_today_' + new Date().toISOString().slice(0, 10);
        if (!sessionStorage.getItem(stamp)) {
          sasToast(SAS_TODAY_TOAST.title, SAS_TODAY_TOAST.body, SAS_TODAY_TOAST.variant);
          sessionStorage.setItem(stamp, '1');
        }
      }

      // 3) New unread notifications — only ones not seen before on this device
      (window.SAS_UNREAD || []).forEach(n => {
        const k = 'sas_notif_' + n.id;
        if (!localStorage.getItem(k)) {
          sasToast(n.title, n.body, 'info');
          localStorage.setItem(k, '1');
        }
      });
    })();
  </script>
  <script>
    // --- Real-time notification poller (front desk) --------------------------
    // Front-desk staff get a live toast + bell update whenever anyone in their
    // clinic changes an appointment's status. Reuses the same per-id localStorage
    // keys as the on-load toasts so nothing double-fires.
    (function () {
      if (!window.SAS_IS_FRONTDESK || !window.SAS_FEED_URL) return;
      const badge = document.getElementById('sasBellBadge');

      function updateBadge(n) {
        if (!badge) return;
        if (n > 0) { badge.textContent = n > 9 ? '9+' : n; badge.style.display = ''; }
        else { badge.style.display = 'none'; }
      }

      function poll() {
        fetch(window.SAS_FEED_URL, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
          .then(r => r.ok ? r.json() : null)
          .then(data => {
            if (!data) return;
            updateBadge(data.unread || 0);
            (data.items || []).forEach(n => {
              const k = 'sas_notif_' + n.id;
              if (!localStorage.getItem(k)) {
                if (window.sasToast) sasToast(n.title, n.body, 'info');
                localStorage.setItem(k, '1');
              }
            });
          })
          .catch(() => { /* transient/offline — ignore */ });
      }

      setInterval(poll, 20000);   // every 20s
      setTimeout(poll, 5000);     // and shortly after load
    })();
  </script>
  <script>
    // Sidebar toggle (mobile)
    (function () {
      const sb = document.getElementById('sasSidebar');
      const bd = document.getElementById('sasBackdrop');
      document.querySelectorAll('[data-sas-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          sb.classList.toggle('open');
          bd.classList.toggle('show');
        });
      });
      bd && bd.addEventListener('click', function () {
        sb.classList.remove('open');
        bd.classList.remove('show');
      });
    })();
  </script>
  @stack('scripts')
</body>
</html>
