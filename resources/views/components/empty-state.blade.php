@props(['icon' => 'fi-rr-inbox', 'title' => 'Nothing here yet', 'description' => null, 'colspan' => null])
@if($colspan)
  <tr>
    <td colspan="{{ $colspan }}" class="p-0 border-0">
      <div class="sas-empty-state">
        <div class="sas-empty-state__ring"><i class="fi {{ $icon }}"></i></div>
        <strong>{{ $title }}</strong>
        @if($description)<span class="small">{{ $description }}</span>@endif
        @if($slot->isNotEmpty())<div class="mt-3">{{ $slot }}</div>@endif
      </div>
    </td>
  </tr>
@else
  <div class="sas-empty-state">
    <div class="sas-empty-state__ring"><i class="fi {{ $icon }}"></i></div>
    <strong>{{ $title }}</strong>
    @if($description)<span class="small">{{ $description }}</span>@endif
    @isset($slot)<div class="mt-3">{{ $slot }}</div>@endisset
  </div>
@endif
