<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="theme-color" content="#5955D1">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'Sign In') | {{ config('app.name') }}</title>
  <link rel="icon" type="image/png" href="{{ asset('assets/images/favicon.png') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com/">
  <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:ital,wght@0,400..700;1,400..700&amp;display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/2.6.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
  <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
</head>
<body>
  <div class="page-layout">
    <div class="auth-wrapper min-vh-100 px-2">
      <div class="row g-0 min-vh-100 justify-content-center">
        <div class="col-xl-5 col-lg-6 col-md-8 align-self-center py-4">
          <div class="card card-body p-4 p-sm-5 maxw-450px m-auto rounded-4 shadow-sm">
            <div class="mb-4 text-center">
              <img class="visible-light" src="{{ asset('assets/images/logo.svg') }}" alt="{{ config('app.name') }}" height="36">
              <h4 class="mt-3 mb-0">{{ config('app.name') }}</h4>
            </div>

            @if ($errors->any())
              <div class="alert alert-danger">
                <ul class="mb-0 ps-3">
                  @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                  @endforeach
                </ul>
              </div>
            @endif

            @if (session('status'))
              <div class="alert alert-success">{{ session('status') }}</div>
            @endif

            @yield('content')
          </div>
        </div>
      </div>
    </div>
  </div>
  <script src="{{ asset('assets/libs/global/global.min.js') }}"></script>
</body>
</html>
