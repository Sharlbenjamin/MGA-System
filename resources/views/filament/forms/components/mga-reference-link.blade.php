@if($file && $url)
    <a href="{{ $url }}" 
       class="text-blue-600 hover:text-blue-800 underline font-medium transition-colors duration-200" 
       target="_blank">
        {{ $file->mga_reference }}
    </a>
@else
    <span class="text-gray-500">No file selected</span>
@endif
