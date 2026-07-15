<header class="sas-topbar">
  <button class="btn btn-icon btn-action-gray rounded-circle sas-sidebar__toggle" data-sas-toggle type="button" aria-label="Toggle navigation menu">
    <i class="fi fi-rr-menu-burger"></i>
  </button>

  <div class="d-none d-md-flex align-items-center flex-grow-1">
    <span class="text-muted small">
      <i class="fi fi-rr-calendar me-1"></i> {{ now()->format('l, F j, Y') }}
    </span>
  </div>

  <div class="d-flex align-items-center gap-2 ms-auto">
    @can('manage appointments')
      <a href="{{ route('appointments.create') }}" class="btn btn-primary btn-sm">
        <i class="fi fi-rr-plus me-1"></i> New Appointment
      </a>
    @endcan

    {{-- Notification bell (megamenu) --}}
    @php
      $bellUnread = auth()->user()->unreadNotifications()->count();
      $bellItems = auth()->user()->notifications()->latest()->limit(8)->get();
    @endphp
    <div class="dropdown">
      <a href="#" class="btn btn-icon btn-action-gray rounded-circle position-relative" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Notifications{{ $bellUnread ? " ($bellUnread unread)" : '' }}">
        <i class="fi fi-rr-bell fs-5"></i>
        <span id="sasBellBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger {{ $bellUnread ? 'sas-pulse' : '' }}"
              style="font-size:.6rem; {{ $bellUnread ? '' : 'display:none' }}">{{ $bellUnread > 9 ? '9+' : $bellUnread }}</span>
      </a>
      <div class="dropdown-menu dropdown-menu-end p-0 mt-2 shadow-lg" style="width:360px">
        <div class="d-flex justify-content-between align-items-center px-3 py-3 border-bottom">
          <div class="d-flex align-items-center gap-2">
            <h6 class="mb-0 fw-bold">Notifications</h6>
            @if ($bellUnread)
              <span class="badge badge-light-primary rounded-pill">{{ $bellUnread }} new</span>
            @endif
          </div>
          @if ($bellUnread)
            <form method="POST" action="{{ route('notifications.readAll') }}" class="m-0">
              @csrf
              <button class="btn btn-sm btn-link p-0 text-decoration-none fw-semibold">Mark all read</button>
            </form>
          @endif
        </div>
        <div style="max-height:360px;overflow-y:auto">
          @forelse ($bellItems as $n)
            <a href="{{ route('notifications.read', $n->id) }}" class="dropdown-item d-flex gap-2 py-2 px-3 border-bottom text-wrap">
              <span class="sas-avatar sas-avatar-sm {{ $n->read_at ? 'bg-light text-muted' : 'badge-light-primary' }} flex-shrink-0">
                <i class="fi {{ $n->data['icon'] ?? 'fi-rr-bell' }}"></i>
              </span>
              <span class="flex-grow-1">
                <span class="d-flex align-items-center gap-1">
                  <span class="fw-semibold d-block">{{ $n->data['title'] ?? 'Notification' }}</span>
                  @unless ($n->read_at)
                    <span class="rounded-circle bg-danger flex-shrink-0" style="width:6px;height:6px;"></span>
                  @endunless
                </span>
                <small class="text-muted d-block">{{ \Illuminate\Support\Str::limit($n->data['body'] ?? '', 70) }}</small>
                <small class="text-muted d-block">{{ $n->created_at->diffForHumans() }}</small>
              </span>
            </a>
          @empty
            <div class="sas-empty-state py-4">
              <i class="fi fi-rr-bell"></i>
              <strong>You're all caught up</strong>
              <span class="small">No notifications yet</span>
            </div>
          @endforelse
        </div>
        <a href="{{ route('notifications.index') }}" class="dropdown-item text-center py-2 fw-semibold text-primary">View all notifications</a>
      </div>
    </div>

    {{-- User menu (card-style) --}}
    <div class="dropdown">
      <a href="#" class="d-flex align-items-center text-decoration-none gap-2" data-bs-toggle="dropdown" aria-expanded="false">
        <img src="{{ auth()->user()->avatar_url }}" alt="avatar" class="sas-avatar sas-avatar-md">
        <div class="d-none d-sm-block text-start">
          <div class="fw-bold text-dark lh-1" style="font-size:.85rem">{{ auth()->user()->name }}</div>
          <small class="text-muted">{{ auth()->user()->roles->first()?->name ? ucwords(str_replace('_', ' ', auth()->user()->roles->first()->name)) : 'User' }}</small>
        </div>
        <i class="fi fi-rr-angle-small-down text-muted d-none d-sm-inline" style="font-size:.7rem"></i>
      </a>
      <ul class="dropdown-menu dropdown-menu-end mt-2 p-0 shadow-lg" style="width:260px">
        <li class="d-flex align-items-center gap-2 px-3 py-3 border-bottom">
          <img src="{{ auth()->user()->avatar_url }}" alt="avatar" class="sas-avatar sas-avatar-md">
          <div class="text-truncate">
            <div class="fw-bold text-truncate">{{ auth()->user()->name }}</div>
            <small class="text-muted text-truncate d-block">{{ auth()->user()->email }}</small>
          </div>
        </li>
        <li><a class="dropdown-item mx-2 mt-2" href="{{ route('profile.edit') }}"><i class="fi fi-rr-user me-2"></i> My Profile</a></li>
        <li><a class="dropdown-item mx-2" href="{{ route('notifications.index') }}"><i class="fi fi-rr-bell me-2"></i> Notifications</a></li>
        <li><hr class="dropdown-divider"></li>
        <li class="pb-2">
          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="dropdown-item mx-2 text-danger" type="submit"><i class="fi fi-rr-exit me-2"></i> Log Out</button>
          </form>
        </li>
      </ul>
    </div>
  </div>
</header>
