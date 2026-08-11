@extends('layouts.app')

@section('title', 'Notifications')

@section('page_icon')<i class="fi fi-rr-bell"></i>@endsection
@section('page_title', 'Notifications')
@section('page_description', 'Stay updated with important activity and alerts.')

@section('page_actions')
  <form method="POST" action="{{ route('notifications.readAll') }}">
    @csrf
    {{-- Same unreadNotifications() count the sidebar/topbar badges already
         use — computed independently here since page_actions renders before
         the PHP block further down in the content section. --}}
    <button class="btn btn-light-secondary btn-sm fw-semibold" @disabled(auth()->user()->unreadNotifications()->count() === 0)>
      <i class="fi fi-rr-check-double me-1"></i> Mark all as read
    </button>
  </form>
@endsection

@section('content')
  @php
    // The controller's $notifications is the real, unchanged paginator (30
    // per page) — that stays exactly as-is for the list + pagination below.
    // The KPI row and category filter need an honest total across *all* of
    // this user's notifications, not just the current page, so this is one
    // extra lightweight query here in the view (same pattern used on the
    // dashboard/audit pages this session) rather than a controller change.
    $all = auth()->user()->notifications()->get(['id', 'data', 'read_at']);
    $unreadCount = $all->whereNull('read_at')->count();

    // Every in-app notification today is created by GenericNotification,
    // which only ever sets one of these four icons (verified against every
    // ->notify() call site in the app) — categorize off that rather than
    // inventing a taxonomy the data can't back up. "Broadcast" has no
    // wired-up sender yet (announcements go out by email/WhatsApp, not to
    // this bell), so it will honestly show 0 until that changes.
    $categoryOf = function ($n) {
      return match ($n->data['icon'] ?? null) {
        'fi-rr-calendar-check', 'fi-rr-calendar-clock', 'fi-rr-refresh' => 'appointment',
        'fi-rr-comment-alt' => 'alert',
        default => 'system',
      };
    };
    $categoryMeta = [
      'appointment' => ['label' => 'Appointment', 'bg' => 'bg-info-subtle', 'fg' => 'text-info'],
      'alert' => ['label' => 'Alert', 'bg' => 'bg-danger-subtle', 'fg' => 'text-danger'],
      'broadcast' => ['label' => 'Broadcast', 'bg' => 'bg-warning-subtle', 'fg' => 'text-warning'],
      'system' => ['label' => 'System', 'bg' => 'bg-success-subtle', 'fg' => 'text-success'],
    ];
    $countByCategory = fn ($cat) => $all->filter(fn ($n) => $categoryOf($n) === $cat)->count();

    $kpis = [
      ['label' => 'Total Notifications', 'value' => $all->count(), 'icon' => 'fi-rr-bell', 'bg' => 'bg-accent-subtle', 'fg' => 'text-accent'],
      ['label' => 'Unread', 'value' => $unreadCount, 'icon' => 'fi-rr-envelope', 'bg' => 'bg-success-subtle', 'fg' => 'text-success'],
      ['label' => 'Appointments', 'value' => $countByCategory('appointment'), 'icon' => 'fi-rr-calendar', 'bg' => 'bg-warning-subtle', 'fg' => 'text-warning'],
      ['label' => 'Broadcasts', 'value' => $countByCategory('broadcast'), 'icon' => 'fi-rr-megaphone', 'bg' => 'bg-info-subtle', 'fg' => 'text-info'],
      ['label' => 'Alerts', 'value' => $countByCategory('alert'), 'icon' => 'fi-rr-bell-ring', 'bg' => 'bg-danger-subtle', 'fg' => 'text-danger'],
    ];
  @endphp

  {{-- Summary row --}}
  <div class="row g-3 mb-4">
    @foreach ($kpis as $k)
      <div class="col-lg col-md-4 col-6">
        <div class="card">
          <div class="card-body d-flex align-items-center gap-3 py-3">
            <span class="sas-icon-tile {{ $k['bg'] }} {{ $k['fg'] }}"><i class="fi {{ $k['icon'] }}"></i></span>
            <div style="min-width:0">
              <div class="h4 mb-0 fw-bold">{{ $k['value'] }}</div>
              <div class="text-muted small text-truncate">{{ $k['label'] }}</div>
            </div>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  <x-card bodyClass="p-0">
    {{-- Filter toolbar — client-side over the notifications rendered on this
         page. Laravel's own pagination (below) still drives moving between
         pages, unchanged; this only narrows what's visible on the current
         one, so no controller/query changes are needed. --}}
    <div class="sas-table-toolbar">
      <div class="sas-table-toolbar__filters">
        <select class="form-select form-select-sm" id="notifFilterType" aria-label="Filter by type">
          <option value="">All Types</option>
          @foreach ($categoryMeta as $key => $meta)
            <option value="{{ $key }}">{{ $meta['label'] }}</option>
          @endforeach
        </select>
        <select class="form-select form-select-sm" id="notifFilterStatus" aria-label="Filter by status">
          <option value="">All Status</option>
          <option value="unread">Unread</option>
          <option value="read">Read</option>
        </select>
      </div>

      <div class="sas-table-toolbar__search">
        <i class="fi fi-rr-search"></i>
        <input type="text" class="form-control form-control-sm" id="notifSearch" placeholder="Search notifications...">
      </div>
      <button type="button" class="btn btn-light-secondary btn-sm fw-semibold" id="notifClearFilters">
        <i class="fi fi-rr-cross-small me-1"></i> Clear
      </button>
    </div>

    <ul class="list-group list-group-flush" id="notifList">
      @forelse ($notifications as $n)
        @php
          $cat = $categoryOf($n);
          $meta = $categoryMeta[$cat];
          $isUnread = ! $n->read_at;
          $url = $n->data['url'] ?? null;
        @endphp
        <li class="sas-notif-row {{ $isUnread ? 'is-unread' : '' }}"
            data-category="{{ $cat }}" data-status="{{ $isUnread ? 'unread' : 'read' }}"
            data-search="{{ strtolower(($n->data['title'] ?? '').' '.($n->data['body'] ?? '')) }}">
          <span class="sas-notif-row__dot" @unless($isUnread) style="visibility:hidden" @endunless aria-hidden="true"></span>
          <span class="sas-icon-tile {{ $meta['bg'] }} {{ $meta['fg'] }} sas-notif-row__icon">
            <i class="fi {{ $n->data['icon'] ?? 'fi-rr-bell' }}"></i>
          </span>
          <div class="flex-grow-1" style="min-width:0">
            <div class="fw-semibold {{ $isUnread ? '' : 'text-muted' }}">{{ $n->data['title'] ?? 'Notification' }}</div>
            @if ($n->data['body'] ?? null)
              <div class="text-muted small d-flex align-items-center gap-1">
                <i class="fi fi-rr-calendar" style="font-size:.75rem"></i> {{ $n->data['body'] }}
              </div>
            @endif
          </div>
          <div class="text-muted small text-nowrap d-none d-sm-block">{{ $n->created_at->diffForHumans() }}</div>

          @if ($url || $isUnread)
            <div class="dropdown sas-dropdown-actions">
              <button type="button" class="btn btn-sm btn-icon-square" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions for this notification">
                <i class="fi fi-rr-menu-dots-vertical"></i>
              </button>
              <ul class="dropdown-menu dropdown-menu-end">
                @if ($url)
                  <li><a class="dropdown-item" href="{{ route('notifications.read', $n->id) }}"><i class="fi fi-rr-eye"></i> View details</a></li>
                @endif
                @if ($isUnread)
                  <li><a class="dropdown-item" href="{{ route('notifications.read', $n->id) }}"><i class="fi fi-rr-check"></i> Mark as read</a></li>
                @endif
              </ul>
            </div>
          @endif
        </li>
      @empty
        <li class="list-group-item p-0">
          <x-empty-state icon="fi-rr-bell" title="No notifications yet" description="You're all caught up. New appointment and system activity will appear here." />
        </li>
      @endforelse
    </ul>

    @if ($notifications->hasPages())
      <x-slot:footer>
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
          <span class="text-muted small">
            Showing {{ $notifications->firstItem() }} to {{ $notifications->lastItem() }} of {{ $notifications->total() }} entries
          </span>
          {{ $notifications->links() }}
        </div>
      </x-slot:footer>
    @endif
  </x-card>

  {{-- No-results state for when filters narrow the current page to zero rows --}}
  <div id="notifNoResults" class="sas-empty-state d-none">
    <div class="sas-empty-state__ring"><i class="fi fi-rr-search"></i></div>
    <strong>No matching notifications</strong>
    <span class="small">Try a different search term or filter.</span>
  </div>
