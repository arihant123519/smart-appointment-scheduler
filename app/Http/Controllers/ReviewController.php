<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Review;
use App\Services\AI\AiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReviewController extends Controller
{
    /** Staff/admin view of all feedback. */
    public function index(AiService $ai): View
    {
        $reviews = Review::with(['patient', 'provider.user'])->latest()->get();
        $average = round((float) $reviews->avg('rating'), 1);
        $sentiment = $reviews->groupBy('sentiment')->map->count();

        // AI-extracted recurring themes across recent comments (PRD §5.2).
        $comments = $reviews->pluck('comment')->filter()->take(50)->values()->all();
        $themes = $comments ? $ai->summarizeFeedbackThemes($comments) : ['summary' => '', 'themes' => []];

        return view('reviews.index', compact('reviews', 'average', 'sentiment', 'themes'));
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
            'appointment_id' => $appointment->id,
            'patient_id' => auth()->id(),
            'provider_id' => $appointment->provider_id,
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
            'sentiment' => $data['comment'] ? $ai->analyzeSentiment($data['comment'], $appointment->clinic_id) : null,
        ]);

        return redirect()->route('dashboard')->with('success', 'Thanks for your feedback!');
    }
}
