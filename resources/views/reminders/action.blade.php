<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $title }} | {{ config('app.name') }}</title>

  <link rel="preconnect" href="https://fonts.googleapis.com/">
  <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400..800;1,400..800&amp;display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/2.6.0/uicons-regular-rounded/css/uicons-regular-rounded.css">

  <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/sas-ui.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/app-shell.css') }}">
  {{-- This is a public, token-based page (no auth session) reached from an emailed/texted
       reminder link, so the tenant's brand color comes from the appointment's clinic
       rather than auth()->user(). --}}
  @php $sasBrandColor = $a->clinic?->primary_color; @endphp
  @if ($sasBrandColor)
    <style>
      :root {
        --bs-primary: {{ $sasBrandColor }};
        --bs-primary-rgb: {{ implode(',', sscanf($sasBrandColor, '#%02x%02x%02x')) }};
        --sas-primary: {{ $sasBrandColor }};
      }
      .btn-primary { background-color: {{ $sasBrandColor }}; border-color: {{ $sasBrandColor }}; }
    </style>
  @endif
</head>
<body>
  @php
    $icon = match ($variant) {
        'success' => 'fi-rr-check-circle',
        'warning' => 'fi-rr-calendar-xmark',
        default => 'fi-rr-calendar-clock',
    };
  @endphp
  <div class="d-flex align-items-center justify-content-center min-vh-100 bg-light px-2">
    <div class="card maxw-450px w-100">
      <div class="card-body p-5 text-center">
        <div class="sas-stat__icon bg-{{ $variant }}-subtle text-{{ $variant }} mx-auto mb-3">
          <i class="fi {{ $icon }}"></i>
        </div>
        <x-badge-status :color="$variant" :label="$title" class="mb-3" />
        <h5 class="mb-2 mt-2">{{ $message }}</h5>
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
