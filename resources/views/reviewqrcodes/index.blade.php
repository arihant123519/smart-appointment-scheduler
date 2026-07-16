@extends('layouts.app')

@section('title', 'Review QR Codes')

@section('content')
  <div class="row g-3">
    <div class="col-xl-8">
      <x-card title="Review QR codes" bodyClass="p-0">
        <div class="table-responsive">
          <table class="table align-middle mb-0 datatable">
            <thead class="table-light"><tr><th>Code</th><th>Label</th><th>Scans</th><th>Submissions</th><th></th></tr></thead>
            <tbody>
              @forelse ($codes as $qr)
                <tr>
                  <td><img src="{{ $qr->image_url }}" alt="QR code for {{ $qr->label }}" width="64" height="64"></td>
                  <td>
                    {{ $qr->label }}
                    <div class="small text-muted text-break">{{ $qr->submit_url }}</div>
                  </td>
                  <td>{{ $qr->scans_count }}</td>
                  <td>{{ $qr->submissions_count }}</td>
                  <td class="text-end">
                    <a href="{{ $qr->image_url }}" download="review-qr-{{ \Illuminate\Support\Str::slug($qr->label) }}.png" class="btn btn-sm btn-outline-secondary">Download</a>
                    <form method="POST" action="{{ route('reviewqrcodes.destroy', $qr) }}" class="d-inline" data-sas-confirm="Delete this review QR code? Existing printed copies will stop working." data-sas-confirm-label="Delete">
                      @csrf @method('DELETE')
                      <button class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                  </td>
                </tr>
              @empty
                <x-empty-state colspan="5" icon="fi-rr-star" title="No review QR codes yet" description="Generate one to let patients leave feedback without logging in." />
              @endforelse
            </tbody>
          </table>
        </div>
      </x-card>
    </div>

    <div class="col-xl-4">
      <x-card title="Create a review QR code">
        <form method="POST" action="{{ route('reviewqrcodes.store') }}">
          @csrf
          <div class="mb-3">
            <x-form-field name="label" label="Label" :required="true" placeholder="e.g. Front-desk counter card" />
          </div>
          <button class="btn btn-primary w-100">Generate QR code</button>
        </form>
      </x-card>
    </div>
  </div>
@endsection
