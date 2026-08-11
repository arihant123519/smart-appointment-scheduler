@extends('layouts.app')

@section('title', 'Providers')

@php
  // Same keyword → icon heuristic used on the Appointments/Waitlist pages;
  // Service has no category field so this is decorative only, with a
  // neutral fallback for anything unmatched.
  $providerServiceIcon = function (?string $name) {
    $name = strtolower($name ?? '');
    return match (true) {
      str_contains($name, 'dental') || str_contains($name, 'tooth') => 'fi-rr-tooth',
      str_contains($name, 'therapy') || str_contains($name, 'counsel') || str_contains($name, 'mental') => 'fi-rr-brain',
      str_contains($name, 'derma') || str_contains($name, 'skin') => 'fi-rr-hand-holding-medical',
      str_contains($name, 'eye') || str_contains($name, 'optic') => 'fi-rr-eye',
      str_contains($name, 'cardio') || str_contains($name, 'heart') => 'fi-rr-heart',
      default => 'fi-rr-stethoscope',
    };
  };
  $providerSpecialties = $providers->pluck('specialty')->filter()->unique()->sort()->values();
@endphp

@push('styles')
  <style>
    .sas-page-toolbar { display: none; }

    .sas-prov-header__icon {
      width: 52px; height: 52px; border-radius: var(--sas-radius-lg); flex-shrink: 0;
      background: var(--sas-primary-50); color: var(--sas-primary-700);
      display: grid; place-items: center; font-size: 1.4rem;
    }
    .sas-prov-header__title { font-size: 2.25rem; font-weight: 800; letter-spacing: -.02em; color: var(--sas-gray-900); }
    .sas-prov-header__subtitle { font-size: var(--sas-fs-base); color: var(--sas-gray-500); }

    .sas-prov-search { position: relative; width: 260px; }
    .sas-prov-search i { position: absolute; left: .9rem; top: 50%; transform: translateY(-50%); color: var(--sas-gray-400); }
    .sas-prov-search input {
      width: 100%; border: 1px solid var(--sas-gray-200); border-radius: var(--sas-radius-md); background: #fff;
      padding: .6rem .9rem .6rem 2.4rem; font-size: var(--sas-fs-sm); transition: border-color .15s var(--sas-ease), box-shadow .15s var(--sas-ease);
    }
    .sas-prov-search input:focus { outline: none; border-color: var(--sas-primary-400); box-shadow: 0 0 0 .2rem var(--sas-primary-100); }

    .sas-prov-filter-btn {
      border: 1px solid var(--sas-gray-200); background: #fff; color: var(--sas-gray-600); font-weight: 600; font-size: var(--sas-fs-sm);
      border-radius: var(--sas-radius-md); padding: .6rem 1rem; display: inline-flex; align-items: center; gap: .4rem; flex-shrink: 0;
    }
    .sas-prov-filter-btn:hover { background: var(--sas-gray-50); }
    .sas-prov-filter-btn.has-active { border-color: var(--sas-primary-400); color: var(--sas-primary-600); background: var(--sas-primary-50); }

    #providerList > .card { position: relative; }
    .sas-prov-card { display: grid; grid-template-columns: minmax(240px, 1.4fr) minmax(200px, 1fr) auto; gap: 1.75rem; align-items: center; }
    @media (max-width: 991.98px) { .sas-prov-card { grid-template-columns: 1fr; } }

    .sas-prov-avatar { position: relative; flex-shrink: 0; }
    .sas-prov-avatar img { width: 64px; height: 64px; border-radius: 50%; object-fit: cover; }
    .sas-prov-avatar__dot { position: absolute; right: 2px; bottom: 2px; width: 13px; height: 13px; border-radius: 50%; border: 2.5px solid #fff; }
    .sas-prov-avatar__dot.is-active { background: var(--sas-success); }
    .sas-prov-avatar__dot.is-inactive { background: var(--sas-gray-400); }
    .sas-prov-name { font-size: var(--sas-fs-lg); font-weight: 700; color: var(--sas-gray-900); }
    .sas-prov-name:hover { color: var(--sas-primary-700); }
    .sas-prov-qual { font-size: var(--sas-fs-sm); color: var(--sas-gray-500); }
    .sas-prov-contact { display: flex; align-items: center; gap: .5rem; font-size: var(--sas-fs-sm); color: var(--sas-gray-700); }
    .sas-prov-contact i { color: var(--sas-gray-400); width: 16px; text-align: center; }

    .sas-prov-services__label { font-size: var(--sas-fs-xs); font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--sas-gray-500); margin-bottom: .55rem; }
    .sas-prov-chip { display: inline-flex; align-items: center; gap: .4rem; background: var(--sas-primary-50); color: var(--sas-primary-700); border-radius: var(--sas-radius-md); padding: .4rem .7rem; font-size: var(--sas-fs-xs); font-weight: 600; }

    .sas-prov-total { display: flex; align-items: center; gap: .9rem; }
    .sas-prov-total__label { font-size: var(--sas-fs-xs); font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--sas-gray-500); margin-bottom: .5rem; }
    .sas-prov-total__tile { width: 48px; height: 48px; border-radius: var(--sas-radius-md); background: var(--sas-primary-50); color: var(--sas-primary-600); display: grid; place-items: center; font-size: 1.2rem; flex-shrink: 0; }
    .sas-prov-total__value { font-size: 1.75rem; font-weight: 800; color: var(--sas-gray-900); line-height: 1; }
    .sas-prov-total__caption { font-size: var(--sas-fs-xs); color: var(--sas-gray-500); }

    .sas-prov-kebab { position: absolute; top: 1.5rem; right: 1.5rem; }
    .sas-prov-kebab .btn-icon-square { width: 34px; height: 34px; border-radius: var(--sas-radius-md); border: 1px solid var(--sas-gray-200); background: #fff; color: var(--sas-gray-600); }
    .sas-prov-kebab .btn-icon-square:hover { background: var(--sas-gray-50); }
  </style>
@endpush

@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div class="d-flex align-items-start gap-3">
      <span class="sas-prov-header__icon"><i class="fi fi-rr-users" aria-hidden="true"></i></span>
      <div>
        <h1 class="sas-prov-header__title mb-1">Providers</h1>
        <p class="sas-prov-header__subtitle mb-0">Manage your healthcare providers and their services.</p>
      </div>
    </div>
    <div class="d-flex align-items-center gap-2">
      <div class="sas-prov-search">
        <i class="fi fi-rr-search" aria-hidden="true"></i>
        <input type="text" id="providerSearch" placeholder="Search providers…" aria-label="Search providers">
      </div>
      <div class="dropdown">
        <button type="button" class="sas-prov-filter-btn" id="providerFilterBtn" data-bs-toggle="dropdown" aria-expanded="false">
          <i class="fi fi-rr-filter" aria-hidden="true"></i> Filters
        </button>
        <ul class="dropdown-menu dropdown-menu-end p-3" style="min-width:220px">
          <div class="sas-prov-services__label">Status</div>
          <div class="form-check"><input class="form-check-input sas-prov-status-check" type="checkbox" value="active" id="provFilterActive"><label class="form-check-label small" for="provFilterActive">Active</label></div>
          <div class="form-check mb-3"><input class="form-check-input sas-prov-status-check" type="checkbox" value="inactive" id="provFilterInactive"><label class="form-check-label small" for="provFilterInactive">Inactive</label></div>
          @if ($providerSpecialties->isNotEmpty())
            <div class="sas-prov-services__label">Specialty</div>
            @foreach ($providerSpecialties as $spec)
              <div class="form-check"><input class="form-check-input sas-prov-specialty-check" type="checkbox" value="{{ $spec }}" id="provFilterSpec{{ $loop->index }}"><label class="form-check-label small" for="provFilterSpec{{ $loop->index }}">{{ $spec }}</label></div>
            @endforeach
          @endif
        </ul>
      </div>
      <a href="{{ route('providers.create') }}" class="btn btn-primary btn-lg text-nowrap"><i class="fi fi-rr-plus me-1"></i> Add Provider</a>
    </div>
  </div>

  <div class="d-flex flex-column gap-3 sas-stagger" id="providerList">
    @forelse ($providers as $p)
      <x-card class="sas-card-hover" data-status="{{ $p->is_active ? 'active' : 'inactive' }}" data-specialty="{{ $p->specialty }}" data-search="{{ strtolower($p->name.' '.$p->specialty.' '.$p->services->pluck('name')->implode(' ')) }}">
        <div class="sas-prov-card">
          <div class="d-flex align-items-start gap-3">
            <span class="sas-prov-avatar">
              <img src="{{ $p->user->avatar_url }}" alt="">
              <span class="sas-prov-avatar__dot {{ $p->is_active ? 'is-active' : 'is-inactive' }}" aria-hidden="true"></span>
            </span>
            <div>
              <a href="{{ route('providers.show', $p) }}" class="sas-prov-name d-block text-decoration-none">{{ $p->name }}</a>
              <div class="sas-prov-qual mb-2">{{ $p->specialty }} @if($p->credentials)&middot; {{ $p->credentials }}@endif</div>
              <x-badge-status class="mb-2 d-inline-flex" :color="$p->is_active ? 'success' : 'secondary'" :label="$p->is_active ? 'Active' : 'Inactive'" :icon="$p->is_active ? 'fi-rr-check' : 'fi-rr-minus'" />
              <div class="d-flex flex-column gap-1 mt-2">
                <span class="sas-prov-contact"><i class="fi fi-rr-envelope" aria-hidden="true"></i> {{ $p->user->email }}</span>
                @if ($p->user->phone)<span class="sas-prov-contact"><i class="fi fi-rr-phone-call" aria-hidden="true"></i> {{ $p->user->phone }}</span>@endif
                @if ($p->clinic)<span class="sas-prov-contact"><i class="fi fi-rr-marker" aria-hidden="true"></i> {{ $p->clinic->name }}</span>@endif
              </div>
            </div>
          </div>

          <div>
            <div class="sas-prov-services__label">Specialties / Services</div>
            <div class="d-flex flex-wrap gap-2">
              @forelse ($p->services as $s)
                <span class="sas-prov-chip"><i class="fi {{ $providerServiceIcon($s->name) }}" aria-hidden="true"></i>{{ $s->name }}</span>
              @empty
                <span class="text-muted small">No services assigned</span>
              @endforelse
            </div>
          </div>

          <div class="sas-prov-total">
            <div>
              <div class="sas-prov-total__label">Total Appointments</div>
              <div class="d-flex align-items-center gap-2">
                <span class="sas-prov-total__tile"><i class="fi fi-rr-calendar" aria-hidden="true"></i></span>
                <div>
                  <div class="sas-prov-total__value">{{ $p->appointments_count }}</div>
                  <div class="sas-prov-total__caption">appointments</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="dropdown sas-prov-kebab">
          <button type="button" class="btn btn-sm btn-icon-square" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions for {{ $p->name }}">
            <i class="fi fi-rr-menu-dots-vertical"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="{{ route('providers.show', $p) }}"><i class="fi fi-rr-eye"></i> View profile</a></li>
            <li><a class="dropdown-item" href="{{ route('providers.edit', $p) }}"><i class="fi fi-rr-edit"></i> Edit provider</a></li>
            <li><hr class="dropdown-divider"></li>
            <li>
              {{-- Toggles is_active via the real update() endpoint, resubmitting
                   every other field unchanged so nothing else is touched. --}}
              <form method="POST" action="{{ route('providers.update', $p) }}">
                @csrf @method('PUT')
                <input type="hidden" name="name" value="{{ $p->name }}">
                <input type="hidden" name="email" value="{{ $p->user->email }}">
                <input type="hidden" name="phone" value="{{ $p->user->phone }}">
                <input type="hidden" name="specialty" value="{{ $p->specialty }}">
                <input type="hidden" name="credentials" value="{{ $p->credentials }}">
                <input type="hidden" name="bio" value="{{ $p->bio }}">
                <input type="hidden" name="accepts_telehealth" value="{{ $p->accepts_telehealth ? 1 : 0 }}">
                <input type="hidden" name="is_active" value="{{ $p->is_active ? 0 : 1 }}">
                @foreach ($p->services as $s)<input type="hidden" name="services[]" value="{{ $s->id }}">@endforeach
                <button type="submit" class="dropdown-item">
                  <i class="fi {{ $p->is_active ? 'fi-rr-pause' : 'fi-rr-check-circle' }}"></i> {{ $p->is_active ? 'Deactivate' : 'Activate' }}
                </button>
              </form>
            </li>
            <li>
              <form method="POST" action="{{ route('providers.destroy', $p) }}" data-sas-confirm="Remove {{ $p->name }}? This can't be undone." data-sas-confirm-label="Remove provider">
                @csrf @method('DELETE')
                <button type="submit" class="dropdown-item text-danger"><i class="fi fi-rr-trash"></i> Remove provider</button>
              </form>
            </li>
          </ul>
        </div>
      </x-card>
    @empty
      <x-card>
        <x-empty-state icon="fi-rr-user-md" title="Add your first provider" description="Add a provider to start scheduling appointments and assigning services.">
          <a href="{{ route('providers.create') }}" class="btn btn-sm btn-primary"><i class="fi fi-rr-plus me-1"></i> Add Provider</a>
        </x-empty-state>
      </x-card>
    @endforelse
    <div class="text-center text-muted small py-4 d-none" id="providerNoMatches">No providers match your search or filters.</div>
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      const cards = Array.from(document.querySelectorAll('#providerList > .card'));
      if (!cards.length) return;
      const searchInput = document.getElementById('providerSearch');
      const statusChecks = document.querySelectorAll('.sas-prov-status-check');
      const specialtyChecks = document.querySelectorAll('.sas-prov-specialty-check');
      const filterBtn = document.getElementById('providerFilterBtn');
      const noMatches = document.getElementById('providerNoMatches');

      function apply() {
        const q = searchInput.value.trim().toLowerCase();
        const statuses = Array.from(statusChecks).filter(c => c.checked).map(c => c.value);
        const specialties = Array.from(specialtyChecks).filter(c => c.checked).map(c => c.value);
        filterBtn.classList.toggle('has-active', statuses.length > 0 || specialties.length > 0);

        let visible = 0;
        cards.forEach(function (card) {
          const matchesSearch = !q || (card.dataset.search || '').includes(q);
          const matchesStatus = !statuses.length || statuses.includes(card.dataset.status);
          const matchesSpecialty = !specialties.length || specialties.includes(card.dataset.specialty);
          const show = matchesSearch && matchesStatus && matchesSpecialty;
          card.classList.toggle('d-none', !show);
          if (show) visible++;
        });
        noMatches.classList.toggle('d-none', visible > 0);
      }

      searchInput.addEventListener('input', apply);
      statusChecks.forEach(c => c.addEventListener('change', apply));
      specialtyChecks.forEach(c => c.addEventListener('change', apply));
    })();
  </script>
@endpush
