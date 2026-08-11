<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A focused subset of the clinic profile (see ClinicController, `manage clinics`
 * only) that `manage settings` holders — clinic_admin included — can reach to
 * configure their own clinic's prescription letterhead without full clinic CRUD.
 */
class PrescriptionSettingsController extends Controller
{
    public function edit(): View
    {
        $clinic = $this->clinic();

        return view('settings.prescription', compact('clinic'));
    }

    public function update(Request $request): RedirectResponse
    {
        $clinic = $this->clinic();

        $data = $request->validate([
            'prescription_header_note' => ['nullable', 'string', 'max:500'],
            'prescription_footer_text' => ['nullable', 'string', 'max:1000'],
        ]);

        $clinic->update($data);

        return back()->with('success', 'Prescription letterhead updated.');
    }

    private function clinic(): Clinic
    {
        abort_unless(auth()->user()->clinic_id, 404, 'No clinic assigned to your account.');

        return Clinic::findOrFail(auth()->user()->clinic_id);
    }
}
