import './bootstrap';

// Clipboard functionality
document.addEventListener('DOMContentLoaded', function() {
    // Listen for clipboard copy events
    document.addEventListener('click', function(e) {
        if (e.target.closest('[data-clipboard-action]')) {
            e.preventDefault();
            const action = e.target.closest('[data-clipboard-action]');
            const text = action.getAttribute('data-clipboard-text');
            
            if (text) {
                copyToClipboard(text);
            }
        }
    });
});

// Function to copy text to clipboard
async function copyToClipboard(text) {
    try {
        await navigator.clipboard.writeText(text);
        console.log('Text copied to clipboard:', text);
    } catch (err) {
        console.error('Failed to copy text: ', err);
        // Fallback for older browsers
        fallbackCopyTextToClipboard(text);
    }
}

// Fallback method for older browsers
function fallbackCopyTextToClipboard(text) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.top = '0';
    textArea.style.left = '0';
    textArea.style.position = 'fixed';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        const successful = document.execCommand('copy');
        if (successful) {
            console.log('Text copied to clipboard (fallback):', text);
        }
    } catch (err) {
        console.error('Fallback copy failed: ', err);
    }
    
    document.body.removeChild(textArea);
}
