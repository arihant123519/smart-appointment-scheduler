# Naye Features — Kya Bana, Kaise Test Karein, Aapki Taraf Se Kya Baaki Hai

**Date:** 2026-07-13
**Demo logins** (sab ka password: `password`):
- Admin/Owner: `archiaswal1234567890@gmail.com`
- Front Desk: `frontdesk@scheduler.test`
- Provider: `sarah.chen@scheduler.test`
- Patient: `anabhaiya123@gmail.com`

> Puri list ke liye `docs/PRD-VS-IMPLEMENTATION-AUDIT.md` dekhein — ye document sirf "kya bana + kaise test karein + kya baaki hai" ke liye hai.

---

## PHASE 1 — Retention Loop (patients ko wapas laana)

### 1. Recall campaigns (overdue follow-up reminders)
**Kya karta hai:** Agar kisi service pe "follow-up window" set ho aur patient us window ke andar dobara nahi aaya, system automatically unhe WhatsApp/email pe reminder bhejta hai.

**Test kaise karein:**
1. Admin login karein → **Services** → kisi service ko edit karein
2. "Follow-up recall (days after a completed visit)" field me `1` daalein, save karein
3. Ek appointment banayein us service ke liye, status "Completed" karein (Appointments page se status update karein)
4. Terminal/CMD me project folder me jaake: `php artisan recall:dispatch` chalayein
5. Ya seedha wait karein — ye command har 15 minute me automatically chalta hai (agar `php artisan schedule:work` chal raha ho)
6. Check karein: **Settings → Integrations** ya patient ke email/WhatsApp log me message dikhega

### 2. Care-gap outreach (treatment plan follow-through)
Recall jaisa hi, bas "Care-plan cadence (days between visits)" field use hota hai — same service edit screen pe.

### 3. Referral tracking
**Kya karta hai:** Patients apna ek shareable link share kar sakte hain; jab koi us link se book kare, wo track hota hai.

**Test kaise karein:**
1. Patient login karein (`anabhaiya123@gmail.com`) → Dashboard pe "Know someone who'd like it here?" card dikhega
2. Wahan se link copy karein
3. Ek naye/incognito browser tab me wo link paste karein → aapko register/booking page pe le jayega
4. Naya account banake booking complete karein
5. Admin login → sidebar me **Referrals** → wahan is referral ka status "Booked" dikhega, conversion rate bhi

### 4. Review requests (auto-timed)
**Kya karta hai:** Completed visit ke 2 ghante baad automatically review-request bhejta hai (cancelled/no-show pe nahi).

**Test kaise karein:**
1. Ek appointment ko "Completed" status karein
2. `php artisan reviews:request-dispatch` chalayein (ya 2 ghante wait karein — command har 15 min chalta hai)
3. Patient ko message milega jisme review dene ka link hoga

---

## PHASE 2 — Owner Dashboard

### 5. Fill rate gauge
**Kahan milega:** Admin/Front-desk **Dashboard** → "Fill rate (last 30 days)" wala gauge chart
**Kya dikhata hai:** Aapke providers ke actual working-hours capacity me se kitna % booked hua hai (real availability data se calculate hota hai)

### 6. Booking channel mix (donut chart)
**Kahan milega:** Dashboard pe "Booking channels" donut — web/app/phone/sms/qr/api sabka breakdown

**Test:** Bas Dashboard khol ke dekh lein — automatically real data se banta hai, koi setup nahi chahiye.

---

## PHASE 3 — Deposits & Payments

### 7. Deposit collection at booking
**Kya karta hai:** Kisi service pe deposit required set karo → jab patient book kare, ek pending deposit khul jaata hai, 10 minute me confirm nahi hua to booking automatically release ho jaati hai.

**Test kaise karein:**
1. Admin → **Services** → koi service edit karein → "Require a deposit at booking" ON karein, amount daalein (e.g. $20), save
2. Patient login → **Book Appointment** → wahi service select karke book karein
3. Message aayega: "Your appointment is held! A $20.00 deposit is due within 10 minutes"
4. Admin → **Payments** page → "Pending deposits" section me ye dikhega → "Mark collected" button se confirm karein (manual/cash collection simulate karta hai)
5. Test karne ke liye ki auto-release kaam karta hai: deposit ko pending chhod dein, terminal me `php artisan payments:release-abandoned` chalayein (agar 10 min ho chuke hon) — appointment automatically cancel ho jayegi

