<div class="space-y-4">
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 border">
        <div class="prose prose-sm max-w-none dark:prose-invert">
            @php
                $state = $getState();
                $content = is_array($state) ? implode(', ', $state) : (string) $state;
            @endphp
            @if($content && $content !== '')
                {!! nl2br(e($content)) !!}
            @else
                <p class="text-gray-500 dark:text-gray-400 italic">Select a template to see the preview...</p>
            @endif
        </div>
    </div>
    
    @if($content && $content !== '')
        <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
            <span>Message length: {{ strlen($content) }} characters</span>
            <span>Words: {{ str_word_count(strip_tags($content)) }}</span>
        </div>
    @endif
</div> 