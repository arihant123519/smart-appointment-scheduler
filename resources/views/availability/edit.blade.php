@extends('layouts.app')

@section('title', 'Availability — '.$provider->name)

@section('content')
  <div class="row g-3">
    <div class="col-xl-7">
      <div class="card">
        <div class="card-header"><h6 class="mb-0">Weekly working hours</h6></div>
        <div class="card-body">
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
        </div>
      </div>
    </div>

    <div class="col-xl-5">
      <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0">Add time off / holiday</h6></div>
        <div class="card-body">
          <form method="POST" action="{{ route('availability.exception.store', $provider) }}">
            @csrf
            <div class="mb-2"><label class="form-label">Date</label><input type="date" name="date" class="form-control" required></div>
            <div class="mb-2"><label class="form-label">Reason</label><input type="text" name="reason" class="form-control" placeholder="Holiday, vacation…"></div>
            <button class="btn btn-outline-danger w-100">Block this day</button>
          </form>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><h6 class="mb-0">Exceptions</h6></div>
        <div class="card-body p-0">
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
                <tr><td class="text-center text-muted py-3">No exceptions.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
@endsection
