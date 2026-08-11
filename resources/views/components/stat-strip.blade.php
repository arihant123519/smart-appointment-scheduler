{{-- A row of KPIs as one card divided by hairlines, instead of separate
     cards side by side. Usage:
       <x-stat-strip>
         <x-stat-strip-item label="Total waiting" :value="$n" caption="Patients" icon="fi-rr-users-alt" bg="bg-primary-subtle" fg="text-primary" />
         ...
       </x-stat-strip> --}}
<div {{ $attributes->class(['card']) }}>
  <div class="sas-stat-strip__row">
    {{ $slot }}
  </div>
</div>
