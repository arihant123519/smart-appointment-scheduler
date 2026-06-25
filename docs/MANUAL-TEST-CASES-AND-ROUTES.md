# Smart Appointment Scheduler — Route Guide & Manual Test Cases

This document has two parts:

- **Part A — Route Reference:** every route, who can reach it, and how it works step by step.
- **Part B — Manual Test Cases:** hand-executable QA cases per page/module (steps + expected result).

---

## Before you start

Run the app:

```
cd "c:\xampp\htdocs\smart appointment scheduler\scheduler"
php artisan serve     →  http://127.0.0.1:8000
```

**Demo logins** (password for all: `password`)

| Role | Email | What they can do |
|------|-------|------------------|
| System Admin | `admin@scheduler.test` | Everything |
| Clinic Admin | `clinicadmin@scheduler.test` | Everything except system-wide config |
| Front Desk | `frontdesk@scheduler.test` | Calendar, appointments, patients, waitlist |
| Billing | `billing@scheduler.test` | Dashboard, billing, reports, view appointments |
| Provider | `sarah.chen@scheduler.test` | Dashboard, own calendar/appointments |
| Patient | `patient1@scheduler.test` | Own dashboard + self-booking only |

**Role → permission matrix** (drives what each user sees and can open)

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

> Patients hold **no** staff permissions. They use a dedicated patient dashboard and the
> `/book` self-service flow only. Opening a staff URL as a patient returns **403 Forbidden**.

---

# Part A — Route Reference (how each route works)

Format: `METHOD URI` — **route name** — *who can access* — what happens.

### Entry & authentication

- **`GET /`** — *anyone* — Redirects straight to `/dashboard`. If not logged in, the `auth`
  middleware on the dashboard bounces you to `/login`.
- **`GET /login`** — `login` — *guests only* — Shows the login form (`auth/login` view). Logged-in
  users hitting this are redirected to `/dashboard` by the `guest` middleware.
- **`POST /login`** — *guests* — `AuthController@login`. Validates email + password → `Auth::attempt()`.
  On failure, redirects back with a validation error on `email`. On success it (1) checks `is_active`
  (inactive accounts are logged out with an error), (2) regenerates the session, (3) stamps
  `last_login_at`, (4) writes a `login` row to `audit_logs`, then redirects to the intended page or `/dashboard`.
- **`GET /register`** — `register` — *guests* — Shows the registration form.
- **`POST /register`** — *guests* — `AuthController@register`. Validates and creates a `users` row,
  assigns the **patient** role, logs the user in, redirects to `/dashboard`.
- **`POST /logout`** — `logout` — *authenticated* — Logs out, invalidates the session, redirects to `/login`.

### Dashboard

- **`GET /dashboard`** — `dashboard` — *any authenticated user* — `DashboardController@index`.
  Branches by role:
  - **Patient-only** users get the patient dashboard (`dashboard.patient`): their upcoming + past
    appointments and a "Book Appointment" button.
  - **Staff** get the operations dashboard (`dashboard.index`): stat cards (today's count, upcoming,
    patients, 30-day no-show rate), a 14-day appointments trend chart (ApexCharts), status breakdown,
    today's schedule table, and a high-no-show-risk list.

### Profile (any logged-in user)

- **`GET /profile`** — `profile.edit` — Shows profile + change-password forms.
- **`PUT /profile`** — `profile.update` — Validates and saves name/email/phone/address/locale.
- **`PUT /profile/password`** — `profile.password` — Requires the correct current password, then
  updates to the new (confirmed) password.

### Patient self-service booking (any logged-in user; built for patients)

- **`GET /book`** — `booking.create` — Booking wizard: pick service → provider → date → an available
  time slot (times load live from `/api/slots`).
- **`POST /book`** — `booking.store` — `BookingController@store`. Validates, then calls the
  `SchedulingService` to book the slot for the **logged-in patient**. If the slot was taken in the
  meantime, it returns with an "no longer available" error. Otherwise creates the appointment and
  redirects to the dashboard with a success message.
- **`PATCH /book/{appointment}/cancel`** — `booking.cancel` — Cancels the appointment, but only if it
  belongs to the current user (otherwise 403).

