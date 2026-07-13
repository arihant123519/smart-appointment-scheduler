<?php

namespace App\Services;

use App\Models\Experiment;
use App\Models\ExperimentAssignment;

/**
 * Generic A/B testing (PRD "testing what actually improves booking
 * completion"): two versions of a specific step shown to different patients
 * and compared on actual completion rates, with a clear winner adopted going
 * forward — never silently, always as a reviewed change (see results()).
 *
 * Assignment is a deterministic hash split, so the same subject always sees
 * the same variant across visits (no flicker, no double-counting).
 */
class ExperimentService
{
    /**
     * @param  array<int, string>  $variants
     */
    public function variantFor(string $key, array $variants, string $subjectKey): string
    {
        $experiment = Experiment::firstOrCreate(
            ['key' => $key],
            ['name' => $key, 'variants' => $variants, 'status' => 'active'],
        );

        $assignment = ExperimentAssignment::firstOrNew([
            'experiment_id' => $experiment->id,
            'subject_key' => $subjectKey,
        ]);

        if (! $assignment->exists) {
            $options = $experiment->variants ?: $variants;
            $index = crc32($key.'|'.$subjectKey) % count($options);
            $assignment->variant = $options[$index];
            $assignment->save();
        }

        return $assignment->variant;
    }

    public function recordConversion(string $key, string $subjectKey): void
    {
        $experiment = Experiment::where('key', $key)->first();
        if (! $experiment) {
            return;
        }

        ExperimentAssignment::where('experiment_id', $experiment->id)
            ->where('subject_key', $subjectKey)
            ->whereNull('converted_at')
            ->update(['converted_at' => now()]);
    }

    /** @return array{experiment: Experiment, variants: array<int, array{variant: string, assigned: int, converted: int, rate: float}>}|null */
    public function results(string $key): ?array
    {
        $experiment = Experiment::where('key', $key)->first();
        if (! $experiment) {
            return null;
        }

        $rows = ExperimentAssignment::where('experiment_id', $experiment->id)
            ->selectRaw('variant, count(*) as assigned, sum(case when converted_at is not null then 1 else 0 end) as converted')
            ->groupBy('variant')
            ->get()
            ->map(fn ($r) => [
                'variant' => $r->variant,
                'assigned' => (int) $r->assigned,
                'converted' => (int) $r->converted,
                'rate' => $r->assigned > 0 ? round($r->converted / $r->assigned * 100, 1) : 0,
            ]);

        return ['experiment' => $experiment, 'variants' => $rows->all()];
    }

    /** All experiments with results, for an admin summary screen. */
    public function allResults(): array
    {
        return Experiment::all()->map(fn (Experiment $e) => $this->results($e->key))->filter()->values()->all();
    }
}
