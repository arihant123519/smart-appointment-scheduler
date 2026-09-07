# Smart Appointment Scheduler

A complete appointment-scheduling platform for healthcare clinics — built to replace the
mess of phone-tag, paper registers, and spreadsheet calendars that most small-to-mid-size
clinics still run on.

## The problem

Most clinics juggle scheduling across too many disconnected tools: a front-desk register,
a WhatsApp group for reminders, a notebook for the walk-in queue, and a gut feeling for
which patients are likely to skip their appointment. The result is predictable —

- **No-shows eat into revenue** and nobody notices the pattern until it's a habit.
- **Empty slots stay empty** even when there's a waitlist of patients who'd have taken them.
- **Reminders are manual**, inconsistent, and easy to forget.
- **Walk-ins and booked patients collide** at the front desk with no shared queue.
- **Owners have no visibility** into where the clinic is quietly losing money or how it
  compares to running well.
- **Patient follow-up falls through the cracks** — no easy way to bring lapsed patients back.

This project tackles all of that in one system, built specifically around how a real
clinic's day actually runs — reception, providers, billing, and patients all working off
the same calendar.

## Who it's for

Independent clinics, multi-provider practices, and small clinic chains that need real
scheduling software without paying for (or wrestling with) an enterprise EHR suite.

## What it does

**Booking & scheduling**
- Live, conflict-free slot booking — no double-booking, ever.
- Patients can self-book online, 24/7, without calling in.
- Booking also works over **SMS and WhatsApp** for patients who'd rather text than use an app.
- QR-code check-in and QR-based booking for walk-ins and in-clinic posters.
- A shared walk-in queue so front desk isn't juggling a paper list alongside the calendar.
- Waitlist that **auto-offers freed-up slots** to the best-matched waiting patient the moment
  a cancellation happens — no manual calling down a list.

**Smart, but honest, automation**
- No-show risk scoring on every upcoming appointment, so staff know who to double-confirm.
- Personalized reschedule suggestions based on a patient's own booking habits (a patient who
  always books mornings gets offered mornings first).
- Plain-language flags on patterns that are quietly costing the clinic money — always shown
  to a human to act on, never applied automatically.
- Suggestions for reshaping a provider's weekly schedule around real demand.
- Anonymous benchmarking against comparable clinics, without ever exposing another clinic's data.
- All of this is designed to be a dependable, explainable baseline first — with room to plug
  in a smarter AI layer on top later, rather than depending on one to function at all.

**Patient experience**
- Self-service dashboard: book, reschedule, cancel, view history.
- Automated reminders across channels, plus review requests after a visit.
- Telehealth links generated automatically for virtual visits.
- A recall/retention loop that reaches out to patients who are overdue for a follow-up.

**Clinic operations**
- Role-based access for system admin, clinic admin, front desk, provider, and billing staff,
  so everyone sees only what's relevant to their job.
- Full calendar views (day/week/month/list) across providers and clinics.
- Patient records, intake forms, documents, prescriptions, and consultation notes in one place.
- Billing and payment tracking.
- Referral tracking between providers.
- An audit trail of who changed what, for accountability.

**Insights**
- A dashboard with the numbers that actually matter day-to-day — bookings, no-shows,
  utilization, revenue trends.
- Deeper reports: no-shows by provider, booking-channel mix, schedule utilization, billing summary.

## What's genuinely difficult about this problem (and how it's handled)

- **Double-booking under concurrent requests** — two people booking the same slot at the same
  moment is a real race condition, not just a UI validation problem. Handled at the data layer,
  not just the form.
- **"Smart" features that clinics can actually trust** — automated suggestions are only useful
  if staff understand *why* a system is recommending something. Every score or suggestion comes
  with a plain-language reason, and nothing is changed without a human approving it.
- **Multiple booking channels feeding one calendar** — web, WhatsApp, SMS, and QR bookings all
  have to land on the same conflict-free schedule without stepping on each other.
- **Clinics starting from zero data** — benchmarking and scoring features are honest about the
  cold-start problem (too little history to compare against) instead of faking a confident number.

## Status

Core scheduling, staff/admin tooling, patient self-service, multi-channel booking and
reminders, the smart-scheduling features, telehealth, and reporting are all live.
Payment gateway integration, deeper EHR/health-record interoperability, and a full
AI-assistant layer on top of the current rule-based engine are the next frontier.

## Getting started

```bash
composer install
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

## Demo logins (password: `password`)

| Role | Email |
|------|-------|
| System Admin | `admin@scheduler.test` |
| Clinic Admin | `clinicadmin@scheduler.test` |
| Front Desk | `frontdesk@scheduler.test` |
| Billing | `billing@scheduler.test` |
| Provider | `sarah.chen@scheduler.test` |
| Patient | `patient1@scheduler.test` … `patient15@scheduler.test` |

---

Built with Laravel, MySQL, and a Bootstrap-based admin interface.