### Slot lookup (shared AJAX endpoint)

- **`GET /api/slots?provider_id=&service_id=&date=`** — `appointments.slots` — *any authenticated user* —
  Returns JSON `{ "slots": [{start,end,label}, …] }`. Slots are generated from the provider's working
  hours for that weekday, chopped into service-duration + buffer steps, with already-booked and
  past times removed. Used by both the staff "New Appointment" form and the patient `/book` page.

### Calendar (needs **view appointments**)

- **`GET /calendar`** — `calendar` — FullCalendar UI (week/month/day/list) with a provider filter.
- **`GET /calendar/events?start=&end=&provider_id=`** — `calendar.events` — Returns appointments as
  JSON events for the visible range. **Providers automatically see only their own** appointments.

### Appointments

*View (needs **view appointments**):*
- **`GET /appointments`** — `appointments.index` — Filterable, paginated list (search patient,
  status, provider, date). Providers see only their own.
- **`GET /appointments/{appointment}`** — `appointments.show` — Full detail: patient/provider/service,
  time, no-show risk gauge, reminders, and (for managers) a status-update form.

*Manage (needs **manage appointments**):*
- **`GET /appointments-create/new`** — `appointments.create` — New-appointment form with the live slot picker.
  (The URL is deliberately not `/appointments/create` so it can't clash with the `{appointment}` detail route.)
- **`POST /appointments`** — `appointments.store` — Validates, books via `SchedulingService`
  (conflict-safe), computes a no-show score, writes a `created` audit entry, redirects to the detail page.
- **`GET /appointments/{appointment}/edit`** — `appointments.edit` — Edit form.
- **`PUT /appointments/{appointment}`** — `appointments.update` — If time/provider changed, runs a
  conflict-safe reschedule; saves other fields; writes an `updated` audit entry.
- **`DELETE /appointments/{appointment}`** — `appointments.destroy` — Soft-deletes; writes a `deleted` audit entry.
- **`PATCH /appointments/{appointment}/status`** — `appointments.status` — Moves status
  (booked → confirmed → checked-in → completed / cancelled / no-show), stamping the matching timestamp.

### Waitlist (needs **manage waitlist**)

- **`GET /waitlist`** — `waitlist.index` — Waiting list ordered by priority, plus an "add" form.
- **`POST /waitlist`** — `waitlist.store` — Adds a patient with service/provider/time preference + priority.
- **`DELETE /waitlist/{waitlist}`** — `waitlist.destroy` — Removes an entry.

### Patients (needs **manage patients**)

- **`GET /patients`** — `patients.index` — Searchable, paginated patient list with visit counts.
- **`GET /patients/create`** — `patients.create` — Add-patient form.
- **`POST /patients`** — `patients.store` — Creates a user with the patient role (default password `password`).
- **`GET /patients/{patient}`** — `patients.show` — Profile + full appointment history.
- **`GET /patients/{patient}/edit`** + **`PUT /patients/{patient}`** — Edit / save.
- **`DELETE /patients/{patient}`** — Soft-delete.

### Providers (needs **manage providers**)

- **`GET /providers`** — card grid of providers with their services and appointment counts.
- **`GET /providers/create` · `POST /providers`** — Creates a user (provider role) + provider profile;
  attaches selected services.
- **`GET /providers/{provider}`** — Profile, services, and weekly working hours.
- **`GET /providers/{provider}/edit` · `PUT /providers/{provider}`** — Edit / save (including services).
- **`DELETE /providers/{provider}`** — Soft-delete.

### Services (needs **manage services**)

- **`GET /services`** — Table of services (duration, buffer, price, colour, telehealth, status).
- **`GET /services/create` · `POST /services`** — Create.
- **`GET /services/{service}/edit` · `PUT /services/{service}`** — Edit / save.
- **`DELETE /services/{service}`** — Soft-delete.

### Reports (needs **view reports**)

- **`GET /reports`** — `reports.index` — 30-day no-show rate, no-show by provider, booking-channel
  donut, and provider-utilization bar chart.

### Billing (needs **view billing**)

- **`GET /payments`** — `payments.index` — Collected / pending / refunded totals + transaction list.
  (No transactions exist yet — Stripe is on the roadmap — so the table is empty by design.)

### Users & Roles (needs **manage users**)

- **`GET /users`** — Search/filter users by name/role; shows roles + active state.
- **`GET /users/create` · `POST /users`** — Create a user and assign one role.
- **`GET /users/{user}/edit` · `PUT /users/{user}`** — Edit, optionally reset password, change role/active.
- **`DELETE /users/{user}`** — Delete (blocked for your own account).

### Clinics (needs **manage clinics**)

- **`GET /clinics`** — List with provider/service/appointment counts.
- **`GET /clinics/create` · `POST /clinics`** — Create (auto-generates a unique slug).
- **`GET /clinics/{clinic}/edit` · `PUT /clinics/{clinic}`** — Edit / save.

### Audit Logs (needs **view audit logs**)

- **`GET /audit`** — `audit.index` — Chronological log of recorded actions (logins, appointment
  create/update/delete/status changes) with user, entity and IP.

---

# Part B — Manual Test Cases

Each case lists **steps** and the **expected result**. "Login as X" means log out first, then sign in
with that demo account.

## 1. Authentication & access control

| # | Test | Steps | Expected |
|---|------|-------|----------|
| AUTH-01 | Login page loads | Visit `/login` while logged out | Login form with email/password + demo-credentials hint shown |
| AUTH-02 | Valid login | Enter `admin@scheduler.test` / `password` → Login | Redirected to `/dashboard`; your name appears top-right |
| AUTH-03 | Wrong password | Enter a real email + wrong password | Stays on login, red error "credentials do not match" |
| AUTH-04 | Required fields | Submit empty form | Validation errors on email and password |
| AUTH-05 | Register patient | `/register` → fill name/email/password (matching confirm) → submit | Logged in, lands on patient dashboard; new user has patient role |
| AUTH-06 | Register duplicate email | Register with `patient1@scheduler.test` | Error: email already taken |
| AUTH-07 | Logout | Click avatar → Log Out (or sidebar Log Out) | Back to `/login`; visiting `/dashboard` redirects to login |
| AUTH-08 | Guest blocked | While logged out visit `/appointments` | Redirected to `/login` |
| AUTH-09 | Patient blocked from staff page | Login as `patient1@scheduler.test`, visit `/appointments` in the URL bar | **403 Forbidden** |
| AUTH-10 | Provider sees only own data | Login as `sarah.chen@scheduler.test`, open Calendar/Appointments | Only Dr. Chen's appointments appear |

## 2. Dashboard

| # | Test | Steps | Expected |
|---|------|-------|----------|
| DASH-01 | Staff dashboard | Login as admin | 4 stat cards, 14-day trend chart renders, status breakdown, today's schedule, high-risk list |
| DASH-02 | Today's schedule click | Click a row in "Today's schedule" | Opens that appointment's detail page |
| DASH-03 | No-show rate value | Read "No-show rate (30d)" card | A percentage (e.g. 0–40%), not blank/NaN |
| DASH-04 | Patient dashboard | Login as `patient1@scheduler.test` | Shows your upcoming + past appointments and "Book Appointment" button; no staff nav items |
| DASH-05 | Empty states | Login as a brand-new registered patient | "No upcoming appointments. Book one now." message |

## 3. Calendar

| # | Test | Steps | Expected |
|---|------|-------|----------|
| CAL-01 | Calendar loads | Login as admin → Calendar | FullCalendar shows the week with coloured appointment blocks |
| CAL-02 | Switch views | Click Month / Day / List buttons | View changes; events still show |
| CAL-03 | Provider filter | Choose a provider in the dropdown | Calendar reloads showing only that provider's appointments |
| CAL-04 | Event click | Click an appointment block | Navigates to the appointment detail page |
| CAL-05 | Navigate weeks | Click prev/next/today | Date range changes and events refetch |
| CAL-06 | Provider scope | Login as a provider → Calendar | Only that provider's events appear, regardless of filter |

## 4. Appointments (as Front Desk or Admin)

| # | Test | Steps | Expected |
|---|------|-------|----------|
| APPT-01 | List loads | Open Appointments | Paginated table with When/Patient/Provider/Service/Status/Risk |
| APPT-02 | Filter by status | Choose "Confirmed" → Filter | Only confirmed appointments listed |
| APPT-03 | Filter by provider/date | Pick a provider and a date → Filter | List narrows accordingly |
| APPT-04 | Search patient | Type a patient name → Filter | Matching rows only |
| APPT-05 | New appointment – slot picker | New Appointment → choose patient, service, provider, date | Available time buttons appear after the 4 fields are set |
| APPT-06 | Book a slot | Pick a time → Book Appointment | Redirects to detail page with "booked successfully" message |
| APPT-07 | **Double-booking blocked** | Book a slot; then create another for the **same provider + same time** | Second attempt returns with "time slot is no longer available"; only one appointment exists |
| APPT-08 | No slots on day off | Choose a Sunday as the date | "No available slots for this day." |
| APPT-09 | Edit appointment | Open an appointment → Edit → change reason → Save | Detail shows updated info; "updated" message |
| APPT-10 | Reschedule conflict | Edit → set start time to a slot already taken for that provider | Returns with conflict error |
| APPT-11 | Status workflow | On detail page set status to Confirmed → Update | Badge changes to Confirmed; `confirmed_at` set |
| APPT-12 | Cancel via status | Set status Cancelled with a reason | Badge shows Cancelled |
| APPT-13 | Delete | Detail → Delete → confirm | Returns to list; appointment gone (soft-deleted) |
| APPT-14 | Risk badge | Look at the Risk column / detail gauge | Shows 0–100% with low/med/high colour |

## 5. Patient self-service booking (as a patient)

| # | Test | Steps | Expected |
|---|------|-------|----------|
| BOOK-01 | Open booking | Login as patient → Book Appointment | Service/provider/date fields shown |
| BOOK-02 | Live slots | Choose service, provider, future date | Time buttons load |
| BOOK-03 | Confirm booking | Pick a time → Confirm Booking | Redirects to dashboard, "appointment has been booked!"; appears under Upcoming |
| BOOK-04 | Cancel own appointment | Dashboard → Cancel on an upcoming row → confirm | Status becomes Cancelled |
| BOOK-05 | Past-date guard | Try to submit with a past date/time | Validation error (must be in the future) |
| BOOK-06 | Cannot cancel others | (Advanced) change the appointment id in the cancel URL | 403 Forbidden |

## 6. Patients (as Front Desk or Admin)

| # | Test | Steps | Expected |
|---|------|-------|----------|
| PAT-01 | List + search | Open Patients; type a name/email/phone | Matching patients with avatar + visit count |
| PAT-02 | Create | Add Patient → fill name/email → Create | Lands on profile; "Patient created"; default password is `password` |
| PAT-03 | Duplicate email | Create with an existing email | Validation error |
| PAT-04 | View profile | Open a patient | Profile details + appointment history table |
| PAT-05 | Edit | Edit → change phone/DOB → Save | Updated values shown |
| PAT-06 | Deactivate | Edit → toggle Active off → Save | Status badge shows Inactive |
| PAT-07 | Delete | Delete a patient | Removed from list |

## 7. Providers (as Admin)

| # | Test | Steps | Expected |
|---|------|-------|----------|
| PROV-01 | Grid loads | Open Providers | Cards with specialty, services, counts |
| PROV-02 | Create | Add Provider → fill details + tick services → Create | New provider card appears; user gets provider role |
| PROV-03 | View | Open a provider | Services + weekly working hours table |
| PROV-04 | Edit services | Edit → change ticked services → Save | Updated service badges |
| PROV-05 | Telehealth/active toggles | Edit → toggle telehealth / active → Save | Reflected on card/detail |

## 8. Services (as Admin)

| # | Test | Steps | Expected |
|---|------|-------|----------|
| SVC-01 | List | Open Services | Table with duration/buffer/price/colour/telehealth/status |
| SVC-02 | Create | Add Service → set name + duration 45, buffer 10, price → Create | Appears in list with values |
| SVC-03 | Validation | Set duration below 5 | Validation error |
| SVC-04 | Edit | Edit a service → change price/colour → Save | Updated |
| SVC-05 | Delete | Delete a service | Removed |
| SVC-06 | Slot effect | Set a service to 60 min, then book it | Slot picker steps in 60-min increments |

## 9. Waitlist (as Front Desk or Admin)

| # | Test | Steps | Expected |
|---|------|-------|----------|
| WAIT-01 | List loads | Open Waitlist | Table (empty initially) + add form |
| WAIT-02 | Add entry | Choose patient, service, priority 2 → Add | Row appears ordered by priority |
| WAIT-03 | Remove | Remove an entry → confirm | Row disappears |

## 10. Reports (as Billing or Admin)

| # | Test | Steps | Expected |
|---|------|-------|----------|
| REP-01 | Page loads | Open Reports | No-show rate cards + 2 charts render |
| REP-02 | By-provider table | Read the table | Each provider with total / no-shows / rate% |
| REP-03 | Channel donut | Inspect donut | Segments for web/app/phone/ai |
| REP-04 | Utilization bar | Inspect bar chart | One bar per provider |

## 11. Billing (as Billing or Admin)

| # | Test | Steps | Expected |
|---|------|-------|----------|
| BILL-01 | Page loads | Open Billing | Collected/Pending/Refunded totals |
| BILL-02 | Empty list | Read transactions table | "No transactions yet" note (Stripe pending) |

## 12. Users & Roles (as Admin)

| # | Test | Steps | Expected |
|---|------|-------|----------|
| USR-01 | List + filter | Open Users; filter by role | Users with role badges + active state |
| USR-02 | Create user | Add User → set role = Front Desk + password → Create | Appears with that role |
| USR-03 | Change role | Edit a user → switch role → Save | New role badge; their menu/permissions change on next login |
| USR-04 | Reset password | Edit → set new password → Save | That user can log in with the new password |
| USR-05 | Self-delete blocked | Try to delete your own account | Error: "cannot delete your own account" |

## 13. Clinics (as Admin)

| # | Test | Steps | Expected |
|---|------|-------|----------|
| CLN-01 | List | Open Clinics | Clinics with provider/service/appt counts |
| CLN-02 | Create | Add Clinic → name + timezone → Create | New clinic in list |
| CLN-03 | Edit | Edit → change city/active → Save | Updated |

## 14. Audit Logs (as Admin)

| # | Test | Steps | Expected |
|---|------|-------|----------|
| AUD-01 | Page loads | Open Audit Logs | Chronological list with user/action/entity/IP |
| AUD-02 | Login is logged | Log out, log back in, open Audit Logs | A fresh `login` entry at the top |
| AUD-03 | Appointment actions logged | Create/edit/delete an appointment, then open Audit Logs | Matching created/updated/deleted entries |

## 15. Profile (any user)

| # | Test | Steps | Expected |
|---|------|-------|----------|
| PRO-01 | Update profile | Profile → change phone → Save profile | "Profile updated"; value persists |
| PRO-02 | Wrong current password | Change password with a wrong current password | Validation error |
| PRO-03 | Change password | Enter correct current + matching new password → Save | "Password changed"; new password works after logout |

## 16. Layout / navigation (cross-cutting)

| # | Test | Steps | Expected |
|---|------|-------|----------|
| NAV-01 | Role-based menu | Log in as each role | Sidebar shows only the sections that role can access |
| NAV-02 | Active highlight | Navigate between pages | Current page's sidebar link is highlighted |
| NAV-03 | Mobile sidebar | Shrink the window < ~990px → tap the burger | Sidebar slides in over a backdrop; tapping backdrop closes it |
| NAV-04 | Top-bar quick action | Click "New Appointment" (staff) | Opens the create form |
| NAV-05 | Flash messages | Do any create/update | Green success alert appears and is dismissable |

---

## Quick regression checklist (5-minute smoke test)

1. Login as admin → dashboard loads with chart. ✅
2. Calendar shows events; provider filter works. ✅
3. Create an appointment via the slot picker. ✅
4. Try to double-book the same slot → blocked. ✅
5. Change its status to Confirmed. ✅
6. Login as `patient1` → book an appointment → see it under Upcoming → cancel it. ✅
7. Login as `patient1` → open `/appointments` in the URL → 403. ✅
8. Open Audit Logs as admin → see your recent login + appointment actions. ✅
