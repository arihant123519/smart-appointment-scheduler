<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\ReviewQrCode;
use App\Services\AI\AiService;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Public, no-login review submission via a clinic's QR code / shareable
 * link — kept separate from ReviewController (which is staff-only + the
 * authenticated patient flow) since these routes sit outside the `auth`
 * middleware group entirely.
 */
class PublicReviewController extends Controller
{
    public function show(string $token): View
    {
        $qr = ReviewQrCode::with('clinic')->where('token', $token)->firstOrFail();
        $qr->increment('scans_count');

        return view('reviews.public', ['qr' => $qr]);
    }

    public function store(Request $request, string $token, AiService $ai): RedirectResponse
    {
        $qr = ReviewQrCode::where('token', $token)->firstOrFail();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        Review::create([
            'clinic_id' => $qr->clinic_id,
            'reviewer_name' => $data['name'],
            'reviewer_phone' => $data['phone'],
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
            'sentiment' => $data['comment'] ? $ai->analyzeSentiment($data['comment'], $qr->clinic_id) : null,
            'source' => 'public_qr',
        ]);

        $qr->increment('submissions_count');

        return back()->with('success', 'Thanks for your feedback!');
    }

    /** Streams the QR image (PNG) — printable, no login required to scan. */
    public function image(string $token): Response
    {
        $qr = ReviewQrCode::where('token', $token)->firstOrFail();

        $result = (new Builder())->build(
            writer: new PngWriter(),
            data: $qr->submit_url,
            size: 480,
            margin: 16,
        );

        return response($result->getString(), 200)->header('Content-Type', $result->getMimeType());
    }
}
