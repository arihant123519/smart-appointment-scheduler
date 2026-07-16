<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Review;
use App\Services\AI\AiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReviewController extends Controller
{
    /** Staff/admin view of all feedback. */
    public function index(Request $request, AiService $ai): View
    {
        $isSystemAdmin = auth()->user()->hasRole('system_admin');
        $clinicId = $isSystemAdmin ? $request->integer('clinic_id') ?: null : null;

        $reviews = Review::with(['patient', 'provider.user'])
            ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->forCurrentClinic()
            ->latest()->get();
        $average = round((float) $reviews->avg('rating'), 1);
        $sentiment = $reviews->groupBy('sentiment')->map->count();

        // AI-extracted recurring themes across recent comments (PRD §5.2).
        $comments = $reviews->pluck('comment')->filter()->take(50)->values()->all();
        $themes = $comments ? $ai->summarizeFeedbackThemes($comments) : ['summary' => '', 'themes' => []];

        $clinics = $isSystemAdmin ? Clinic::orderBy('name')->get() : collect();

        return view('reviews.index', compact('reviews', 'average', 'sentiment', 'themes', 'clinics', 'clinicId'));
    }

    /** Lightweight JSON feed for the live-updating reviews list. */
    public function feed(Request $request): JsonResponse
    {
        $isSystemAdmin = auth()->user()->hasRole('system_admin');
        $clinicId = $isSystemAdmin ? $request->integer('clinic_id') ?: null : null;
        $afterId = $request->integer('after_id', 0);

        $items = Review::with(['patient', 'provider.user'])
            ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->forCurrentClinic()
            ->where('id', '>', $afterId)
            ->latest('id')->limit(20)->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'date' => $r->created_at->format('M j, Y'),
                'patient' => $r->reviewer_display_name,
                'provider' => $r->provider->name ?? '—',
                'rating' => $r->rating,
                'comment' => $r->comment,
                'sentiment' => $r->sentiment,
            ])->values();

        return response()->json(['items' => $items]);
    }

    /** Patient leaves feedback for a completed visit. */
    public function create(Appointment $appointment): View
    {
        abort_unless($appointment->patient_id === auth()->id(), 403);

        return view('reviews.create', compact('appointment'));
    }

    public function store(Request $request, Appointment $appointment, AiService $ai): RedirectResponse
    {
        abort_unless($appointment->patient_id === auth()->id(), 403);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        Review::create([
            'clinic_id' => $appointment->clinic_id,
            'appointment_id' => $appointment->id,
            'patient_id' => auth()->id(),
            'provider_id' => $appointment->provider_id,
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
            'sentiment' => $data['comment'] ? $ai->analyzeSentiment($data['comment'], $appointment->clinic_id) : null,
            'source' => 'portal',
        ]);

        return redirect()->route('dashboard')->with('success', 'Thanks for your feedback!');
    }
}
