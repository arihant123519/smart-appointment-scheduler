<!DOCTYPE html>
{{-- dompdf has limited CSS support (no flexbox/grid) — this template intentionally
     sticks to tables/floats/borders only. --}}
<html>
<head>
  <meta charset="utf-8">
  <style>
    @page { margin: 100px 40px 90px 40px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #222; }
    .header { border-bottom: 2px solid {{ $clinic->primary_color ?: '#2563EB' }}; padding-bottom: 8px; margin-bottom: 12px; }
    .header table { width: 100%; }
    .header .logo { width: 64px; height: 64px; }
    .clinic-name { font-size: 18px; font-weight: bold; color: {{ $clinic->primary_color ?: '#2563EB' }}; }
    .clinic-meta { font-size: 10px; color: #555; }
    .tagline { font-size: 10px; font-style: italic; color: #555; }
    .meta-table { width: 100%; margin-bottom: 10px; }
    .meta-table td { vertical-align: top; padding: 2px 0; font-size: 11px; }
    .label { color: #777; font-size: 9px; text-transform: uppercase; }
    .rx-mark { font-size: 20px; font-weight: bold; margin: 14px 0 6px 0; }
    table.items { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
    table.items th, table.items td { border: 1px solid #ccc; padding: 5px 6px; font-size: 11px; text-align: left; }
    table.items th { background: #f2f4f8; }
    .section-title { font-size: 11px; font-weight: bold; margin: 10px 0 3px 0; color: #444; }
    .section-body { font-size: 11px; margin-bottom: 8px; white-space: pre-wrap; }
    .footer { position: fixed; bottom: -70px; left: 0; right: 0; height: 70px; border-top: 1px solid #ccc; padding-top: 6px; font-size: 9px; color: #666; }
    .signature-block { text-align: right; }
    .signature-img { height: 40px; }
    .doctor-name { font-weight: bold; font-size: 11px; color: #222; }
  </style>
</head>
<body>

  <div class="header">
    <table>
      <tr>
        <td style="width: 70px;">
          @if ($logoDataUri)
            <img src="{{ $logoDataUri }}" class="logo">
          @endif
        </td>
        <td>
          <div class="clinic-name">{{ $clinic->name }}</div>
          <div class="clinic-meta">
            {{ collect([$clinic->address, $clinic->city, $clinic->state])->filter()->implode(', ') }}
            @if ($clinic->phone) &middot; {{ $clinic->phone }} @endif
          </div>
          @if ($clinic->prescription_header_note)
            <div class="tagline">{{ $clinic->prescription_header_note }}</div>
          @endif
        </td>
        <td style="width: 160px; text-align: right; font-size: 11px;">
          <div class="doctor-name">{{ $provider->name }}</div>
          <div class="clinic-meta">
            {{ $provider->credentials }}
            @if ($provider->specialty) &middot; {{ $provider->specialty }} @endif
          </div>
          @if ($provider->registration_no)
            <div class="clinic-meta">Reg. No: {{ $provider->registration_no }}</div>
          @endif
        </td>
      </tr>
    </table>
  </div>

  <table class="meta-table">
    <tr>
      <td style="width: 60%;">
        <div class="label">Patient</div>
        {{ $prescription->patient->name }}
        @if ($prescription->patient->date_of_birth) &middot; {{ $prescription->patient->date_of_birth->age }} yrs @endif
        @if ($prescription->patient->gender) &middot; {{ ucfirst($prescription->patient->gender) }} @endif
      </td>
      <td style="width: 40%; text-align: right;">
        <div class="label">Date</div>
        {{ ($prescription->issued_at ?? $appointment->start_at)->format('F j, Y') }}
      </td>
    </tr>
  </table>

  @if ($consultation?->diagnosis)
    <div class="section-title">Diagnosis</div>
    <div class="section-body">{{ $consultation->diagnosis }}</div>
  @endif

  <div class="rx-mark">Rx</div>

  @if ($prescription->items->isNotEmpty())
    <table class="items">
      <thead>
        <tr>
          <th style="width: 26%;">Medicine</th>
          <th style="width: 16%;">Dosage</th>
          <th style="width: 18%;">Frequency</th>
          <th style="width: 16%;">Duration</th>
          <th style="width: 24%;">Instructions</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($prescription->items as $item)
          <tr>
            <td>{{ $item->medicine_name }}</td>
            <td>{{ $item->dosage }}</td>
            <td>{{ $item->frequency }}</td>
            <td>{{ $item->duration }}</td>
            <td>{{ $item->instructions }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @else
    <p class="section-body">No medicines listed.</p>
  @endif

  @if ($prescription->notes)
    <div class="section-title">Advice</div>
    <div class="section-body">{{ $prescription->notes }}</div>
  @endif

  @if ($consultation?->follow_up_date || $consultation?->follow_up_instructions)
    <div class="section-title">Follow-up</div>
    <div class="section-body">
      @if ($consultation->follow_up_date) {{ $consultation->follow_up_date->format('F j, Y') }} @endif
      @if ($consultation->follow_up_instructions) — {{ $consultation->follow_up_instructions }} @endif
    </div>
  @endif

  <div class="signature-block" style="margin-top: 30px;">
    @if ($signatureDataUri)
      <img src="{{ $signatureDataUri }}" class="signature-img"><br>
    @endif
    <div class="doctor-name">{{ $provider->name }}</div>
    <div class="clinic-meta">{{ $provider->credentials }}</div>
  </div>

  <div class="footer">
    {{ $clinic->prescription_footer_text ?: 'This is a system-generated prescription.' }}
  </div>

</body>
</html>
