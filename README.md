# Smart Appointment Scheduler (Healthcare)

A Laravel 11 healthcare appointment-scheduling platform built to the project PRD, using
the **NexLink** Bootstrap 5 admin template for the staff/admin UI.

## Stack
- **Laravel 11** (PHP 8.2+)
- **MySQL / MariaDB** (`smart_appointment_scheduler` database)
- **spatie/laravel-permission** for RBAC
- **NexLink** template assets (Bootstrap 5, FullCalendar, ApexCharts, Flatpickr, DataTables)

## What's implemented (core MVP)
- **Authentication** — login, registration (patients), logout, inactive-account guard, login auditing.
- **RBAC** — 6 roles (patient, front_desk, provider, billing, clinic_admin, system_admin) with
  granular permissions; routes & sidebar gated by `can:`/`@can`.
- **Data model** — clinics, providers, services (+ provider_service), availability (+ exceptions),
  resources, appointments, waitlist, intake forms, reminders, payments, AI request logs, audit logs.
- **Scheduling** — `SchedulingService` generates bookable slots from provider working hours and
  prevents **double-booking** via a row-locking transaction + overlap check (and a DB unique index).
- **No-show prediction** — rule-based `NoShowPredictor` (0–100) used as the dependable fallback
  described in the PRD; risk shown on the dashboard and appointment pages.
- **Calendar** — FullCalendar week/month/day/list views fed by a JSON events endpoint, provider filter.
- **Appointments** — full CRUD, AJAX slot picker, status workflow (booked → confirmed → checked-in →
  completed / cancelled / no-show), audit logging.
- **Management** — patients, providers (with services & working hours), services, clinics, users & roles.
- **Patient self-service** — focused patient dashboard + 24/7 booking flow + self-cancel.
- **Insights** — dashboard stats & 14-day trend chart, reports (no-show by provider, channel mix,
  utilization), billing summary.
- **Audit logs** viewer.

## Setup
```bash
# from this folder (scheduler/)
composer install
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve   # http://127.0.0.1:8000
```
Database is configured in `.env` for XAMPP MariaDB (`root`, no password, db
`smart_appointment_scheduler`).

## Demo logins (password: `password`)
| Role | Email |
|------|-------|
| System Admin | `admin@scheduler.test` |
| Clinic Admin | `clinicadmin@scheduler.test` |
| Front Desk | `frontdesk@scheduler.test` |
| Billing | `billing@scheduler.test` |
| Provider | `sarah.chen@scheduler.test` |
| Patient | `patient1@scheduler.test` … `patient15@scheduler.test` |

## Roadmap (from PRD, not yet built)
AI layer (Gemini/OpenAI NLP booking, intake summarization, smart waitlist auto-fill),
multi-channel reminders (WhatsApp/Twilio/SMTP) via queues + scheduler, Stripe payments,
telehealth links, intake form builder & e-signatures, EHR/FHIR integration, 2FA,
PHI encryption at rest, multi-language, native mobile apps.

> Note: the NexLink template is a licensed commercial product — ensure a valid license
> before production use.
