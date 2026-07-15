@extends('layouts.app')

@section('title', 'Availability — '.$provider->name)

@section('content')
  @if (!empty($scheduleSuggestions))
    <div class="alert alert-info-subtle border-info-subtle mb-3">
      <div class="fw-semibold small mb-1">💡 Schedule suggestion</div>
      @foreach ($scheduleSuggestions as $suggestion)
        <div class="small mb-1">{{ $suggestion }}</div>
      @endforeach
      <div class="text-muted small mb-0">Based on the last 60 days of bookings. Nothing changes until you edit the hours below yourself.</div>
    </div>
  @endif

  <div class="row g-3">
    <div class="col-xl-7">
      <x-card title="Weekly working hours">
        <form method="POST" action="{{ route('availability.update', $provider) }}">
          @csrf @method('PUT')
          @foreach ($days as $dow => $label)
            @php $row = $provider->availabilities->firstWhere('day_of_week', $dow); @endphp
            <div class="row align-items-center mb-2">
              <div class="col-4 col-sm-3">
                <div class="form-check">
                  <input type="checkbox" class="form-check-input" name="hours[{{ $dow }}][enabled]" value="1" id="d{{ $dow }}" @checked($row)>
                  <label class="form-check-label" for="d{{ $dow }}">{{ $label }}</label>
                </div>
              </div>
              <div class="col"><input type="time" class="form-control form-control-sm" name="hours[{{ $dow }}][start]" value="{{ $row ? \Illuminate\Support\Str::substr($row->start_time, 0, 5) : '09:00' }}"></div>
              <div class="col-auto">to</div>
              <div class="col"><input type="time" class="form-control form-control-sm" name="hours[{{ $dow }}][end]" value="{{ $row ? \Illuminate\Support\Str::substr($row->end_time, 0, 5) : '17:00' }}"></div>
            </div>
          @endforeach
          <button class="btn btn-primary mt-3">Save working hours</button>
        </form>
      </x-card>
    </div>

    <div class="col-xl-5">
      <x-card title="Add time off / holiday" class="mb-3">
        <form method="POST" action="{{ route('availability.exception.store', $provider) }}">
          @csrf
          <div class="mb-2">
            <x-form-field name="date" type="date" label="Date" :required="true" />
          </div>
          <div class="mb-2">
            <x-form-field name="reason" label="Reason" placeholder="Holiday, vacation…" />
          </div>
          <button class="btn btn-outline-danger w-100">Block this day</button>
        </form>
      </x-card>
      <x-card title="Exceptions" bodyClass="p-0">
        <div class="table-responsive">
          <table class="table mb-0">
            <tbody>
              @forelse ($provider->exceptions as $ex)
                <tr>
                  <td>{{ $ex->date->format('M j, Y') }}</td>
                  <td>{{ $ex->is_available ? 'Extra hours' : 'Blocked' }} — {{ $ex->reason }}</td>
                  <td class="text-end">
                    <form method="POST" action="{{ route('availability.exception.destroy', [$provider, $ex]) }}">
                      @csrf @method('DELETE')
                      <button class="btn btn-sm btn-light">✕</button>
                    </form>
                  </td>
                </tr>
              @empty
                <x-empty-state colspan="3" icon="fi-rr-calendar-check" title="No exceptions" />
              @endforelse
            </tbody>
          </table>
        </div>
      </x-card>
    </div>
  </div>
@endsection
