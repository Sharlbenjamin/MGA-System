<div class="leading-tight" x-data="{ copied: false }">
    <button 
        type="button"
        @click="
            const text = @js($appointmentText);
            const normalizedText = text.replace(/\r?\n/g, '\r\n');

            const fallbackCopy = (value) => {
                const textarea = document.createElement('textarea');
                textarea.value = value;
                textarea.style.position = 'fixed';
                textarea.style.top = '50%';
                textarea.style.left = '50%';
                textarea.style.transform = 'translate(-50%, -50%)';
                textarea.style.width = '1px';
                textarea.style.height = '1px';
                textarea.style.opacity = '0.01';
                textarea.style.zIndex = '9999';
                document.body.appendChild(textarea);
                textarea.focus();
                textarea.select();
                textarea.setSelectionRange(0, textarea.value.length);
                const copiedUsingFallback = document.execCommand('copy');
                document.body.removeChild(textarea);
                return copiedUsingFallback;
            };

            const copyToClipboard = async () => {
                try {
                    if (navigator.clipboard && window.isSecureContext) {
                        await navigator.clipboard.writeText(normalizedText);
                    } else {
                        fallbackCopy(normalizedText);
                    }
                    copied = true;
                    setTimeout(() => copied = false, 2000);
                    // Call Livewire method if available
                    if (typeof $wire !== 'undefined') {
                        $wire.copyToClipboard(normalizedText, 'Appointment Request');
                    }
                } catch (err) {
                    const copiedUsingFallback = fallbackCopy(normalizedText);
                    if (copiedUsingFallback) {
                        copied = true;
                        setTimeout(() => copied = false, 2000);
                        if (typeof $wire !== 'undefined') {
                            $wire.copyToClipboard(normalizedText, 'Appointment Request');
                        }
                    } else {
                        console.error('Failed to copy:', err);
                    }
                }
            };
            copyToClipboard();
        "
        :class="copied ? 'text-success-600 font-semibold' : 'text-primary-600 hover:text-primary-700 hover:underline'"
        class="cursor-pointer text-sm font-medium"
        title="Click to copy appointment details">
        <span x-text="copied ? 'Copied!' : 'Request'"></span>
    </button>
</div>
