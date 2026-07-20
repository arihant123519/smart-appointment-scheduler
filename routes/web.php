<?php

use App\Http\Controllers\AiBookingController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AppointmentNotificationController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PhoneAuthController;
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

    // Phone + OTP login (no password) — parallel path, existing login untouched.
    Route::get('login/phone', [PhoneAuthController::class, 'showRequest'])->name('login.phone');
    Route::post('login/phone', [PhoneAuthController::class, 'sendCode'])->middleware('throttle:5,1')->name('login.phone.send');
    Route::get('login/phone/verify', [PhoneAuthController::class, 'showVerify'])->name('login.phone.verify');
    Route::post('login/phone/verify', [PhoneAuthController::class, 'verifyCode'])->middleware('throttle:10,1')->name('login.phone.verify.submit');
});

Route::post('logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// --- One-tap reminder actions (token-authenticated, no login) --------------
Route::get('r/{token}/confirm', [ReminderActionController::class, 'confirm'])->name('reminder.confirm');
Route::get('r/{token}/cancel', [ReminderActionController::class, 'cancel'])->name('reminder.cancel');
Route::get('r/{token}/reschedule', [ReminderActionController::class, 'reschedule'])->name('reminder.reschedule');

// --- Referral redemption (token-authenticated, no login needed to view) ----
Route::get('ref/{token}', [\App\Http\Controllers\ReferralController::class, 'show'])->name('referral.redeem');

// --- QR code redemption + image (public, token-authenticated) --------------
Route::get('qr/{token}', [\App\Http\Controllers\QrCodeController::class, 'redeem'])->name('qrcodes.redeem');
Route::get('qr/{token}/image', [\App\Http\Controllers\QrCodeController::class, 'image'])->name('qrcodes.image');

// --- Public review submission (public, token-authenticated, no login) ------
Route::middleware('throttle:10,1')->group(function () {
    Route::get('feedback/{token}', [\App\Http\Controllers\PublicReviewController::class, 'show'])->name('reviews.public.show');
    Route::post('feedback/{token}', [\App\Http\Controllers\PublicReviewController::class, 'store'])->name('reviews.public.store');
});
Route::get('feedback/{token}/image', [\App\Http\Controllers\PublicReviewController::class, 'image'])->name('reviews.public.image');

// --- Inbound Gupshup webhook (public, shared-secret authenticated) ---------
Route::post('webhooks/gupshup', [\App\Http\Controllers\Webhooks\GupshupWebhookController::class, 'handle'])
    ->name('webhooks.gupshup');

// --- Inbound Razorpay webhook (public, signature authenticated) ------------
Route::post('webhooks/razorpay', [\App\Http\Controllers\Webhooks\RazorpayWebhookController::class, 'handle'])
    ->name('webhooks.razorpay');

// --- Inbound SMS webhook (public, shared-secret authenticated) -------------
Route::post('webhooks/sms-inbound', [\App\Http\Controllers\Webhooks\SmsInboundWebhookController::class, 'handle'])
    ->name('webhooks.sms-inbound');

// --- Missed-call text-back webhook (public, shared-secret authenticated) ---
Route::post('webhooks/missed-call', [\App\Http\Controllers\Webhooks\MissedCallWebhookController::class, 'handle'])
    ->name('webhooks.missed-call');

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
    Route::get('notifications/feed', [NotificationController::class, 'feed'])->name('notifications.feed');
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
        Route::get('appointments/{appointment}', [AppointmentController::class, 'show'])->name('appointments.show');
        Route::get('appointments/{appointment}/reschedule-suggestions', [AppointmentController::class, 'rescheduleSuggestions'])->name('appointments.reschedule-suggestions');
    });
    // Bulk patient-data export is restricted to owners/managers, not every
    // role that can merely view appointments (front-desk included).
    Route::middleware('can:export patient data')->group(function () {
        Route::get('appointments/export.csv', [ExportController::class, 'appointmentsCsv'])->name('appointments.export');
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
        Route::patch('appointments/{appointment}/reason', [AppointmentController::class, 'updateReason'])->name('appointments.reason');
        // Payments against an appointment
        Route::post('appointments/{appointment}/charge', [PaymentController::class, 'charge'])->name('payments.charge');
    });

    // Waitlist
    Route::middleware('can:manage waitlist')->group(function () {
        Route::get('waitlist', [WaitlistController::class, 'index'])->name('waitlist.index');
        Route::post('waitlist', [WaitlistController::class, 'store'])->name('waitlist.store');
        Route::delete('waitlist/{waitlist}', [WaitlistController::class, 'destroy'])->name('waitlist.destroy');
    });

    // Walk-in queue
    Route::middleware('can:manage walk_in_queue')->group(function () {
        Route::get('walkins', [\App\Http\Controllers\WalkInQueueController::class, 'index'])->name('walkins.index');
        Route::post('walkins', [\App\Http\Controllers\WalkInQueueController::class, 'store'])->name('walkins.store');
        Route::patch('walkins/{walkin}/status', [\App\Http\Controllers\WalkInQueueController::class, 'updateStatus'])->name('walkins.status');
        Route::delete('walkins/{walkin}', [\App\Http\Controllers\WalkInQueueController::class, 'destroy'])->name('walkins.destroy');
    });

    // Patients
    Route::middleware('can:manage patients')->group(function () {
        Route::resource('patients', PatientController::class)->except('show');
        Route::get('patients/{patient}', [PatientController::class, 'show'])->name('patients.show');
        Route::get('referrals', [\App\Http\Controllers\ReferralController::class, 'index'])->name('referrals.index');

        // Referral letters / consent forms / visit recaps — draft, edit, approve+send
        Route::post('appointments/{appointment}/documents', [\App\Http\Controllers\PatientDocumentController::class, 'store'])->name('appointments.documents.store');
        Route::patch('documents/{document}', [\App\Http\Controllers\PatientDocumentController::class, 'update'])->name('documents.update');
        Route::post('documents/{document}/approve', [\App\Http\Controllers\PatientDocumentController::class, 'approve'])->name('documents.approve');
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

        // QR-code booking attribution — service-adjacent admin task, reuses this permission.
        Route::get('qrcodes', [\App\Http\Controllers\QrCodeController::class, 'index'])->name('qrcodes.index');
        Route::post('qrcodes', [\App\Http\Controllers\QrCodeController::class, 'store'])->name('qrcodes.store');
        Route::delete('qrcodes/{qrcode}', [\App\Http\Controllers\QrCodeController::class, 'destroy'])->name('qrcodes.destroy');

        // Review QR codes — per-clinic public feedback link, same admin permission.
        Route::get('reviewqrcodes', [\App\Http\Controllers\ReviewQrCodeController::class, 'index'])->name('reviewqrcodes.index');
        Route::post('reviewqrcodes', [\App\Http\Controllers\ReviewQrCodeController::class, 'store'])->name('reviewqrcodes.store');
        Route::delete('reviewqrcodes/{reviewqrcode}', [\App\Http\Controllers\ReviewQrCodeController::class, 'destroy'])->name('reviewqrcodes.destroy');
    });

    // Reports + reviews/feedback
    Route::middleware('can:view reports')->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::post('reports/ask', [ReportController::class, 'ask'])->name('reports.ask');
        Route::get('reviews', [ReviewController::class, 'index'])->name('reviews.index');
        Route::get('reviews/feed', [ReviewController::class, 'feed'])->name('reviews.feed');
    });

    // Billing
    Route::middleware('can:view billing')->group(function () {
        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::post('payments/{payment}/refund', [PaymentController::class, 'refund'])->name('payments.refund');
        Route::post('payments/{payment}/confirm-deposit', [PaymentController::class, 'confirmDeposit'])->name('payments.confirm-deposit');
    });

    // Broadcast messaging
    Route::middleware('can:manage reminders')->group(function () {
        Route::get('announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
        Route::post('announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
        Route::get('announcements/preview-audience', [AnnouncementController::class, 'previewAudience'])->name('announcements.previewAudience');
        Route::get('announcements/{announcement}/edit', [AnnouncementController::class, 'edit'])->name('announcements.edit');
        Route::put('announcements/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
        Route::delete('announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');
    });

    // WhatsApp conversation flows (visual builder)
    Route::middleware('can:manage flows')->group(function () {
        Route::get('flows', [\App\Http\Controllers\WhatsappFlowController::class, 'index'])->name('flows.index');
        Route::get('flows/create', [\App\Http\Controllers\WhatsappFlowController::class, 'create'])->name('flows.create');
        Route::post('flows', [\App\Http\Controllers\WhatsappFlowController::class, 'store'])->name('flows.store');
        Route::get('flows/{flow}/edit', [\App\Http\Controllers\WhatsappFlowController::class, 'edit'])->name('flows.edit');
        Route::put('flows/{flow}', [\App\Http\Controllers\WhatsappFlowController::class, 'update'])->name('flows.update');
        Route::patch('flows/{flow}/activate', [\App\Http\Controllers\WhatsappFlowController::class, 'activate'])->name('flows.activate');
        Route::delete('flows/{flow}', [\App\Http\Controllers\WhatsappFlowController::class, 'destroy'])->name('flows.destroy');
        Route::get('flows/{flow}/conversations', [\App\Http\Controllers\WhatsappFlowController::class, 'conversations'])->name('flows.conversations');
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
