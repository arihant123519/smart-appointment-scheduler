{{-- "Patient view" modals — rendered server-side per row so opening it needs
     no page navigation or extra request. --}}
@foreach ($entries as $e)
  <x-modal id="walkinPos{{ $e->id }}" title="Patient view — {{ $e->name }}">
    <div class="text-center py-4">
      <p class="text-muted small mb-1">Hi {{ $e->name }},</p>
      @if ($e->status === 'waiting')
        <div class="display-4 fw-bold text-primary mb-2">{{ $e->position }}</div>
        <h6 class="mb-3">{{ $e->position === 1 ? "You're next!" : 'people ahead of you' }}</h6>
        <p class="text-muted small mb-0">Waiting since {{ $e->joined_at->format('g:i A') }}.</p>
      @elseif ($e->status === 'serving')
        <div class="badge bg-success-subtle text-success mb-3 fs-6">You're up!</div>
        <h5 class="mb-0">Please head to the front desk now.</h5>
      @endif
    </div>
  </x-modal>
@endforeach
