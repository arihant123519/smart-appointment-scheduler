@extends('layouts.app')

@section('title', 'My Profile')

@section('content')
  <div class="row g-3 justify-content-center">
    <div class="col-xl-7">
      <div class="card mb-3"><div class="card-header"><h6 class="mb-0">Profile information</h6></div>
        <div class="card-body">
          <form method="POST" action="{{ route('profile.update') }}">
            @csrf @method('PUT')
            <div class="row g-3">
              <div class="col-md-6"><label class="form-label">Name</label><input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required></div>
              <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required></div>
              <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="{{ old('phone', $user->phone) }}"></div>
              <div class="col-md-6"><label class="form-label">Locale</label><input type="text" name="locale" class="form-control" value="{{ old('locale', $user->locale) }}"></div>
              <div class="col-12"><label class="form-label">Address</label><input type="text" name="address" class="form-control" value="{{ old('address', $user->address) }}"></div>
            </div>
            <div class="mt-3"><button class="btn btn-primary">Save profile</button></div>
          </form>
        </div>
      </div>

      <div class="card mb-3"><div class="card-header"><h6 class="mb-0">Change password</h6></div>
        <div class="card-body">
          <form method="POST" action="{{ route('profile.password') }}">
            @csrf @method('PUT')
            <div class="row g-3">
              <div class="col-12"><label class="form-label">Current password</label><input type="password" name="current_password" class="form-control" required></div>
              <div class="col-md-6"><label class="form-label">New password</label><input type="password" name="password" class="form-control" required></div>
              <div class="col-md-6"><label class="form-label">Confirm new password</label><input type="password" name="password_confirmation" class="form-control" required></div>
            </div>
            <div class="mt-3"><button class="btn btn-primary">Change password</button></div>
          </form>
        </div>
      </div>

      <div class="card mb-3"><div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="mb-0">API tokens</h6>
          <a href="{{ route('export.ics') }}" class="btn btn-sm btn-light"><i class="fi fi-rr-calendar me-1"></i> Calendar feed (.ics)</a>
        </div>
        <div class="card-body">
          <form method="POST" action="{{ route('profile.tokens.create') }}" class="d-flex gap-2 mb-3">
            @csrf
            <input type="text" name="token_name" class="form-control" placeholder="Token name (e.g. Mobile app)" required>
            <button class="btn btn-primary">Generate</button>
          </form>
          <table class="table mb-0">
            <tbody>
              @forelse ($tokens as $t)
                <tr>
                  <td>{{ $t->name }}</td>
                  <td class="text-muted small">{{ $t->created_at->format('M j, Y') }}</td>
                  <td class="text-end">
                    <form method="POST" action="{{ route('profile.tokens.delete', $t->id) }}">
                      @csrf @method('DELETE')
                      <button class="btn btn-sm btn-outline-danger">Revoke</button>
                    </form>
                  </td>
                </tr>
              @empty
                <tr><td class="text-muted text-center py-2">No API tokens.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
@endsection
