<div class="leading-tight">
    @if($value && $value !== 'N/A')
        <button 
            type="button"
            onclick="copyFieldToClipboard(this)"
            data-copy-text="{{ htmlspecialchars($value, ENT_QUOTES, 'UTF-8') }}"
            class="fi-link text-primary-600 hover:text-primary-700 hover:underline cursor-pointer text-sm"
            title="Click to copy: {{ $value }}">
            {{ $label }}
        </button>
        <script>
        if (typeof copyFieldToClipboard === 'undefined') {
            function copyFieldToClipboard(button) {
                const text = button.getAttribute('data-copy-text');
                if (!text || text === 'N/A') {
                    return;
                }
                
                navigator.clipboard.writeText(text).then(function() {
                    const originalText = button.textContent;
                    button.textContent = 'Copied!';
                    button.classList.add('text-success-600');
                    
                    setTimeout(function() {
                        button.textContent = originalText;
                        button.classList.remove('text-success-600');
                    }, 1000);
                }).catch(function(err) {
                    console.error('Failed to copy text:', err);
                });
            }
        }
        </script>
    @else
        <span class="text-sm text-gray-400">{{ $label }}</span>
    @endif
</div>

