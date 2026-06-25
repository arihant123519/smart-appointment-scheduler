<header class="sas-topbar">
  <button class="btn btn-icon btn-action-gray rounded-circle sas-sidebar__toggle" data-sas-toggle type="button">
    <i class="fi fi-rr-menu-burger"></i>
  </button>

  <div class="d-none d-md-flex align-items-center flex-grow-1">
    <span class="text-muted small">
      <i class="fi fi-rr-calendar me-1"></i> {{ now()->format('l, F j, Y') }}
    </span>
  </div>

  <div class="d-flex align-items-center gap-3 ms-auto">
    @can('manage appointments')
      <a href="{{ route('appointments.create') }}" class="btn btn-primary btn-sm">
        <i class="fi fi-rr-plus me-1"></i> New Appointment
      </a>
    @endcan

    {{-- Notification bell --}}
    @php
      $bellUnread = auth()->user()->unreadNotifications()->count();
      $bellItems = auth()->user()->notifications()->latest()->limit(8)->get();
    @endphp
    <div class="dropdown">
      <a href="#" class="position-relative d-flex align-items-center text-decoration-none" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
        <i class="fi fi-rr-bell fs-5 text-dark"></i>
        @if ($bellUnread)
          <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.6rem">{{ $bellUnread > 9 ? '9+' : $bellUnread }}</span>
        @endif
      </a>
      <div class="dropdown-menu dropdown-menu-end p-0 mt-2" style="width:330px">
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
          <h6 class="mb-0">Notifications</h6>
          @if ($bellUnread)
            <form method="POST" action="{{ route('notifications.readAll') }}" class="m-0">
              @csrf
              <button class="btn btn-sm btn-link p-0 text-decoration-none">Mark all read</button>
            </form>
          @endif
        </div>
        <div style="max-height:320px;overflow-y:auto">
          @forelse ($bellItems as $n)
            <a href="{{ route('notifications.read', $n->id) }}" class="dropdown-item d-flex gap-2 py-2 border-bottom text-wrap {{ $n->read_at ? '' : 'bg-light' }}">
              <i class="fi {{ $n->data['icon'] ?? 'fi-rr-bell' }} text-primary mt-1"></i>
              <span class="flex-grow-1">
                <span class="fw-semibold d-block">{{ $n->data['title'] ?? 'Notification' }}</span>
                <small class="text-muted">{{ \Illuminate\Support\Str::limit($n->data['body'] ?? '', 70) }}</small>
                <small class="text-muted d-block">{{ $n->created_at->diffForHumans() }}</small>
              </span>
            </a>
          @empty
            <div class="text-center text-muted py-4 small">No notifications</div>
          @endforelse
        </div>
        <a href="{{ route('notifications.index') }}" class="dropdown-item text-center py-2 text-primary">View all</a>
      </div>
    </div>

    <div class="dropdown">
      <a href="#" class="d-flex align-items-center text-decoration-none" data-bs-toggle="dropdown" aria-expanded="false">
        <div class="text-end me-2 d-none d-sm-block">
          <div class="fw-bold text-dark lh-1">{{ auth()->user()->name }}</div>
          <small class="text-muted">{{ auth()->user()->roles->first()?->name ? ucwords(str_replace('_', ' ', auth()->user()->roles->first()->name)) : 'User' }}</small>
        </div>
        <img src="{{ auth()->user()->avatar_url }}" alt="avatar" class="rounded-circle" width="40" height="40">
      </a>
      <ul class="dropdown-menu dropdown-menu-end mt-2">
        <li class="px-3 py-2">
          <div class="fw-bold">{{ auth()->user()->name }}</div>
          <small class="text-muted">{{ auth()->user()->email }}</small>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="fi fi-rr-user me-2"></i> Profile</a></li>
        <li>
          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="dropdown-item text-danger" type="submit"><i class="fi fi-sr-exit me-2"></i> Log Out</button>
          </form>
        </li>
      </ul>
    </div>
  </div>
</header>
