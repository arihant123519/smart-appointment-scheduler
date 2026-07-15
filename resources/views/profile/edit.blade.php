@extends('layouts.app')

@section('title', 'My Profile')

@section('content')
  <div class="row g-3 justify-content-center">
    <div class="col-xl-7">
      <x-card class="mb-3" title="Profile information">
        <form method="POST" action="{{ route('profile.update') }}">
          @csrf @method('PUT')
          <div class="row g-3">
            <div class="col-md-6">
              <x-form-field name="name" label="Name" :required="true" :value="old('name', $user->name)" />
            </div>
            <div class="col-md-6">
              <x-form-field name="email" type="email" label="Email" :required="true" :value="old('email', $user->email)" />
            </div>
            <div class="col-md-6">
              <x-form-field name="phone" label="Phone" :value="old('phone', $user->phone)" />
            </div>
            <div class="col-md-6">
              <x-form-field name="locale" label="Locale" :value="old('locale', $user->locale)" />
            </div>
            <div class="col-12">
              <x-form-field name="address" label="Address" :value="old('address', $user->address)" />
            </div>
          </div>
          <div class="mt-3"><button class="btn btn-primary">Save profile</button></div>
        </form>
      </x-card>

      <x-card class="mb-3" title="Change password">
        <form method="POST" action="{{ route('profile.password') }}">
          @csrf @method('PUT')
          <div class="row g-3">
            <div class="col-12">
              <x-form-field name="current_password" type="password" label="Current password" :required="true" />
            </div>
            <div class="col-md-6">
              <x-form-field name="password" type="password" label="New password" :required="true" />
            </div>
            <div class="col-md-6">
              <x-form-field name="password_confirmation" type="password" label="Confirm new password" :required="true" />
            </div>
          </div>
          <div class="mt-3"><button class="btn btn-primary">Change password</button></div>
        </form>
      </x-card>

      <x-card class="mb-3" bodyClass="p-0">
        <x-slot:title>API tokens</x-slot:title>
        <x-slot:toolbar>
          <a href="{{ route('export.ics') }}" class="btn btn-sm btn-light"><i class="fi fi-rr-calendar me-1"></i> Calendar feed (.ics)</a>
        </x-slot:toolbar>
        <div class="p-3 pb-0">
          <form method="POST" action="{{ route('profile.tokens.create') }}" class="d-flex gap-2 mb-3">
            @csrf
            <input type="text" name="token_name" class="form-control" placeholder="Token name (e.g. Mobile app)" required>
            <button class="btn btn-primary flex-shrink-0">Generate</button>
          </form>
        </div>
        <table class="table align-middle mb-0">
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
              <x-empty-state colspan="3" icon="fi-rr-key" title="No API tokens" description="Generate one above to authenticate external integrations." />
            @endforelse
          </tbody>
        </table>
      </x-card>
    </div>
  </div>
@endsection
