# Smart Appointment Scheduler ("Tempo") — Consolidated PRD & Implementation Status

**Sources merged into this document:**
- `PRD/prd.html` — Smart Appointment Scheduler (Healthcare) PRD v1.0, 22 Jun 2026
- `PRD/Tempo — What It Does For Your Clinic - Final changes.html` — Tempo plain-language business scope v2, 8 Jul 2026 (Ichelon Consulting Group, prepared for pilot clinic rollout)
- `scheduler/docs/PRD-GAP-ANALYSIS.md` — codebase-vs-PRD gap analysis
- `scheduler/docs/PRD-VS-IMPLEMENTATION-AUDIT.md` — Tempo-PRD-vs-implementation audit (updated after Phases 1–6)
- `scheduler/docs/MANUAL-TEST-CASES-AND-ROUTES.md` — route reference + manual QA cases
- `scheduler/docs/NEW-FEATURES-TESTING-GUIDE.md` — phase-by-phase feature testing guide

This is the single reference: what the product is supposed to do (two PRDs, written at different times with different framings), what's actually built, what's left, and how to test it.

---

## 1. What This Product Is

Two PRDs exist for the same underlying codebase, written ~2 weeks apart with different audiences:

| | Original PRD (`prd.html`) | Tempo business-scope doc |
|---|---|---|
| Audience | Eng/Design/QA/stakeholders | Clinic owners, non-technical |
| Framing | Feature-ID checklist (CORE-xx / AI-xx / FUT-xx), MoSCoW priority | Patient-journey narrative, funnel/drop-off analysis, pricing & accountability |
| Scope | Full healthcare platform incl. EHR-adjacent, billing, telehealth | Deliberately narrower: booking + retention layer that sits *on top of* existing clinic software, explicitly not touching medical records/billing/pharmacy |

**One-line pitch:** An AI-augmented healthcare appointment scheduler (Laravel/MySQL) that captures bookings from every channel a patient might use, technically guarantees no double-booking, and actively works to recover cancellations, no-shows, and lapsing patients instead of just running a calendar.

**Problem it solves:** Manual phone-based booking drives high no-show rates (industry avg. 15–30%), wastes front-desk time, and most of what a clinic loses — cancelled slots, no-shows, forgotten follow-ups — just evaporates silently with no system built to catch it.

---

## 2. Vision & Goals

> "To make healthcare scheduling effortless for patients and intelligent for providers — eliminating no-shows, idle time and administrative friction through automation and AI."

| Goal | Target |
|---|---|
| Reduce no-shows | −30–40% via prediction + multi-channel reminders + smart waitlist |
| Increase access | 24/7 self-service booking; ≥60% of bookings without staff involvement |
| Save staff time | 10+ front-desk hours/week/clinic via automated reminders/intake/recall |
| Optimize utilization | +15% provider/room utilization via load balancing + cancellation auto-fill |
| Stay compliant | HIPAA-aware controls: encryption, audit logs, RBAC |
| Delight patients | Booking CSAT > 4.5/5 |

**Guiding principles:** reliability first (core scheduling must never double-book or lose data); AI assists, humans decide (no autonomous clinical/final booking decisions); compliance by design; mobile-first & accessible (WCAG 2.1 AA target).

---

## 3. Target Users & Personas

| Persona | Needs |
|---|---|
| Front-Desk Staff | Fast calendar, minimal clicks, daily view, waitlist tools |
| Provider (Doctor/Therapist/Nurse) | Own availability rules, intake summaries, telehealth launch |
| Patient | 24/7 booking, reminders, easy reschedule, intake forms, telehealth, multi-language |
| Clinic/Practice Admin | Multi-location setup, reporting, role management, billing config |
| System Admin | RBAC, audit logs, API keys, integration settings |
| Billing Staff (secondary) | Eligibility verification, payment records, exports |

---

## 4. Scope

### 4.1 Original PRD — In Scope (v1.0)
Patient self-service booking (web + responsive mobile) · multi-provider/location/resource scheduling · automated multi-channel reminders (SMS/email) · smart waitlist & cancellation auto-fill · digital intake forms & check-in · AI assistant (NL booking, reminders, no-show prediction, intake summary) · role-based admin/dashboards/reports · online payments/copay · telehealth video booking (via integration).

