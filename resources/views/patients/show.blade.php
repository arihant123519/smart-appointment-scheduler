@extends('layouts.app')

@section('title', $patient->name)

@section('page_actions')
  <a href="{{ route('patients.edit', $patient) }}" class="btn btn-light"><i class="fi fi-rr-pencil me-1"></i> Edit</a>
@endsection

@section('content')
  <div class="row g-3">
    <div class="col-xl-4">
      <x-card>
        <div class="text-center">
          <img src="{{ $patient->avatar_url }}" class="rounded-circle mb-3" width="84" height="84" alt="">
          <h5 class="mb-0">{{ $patient->name }}</h5>
          <p class="text-muted">{{ $patient->email }}</p>
          <dl class="row text-start small mb-0">
            <dt class="col-5 text-muted">Phone</dt><dd class="col-7">{{ $patient->phone ?? '—' }}</dd>
            <dt class="col-5 text-muted">DOB</dt><dd class="col-7">{{ $patient->date_of_birth?->format('M j, Y') ?? '—' }}</dd>
            <dt class="col-5 text-muted">Gender</dt><dd class="col-7">{{ ucfirst($patient->gender ?? '—') }}</dd>
            <dt class="col-5 text-muted">Address</dt><dd class="col-7">{{ $patient->address ?? '—' }}</dd>
          </dl>
        </div>
      </x-card>
    </div>
    <div class="col-xl-8">
      <x-card bodyClass="p-0">
        <ul class="nav nav-tabs px-3 pt-2" id="patientHistoryTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-appointments-btn" data-bs-toggle="tab" data-bs-target="#tab-appointments" type="button" role="tab">Appointments</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-billing-btn" data-bs-toggle="tab" data-bs-target="#tab-billing" type="button" role="tab">Billing</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-reviews-btn" data-bs-toggle="tab" data-bs-target="#tab-reviews" type="button" role="tab">Reviews</button>
          </li>
        </ul>
        <div class="tab-content">
          <div class="tab-pane fade show active" id="tab-appointments" role="tabpanel">
            <div class="table-responsive">
              <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th>When</th><th>Provider</th><th>Service</th><th>Reason / Notes</th><th>No-show risk</th><th>Status</th></tr></thead>
                <tbody>
                  @forelse ($patient->appointments as $a)
                    <tr onclick="window.location='{{ route('appointments.show', $a) }}'" style="cursor:pointer">
                      <td>{{ $a->start_at->format('M j, Y g:i A') }}</td>
                      <td>{{ $a->provider->name }}</td>
                      <td>{{ $a->service->name ?? '—' }}</td>
                      <td class="small text-muted">
                        {{ $a->reason ?? '—' }}
                        @if ($a->status === \App\Models\Appointment::STATUS_CANCELLED && $a->cancellation_reason)
                          <br><span class="text-danger">Cancelled: {{ $a->cancellation_reason }}</span>
                        @endif
                      </td>
                      <td>
                        @php $riskColor = ['high' => 'danger', 'medium' => 'warning', 'low' => 'success'][$a->risk_level] ?? 'secondary'; @endphp
                        <x-badge-status :color="$riskColor" :label="$a->no_show_score.'%'" />
                      </td>
                      <td><x-badge-status :color="$a->status_color" :label="$a->status_label" /></td>
                    </tr>
                  @empty
                    <x-empty-state colspan="6" icon="fi-rr-calendar" title="No appointments" />
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>
          <div class="tab-pane fade" id="tab-billing" role="tabpanel">
            <div class="table-responsive">
              <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th>Date</th><th>Type</th><th>Amount</th><th>Method</th><th>Status</th></tr></thead>
                <tbody>
                  @forelse ($patient->payments as $pay)
                    <tr>
                      <td>{{ $pay->created_at->format('M j, Y') }}</td>
                      <td>{{ ucfirst(str_replace('_', ' ', $pay->type)) }}</td>
                      <td>₹{{ number_format($pay->amount, 2) }}</td>
                      <td>{{ ucfirst($pay->method ?? '—') }}</td>
                      <td>
                        @php
                          $payBadge = match ($pay->status) {
                              'paid' => 'success',
                              'pending' => 'warning',
                              'forfeited', 'failed' => 'danger',
                              default => 'secondary',
                          };
                        @endphp
                        <x-badge-status :color="$payBadge" :label="ucfirst($pay->status)" />
                      </td>
                    </tr>
                  @empty
                    <x-empty-state colspan="5" icon="fi-rr-credit-card" title="No payments" />
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>
          <div class="tab-pane fade" id="tab-reviews" role="tabpanel">
            <div class="table-responsive">
              <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th>Date</th><th>Provider</th><th>Rating</th><th>Comment</th><th>Sentiment</th></tr></thead>
                <tbody>
                  @forelse ($patient->reviews as $r)
                    <tr>
                      <td>{{ $r->created_at->format('M j, Y') }}</td>
                      <td>{{ $r->provider->name ?? '—' }}</td>
                      <td class="text-warning">{{ str_repeat('★', $r->rating) }}</td>
                      <td>{{ $r->comment ?? '—' }}</td>
                      <td>
                        @php $sentimentColor = ['positive' => 'success', 'negative' => 'danger', 'neutral' => 'secondary'][$r->sentiment] ?? 'secondary'; @endphp
                        @if ($r->sentiment)<x-badge-status :color="$sentimentColor" :label="ucfirst($r->sentiment)" />@endif
                      </td>
                    </tr>
                  @empty
                    <x-empty-state colspan="5" icon="fi-rr-star" title="No reviews yet" />
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </x-card>
    </div>
  </div>
@endsection
