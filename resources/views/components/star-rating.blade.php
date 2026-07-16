@props(['name' => 'rating', 'id' => null, 'value' => null, 'required' => false])
@php $id = $id ?? $name; @endphp
<div class="sas-star-rating" data-star-rating>
  <div class="sas-star-rating__stars d-inline-flex" role="radiogroup" aria-label="Rating">
    @for ($i = 1; $i <= 5; $i++)
      <button type="button" class="sas-star-rating__star" data-value="{{ $i }}" aria-label="{{ $i }} star{{ $i > 1 ? 's' : '' }}">
        <i class="fi fi-rr-star"></i>
      </button>
    @endfor
  </div>
  <div class="sas-star-rating__label" data-star-label>Tap a star to rate</div>
  <input type="hidden" name="{{ $name }}" id="{{ $id }}" value="{{ $value }}" @if($required) required @endif>
</div>

@once
  @push('scripts')
    <script>
      (function () {
        var labels = ['Tap a star to rate', 'Poor', 'Fair', 'Good', 'Very good', 'Excellent'];

        function paint(root, value) {
          root.querySelectorAll('.sas-star-rating__star').forEach(function (s) {
            s.classList.toggle('is-filled', Number(s.dataset.value) <= value);
          });
          var label = root.querySelector('[data-star-label]');
          label.textContent = labels[value] || labels[0];
          label.classList.toggle('is-set', !!value);
        }

        document.querySelectorAll('[data-star-rating]').forEach(function (root) {
          if (root.dataset.bound) return;
          root.dataset.bound = '1';

          var input = root.querySelector('input[type=hidden]');
          var stars = root.querySelectorAll('.sas-star-rating__star');

          stars.forEach(function (s) {
            s.addEventListener('mouseenter', function () { paint(root, Number(s.dataset.value)); });
            s.addEventListener('focus', function () { paint(root, Number(s.dataset.value)); });
            s.addEventListener('click', function () {
              input.value = s.dataset.value;
              root.classList.add('is-selected');
              paint(root, Number(s.dataset.value));
            });
          });
          root.addEventListener('mouseleave', function () { paint(root, Number(input.value || 0)); });

          paint(root, Number(input.value || 0));
        });
      })();
    </script>
  @endpush
@endonce