### 8. Forfeiture policy (cancellation ke waqt deposit katna)
1. Same service me "Forfeit if cancelled within (hours)" me `24` daalein
2. Deposit wali booking ko 24 ghante ke andar cancel karein → deposit "forfeited" ho jayega (Payments page pe status dikhega)
3. 24 ghante se bahar cancel karein → automatically refund ho jayega

### 9. Controlled overbooking (advanced — Phase 6 me bhi related)
Services edit screen me "Allow controlled overbooking" — sirf un slots pe apply hota hai jinka historical no-show rate zyada ho. Isko test karne ke liye kaafi historical data chahiye, isliye ye zyada relevant hai jab clinic real use me aa jaye.

---

## PHASE 4 — Compliance & Login

### 10. Compliance gate (clinic activation)
**Kahan milega:** Admin → **Clinics** → koi clinic edit karein
**Test:** "Active" checkbox ON karne ki koshish karein bina "Compliance agreements signed (HIPAA/DPDP)" checkbox check kiye → error aayega. Pehle compliance checkbox check karein, phir Active ON hoga.

### 11. Phone + OTP login (bina password ke)
**Kahan milega:** Login page pe "Log in with phone instead" link
**Test kaise karein:**
1. Kisi existing patient ka phone number pata karein (ya admin se ek patient ka phone set karwayein)
2. Login page → "Log in with phone instead" → phone number daalein → Submit
3. Code WhatsApp pe jayega (agar WhatsApp configured nahi hai to ye sirf log file me likha jayega — neeche "kya baaki hai" section dekhein)
4. Log file dekhne ke liye: `storage/logs/laravel.log` me "verification code is XXXXXX" search karein
5. Wo 6-digit code verify screen pe daalein → login ho jayega

### 12. Patient-data export ab sirf owner/manager ke liye
**Test:** Front-desk account se login karke `/appointments/export.csv` URL try karein → 403 error aayega. Admin/clinic_admin se try karein → kaam karega.

---

## PHASE 5 — Alternate Booking Channels

### 13. SMS booking
**Kya karta hai:** Patient "book" text kare → 3 available slots offer hote hain → ek number reply karke confirm.
**Real SMS ke bina test karne ka tarika (tinker se):**
```
php artisan tinker
$sms = app(\App\Services\SmsBookingService::class);
$sms->handleInbound('<patient-ka-phone>', 'book');
// storage/logs/laravel.log me offer dikhega
$sms->handleInbound('<patient-ka-phone>', '1'); // pehla slot book karega
```
Real duniya me: Gupshup SMS credentials set karne ke baad, `/webhooks/sms-inbound` URL Gupshup dashboard me configure karna hoga.

### 14. Missed-call text-back
**Test (tinker se):**
```
php artisan tinker
config(['services.sms.missed_call_webhook_secret' => 'test123']);
$controller = app(\App\Http\Controllers\Webhooks\MissedCallWebhookController::class);
$req = \Illuminate\Http\Request::create('/webhooks/missed-call?token=test123', 'POST', ['phone' => '<koi-phone>']);
$controller->handle($req, app(\App\Services\Messaging\MessageService::class));
```
Real duniya me: apni telephony/call-forwarding service ka "missed call" webhook `/webhooks/missed-call?token=<secret>` pe point karna hoga.

### 15. Walk-in queue
**Kahan milega:** Front-desk/Admin sidebar → **Walk-in Queue**
**Test kaise karein:**
1. "Add a walk-in" form se naam daalke add karein
2. Wahi list me position dikhega (1, 2, 3...)
3. Eye icon (👁) pe click karein → patient-facing "your position" page khulega (naye tab me) — auto-refresh hoti hai
4. "Call in" button dabayein → status "Serving" ho jayega, baaki logon ki position automatically update ho jayegi

