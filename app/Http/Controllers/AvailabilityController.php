<?php

namespace App\Http\Controllers;

use App\Models\Availability;
use App\Models\AvailabilityException;
use App\Models\Provider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AvailabilityController extends Controller
{
    public function edit(Provider $provider): View
    {
        $provider->load(['user', 'availabilities', 'exceptions' => fn ($q) => $q->orderBy('date')]);
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        return view('availability.edit', compact('provider', 'days'));
    }

    /** Replace the provider's weekly working hours. */
    public function update(Request $request, Provider $provider): RedirectResponse
    {
        $data = $request->validate([
            'hours' => ['array'],
            'hours.*.enabled' => ['nullable', 'boolean'],
            'hours.*.start' => ['nullable', 'date_format:H:i'],
            'hours.*.end' => ['nullable', 'date_format:H:i', 'after:hours.*.start'],
        ]);

        $provider->availabilities()->delete();

        foreach ($data['hours'] ?? [] as $dow => $row) {
            if (! empty($row['enabled']) && ! empty($row['start']) && ! empty($row['end'])) {
                $provider->availabilities()->create([
                    'day_of_week' => (int) $dow,
                    'start_time' => $row['start'].':00',
                    'end_time' => $row['end'].':00',
                    'recurring' => true,
                ]);
            }
        }

        return back()->with('success', 'Working hours updated.');
    }

    /** Add a day off / holiday / one-off block. */
    public function storeException(Request $request, Provider $provider): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'is_available' => ['nullable', 'boolean'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
        ]);

        $provider->exceptions()->create([
            'date' => $data['date'],
            'reason' => $data['reason'] ?? 'Time off',
            'is_available' => $request->boolean('is_available'),
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
        ]);

        return back()->with('success', 'Exception added.');
    }

    public function destroyException(Provider $provider, AvailabilityException $exception): RedirectResponse
    {
        abort_unless($exception->provider_id === $provider->id, 404);
        $exception->delete();

        return back()->with('success', 'Exception removed.');
    }
}
