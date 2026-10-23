<div class="quantity-control {{ $size ?? 'large' }}" data-quantity-stepper data-max="{{ max(1, $max) }}">
    <button type="button" data-step="-1" @disabled($disabled ?? false) aria-label="Giảm số lượng"><x-icon name="minus" /></button>
    <input type="hidden" name="quantity" value="{{ ($disabled ?? false) ? 0 : 1 }}" data-quantity-input>
    <span data-quantity-label>{{ ($disabled ?? false) ? 0 : 1 }}</span>
    <button type="button" data-step="1" @disabled(($disabled ?? false) || $max <= 1) aria-label="Tăng số lượng"><x-icon name="plus" /></button>
</div>
