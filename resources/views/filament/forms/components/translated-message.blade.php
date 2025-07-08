<div class="space-y-4">
    <div class="text-center">
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            Translated Message ({{ $languageName }})
        </h3>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            Here's your message translated to {{ $languageName }}:
        </p>
    </div>
    
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 border">
        <div class="prose prose-sm max-w-none dark:prose-invert">
            @if($translatedMessage)
                {!! nl2br(e($translatedMessage)) !!}
            @else
                <p class="text-gray-500 dark:text-gray-400 italic">No message to translate.</p>
            @endif
        </div>
    </div>
    
    @if($translatedMessage)
        <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
            <span>Message length: {{ strlen($translatedMessage) }} characters</span>
            <span>Words: {{ str_word_count(strip_tags($translatedMessage)) }}</span>
        </div>
    @endif
    
    <div class="text-center text-sm text-gray-500 dark:text-gray-400">
        <p>You can copy this message and use it in your communication.</p>
    </div>
</div> 