### 16. QR code booking
**Kahan milega:** Admin sidebar → **QR Codes**
**Test kaise karein:**
1. "Create a QR code" form se label daalein (e.g. "Waiting room poster"), koi service select karein (optional), Generate karein
2. List me ek asli QR code image dikhega — download bhi kar sakte hain
3. Us QR code ko scan karein (phone se) ya uska link (`/qr/{token}`) browser me kholein
4. Booking page khulega, service pre-selected hoga
5. Booking complete karein → QR Codes page pe wapas jaake dekhein "Scans" aur "Bookings" count badh gaya hoga

---

## PHASE 6 — Revenue & Patient Intelligence

### 17. Waitlist priority (ab automatic score se)
**Kahan milega:** Front-desk/Admin → **Waitlist**
**Test:** Kisi patient ko waitlist me add karein bina "Priority override" bhare — automatically ek score (0-100) assign hoga based on unki visit history/attendance/referrals. Priority badge pe hover karein → reason dikhega ("Based on 3 completed visits, no no-shows...").

### 18. Controlled overbooking
Phase 3 me cover kiya (Services form ka hissa hai).

### 19. Referral analytics
**Kahan milega:** Referrals page (Phase 1 wala) — ab top me "Total referrals / Converted / Conversion rate" stats aur "Top referrers" table bhi dikhta hai.

### 20. Revenue-leak flags
**Kahan milega:** Admin → **Reports** → "💡 Revenue & patient intelligence" section → "Flagged patterns"
**Kya dikhata hai:** Jaise "Tuesday 4pm slots cancelled 60% of the time" — plain-language warnings, koi automatic action nahi hoti, sirf review ke liye.
**Note:** Naye/demo database me shayad koi flag na dikhe kyunki isko meaningful pattern dikhne ke liye kaafi historical data chahiye (kam se kam 3+ appointments ek hi slot pattern me).

### 21. Clinic benchmarking
**Kahan milega:** Reports page → "How you compare to similar clinics"
**Note:** Abhi sirf 1 active clinic hai database me, isliye ye honestly bolega "Not enough other active clinics yet to benchmark against" — jab 2+ clinics active hongi, real comparison dikhega.

### 22. Provider utilization heatmap
**Kahan milega:** Reports page → "Provider utilization by day & hour" — ek table jisme har provider ke liye har din/ghante ka booking count color-coded hai (darker = busier).

### 23. A/B testing (booking page copy)
**Kahan milega:** Har patient ko booking page pe automatically 2 me se ek intro-line version dikhta hai (consistent — same patient hamesha same version dekhega)
**Test:** 2-3 alag patient accounts se login karke booking page kholein — kuch ko ek wording dikhegi, kuch ko dusri
**Results kahan dekhein:** Reports page → "A/B tests" section — kitne assigned hue, kitne ne actually book kiya, conversion rate % — jab tak koi booking nahi hui hoti, ye section khaali rahega

### 24. Personalized reschedule suggestions
**Kahan milega:** Kisi appointment ka detail page kholein (Appointments → koi appointment click karein) → right side "Suggested reschedule times" card
**Kya karta hai:** Patient ki purani booking history dekh ke unke pasandida time (e.g. hamesha subah book karte hain) ke hisaab se 3 best alternative slots dikhata hai
**Test:** "Use this time" button dabayein → confirm karein → appointment reschedule ho jayegi

### 25. Schedule optimization suggestions
**Kahan milega:** Admin → Providers → kisi provider ki "Availability" edit karein
**Kya dikhata hai:** Agar koi din consistently under-booked hai aur doosra din over-capacity hai, ek suggestion box dikhega
**Note:** Isko dikhne ke liye bhi kaafi historical booking data chahiye — naye database me shayad khaali rahe

---

## Aapki taraf se kya baaki hai (credentials/integration)

Maine `.env` file check ki — abhi **koi bhi external service configure nahi hai**, sab kuch safe "log"/"manual" mode me hai (matlab sab kuch app ke andar hi kaam karta hai, bas real WhatsApp/SMS/payment nahi jaata). Yahan wo cheezein hain jo aapko khud karni hongi:

### 1. WhatsApp (Gupshup) — reminders, OTP login, retention messages ke liye
**Update:** Ye ab `.env` se nahi, **Settings → Integrations** page se hota hai — aur ab **per-clinic** hai (pehle galti se sabhi clinics ke liye ek hi shared credential use ho raha tha; ab har clinic apni khud ki credentials save karta hai). Clinic admin login karke apne clinic ke liye ye bharega; system admin ke liye page ke top pe ek clinic-picker dropdown hai jisse kisi bhi clinic ki settings dekh/edit kar sakte hain.

