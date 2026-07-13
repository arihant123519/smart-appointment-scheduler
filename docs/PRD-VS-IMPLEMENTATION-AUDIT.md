# Tempo PRD vs. Actual Implementation — Audit

**Original audit date:** 2026-07-09
**Updated:** 2026-07-13 — after implementing Phases 1–6 (retention loop, dashboard completeness, deposits/payments, compliance basics, alternate booking channels, revenue & patient intelligence)
**Source PRD:** `PRD/Tempo — What It Does For Your Clinic.html`
**Codebase audited:** `scheduler/` (Laravel 11, PHP 8.2+, MySQL, Blade + Bootstrap 5, `spatie/laravel-permission`)

Legend: ✅ Implemented · 🟡 Partially implemented / scaffolded · ❌ Not built (needs a partner integration or out of scope)

---

## Summary

Of the ~58 PRD items originally audited, implementation now covers the large majority. What's genuinely left unbuilt requires either a partner/platform relationship this deployment doesn't have yet (Google Business Profile for "Reserve with Google", Meta for social-media booking, a telephony/IVR vendor for a full phone tree, real Stripe/Gupshup credentials to go live) or is explicitly out of scope by design (SSO, ABDM live registration — the clinic's own responsibility per the PRD itself).

## Area 1 — Getting the booking

| # | Feature | Status | Notes |
|---|---------|--------|-------|
| 1 | Website booking | ✅ | `BookingController`, `SchedulingService` |
| 2 | SMS/text booking | ✅ | `SmsBookingService` — cold "book" text offers next 3 slots, numeric reply confirms. Scoped to existing patients (phone on file); Gupshup SMS API integration real-ready, needs live credentials to send |
| 3 | Reserve with Google | ❌ | Requires a Google Business Profile partner integration — out of scope without that relationship |
| 4 | Book via QR code | ✅ | `QrBookingCode` model, real PNG generation (`endroid/qr-code`), scan/booking attribution tracked |
| 5 | Book through social media | ❌ | Requires a Meta Business partner integration |
| 6 | Automated phone tree | ❌ | Requires a telephony/IVR vendor |
| 7 | AI voice assistant booking | ❌ | Existing AI assistant is text-only; voice requires a telephony/speech vendor |
| 8 | Walk-in booking | ✅ | `WalkInQueue` model, staff add-to-queue UI, public live-position page |
| 9 | Missed-call text-back | ✅ | Webhook (`webhooks/missed-call`), rate-limited once/24h per number |
| 10 | Booking on someone else's behalf | 🟡 | Staff can book for any patient; no patient-facing "book for a family member" self-service option |
| 11 | Phone + OTP verification | ✅ | `/login/phone` — WhatsApp-delivered code, existing email/password login untouched. Scoped to existing accounts (schema requires email for new accounts) |

## Area 2 — Making sure the calendar never breaks

| # | Feature | Status | Notes |
|---|---------|--------|-------|
| 12 | Multiple providers, one system | ✅ | |
| 13 | Multiple locations, one system | ✅ | Real multi-tenant scoping (`ScopedToClinic`) |
| 14 | The double-booking guarantee | ✅ | **Fixed a real pre-existing bug** while building overbooking: the unique index included nullable `deleted_at`, and SQL's NULL≠NULL semantics meant the DB never actually rejected duplicate active bookings — only an app-level lock was protecting it. Now a generated column makes the DB-level guarantee genuinely airtight, verified via direct SQL-level stress tests (concurrent-style duplicate inserts, cancel/rebook cycles) |
| 15 | Syncing with existing clinic software (EMR) | ❌ | No EMR integration exists to sync with |
| 16 | Smart time/need matching | 🟡 | Keyword/specialty-based, not a full optimization engine |
| 17 | Predicting who might not show up | ✅ | `NoShowPredictor`, daily re-score |
| 18 | Automated recovery after a missed appointment | 🟡 | Notifies, but no one-click automated rebooking flow |
| 19 | Auto-filling cancelled slots from waitlist | ✅ | `WaitlistService`, now ranked by a computed patient-value score |
| 20 | Managing walk-in queues | ✅ | See #8 |

## Area 3 — Staying in touch before the visit

| # | Feature | Status | Notes |
|---|---------|--------|-------|
| 21–23 | Reminders, two-way texting, AI assistant | ✅ | Unchanged from original build |
| 24 | Digital forms auto-sent on confirmed booking | 🟡 | Form flow exists; no automatic push notification the instant a booking confirms |
| 25 | Pre-visit "what's your one question" prompt | ❌ | Not built as a distinct feature |
| 26 | Structured symptom questions | 🟡 | Static 5-field schema, not adaptive |
| 27 | Referral letters / consent forms, pre-filled + staff-approved | ❌ | Not built |
| 28 | Plain-language post-visit recap | ❌ | Not built |
| 29 | A/B-testable prompts with per-clinic analytics | 🟡 | Generic A/B testing framework now exists (`ExperimentService`) and is wired into the booking flow; not yet extended to intake/symptom prompt wording specifically |

## Area 4 — The moment they arrive

| # | Feature | Status | Notes |
|---|---------|--------|-------|
| 30 | Deposits for certain visit types | ✅ | `PaymentService::createDeposit()`, Razorpay-ready (raw REST, needs live keys, clinic-scoped credentials), manual driver fully functional |
| 31 | Configurable forfeiture policy | ✅ | Per-service `deposit_forfeit_hours`; no-show always forfeits, late cancellation forfeits within the window, otherwise auto-refunds |
| 32 | Slot auto-release if payment abandoned | ✅ | `payments:release-abandoned`, 10-minute hold, offers freed slot to waitlist |

## Area 5 — Keeping patients coming back

| # | Feature | Status | Notes |
|---|---------|--------|-------|
| 33 | Recall campaigns for overdue follow-ups | ✅ | `RetentionService`, per-service `recall_window_days` |
| 34 | Referral tracking through completion | ✅ | `Referral` model, patient share-link, conversion analytics on the Referrals page |
| 35 | Care-gap outreach | ✅ | Per-service `recall_cadence_days` |
| 36 | Follow-through nudges | ✅ | Reuses appointment `notes` field |
| 37 | Review requests, timed right | ✅ | Auto-fires 2h after a genuinely completed visit |

## Area 6 — Keeping everything safe and compliant

| # | Feature | Status | Notes |
|---|---------|--------|-------|
| 38 | HIPAA/DPDP compliance gate | 🟡 (scaffolding, by design) | `compliance_agreements_signed_at` hard-blocks clinic activation until checked — the actual legal agreements are the clinic's own process, not something software can complete |
| 39 | Audit logging | ✅ | Extended to cover payment/deposit actions (charge, refund, confirm, auto-forfeit, auto-release) that were previously untracked |
| 40 | SSO | ❌ | Out of scope (no identity provider configured) |
| 41 | ABDM integration | 🟡 (scaffolding, by design) | `abdm_health_id` field records a clinic's own completed registration; this app doesn't register clinics with the government framework itself |
| 42 | Role-based access (front-desk vs owner) | ✅ | Fixed: bulk patient-data CSV export now requires a dedicated `export patient data` permission (owner/manager only), no longer available to front-desk via `view appointments` |
| 43 | Phone + OTP login | ✅ | See #11 |
| 44 | White-label branding | ❌ | Not built |
| 45 | Data export / portability | 🟡 | CSV + iCal export exist; no single "everything about this patient" bundle |
| 46 | WCAG accessibility | 🟡 | Skip-to-content link, alert close-button label, booking-form label associations, slot-button `aria-pressed` state fixed — not a full audit |

## Area 7 — Understanding how well it's working

| # | Feature | Status | Notes |
|---|---------|--------|-------|
| 47 | Owner dashboard (fill rate, channel mix, no-show trend) | ✅ | Fill-rate gauge and channel-mix donut added, computed from real `Availability`/`Appointment` data |

## Area 8 — Revenue & patient intelligence

| # | Feature | Status | Notes |
|---|---------|--------|-------|
| 48 | Slot-value scoring | ✅ | `SlotScoringService` — day/hour demand scoring from real history, explainable |
| 49 | Patient-value scoring | ✅ | `PatientScoringService` — visit frequency, attendance reliability, referrals. Now drives waitlist priority (replacing manual-only entry), staff can still override |
| 50 | Controlled overbooking | ✅ | Opt-in per service, capacity = 1 + margin, only for day/hour slots with a demonstrated ≥25% historical no-show rate. Required a schema change (`overbook_slot` column + corrected unique index) — extensively stress-tested |
| 51 | Provider utilization by time block | ✅ | Day × hour heatmap added to Reports (previously only a flat per-provider total) |
| 52 | Benchmarking vs. similar clinics | ✅ | `BenchmarkingService` — real cross-clinic aggregation; honestly reports "not enough other clinics yet" below a minimum sample rather than fabricating a comparison |
| 53 | Turning patients into a referral channel | ✅ | See Area 5 #34 |
| 54 | Personalized rescheduling suggestions | ✅ | `RescheduleSuggestionService` — ranks open slots by the patient's own historical booking-hour pattern, surfaced on the appointment detail page |
| 55 | A/B testing for booking completion | ✅ | Generic `ExperimentService`, one real experiment live on the booking page (intro copy), results shown on Reports |
| 56 | Revenue-leak pattern flagging | ✅ | `RevenueLeakService` — high-cancellation slots, under-booked services, high-no-show channels, surfaced as plain-language flags on Reports |
| 57 | Explainable/overridable scores | ✅ | Every score (no-show, patient-value, slot-value) carries a plain-language reason; waitlist priority and appointment status always remain staff-editable |
| 58 | Schedule-template optimization suggestions | ✅ | `ScheduleOptimizationService` — flags a day-of-week fill-rate gap on the provider's availability page, never changes the template itself |

---

## What's still genuinely missing

Everything left unbuilt falls into one of two buckets:

1. **Needs a partner/vendor relationship this deployment doesn't have**: Reserve with Google (Google Business Profile), social-media booking (Meta), a full automated phone tree and AI voice assistant (telephony/speech vendor), white-label theming (a design/deployment decision, not blocked technically but not requested in detail).
2. **Deliberately out of scope**: SSO (no identity provider given), ABDM live registration (the clinic's own legal process), EMR sync (no target EMR specified), referral letters / plain-language recap / pre-visit "one question" prompt (would need new patient-facing content flows beyond what was asked for in this pass).

## Verification note

All 6 phases were verified with real data via `php artisan tinker` functional tests (not just code review) and the full automated test suite (44/44 passing) after every phase. Phase 6's controlled-overbooking work in particular required extensive direct SQL-level stress testing since it touches the core double-booking guarantee — see `database/migrations/2026_07_13_000003_fix_appointments_unique_constraint_null_deleted_at.php` for the full writeup of the bug found and fixed.
