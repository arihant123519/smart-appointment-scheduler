@php
  $walkinInitials = function (string $name) {
    $words = preg_split('/\s+/', trim($name));
    return strtoupper(substr($words[0] ?? '', 0, 1) . substr($words[1] ?? '', 0, 1));
  };
@endphp
@forelse ($entries as $e)
  <tr data-status="{{ $e->status }}">
    <td class="sas-wq-position">{{ $e->status === 'waiting' ? $e->position : '—' }}</td>
    <td>
      <div class="d-flex align-items-center gap-2">
        @if ($e->patient)
          <img src="{{ $e->patient->avatar_url }}" class="sas-avatar sas-avatar-sm" alt="">
        @else
          <span class="sas-wq-avatar-initials">{{ $walkinInitials($e->name) }}</span>
        @endif
        <span>
          <span class="d-block fw-semibold">{{ $e->name }}</span>
          @if ($e->phone)<span class="d-block text-muted small">{{ $e->phone }}</span>@endif
        </span>
      </div>
    </td>
    <td>{{ $e->provider->name ?? 'Any' }}</td>
    <td>{{ $e->service->name ?? 'Any' }}</td>
    <td>{{ $e->joined_at->diffForHumans() }}</td>
    <td>
      @php $badge = ['waiting' => 'warning', 'serving' => 'success'][$e->status] ?? 'secondary'; @endphp
      <x-badge-status :color="$badge" :label="ucfirst($e->status)" />
    </td>
    <td class="text-end">
      <div class="d-flex gap-1 justify-content-end align-items-center">
        <button type="button" class="btn btn-sm btn-icon-square" title="Patient view" aria-label="Patient view" data-bs-toggle="modal" data-bs-target="#walkinPos{{ $e->id }}" style="padding: 0;">
          <i class="fi fi-rr-eye"></i>
        </button>
        @if ($e->status === 'waiting')
          <form method="POST" action="{{ route('walkins.status', $e) }}">
            @csrf @method('PATCH')
            <input type="hidden" name="status" value="serving">
            <button class="btn btn-sm btn-success">Call in</button>
          </form>
        @elseif ($e->status === 'serving')
          <form method="POST" action="{{ route('walkins.status', $e) }}">
            @csrf @method('PATCH')
            <input type="hidden" name="status" value="done">
            <button class="btn btn-sm btn-primary">Done</button>
          </form>
        @endif
        <div class="dropdown sas-dropdown-actions">
          <button type="button" class="btn btn-sm btn-icon-square" data-bs-toggle="dropdown" aria-expanded="false" aria-label="More actions">
            <i class="fi fi-rr-menu-dots-vertical"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li>
              <form method="POST" action="{{ route('walkins.status', $e) }}" data-sas-confirm="Mark as left / no-show?" data-sas-confirm-label="Mark left">
                @csrf @method('PATCH')
                <input type="hidden" name="status" value="left">
                <button type="submit" class="dropdown-item"><i class="fi fi-rr-exclamation"></i> Left / no-show</button>
              </form>
            </li>
            <li>
              <form method="POST" action="{{ route('walkins.destroy', $e) }}" data-sas-confirm="Remove from queue?" data-sas-confirm-label="Remove">
                @csrf @method('DELETE')
                <button type="submit" class="dropdown-item text-danger"><i class="fi fi-rr-trash"></i> Remove</button>
              </form>
            </li>
          </ul>
        </div>
      </div>
    </td>
  </tr>
@empty
  <x-empty-state colspan="7" icon="fi-rr-users" title="No one in the queue" description="Walk-ins you add below will show up here." />
@endforelse
