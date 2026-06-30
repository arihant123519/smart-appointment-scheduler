<?php

use App\Http\Controllers\AiBookingController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AppointmentNotificationController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ClinicController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\IntakeFormController;
use App\Http\Controllers\IntegrationSettingsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProviderController;
use App\Http\Controllers\ReminderActionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WaitlistController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

// --- Guest / authentication ------------------------------------------------
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login']);
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register']);
});

Route::post('logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// --- One-tap reminder actions (token-authenticated, no login) --------------
Route::get('r/{token}/confirm', [ReminderActionController::class, 'confirm'])->name('reminder.confirm');
Route::get('r/{token}/cancel', [ReminderActionController::class, 'cancel'])->name('reminder.cancel');
Route::get('r/{token}/reschedule', [ReminderActionController::class, 'reschedule'])->name('reminder.reschedule');

// --- Authenticated ---------------------------------------------------------
Route::middleware('auth')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profile + API tokens (any authenticated user)
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('profile/tokens', [ProfileController::class, 'createToken'])->name('profile.tokens.create');
    Route::delete('profile/tokens/{tokenId}', [ProfileController::class, 'deleteToken'])->name('profile.tokens.delete');

    // Notifications center
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.readAll');

    // Calendar export (iCal) + my-appointments
    Route::get('export/appointments.ics', [ExportController::class, 'ics'])->name('export.ics');

    // Patient self-service booking
    Route::get('book', [BookingController::class, 'create'])->name('booking.create');
    Route::post('book', [BookingController::class, 'store'])->name('booking.store');
    Route::patch('book/{appointment}/cancel', [BookingController::class, 'cancel'])->name('booking.cancel');

    // AI assistant: natural-language booking + conversational chatbot + symptom routing
    Route::get('assistant', [AiBookingController::class, 'index'])->name('ai.booking');
    Route::post('assistant/parse', [AiBookingController::class, 'parse'])->name('ai.parse');
    Route::post('assistant/chat', [AiBookingController::class, 'chat'])->name('ai.chat');
    Route::post('assistant/symptoms', [AiBookingController::class, 'routeSymptoms'])->name('ai.symptoms');

    // Global AI navigation assistant (floating widget, available to every user)
    Route::post('assistant/command', [AssistantController::class, 'command'])->name('ai.command');

    // Intake forms + digital check-in (patient or staff)
    Route::get('appointments/{appointment}/intake', [IntakeFormController::class, 'edit'])->name('intake.edit');
    Route::put('appointments/{appointment}/intake', [IntakeFormController::class, 'update'])->name('intake.update');
    Route::patch('appointments/{appointment}/check-in', [IntakeFormController::class, 'checkIn'])->name('intake.checkin');

    // Reviews (patient leaves feedback)
    Route::get('appointments/{appointment}/review', [ReviewController::class, 'create'])->name('reviews.create');
    Route::post('appointments/{appointment}/review', [ReviewController::class, 'store'])->name('reviews.store');

    // Available-slot lookup (staff + patient booking forms)
    Route::get('api/slots', [AppointmentController::class, 'slots'])->name('appointments.slots');

    // Calendar
    Route::middleware('can:view appointments')->group(function () {
        Route::get('calendar', [CalendarController::class, 'index'])->name('calendar');
        Route::get('calendar/events', [CalendarController::class, 'events'])->name('calendar.events');
    });
    Route::middleware('can:manage calendar')->group(function () {
        Route::match(['post', 'patch'], 'calendar/{appointment}/reschedule', [CalendarController::class, 'reschedule'])->name('calendar.reschedule');
    });

    // Appointments
    Route::middleware('can:view appointments')->group(function () {
        Route::get('appointments', [AppointmentController::class, 'index'])->name('appointments.index');
        Route::get('appointments/export.csv', [ExportController::class, 'appointmentsCsv'])->name('appointments.export');
        Route::get('appointments/{appointment}', [AppointmentController::class, 'show'])->name('appointments.show');
    });
    Route::middleware('can:manage appointments')->group(function () {
        // Appointment Notifications settings (lead-time + status-change messages)
        Route::get('appointments-notifications/settings', [AppointmentNotificationController::class, 'edit'])->name('appointments.notifications.edit');
        Route::put('appointments-notifications/settings', [AppointmentNotificationController::class, 'update'])->name('appointments.notifications.update');
        Route::get('appointments-create/new', [AppointmentController::class, 'create'])->name('appointments.create');
        Route::post('appointments', [AppointmentController::class, 'store'])->name('appointments.store');
        Route::get('appointments/{appointment}/edit', [AppointmentController::class, 'edit'])->name('appointments.edit');
        Route::put('appointments/{appointment}', [AppointmentController::class, 'update'])->name('appointments.update');
        Route::delete('appointments/{appointment}', [AppointmentController::class, 'destroy'])->name('appointments.destroy');
        Route::patch('appointments/{appointment}/status', [AppointmentController::class, 'updateStatus'])->name('appointments.status');
        // Payments against an appointment
        Route::post('appointments/{appointment}/charge', [PaymentController::class, 'charge'])->name('payments.charge');
    });

    // Waitlist
    Route::middleware('can:manage waitlist')->group(function () {
        Route::get('waitlist', [WaitlistController::class, 'index'])->name('waitlist.index');
        Route::post('waitlist', [WaitlistController::class, 'store'])->name('waitlist.store');
        Route::delete('waitlist/{waitlist}', [WaitlistController::class, 'destroy'])->name('waitlist.destroy');
    });

    // Patients
    Route::middleware('can:manage patients')->group(function () {
        Route::resource('patients', PatientController::class)->except('show');
        Route::get('patients/{patient}', [PatientController::class, 'show'])->name('patients.show');
    });

    // Providers + availability management
    Route::middleware('can:manage providers')->group(function () {
        Route::resource('providers', ProviderController::class);
        Route::get('providers/{provider}/availability', [AvailabilityController::class, 'edit'])->name('availability.edit');
        Route::put('providers/{provider}/availability', [AvailabilityController::class, 'update'])->name('availability.update');
        Route::post('providers/{provider}/availability/exception', [AvailabilityController::class, 'storeException'])->name('availability.exception.store');
        Route::delete('providers/{provider}/availability/exception/{exception}', [AvailabilityController::class, 'destroyException'])->name('availability.exception.destroy');
    });

    // Services
    Route::middleware('can:manage services')->group(function () {
        Route::resource('services', ServiceController::class)->except('show');
    });

    // Reports + reviews/feedback
    Route::middleware('can:view reports')->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::post('reports/ask', [ReportController::class, 'ask'])->name('reports.ask');
        Route::get('reviews', [ReviewController::class, 'index'])->name('reviews.index');
    });

    // Billing
    Route::middleware('can:view billing')->group(function () {
        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::post('payments/{payment}/refund', [PaymentController::class, 'refund'])->name('payments.refund');
    });

    // Broadcast messaging
    Route::middleware('can:manage reminders')->group(function () {
        Route::get('announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
        Route::post('announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
        Route::get('announcements/{announcement}/edit', [AnnouncementController::class, 'edit'])->name('announcements.edit');
        Route::put('announcements/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
        Route::delete('announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');
    });

    // Users
    Route::middleware('can:manage users')->group(function () {
        Route::resource('users', UserController::class)->except('show');
    });

    // Roles & permissions (RBAC management)
    Route::middleware('can:manage roles')->group(function () {
        Route::resource('roles', RoleController::class)->except('show');
    });

    // Clinics
    Route::middleware('can:manage clinics')->group(function () {
        Route::resource('clinics', ClinicController::class)->except('show');
    });

    // Audit logs
    Route::get('audit', [AuditLogController::class, 'index'])->middleware('can:view audit logs')->name('audit.index');

    // Integration settings (Email / SMS / WhatsApp credentials)
    Route::middleware('can:manage settings')->group(function () {
        Route::get('settings/integrations', [IntegrationSettingsController::class, 'edit'])->name('settings.integrations.edit');
        Route::put('settings/integrations', [IntegrationSettingsController::class, 'update'])->name('settings.integrations.update');
        Route::post('settings/integrations/test', [IntegrationSettingsController::class, 'test'])->name('settings.integrations.test');
    });
});