@endsection

@push('styles')
  <style>
    .sas-notif-row {
      display: flex;
      align-items: center;
      gap: .85rem;
      padding: var(--sas-space-4) var(--sas-space-5);
      border-bottom: 1px solid var(--sas-gray-100);
      transition: background-color .15s var(--sas-ease);
    }
    .sas-notif-row:last-child { border-bottom: 0; }
    .sas-notif-row.is-unread { background: var(--sas-primary-25); }
    .sas-notif-row:hover { background: var(--sas-gray-50); }
    .sas-notif-row.is-unread:hover { background: var(--sas-primary-50); }
    .sas-notif-row__dot {
      width: 7px; height: 7px;
      border-radius: 50%;
      background: var(--sas-primary);
      flex-shrink: 0;
    }
    .sas-notif-row__icon { width: 40px; height: 40px; border-radius: 50%; font-size: 1rem; }
  </style>
@endpush

@push('scripts')
  <script>
    (function () {
      const rows = Array.from(document.querySelectorAll('#notifList .sas-notif-row'));
      const noResults = document.getElementById('notifNoResults');
      const typeSel = document.getElementById('notifFilterType');
      const statusSel = document.getElementById('notifFilterStatus');
      const search = document.getElementById('notifSearch');
      const clearBtn = document.getElementById('notifClearFilters');
      if (!rows.length) return;

      function apply() {
        const type = typeSel.value;
        const status = statusSel.value;
        const q = search.value.trim().toLowerCase();
        let visible = 0;
        rows.forEach(function (row) {
          const matches =
            (!type || row.dataset.category === type) &&
            (!status || row.dataset.status === status) &&
            (!q || row.dataset.search.includes(q));
          row.classList.toggle('d-none', !matches);
          if (matches) visible++;
        });
        noResults.classList.toggle('d-none', visible > 0);
      }

      typeSel.addEventListener('change', apply);
      statusSel.addEventListener('change', apply);
      search.addEventListener('input', apply);
      clearBtn.addEventListener('click', function () {
        typeSel.value = '';
        statusSel.value = '';
        search.value = '';
        apply();
      });
    })();
  </script>
@endpush
