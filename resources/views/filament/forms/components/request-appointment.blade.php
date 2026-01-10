<div class="leading-tight" x-data="{ copied: false }">
    <button 
        type="button"
        @click="
            const text = @js($appointmentText);
            const copyToClipboard = async () => {
                try {
                    if (navigator.clipboard && window.isSecureContext) {
                        await navigator.clipboard.writeText(text);
                    } else {
                        // Fallback for older browsers
                        const textarea = document.createElement('textarea');
                        textarea.value = text;
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
                        document.execCommand('copy');
                        document.body.removeChild(textarea);
                    }
                    copied = true;
                    setTimeout(() => copied = false, 2000);
                    // Call Livewire method if available
                    if (typeof $wire !== 'undefined') {
                        $wire.copyToClipboard(text, 'Appointment Request');
                    }
                } catch (err) {
                    console.error('Failed to copy:', err);
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
