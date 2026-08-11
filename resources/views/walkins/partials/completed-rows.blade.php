@forelse ($entries as $e)
  <tr>
    <td>
      {{ $e->name }}
      @if ($e->phone)<br><span class="text-muted small">{{ $e->phone }}</span>@endif
    </td>
    <td>{{ $e->provider->name ?? '—' }}</td>
    <td>{{ $e->service->name ?? '—' }}</td>
    <td>{{ $e->joined_at->format('g:i A') }}</td>
    <td>{{ $e->called_at ? round($e->joined_at->diffInMinutes($e->called_at, true)).' min' : '—' }}</td>
    <td>{{ $e->completed_at->format('g:i A') }}</td>
    <td>
      @php $badge = ['done' => 'primary', 'left' => 'danger'][$e->status] ?? 'secondary'; @endphp
      <x-badge-status :color="$badge" :label="$e->status === 'left' ? 'Left / no-show' : 'Done'" />
    </td>
  </tr>
@empty
  <x-empty-state colspan="7" icon="fi-rr-inbox" title="No completed walk-ins yet today" />
@endforelse
