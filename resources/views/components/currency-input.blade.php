@props([
    'model',
    'placeholder' => 'Rp 0',
])

<div
    x-data="{
        raw: @entangle($model).live,
        display: '',

        init() {
            this.display = this.format(this.raw)
        },

        format(value) {
            if (value === null || value === undefined || value === '') {
                return ''
            }

            return 'Rp ' + Number(value).toLocaleString('id-ID')
        },

        handleInput(event) {
            const value = event.target.value.replace(/\D/g, '')

            this.raw = value ? Number(value) : null
            this.display = this.format(value)
        }
    }"
>
    <input
        type="text"
        {{ $attributes }}
        x-model="display"
        @input="handleInput($event)"
        placeholder="{{ $placeholder }}"
    >
</div>