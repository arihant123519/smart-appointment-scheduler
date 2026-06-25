<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $title }} | {{ config('app.name') }}</title>
  <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
</head>
<body>
  <div class="d-flex align-items-center justify-content-center min-vh-100 bg-light px-2">
    <div class="card maxw-450px w-100 rounded-4 shadow-sm">
      <div class="card-body p-5 text-center">
        <div class="badge bg-{{ $variant }}-subtle text-{{ $variant }} mb-3 fs-6">{{ $title }}</div>
        <h5 class="mb-2">{{ $message }}</h5>
        <p class="text-muted small mb-4">
          {{ $a->service?->name }} with {{ $a->provider->name }}<br>
          {{ $a->start_at->format('l, F j, Y \a\t g:i A') }}
        </p>
        @if ($link)
          <a href="{{ $link }}" class="btn btn-primary w-100">Continue</a>
        @endif
      </div>
    </div>
  </div>
</body>
</html>
