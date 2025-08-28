<input type="checkbox" 
       wire:click="toggleBranch({{ $recordId }})"
       @if($checked) checked @endif
       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
