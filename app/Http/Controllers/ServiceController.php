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
        ]);
        $data['buffer'] ??= 0;
        $data['price'] ??= 0;
        $data['telehealth'] = $request->boolean('telehealth');
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
