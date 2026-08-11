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

      @if ($user->provider)
        <x-card class="mb-3" title="Prescription details">
          <p class="text-muted small">Shown on every prescription you write, alongside the clinic's letterhead.</p>
          <form method="POST" action="{{ route('profile.provider.update') }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            <div class="row g-3">
              <div class="col-md-6">
                <x-form-field name="registration_no" label="Medical registration number" :value="old('registration_no', $user->provider->registration_no)" />
              </div>
              <div class="col-md-6">
                <label class="form-label">Signature</label>
                <input type="file" name="signature" class="form-control @error('signature') is-invalid @enderror" accept="image/*">
                @error('signature')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @if ($user->provider->signature_path)
                  <div class="form-text">
                    <img src="{{ $user->provider->signature_url }}" alt="Current signature" style="height:28px" class="mt-1 rounded border p-1">
                    Current signature — choose a file to replace it.
                  </div>
                @endif
              </div>
            </div>
            <div class="mt-3"><button class="btn btn-primary">Save prescription details</button></div>
          </form>
        </x-card>
      @endif

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
          <form method="POST" action="{{ route('profile.tokens.create') }}" class="d-flex gap-2 mb-3 align-items-start">
            @csrf
            <div class="flex-grow-1">
              <x-outline-field name="token_name" label="Token name" placeholder="e.g. Mobile app" required />
            </div>
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
