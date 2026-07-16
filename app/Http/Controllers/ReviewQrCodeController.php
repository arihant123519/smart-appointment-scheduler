<?php

namespace App\Http\Controllers;

use App\Models\ReviewQrCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ReviewQrCodeController extends Controller
{
    public function index(): View
    {
        $codes = ReviewQrCode::forCurrentClinic()->orderByDesc('created_at')->get();

        return view('reviewqrcodes.index', compact('codes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
        ]);

        ReviewQrCode::create($data + [
            'clinic_id' => auth()->user()->clinic_id ?? 1,
            'token' => Str::random(24),
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'Review QR code created.');
    }

    public function destroy(ReviewQrCode $reviewqrcode): RedirectResponse
    {
        $reviewqrcode->delete();

        return back()->with('success', 'Review QR code removed.');
    }
}