Settings → Integrations → **WhatsApp** section me bharein:
- Driver: **Gupshup**
- Gupshup source number (jaise 919876543210)
- Gupshup app name
- Gupshup API key
- Inbound webhook secret (koi random string)

Save karne ke baad page par hi ready-to-paste webhook URL dikh jaayega (`.../webhooks/gupshup?token=...`) — wahi Gupshup dashboard me daalna hai.

### 2. SMS (Gupshup) — SMS booking, missed-call text-back ke liye
Same page, **SMS** section me: driver **Gupshup**, SMS sender ID, API key, inbound webhook secret, missed-call webhook secret — sab UI se hi.

**Important:** Maine Gupshup SMS API ka endpoint apni knowledge se likha hai (`api.gupshup.io/sm/api/v1/msg`) kyunki maine ye live test nahi kar saka (real credentials nahi the). Jab aap real keys lagayein, ek test SMS bhejke confirm kar lena ki ye kaam kar raha hai — agar error aaye to Gupshup ke current SMS API docs se endpoint match karke bata dena, main turant fix kar dunga.

**Missed-call ke liye ek aur cheez chahiye:** aapki telephony/call-forwarding service (jo bhi aap use karte hain — Exotel, Knowlarity, ya koi aur) ka "missed call" webhook, page par diya gaya URL (`.../webhooks/missed-call?token=<secret>`) pe point karna hoga.

### 3. Payments (Razorpay) — real card payments ke liye
Abhi "manual" driver hai (staff cash/desk pe collect karke "Mark collected" dabate hain) — ye already fully kaam karta hai, koi credential nahi chahiye.

**Update:** Payment gateway ab **Stripe ki jagah Razorpay** hai (India-focused, INR me). Settings → Integrations → **Payments** section me bharein:
- Driver: **Razorpay**
- Razorpay Key ID
- Razorpay Key Secret
- Razorpay webhook signing secret

Save karne ke baad page par hi Razorpay ka webhook URL dikh jaayega (`.../webhooks/razorpay`) — wahi Razorpay dashboard (Settings → Webhooks) me add karke `payment.captured` aur `payment.failed` events select karne hain.

**Note:** Backend (Order creation, webhook verification, refunds) ready hai, lekin actual card-entry checkout page (Razorpay Checkout.js popup) abhi nahi bana hai — wo agla step hoga jab aap real jaana chahenge.

### 4. Legal/Compliance
- **HIPAA (US) / DPDP (India) agreements** — ye actual legal documents hain jo aapko khud sign karne honge (lawyer/compliance team ke saath). System sirf ek checkbox rakhta hai jo track karta hai ki sign ho chuka hai ya nahi — jab tak check nahi karenge, clinic activate nahi hogi.
- **ABDM (India health ID)** — agar aap India me hain aur ABDM se connect karna chahte hain, pehle khud government ke ABDM portal pe apni clinic register karni hogi. Uske baad mila hua Health ID system me ek field me daal sakte hain (Clinics → edit → "ABDM health ID").

### 5. Jo bilkul nahi bana (partner integration chahiye, is round me scope se bahar)
- **Google "Reserve with Google"** — Google Business Profile ke saath partnership chahiye
- **Social media (Instagram/Facebook) booking** — Meta Business ke saath partnership chahiye
- **Automated phone tree / AI voice assistant** — koi telephony+speech vendor (jaise Twilio Voice, Exotel IVR) chahiye
- **Single Sign-On (SSO)** — agar aapke paas already koi identity provider hai (Google Workspace, Microsoft 365 etc.) to bataiye, wire kar dunga
- **White-label branding per clinic** — abhi ek hi branding hai; agar multiple clinics ko alag-alag look chahiye to ye ek naya feature banana padega

---

## Quick health-check command

Sab kuch sahi se chal raha hai ya nahi, ye check karne ke liye:
```
php artisan test
```
Agar `44 passed` dikhe, matlab core system healthy hai.
