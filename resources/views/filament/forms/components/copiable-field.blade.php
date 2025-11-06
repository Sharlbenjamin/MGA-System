<div class="leading-tight" x-data="{ copied: false, value: @js($value), label: @js($label) }">
    @if($value && $value !== 'N/A')
        <button 
            type="button"
            @click="
                navigator.clipboard.writeText(value).then(() => {
                    copied = true;
                    setTimeout(() => copied = false, 1000);
                }).catch(err => console.error('Failed to copy:', err));
            "
            :class="copied ? 'text-success-600' : 'fi-link text-primary-600 hover:text-primary-700 hover:underline'"
            class="cursor-pointer text-sm"
            :title="'Click to copy: ' + value">
            <span x-text="copied ? 'Copied!' : label"></span>
        </button>
    @else
        <span class="text-sm text-gray-400">{{ $label }}</span>
    @endif
</div>

