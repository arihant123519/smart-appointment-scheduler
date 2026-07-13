<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(): View
    {
        $services = Service::forCurrentClinic()->withCount('appointments')->orderBy('name')->get();

        return view('services.index', compact('services'));
    }

    public function create(): View
    {
        return view('services.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $service = Service::create($this->validated($request) + [
            'clinic_id' => auth()->user()->clinic_id ?? 1,
        ]);

        return redirect()->route('services.index')->with('success', "Service \"{$service->name}\" created.");
    }

    public function edit(Service $service): View
    {
        return view('services.edit', compact('service'));
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        $service->update($this->validated($request));

        return redirect()->route('services.index')->with('success', 'Service updated.');
    }

    public function destroy(Service $service): RedirectResponse
    {
        $service->delete();

        return redirect()->route('services.index')->with('success', 'Service removed.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'duration' => ['required', 'integer', 'min:5', 'max:480'],
            'buffer' => ['nullable', 'integer', 'min:0', 'max:120'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'color' => ['nullable', 'string', 'max:20'],
            'telehealth' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'recall_window_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'recall_cadence_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'deposit_required' => ['nullable', 'boolean'],
            'deposit_amount' => ['nullable', 'required_if:deposit_required,1', 'numeric', 'min:0.5'],
            'deposit_forfeit_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            'overbooking_enabled' => ['nullable', 'boolean'],
            'overbooking_margin' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);
        $data['buffer'] ??= 0;
        $data['price'] ??= 0;
        $data['telehealth'] = $request->boolean('telehealth');
        $data['is_active'] = $request->boolean('is_active', true);
        $data['deposit_required'] = $request->boolean('deposit_required');
        $data['deposit_amount'] = $data['deposit_required'] ? $data['deposit_amount'] : null;
        $data['overbooking_enabled'] = $request->boolean('overbooking_enabled');
        $data['overbooking_margin'] = $data['overbooking_enabled'] ? ($data['overbooking_margin'] ?? 1) : 0;

        return $data;
    }
}
