<?php

namespace App\Http\Controllers;

use App\Models\QrBookingCode;
use App\Models\Service;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

class QrCodeController extends Controller
{
    public function index(): View
    {
        $codes = QrBookingCode::with('service')->forCurrentClinic()->orderByDesc('created_at')->get();
        $services = Service::forCurrentClinic()->where('is_active', true)->orderBy('name')->get();

        return view('qrcodes.index', compact('codes', 'services'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'service_id' => ['nullable', 'exists:services,id'],
        ]);

        QrBookingCode::create($data + [
            'clinic_id' => auth()->user()->clinic_id ?? 1,
            'token' => Str::random(24),
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'QR code created.');
    }

    public function destroy(QrBookingCode $qrcode): RedirectResponse
    {
        $qrcode->delete();

        return back()->with('success', 'QR code removed.');
    }

    /**
     * Streams the QR image (PNG) — printable, embeddable, no login required.
     * Keyed by token (not the numeric id) so a printed poster's URL can't be
     * trivially enumerated to another clinic's codes.
     */
    public function image(string $token): Response
    {
        $qr = QrBookingCode::where('token', $token)->firstOrFail();

        $result = (new Builder())->build(
            writer: new PngWriter(),
            data: $qr->redeem_url,
            size: 480,
            margin: 16,
        );

        return response($result->getString(), 200)->header('Content-Type', $result->getMimeType());
    }

    /**
     * Public redemption (token-authenticated, no login needed to scan):
     * records the scan, stashes attribution + any pre-selected service in
     * session, then sends the visitor on to book — BookingController::store
     * tags the resulting appointment with this code's source once the
     * booking actually succeeds (never on scan alone).
     */
    public function redeem(string $token): RedirectResponse
    {
        $qr = QrBookingCode::where('token', $token)->first();
        if (! $qr) {
            return redirect()->route('login')->with('error', 'This QR code is no longer valid.');
        }

        $qr->increment('scans_count');
        session(['qr_token' => $token, 'qr_service_id' => $qr->service_id]);

        return auth()->check()
            ? redirect()->route('booking.create')
            : redirect()->route('register');
    }
}