### 4.2 Original PRD — Out of Scope (v1.0)
Full EHR/clinical charting (integrate, don't replace) · medical billing/claims adjudication (RCM via integration only) · e-prescribing & lab orders · native iOS/Android apps (responsive web first) · AI-driven diagnosis/treatment recommendations · insurance claims submission.

### 4.3 Tempo scope-boundary framing (narrower, sharper)
**Does:** lets patients book through website, text, Google, QR code, social media, phone tree, AI voice assistant, or walk-in · guarantees at the DB level that two patients can never get the same slot · reminds automatically, answers simple questions, sends the right paperwork · helps patients prepare for/make sense of a consultation (pre-visit question prompt, symptom questions, plain-language recap) without touching the clinical record · auto-refills cancelled/missed slots · follows up after the visit (recall, referral tracking, review requests) · scores which slots/patients are worth the most and helps act on it · keeps things secure/compliant/branded as the clinic · shows fill rate, booking-source mix, and clinic-to-clinic comparison in one dashboard.

**Does not:** store/manage medical records or clinical notes · handle billing/invoicing/insurance claims · manage pharmacy stock, lab orders/results · track clinic inventory/vendors · run check-in kiosks/hardware · host video calls itself (schedules them, doesn't run the video) · act as a patient-discovery marketplace.

---

## 5. Technology Stack & Architecture

| Layer | Technology | Purpose |
|---|---|---|
| Backend | Laravel 11 (PHP 8.2+) | Core app, REST API, business logic, queues |
| Database | MySQL 8 / MariaDB | Appointments, users, schedules |
| Cache/Queue | Redis + Laravel Queue (PRD target); DB queue driver in practice | Reminders, AI jobs, notifications |
| Frontend | Blade (PRD wanted Livewire/Vue + Tailwind; built with **Bootstrap 5 "NexLink"**) | Responsive UI, interactive calendar (FullCalendar) |
| AI Layer | Google Gemini / OpenAI ChatGPT via a provider-agnostic **AI Service Gateway** | NL booking, summarization, prediction |
| Auth | Custom session auth built; PRD wanted Sanctum/Breeze + 2FA | Sessions (no API tokens/MFA yet) |
| Notifications | Gupshup (WhatsApp primary, SMS fallback), SMTP/Mailgun | Reminders & alerts |
| Payments | Originally spec'd as Stripe; **built on Razorpay** (India/INR-focused) | Deposits/copays, refunds |
| Telehealth | Zoom/Twilio Video/Daily.co (integration only — flag+link field, no video hosting) | Virtual visit links |
| Scheduled Jobs | Laravel Scheduler (cron) | Reminders, recall, prediction batch |
| RBAC | `spatie/laravel-permission`, 6 roles | Route + view gating |

**AI integration approach (original PRD):** all AI calls route through one AI Service Gateway; `AI_PROVIDER=gemini|openai` config flag swaps providers with no business-code change; PHI minimized/pseudonymized before any external call; heavy AI tasks run async on queue workers; graceful rule-based fallback if the AI provider is down.

**High-level architecture (target):**
```
CLIENTS: Patient Web · Staff Portal · Admin Console
        │ HTTPS / REST + Livewire
LARAVEL APPLICATION (PHP 8.2)
  Controllers · Auth/RBAC · Scheduling Engine · Policies
  [Booking & Slot Service] [Notification Service] [AI Service Gateway] → Gemini/ChatGPT API
  Queue Workers (Redis) · Scheduler (cron)
        │           │            │            │
     MySQL DB   Redis Cache  Twilio/SMTP  Razorpay/Zoom (3rd-party)
```

**Under-the-hood plain-language summary (Tempo doc):** bank-grade database with the double-booking guarantee at its deepest layer, not something dependent on app code behaving perfectly · fast in-memory cache so pages load instantly even at peak · WhatsApp/SMS via an established provider, not a homemade texting setup · AI voice assistant built on a conversational voice service designed for real phone calls · automatic monitoring that pages an engineer before an issue becomes patient-visible.

---

## 6. User Roles & Permissions

RBAC via Laravel Policies; sensitive access is audit-logged.

| Role | Key capabilities | Restrictions |
|---|---|---|
| Patient | Book/reschedule/cancel own appointments, intake, pay, message clinic, view own history | Sees only own data |
| Front-Desk Staff | Manage calendar, book for patients, check-in, waitlist, reminders | No billing config/system settings |
| Provider | Own schedule, set availability, intake summaries, telehealth launch | Limited to assigned patients/locations |
| Billing Staff | Payments, eligibility checks, invoices/refunds | No clinical schedule edits |
| Clinic Admin | Configure locations/services/providers/policies, all reports | Scoped to own clinic/tenant |
| System Admin | Full access: users, roles, integrations, AI config, audit logs | — |

**Permission matrix (as implemented):**

| Permission | Patient | Front Desk | Provider | Billing | Clinic Admin | System Admin |
|---|:--:|:--:|:--:|:--:|:--:|:--:|
| view dashboard | – | ✔ | ✔ | ✔ | ✔ | ✔ |
| view appointments | – | ✔ | ✔ | ✔ | ✔ | ✔ |
| manage appointments | – | ✔ | – | – | ✔ | ✔ |
| manage calendar | – | ✔ | ✔ | – | ✔ | ✔ |
| manage patients | – | ✔ | – | – | ✔ | ✔ |
| manage providers | – | – | – | – | ✔ | ✔ |
| manage services | – | – | – | – | ✔ | ✔ |
| manage waitlist | – | ✔ | – | – | ✔ | ✔ |
| view reports | – | – | – | ✔ | ✔ | ✔ |
| view billing | – | – | – | ✔ | ✔ | ✔ |
| manage users | – | – | – | – | ✔ | ✔ |
| manage clinics | – | – | – | – | ✔ | ✔ |
| view audit logs | – | – | – | – | ✔ | ✔ |
| export patient data | – | – | – | – | ✔ | ✔ |

Patients hold no staff permissions; opening a staff URL as a patient returns 403.

**Core user flows:** patient self-booking (service → provider/location → slot → details → optional copay → confirm → reminder) · AI natural-language booking ("I need a dentist next Tuesday afternoon" → parsed intent → proposed slots → confirm) · cancellation → waitlist auto-fill → best-match patient offered → first to accept books · reminder → patient replies CONFIRM/reschedule → status updated.

---

## 7. Core Features — Phase 1 (MVP) — Original PRD

### 7.1 Scheduling & Calendar
| ID | Feature | Priority |
|---|---|---|
| CORE-01 | Online self-booking (24/7) | Must |
| CORE-02 | Real-time availability, locking to prevent double-booking | Must |
| CORE-03 | Provider calendars, individual hours/rules | Must |
| CORE-04 | Multi-location support | Must |
| CORE-05 | Multi-resource scheduling (rooms/equipment) | Should |
| CORE-06 | Appointment types with set durations | Must |
| CORE-07 | Custom durations & buffers | Must |
| CORE-08 | Recurring appointments | Should |
| CORE-09 | Working-hours & rules (windows, breaks, holidays) | Must |
| CORE-10 | Time-zone handling | Should |
| CORE-11 | Drag-and-drop calendar | Should |
| CORE-12 | Reschedule & cancel (self-service, within policy) | Must |

### 7.2 Patient Engagement & Communication
| ID | Feature | Priority |
|---|---|---|
| CORE-13 | Automated reminders (SMS/email/push) | Must |
| CORE-14 | Two-way messaging | Should |
| CORE-15 | Confirmation requests | Must |
| CORE-16 | Recall/recare | Should |
| CORE-17 | Follow-up automation | Should |
| CORE-18 | Broadcast messaging | Could |
| CORE-19 | Reviews & feedback | Could |

### 7.3 Intake & Forms
| ID | Feature | Priority |
|---|---|---|
| CORE-20 | Digital intake forms | Must |
| CORE-21 | Pre-visit questionnaires | Should |
| CORE-22 | Digital check-in | Should |
| CORE-23 | Consent & e-signatures | Must |

### 7.4 Healthcare-Specific
| ID | Feature | Priority |
|---|---|---|
| CORE-24 | HIPAA-aware security (encryption in transit/at rest) | Must |
| CORE-25 | Role-based access control | Must |
| CORE-26 | Audit logs | Must |
| CORE-27 | EHR/EMR integration (HL7/FHIR) | Should |
| CORE-28 | PMS integration | Could |
| CORE-29 | Insurance eligibility check | Should |
| CORE-30 | Telehealth/video booking | Should |
| CORE-31 | Provider credentialing rules | Could |

### 7.5 Payments & Billing
| ID | Feature | Priority |
|---|---|---|
| CORE-32 | Online payments | Should |
| CORE-33 | Copay collection | Should |
| CORE-34 | Invoicing & receipts | Could |
| CORE-35 | Refunds & cancellation fees | Could |
| CORE-36 | RCM integration | Could |

### 7.6 Administration & Analytics
| ID | Feature | Priority |
|---|---|---|
| CORE-37 | Dashboards | Must |
| CORE-38 | No-show & cancellation reports | Should |
| CORE-39 | Utilization reports | Should |
| CORE-40 | Wait-time analytics | Could |
| CORE-41 | Staff/provider management | Must |
| CORE-42 | Custom reporting & export (CSV/PDF) | Could |

### 7.7 Access & Platform
| ID | Feature | Priority |
|---|---|---|
| CORE-43 | Responsive web app | Must |
| CORE-44 | Patient portal | Must |
| CORE-45 | Calendar sync (Google/Outlook/iCal) | Should |
| CORE-46 | Multi-language support | Could |
| CORE-47 | Notifications center | Should |
| CORE-48 | API & integrations (REST) | Should |

---

## 8. AI Features — Phase 2 (Differentiator) — Original PRD

### 8.1 Smart Scheduling Intelligence
| ID | Feature | Priority |
|---|---|---|
| AI-01 | No-show prediction (history, lead time, demographics, weather) | Must |
| AI-02 | Smart waitlist, auto-offers to best-matched patient | Must |
| AI-03 | Auto cancellation fill, ranked by fit & urgency | Should |
| AI-04 | Slot recommendation | Should |
| AI-05 | Load balancing across providers | Should |
| AI-06 | Overbooking optimization | Could |
| AI-07 | Demand-based scheduling | Could |

### 8.2 Conversational & Generative AI
| ID | Feature | Priority |
|---|---|---|
| AI-08 | Natural-language booking | Must |
| AI-09 | AI scheduling assistant (chatbot), 24/7 | Should |
| AI-10 | Intake summarization | Should |
| AI-11 | Smart symptom triage routing (informational, not diagnostic) | Could |
| AI-12 | Smart reminder generation (personalized, multilingual) | Should |
| AI-13 | Multilingual real-time translation | Could |
| AI-14 | Sentiment & feedback analysis | Could |

### 8.3 AI-Assisted Operations
| ID | Feature | Priority |
|---|---|---|
| AI-15 | Predictive analytics & insights | Should |
| AI-16 | Natural-language report queries | Could |
| AI-17 | Smart follow-up suggestions | Could |

**AI governance & safety:** AI is assistive only — never autonomous clinical or final booking decisions. Every AI output is reviewable; PHI is minimized/pseudonymized before leaving the system; every AI request is logged (provider, prompt template version, latency); patients are told when they're talking to AI and can opt for a human; rule-based fallback guarantees scheduling still works if AI is down.

---

## 9. Future / Roadmap Features (Phase 3+) — Original PRD

| ID | Feature |
|---|---|
| FUT-01 | Native mobile apps (iOS/Android) |
| FUT-02 | Voice-based booking (IVR/Alexa/Google) |
| FUT-03 | Wearable & IoT reminders |
| FUT-04 | Self-service kiosk mode |
| FUT-05 | White-label / multi-tenant SaaS |
| FUT-06 | AI voice agent (calls) |
| FUT-07 | Predictive capacity planning |
| FUT-08 | Care-gap & chronic-care recall |
| FUT-09 | AI clinical note assist |
| FUT-10 | Personalized health journeys |
| FUT-11 | Deep EHR/EMR write-back (FHIR, Epic/Cerner) |
| FUT-12 | Full RCM & claims |
| FUT-13 | Pharmacy & lab scheduling |
| FUT-14 | Referral management |
| FUT-15 | Marketplace / provider discovery (Zocdoc-style) |
| FUT-16 | Group & class scheduling |
| FUT-17 | Advanced BI & benchmarking |

> Roadmap items are indicative, not committed. FUT-11 (EHR write-back) and FUT-12 (full RCM) carry the highest integration/compliance cost — validate against real customer demand first.

---

## 10. The Patient Journey (Tempo framing — 10 stages)

1. **Discover & reach out** — website, SMS, Google Reserve, QR code, social media, phone tree, AI voice assistant, walk-in, missed-call text-back — all land on the same calendar.
2. **Pick a provider and exact time** — only real, currently-open, correctly-matched times are ever shown.
3. **Booking locks in** — DB-level guarantee no one else can get that slot; deposit taken here if the visit type requires one.
4. **Days before the visit** — automatic reminders, two-way texting, AI Q&A, digital intake forms, a "what's your one question" prompt, specific symptom questions.
5. **Plans change or no-show** — cancellation → immediate best-match waitlist offer; no-show → automatic rebooking outreach within minutes.
6. **Arrival day** — front desk sees the same live calendar; walk-ins go into the same protected system; live queue-position page for busy clinics.
7. **The visit itself** — deliberately outside Tempo's scope; entirely the provider's and the existing clinical system's.
8. **After the visit** — review request ~2h after a genuinely completed visit (never after cancel/no-show); referral letters/consent pre-drafted for staff approval; plain-language visit recap (staff-reviewed).
9. **Coming back** — recall reminders, referral follow-through tracking, care-plan gap outreach, follow-through nudges.
10. **What the owner sees** — one dashboard: fill rate, channel mix, no-show trend, which slots/patients are worth the most, benchmark vs. similar clinics.

---

## 11. Funnel / Drop-off Analysis (Tempo framing)

| Stage | Where clinics normally lose the patient | What recovers it |
|---|---|---|
| 1–2 Discover/pick time | Phone-only booking during office hours → patients give up and book elsewhere | 9 simultaneous channels incl. after-hours AI voice, SMS, missed-call text-back |
| 3 Booking locks in | No immediate confirmation → patient double-books elsewhere or loses confidence | Instant WhatsApp/SMS confirmation, backed by the DB-level guarantee |
| 4 Days before visit | Biggest revenue leak: patient forgets/changes plans, never communicates it; also wastes consult time forgetting their question | Multi-touch reminders + easy two-way texting + pre-visit question prompt |
| 5 Plans change/no-show | Cancellation = pure lost revenue; no-show = discovered too late to fill | Immediate waitlist offer on cancel; direct rebooking offer within minutes of a no-show |
| 6 Arrival day | Unexplained wait sours an otherwise-successful booking | Live queue-position visibility |
| 7 The visit itself | Outside Tempo's control by design | Left to provider/clinical systems |
| 8 After the visit | Reviews asked too early (ignored) or never asked (missed growth); patient forgets what was discussed | Correctly-timed review request; plain-language recap; specific follow-through nudge |
| 9 Coming back | Follow-up never booked, referral goes cold, treatment plan silently lapses | Recall campaigns, referral tracking, care-gap outreach — all automatic |
| 10 Owner visibility | Without visibility, improvement is guesswork | Dashboard: fill rate, channel mix, no-show trend, slot/patient value, benchmarking |

---

## 12. Tempo's 8 Feature Areas (62 features) — condensed

*(Full plain-language why/rule/example for each feature lives in `PRD/Tempo — What It Does For Your Clinic - Final changes.html`; condensed list + build status below, cross-referenced with §21.)*

**Area 1 — Getting the booking:** website booking, SMS booking, Reserve with Google, QR code booking, social-media booking, automated phone tree, AI voice assistant booking, walk-in booking, missed-call text-back, booking on someone else's behalf, phone+OTP verification.

**Area 2 — Making sure the calendar never breaks:** multi-provider/one system, multi-location/one system, the double-booking guarantee (DB-level, 8-minute slot hold), durations matching reality, syncing with existing clinic software (EMR), smart time/need matching, predicting no-shows, automated recovery after a missed appointment, auto-filling cancelled slots from waitlist, managing walk-in queues.

**Area 3 — Staying in touch before the visit:** reminders, two-way texting, contact-preference control (WhatsApp/SMS/opt-out of marketing only), AI Q&A assistant, digital forms auto-sent, pre-visit "one question" prompt, structured symptom questions (not one blank box), pre-filled referral letters/consent forms (staff-approved), plain-language post-visit recap, A/B-testable prompts with per-clinic analytics.

**Area 4 — The moment they arrive:** deposits for certain visit types, configurable forfeiture policy, slot auto-release if payment abandoned.

**Area 5 — Keeping patients coming back:** recall campaigns, referral tracking through completion, care-gap outreach, follow-through nudges, correctly-timed review requests.

**Area 6 — Keeping everything safe and compliant:** HIPAA/DPDP compliance gate, audit logging, SSO, ABDM integration, role-based access (front-desk vs. owner), phone+OTP login, white-label branding, data export/portability, WCAG accessibility.

**Area 7 — Understanding how well it's working:** owner dashboard (fill rate, channel mix, no-show trend).

**Area 8 — Revenue & patient intelligence:** slot-value scoring, patient-value scoring, controlled overbooking, provider utilization by time block, benchmarking vs. similar clinics, turning patients into a referral channel, personalized rescheduling suggestions, A/B testing for booking completion, revenue-leak pattern flagging, explainable/overridable scores, schedule-template optimization suggestions.

**Competitive positioning (Tempo doc):** basic scheduling tools handle calendar/reminders cheaply but don't recover cancellations/no-shows, don't score value, and are usually single-channel. Marketplaces (Practo, Zocdoc) excel at new-patient discovery but aren't built to run an existing calendar efficiently, limit branding, and don't recover cancellations/track referrals. **Tempo sits on top of existing clinic software** — captures bookings from every channel (including a marketplace listing), guarantees none conflict, then actively recovers what a basic scheduler or marketplace listing would leave on the table.

---

## 13. WhatsApp Messaging Plan (Tempo doc)

- **Primary channel, SMS backup** — WhatsApp first (faster read, free to patient); auto-falls back to SMS if not on WhatsApp or delivery fails.
- **Templates need Meta pre-approval** — every message sent outside an active conversation must use an approved template; submit early since approval isn't instant (typically a few days, longer if rejected).
- **24-hour reply window** — once a patient replies, free-form replies are allowed for 24h; after that, only a template can restart the conversation (WhatsApp platform rule).
- **Opt-in required** — patients opt in at first booking (platform requirement); non-opted-in patients get SMS instead.
- **Cost structure** — Meta charges per conversation; utility messages (reminders/confirmations) are cheapest; marketing-style recall messages billed differently.

**Template list to submit for approval:** booking confirmation · 48h reminder · 2h reminder · pre-visit question prompt · intake form delivery · intake form reminder · reschedule confirmation · cancellation confirmation · waitlist slot offer · no-show follow-up (same day / few days later / ~1 week later, 3 distinct attempts) · recall reminder · pre-booked recall confirmation · post-visit recap · review request · referral booking confirmation · campaign/health-camp broadcast (marketing category, opt-in only) · one-time verification code (auth category, code-only per Meta's restriction).

---

## 14. Data Model (Key Entities)

| Entity | Key Fields | Relationships |
|---|---|---|
| User | id, name, email, phone, password, role_id, locale, mfa_secret | belongsTo Role; hasMany Appointments |
| Clinic/Location | id, name, address, timezone, phone, settings | hasMany Providers, Resources, Appointments |
| Provider | id, user_id, clinic_id, specialty, credentials, bio | belongsTo Clinic; hasMany Availabilities, Appointments |
| Service/AppointmentType | id, clinic_id, name, duration, buffer, price, specialty | hasMany Appointments |
| Availability | id, provider_id, day_of_week, start, end, recurring, exceptions | belongsTo Provider |
| Resource | id, clinic_id, name, type, capacity | belongsToMany Appointments |
| Appointment | id, patient_id, provider_id, clinic_id, service_id, start, end, status, channel, no_show_score | belongsTo Patient/Provider/Clinic/Service; hasOne Payment, IntakeForm |
| WaitlistEntry | id, patient_id, service_id, provider_pref, time_pref, priority, status | belongsTo Patient, Service |
| IntakeForm | id, appointment_id, schema, responses, ai_summary, signed_at | belongsTo Appointment |
| Reminder/Notification | id, appointment_id, channel, template, scheduled_at, sent_at, status | belongsTo Appointment |
| Payment | id, appointment_id, amount, type, provider_ref, status | belongsTo Appointment |
| AiRequestLog | id, user_id, feature, provider, prompt_version, tokens, latency_ms, status | belongsTo User |
| AuditLog | id, user_id, action, entity, entity_id, before, after, ip, created_at | belongsTo User |

**Integrity rules:** slot uniqueness enforced at DB level (provider_id + time range) with locking to prevent double-booking; soft deletes + audit trail on PHI entities; PHI columns encrypted at rest (target — not yet built, see §21).

---

## 15. Non-Functional Requirements

| Category | Requirement |
|---|---|
| Performance | Calendar/slot search < 1s; AI responses < 4s (async for heavy tasks) |
| Availability | 99.9% uptime target; core scheduling resilient to AI/3rd-party outages |
| Scalability | Horizontal scaling of web & queue workers; multi-clinic tenants |
| Reliability | No double-booking guarantee; idempotent reminders; retried/queued external calls |
| Usability | WCAG 2.1 AA; ≤3 steps to book; mobile-first |
| Maintainability | PSR-12, automated tests (PHPUnit/Pest), CI/CD, OpenAPI docs |
| Observability | Centralized logging, error tracking (Sentry), job/queue alerting |
| Localization | i18n-ready; date/time/number & multi-language |

---

## 16. Security, Privacy & Compliance

- **Compliance posture:** HIPAA-aware architecture (US), extensible to GDPR (EU) / DPDP (India). BAAs required with any PHI-touching vendor.
- **Encryption:** TLS 1.2+ in transit; AES-256 at rest for PHI columns and backups (target).
- **Access control:** RBAC + least privilege; mandatory MFA for staff/admin (target).
- **Auditability:** immutable audit logs for all PHI access/changes.
- **Consent:** explicit recorded consent for comms, data processing, AI assistance.
- **AI data handling:** PHI minimized/pseudonymized before any external AI call; zero-retention/enterprise API tier preferred; provider DPAs in place.
- **Data lifecycle:** configurable retention, export, deletion (right-to-be-forgotten where applicable).
- **Resilience:** encrypted backups, DR plan, rate limiting & abuse protection.

> "HIPAA-aware" = architectural alignment with HIPAA safeguards, not formal certification. Legal review + signed BAAs required before handling real PHI in production.

---

## 17. Success Metrics & KPIs

**Original PRD targets:**

| Metric | Target |
|---|---|
| No-show rate reduction | −30–40% |
| Bookings via self-service | ≥60% |
| Patient booking satisfaction | >4.5/5 |
| Provider/room utilization | +15% |
| Front-desk time saved | 10+ hrs/wk/clinic |
| Reminder confirm/response rate | >50% |
| Cancelled slots auto-refilled | >25% |
| Median slot-search response | <1s |
| Platform uptime | >99.9% |

**Tempo doc's accountability metrics (tracked post-launch, not promised upfront):** fill rate, no-show rate, cancellation recovery rate, recall compliance rate, revenue per slot, patient retention rate, booking conversion rate. Real numeric targets are set from the clinic's own baseline (weeks 1–6 post-go-live), not a generic industry guess.

**Review cadence:** baseline report (end of week 4) → 30-day check → 90-day review → 6-month review → annual review, each with a defined attendee list and purpose.

---

## 18. Pricing Structure (Tempo doc — structure only, no committed figures)

- **Monthly subscription** — per clinic or per provider.
- **Messaging costs passed through at actual Meta/carrier rates**, not marked up.
- **Optional deeper-intelligence tier** — slot/patient scoring, benchmarking — priced as an add-on.
- **Possible future outcome-based pricing** — a share of recovered revenue — not available at initial launch.
- **Cost components:** one-time setup (config, branding, template submission, training) · monthly platform fee · messaging costs (usage-based) · additional providers beyond tier allowance · AI voice assistant (billed per minute) · optional EMR/billing integration (one-time + maintenance).
- **Tier shape:** Entry / Mid / Top — all include core booking channels + double-booking guarantee + waitlist/no-show recovery; revenue intelligence, AI voice, broadcast volume, EMR integration, and support SLA (4h → 2h → 30min) scale up by tier.

---

## 19. Go-Live & Support Plan (Tempo doc)

- **Staff training** on the live system before go-live (real scenarios, not slides); defined cutover date.
- **Existing bookings & patient contacts migrated in**, not left in a separate old system.
- **Outage fallback:** front-desk can always book/manage manually; reconciled back into Tempo once service resumes.
- **Support:** in-app chat <4h response (8am–8pm, 6 days/week); email <1 business day for non-urgent; 24/7 emergency line for outages, 30-min response commitment.
- **Named risks:** WhatsApp template approval delay (SMS fallback mitigates) · messy existing appointment data (dedicated clean-up pass before go-live) · double-booking guarantee under real concurrent load (simulated stress-testing before real patients touch it).
- **Escalation playbook:** no fill/no-show improvement after 60 days → configuration review · staff reverting to manual process → refresher training + workflow fix · WhatsApp delivery issues → delivery-log review · patient complaints about booking flow → flow review, usually fixed in config within 24h · targets still missed at 90 days → formal root-cause review + revised plan.

---

## 20. Release Plan & Milestones (Original PRD)

| Phase | Theme | Highlights | Indicative Timing |
|---|---|---|---|
| Phase 1 (Core) | MVP | Self-booking, calendars, multi-location, reminders, intake, RBAC, dashboards, payments | Months 1–4 |
| Phase 2 (AI) | Intelligence layer | No-show prediction, smart waitlist, NL booking, AI assistant, intake summary, smart reminders | Months 4–7 |
| Phase 3 (Scale) | Roadmap | Native apps, voice booking, deep EHR write-back, advanced AI ops, marketplace | Months 7–12+ |

---

## 21. Assumptions, Constraints & Risks (Original PRD)

**Assumptions:** clinics provide provider schedules/services/policies at onboarding · patients have email/SMS and consent to receive them · AI provider offers a compliant API tier with BAA/DPA · telehealth/payments via established third parties.

**Constraints:** built on Laravel/MySQL, hosted in a HIPAA-eligible environment · AI usage bounded by API cost/rate limits/latency · no real PHI to external AI without minimization + BAA.

| Risk | Mitigation |
|---|---|
| AI hallucination / wrong booking | Human/patient confirmation step, validation, rule-based fallback |
| PHI exposure via AI | Minimize/pseudonymize, BAA, zero-retention tier, audit |
| Compliance gaps | Legal review, security audit before PHI go-live |
| AI cost overrun | Caching, batching, provider switch, usage caps |
| Low patient adoption | Simple UX, reminders, staff-assisted onboarding |
| Integration delays (EHR) | Phase EHR to roadmap; ship standalone value first |

---

## 22. Current Implementation Status

### 22.1 Summary
- **Core scheduling system:** largely in place (~60–70% of original PRD §5.1).
- **AI layer (original PRD §5.2):** not built at all — only a rule-based no-show scorer exists, which the PRD explicitly wanted as the *fallback*, not the primary mechanism.
- **Data model:** ~100% present as migrations/models; many tables exist but aren't yet driven by UI/logic.
- **Against the *Tempo* PRD (narrower, later scope):** of ~58 audited items, the large majority are now implemented after Phases 1–6 (retention loop, dashboard completeness, deposits/payments, compliance basics, alternate booking channels, revenue & patient intelligence). What's left needs either a partner/vendor relationship (Google Business Profile, Meta, a telephony/IVR vendor, live Stripe/Gupshup credentials) or is deliberately out of scope (SSO, ABDM live registration).

### 22.2 Technology stack — as built vs. original PRD

| Item | Status | Notes |
|---|:--:|---|
| Laravel 11 (PHP 8.2+) | ✅ | |
| MySQL 8/MariaDB | ✅ | |
| Redis + Laravel Queue | 🟡 | DB queue driver used; Redis not wired in |
| Blade + Tailwind/Livewire/Vue | 🟡 | Bootstrap 5 (NexLink) used instead |
| AI Layer (Gemini/OpenAI) | ❌ | No AI integration |
| Auth (Sanctum/Breeze + 2FA) | 🟡 | Custom session auth; no API tokens, no 2FA (`mfa_secret` unused) |
| Messaging | ✅ | Gupshup WhatsApp/SMS wired, needs live credentials to send |
| Payments | ✅ | Razorpay (not Stripe as originally spec'd); manual driver fully working, Razorpay backend ready but no Checkout.js UI yet |
| Telehealth | 🟡 | Flag + link field only, no link generation |
| Scheduled jobs | ✅ | Recall dispatch, review requests, payment auto-release all cron-driven |
| HIPAA-eligible hosting | ❌ | Local XAMPP — deployment concern |

### 22.3 Roles & permissions

| Item | Status | Notes |
|---|:--:|---|
| RBAC, 6 roles | ✅ | |
| Permission-gated routes/UI | ✅ | |
| Audit logging | 🟡 | Extended to payment/deposit actions; still not full PHI-access coverage |
| Patient sees only own data | ✅ | |
| Clinic Admin scoped to own clinic | ✅ | Real multi-tenant scoping (`ScopedToClinic`) — this closed a gap the original audit flagged |
| Front-desk vs. owner data-export split | ✅ | `export patient data` now a dedicated permission (owner/manager only) |

### 22.4 Booking channels (Tempo PRD Area 1)

| Feature | Status |
|---|:--:|
| Website booking | ✅ |
| SMS booking | ✅ (Gupshup-ready, needs live credentials) |
| Reserve with Google | ❌ (needs Google Business Profile partnership) |
| QR code booking | ✅ (real PNG generation, scan/booking attribution tracked) |
| Social-media booking | ❌ (needs Meta Business partnership) |
| Automated phone tree | ❌ (needs telephony/IVR vendor) |
| AI voice assistant booking | ❌ (existing AI assistant is text-only) |
| Walk-in booking | ✅ |
| Missed-call text-back | ✅ (rate-limited 1×/24h/number) |
| Booking on someone else's behalf | 🟡 (staff can; no patient-facing self-service yet) |
| Phone + OTP login | ✅ |

### 22.5 Calendar integrity (Tempo PRD Area 2)

| Feature | Status | Notes |
|---|:--:|---|
| Multi-provider, multi-location | ✅ | |
| Double-booking guarantee | ✅ | **Real bug found and fixed**: the unique index included nullable `deleted_at`; NULL≠NULL semantics meant the DB never actually rejected duplicate active bookings — only the app-level lock was protecting it. A generated column now makes the DB-level guarantee genuinely airtight (verified with direct SQL concurrency stress tests) |
| EMR sync | ❌ | No target EMR specified |
| Smart time/need matching | 🟡 | Keyword/specialty-based, not a full optimization engine |
| No-show prediction | ✅ | Rule-based (`NoShowPredictor`), daily re-score — not true AI/ML |
| Automated no-show recovery | 🟡 | Notifies, but no one-click automated rebooking flow |
| Auto-fill cancelled slots from waitlist | ✅ | Ranked by computed patient-value score |
| Walk-in queue management | ✅ | |

### 22.6 Pre-visit engagement (Tempo PRD Area 3 / original PRD §7.2–7.3)

| Feature | Status | Notes |
|---|:--:|---|
| Reminders, two-way texting, AI assistant | ✅ | |
| Digital forms auto-sent on confirmed booking | 🟡 | Form flow exists; no instant push the moment a booking confirms |
| Pre-visit "one question" prompt | ❌ | Not built as a distinct feature |
| Structured symptom questions | 🟡 | Static 5-field schema, not adaptive |
| Referral letters / consent forms, pre-filled | ❌ | Not built |
| Plain-language post-visit recap | ❌ | Not built |
| A/B-testable prompts | 🟡 | Generic `ExperimentService` exists, wired into booking flow only, not yet intake wording |
| Recall / recare | ✅ | `RetentionService`, per-service `recall_window_days` |
| Follow-up automation | ✅ | Reuses appointment `notes` field |
| Broadcast messaging | ❌ | |
| Reviews & feedback | ✅ | Auto-fires 2h after a genuinely completed visit |

### 22.7 Arrival & payments (Tempo PRD Area 4 / original PRD §7.5)

| Feature | Status | Notes |
|---|:--:|---|
| Deposits at booking | ✅ | `PaymentService::createDeposit()`, Razorpay-ready, manual driver fully functional |
| Configurable forfeiture policy | ✅ | Per-service `deposit_forfeit_hours` |
| Slot auto-release if payment abandoned | ✅ | `payments:release-abandoned`, 10-min hold, offers freed slot to waitlist |
| Invoicing & receipts | ❌ | |
| RCM integration | ❌ | Roadmap |

### 22.8 Retention (Tempo PRD Area 5)

| Feature | Status |
|---|:--:|
| Recall campaigns | ✅ |
| Referral tracking through completion | ✅ (`Referral` model, share-link, conversion analytics) |
| Care-gap outreach | ✅ |
| Follow-through nudges | ✅ |
| Review requests, timed right | ✅ |

### 22.9 Compliance & access (Tempo PRD Area 6 / original PRD §7.4, §12)

| Feature | Status | Notes |
|---|:--:|---|
| HIPAA-aware security | 🟡 | Soft deletes + audit ✅; PHI **not encrypted at rest**; no formal certification |
| HIPAA/DPDP compliance gate | 🟡 (by design) | `compliance_agreements_signed_at` blocks clinic activation until checked — actual legal agreements remain the clinic's own process |
| Role-based access | ✅ | |
| Audit logs | 🟡 | Partial coverage |
| EHR/EMR integration | ❌ | Roadmap |
| Insurance eligibility | ❌ | Not built |
| Telehealth/video booking | 🟡 | Flag + link field only |
| Provider credentialing rules | ❌ | No qualified-provider matching |
| SSO | ❌ | Out of scope (no identity provider configured) |
| ABDM integration | 🟡 (by design) | `abdm_health_id` field only — app doesn't register clinics with the government framework |
| White-label branding | ❌ | Not built |
| Data export/portability | 🟡 | CSV + iCal exist; no single "everything about this patient" bundle |
| WCAG accessibility | 🟡 | Partial fixes (skip-to-content, aria labels), not a full audit |

### 22.10 Reporting & intelligence (Tempo PRD Area 7–8 / original PRD §7.6)

| Feature | Status | Notes |
|---|:--:|---|
| Dashboards | ✅ | Stats + 14-day trend + status breakdown + high-risk list |
| No-show & cancellation reports | ✅ | |
| Utilization reports | ✅ | Per-provider, now also day×hour heatmap |
| Wait-time analytics | ❌ | |
| Custom reporting & export | 🟡 | CSV export exists; no PDF |
| Owner dashboard (fill rate, channel mix, no-show trend) | ✅ | Real data, no synthetic numbers |
| Slot-value scoring | ✅ | `SlotScoringService`, explainable |
| Patient-value scoring | ✅ | `PatientScoringService`, drives waitlist priority |
| Controlled overbooking | ✅ | Opt-in, requires ≥25% historical no-show rate for that day/hour, extensively stress-tested |
| Clinic benchmarking | ✅ | Honestly reports "not enough other clinics yet" below minimum sample rather than fabricating a comparison |
| Personalized reschedule suggestions | ✅ | `RescheduleSuggestionService` |
| A/B testing for booking completion | ✅ | One live experiment (booking-page intro copy) |
| Revenue-leak pattern flagging | ✅ | `RevenueLeakService` — plain-language flags, no automatic action |
| Explainable/overridable scores | ✅ | Every score carries a plain-language reason; staff can always override |
| Schedule-template optimization suggestions | ✅ | `ScheduleOptimizationService` — flags gaps, never changes the template itself |

### 22.11 AI Features (original PRD §5.2) — entirely missing

No-show prediction (real AI/ML) · smart waitlist AI matching (beyond rule-based) · slot recommendation AI · load balancing AI · overbooking optimization AI · demand-based scheduling · natural-language booking · AI chatbot assistant · intake summarization · smart symptom triage routing · smart reminder generation · real-time translation · sentiment/feedback analysis · predictive analytics · NL report queries · smart follow-up suggestions. The `ai_request_logs` table exists but is never written to.

### 22.12 What's still genuinely missing (from both audits)

**Needs a partner/vendor relationship this deployment doesn't have:** Reserve with Google (Google Business Profile) · social-media booking (Meta) · full automated phone tree + AI voice assistant (telephony/speech vendor) · white-label theming (not blocked technically, just not built).

**Deliberately out of scope:** SSO (no identity provider given) · ABDM live registration (clinic's own legal process) · EMR sync (no target EMR specified) · referral letters / plain-language recap / pre-visit "one question" prompt (need new patient-facing content flows) · full EHR/RCM/e-prescribing/insurance claims (original PRD's explicit out-of-scope list).

**Needs your action to go fully live (currently in safe log/manual mode):**
1. WhatsApp (Gupshup) — Settings → Integrations, per-clinic credentials, then paste the generated webhook URL into the Gupshup dashboard.
2. SMS (Gupshup) — same page; note the SMS API endpoint was written from documentation, not live-tested — verify with a real test send.
3. Payments (Razorpay) — Key ID/Secret/webhook secret in Settings → Integrations; backend (orders, webhook verification, refunds) is ready but the Checkout.js popup UI isn't built yet.
4. Legal/compliance — actual HIPAA/DPDP agreement signing and ABDM clinic registration are the clinic's own legal steps; the system only tracks whether they've been done.

**Priority recommendations (from the codebase-vs-original-PRD gap analysis, still largely open):**
1. Provider availability + holidays UI (currently seed-only, not editable).
2. Multi-tenant clinic scoping — now largely resolved (§22.3), verify remaining edge cases.
3. AI layer — the original PRD's headline differentiator is still entirely unbuilt; the "AI" in the product today is rule-based scoring only.
4. Security hardening — PHI encryption at rest, 2FA/MFA, broader audit coverage.
5. REST API + Sanctum tokens for third-party integrations.
6. PDF export for reports.

---

## 23. Route Reference (summary)

Base URL for local dev: `php artisan serve` → `http://127.0.0.1:8000`

| Area | Route(s) | Guard |
|---|---|---|
| Auth | `/login`, `/register`, `/logout` | guest / auth |
| Dashboard | `/dashboard` (branches patient vs. staff view) | auth |
| Profile | `/profile`, `/profile/password` | auth |
| Patient booking | `/book` (GET/POST), `/book/{appointment}/cancel` | auth, own-record only |
| Slot lookup (AJAX) | `/api/slots?provider_id=&service_id=&date=` | auth |
| Calendar | `/calendar`, `/calendar/events` | view appointments |
| Appointments | `/appointments`, `/appointments/{id}`, `/appointments-create/new`, `/appointments/{id}/edit`, `/appointments/{id}/status` | view / manage appointments |
| Waitlist | `/waitlist` | manage waitlist |
| Patients | `/patients` CRUD | manage patients |
| Providers | `/providers` CRUD | manage providers |
| Services | `/services` CRUD | manage services |
| Reports | `/reports` | view reports |
| Billing | `/payments` | view billing |
| Users & Roles | `/users` CRUD | manage users |
| Clinics | `/clinics` CRUD | manage clinics |
| Audit Logs | `/audit` | view audit logs |
| Walk-in queue | Front-desk/Admin sidebar | manage appointments (implied) |
| QR codes | Admin sidebar `/qr/{token}` public scan link | manage services (implied) |
| Referrals | Sidebar → Referrals | staff |
| Webhooks | `/webhooks/missed-call`, `/webhooks/sms-inbound`, `/webhooks/gupshup`, `/webhooks/razorpay` | signed token, not session auth |
| Phone login | `/login/phone` | guest |

Full step-by-step behavior for every route is in `docs/MANUAL-TEST-CASES-AND-ROUTES.md` §Part A.

---

## 24. Testing Reference

### 24.1 Demo logins (password for all: `password`, unless noted)

| Role | Email |
|---|---|
| System Admin | `admin@scheduler.test` |
| Clinic Admin/Owner | `clinicadmin@scheduler.test` / `archiaswal1234567890@gmail.com` |
| Front Desk | `frontdesk@scheduler.test` |
| Billing | `billing@scheduler.test` |
| Provider | `sarah.chen@scheduler.test` |
| Patient | `patient1@scheduler.test` / `anabhaiya123@gmail.com` |

### 24.2 Quick regression smoke test (5 minutes)
1. Login as admin → dashboard loads with chart.
2. Calendar shows events; provider filter works.
3. Create an appointment via the slot picker.
4. Try to double-book the same slot → blocked.
5. Change its status to Confirmed.
6. Login as patient1 → book → see under Upcoming → cancel it.
7. Login as patient1 → open `/appointments` in the URL bar → 403.
8. Open Audit Logs as admin → see the recent login + appointment actions.
9. `php artisan test` → expect `44 passed`.

### 24.3 Manual test coverage (full detail in `MANUAL-TEST-CASES-AND-ROUTES.md`)
16 test areas, ~90 individual cases: Authentication & access control (10) · Dashboard (5) · Calendar (6) · Appointments incl. double-booking block (14) · Patient self-booking (6) · Patients CRUD (7) · Providers (5) · Services (6) · Waitlist (3) · Reports (4) · Billing (2) · Users & Roles (5) · Clinics (3) · Audit Logs (3) · Profile (3) · Layout/navigation (5).

### 24.4 New-feature testing (full detail in `NEW-FEATURES-TESTING-GUIDE.md`)
Covers hands-on steps (including `php artisan tinker` snippets for testing SMS/webhooks without live credentials) for: recall campaigns, care-gap outreach, referral tracking, review requests, fill-rate/channel-mix dashboard widgets, deposit collection + forfeiture + auto-release, controlled overbooking, compliance gate, phone+OTP login, patient-data export permission split, SMS booking, missed-call text-back, walk-in queue, QR code booking, waitlist auto-priority scoring, referral analytics, revenue-leak flags, clinic benchmarking, provider utilization heatmap, A/B testing, personalized reschedule suggestions, schedule optimization suggestions.

Useful artisan commands surfaced by the guide:
```
php artisan recall:dispatch              # send overdue recall reminders
php artisan reviews:request-dispatch     # send post-visit review requests
php artisan payments:release-abandoned   # release abandoned deposit holds
php artisan test                          # full suite (44 passing at last audit)
```

---

## 25. Ideas to Make It More Useful

Grouped by theme, weighted toward what's cheap to add given the *existing* architecture (scoring services, `ExperimentService`, `ScopedToClinic`, the AI Service Gateway pattern already designed-for) rather than net-new subsystems.

### Close the product's own headline gap
1. **Actually wire the AI layer.** The single biggest gap against the original PRD: `ai_request_logs` exists and is never written to, and the "AI" in the product today is rule-based scoring. Even a narrow first slice — natural-language booking parsing + intake summarization, behind the config-flag gateway the PRD already specifies — would close the gap that most differentiates this from a plain scheduler, and it's the one thing prospective clinic buyers are likely to ask about directly given the product name and pitch.
2. **PHI encryption at rest.** Flagged in both audits as still open. Laravel's built-in encrypted casts on PHI columns (name, DOB, phone, notes) is a small, mechanical change with an outsized compliance payoff before this ever touches a real patient.
3. **2FA/MFA for staff/admin roles.** `mfa_secret` column already exists unused — this is scaffolding waiting to be turned on, and it's a "must" in the original PRD's own security section.

### Extend what's already scored
4. **Turn patient-value and slot-value scores into action, not just display.** They're currently explainable and staff-overridable (good) but passive. A next step: auto-suggest which high-value patients to proactively offer premium/preferred slots to when a provider's schedule opens up, or auto-flag a booking as "worth a personal call" above a threshold.
5. **Extend `ExperimentService` beyond booking-page copy.** It's already generic and wired in one place — the natural next targets are the intake/symptom-prompt wording (explicitly listed as "not yet extended" in the audit) and reminder message tone, both of which are cheap to A/B once the harness exists.
6. **Revenue-leak flags → suggested fix, not just a flag.** Right now it correctly stays passive ("Tuesday 4pm cancels 60% of the time"). A logical next step within the same "never auto-act" philosophy: a one-click "open this pattern up for controlled overbooking" button that pre-fills the existing overbooking config for that flagged slot.

### Retention loop gaps the Tempo PRD itself calls out as unbuilt
7. **Pre-visit "what's your one question" prompt** and **plain-language post-visit recap** are both explicitly designed in the Tempo PRD's patient journey but not built. These are described as high-leverage for actual patient outcomes (not just booking metrics) and reuse existing messaging infrastructure — likely the best next patient-facing add given they're already spec'd in detail.
8. **One-click automated rebooking after a no-show** — currently notifies but doesn't offer a direct one-tap rebook. Given the waitlist auto-offer pattern already exists for cancellations, the same mechanism (best-match slot + short confirm window) could be reused for no-show recovery with modest new work.

### Data & integration surface
9. **REST API + Sanctum tokens.** Currently zero API surface for third parties — this blocks any future EHR/PMS integration, Zapier-style automation, or a future native mobile app, and is explicitly called out as missing in the gap analysis.
10. **PDF export for reports**, alongside the existing CSV — clinic owners sharing numbers with a partner or accountant will want this over raw CSV.
11. **A single "everything about this patient" data-export bundle** (CSV + iCal exist separately) — closes a DPDP/right-to-portability gap and is a common enterprise-sales ask.

### Business-model / stickiness ideas beyond either PRD
12. **Family/dependent account linking.** The Tempo PRD already supports "booking on someone else's behalf" at the appointment level; a logical extension is letting one login manage several linked patient profiles (parent + kids, adult child + elderly parent) instead of re-verifying every time.
13. **Provider-facing shift-swap / cross-cover marketplace.** Multi-provider clinics regularly deal with sick days and last-minute unavailability; a lightweight "offer my slots to another qualified provider" flow would reduce manual admin the PRD doesn't currently address at all.
14. **Patient loyalty/rewards tied to the referral system that already exists.** Referral tracking and conversion analytics are built — pairing it with even a simple "your 3rd successful referral unlocks X" mechanic would likely lift the referral-conversion numbers already being measured.
15. **A lightweight public status page** (uptime, incident history) for clinic owners — the Tempo PRD promises a 24/7 emergency line and monitoring but nothing patient/owner-facing shows system health, which is an easy trust-builder for a product whose entire pitch rests on reliability.

Happy to turn any of these into a scoped implementation plan — just say which one(s).

---
*Consolidated 2026-07-16 from the sources listed at the top of this document. Where the two PRDs disagree (payment provider, frontend framework, auth stack), this document notes both the original spec and what was actually built.*
