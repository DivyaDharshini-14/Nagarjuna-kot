<div class="form-check mb-3">
    <input type="hidden" name="{{ $name }}" value="0">
    <input class="form-check-input" type="checkbox" id="{{ $name }}" name="{{ $name }}" value="1" @checked(old($name, $checked ?? true) == true)>
    <label class="form-check-label" for="{{ $name }}">{{ $label }}</label>
</div>
