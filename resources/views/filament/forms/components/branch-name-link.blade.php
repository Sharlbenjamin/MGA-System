<div>
    @if($branchId)
        <a href="{{ route('filament.admin.resources.provider-branches.view', $branchId) }}" 
           class="text-blue-600 hover:text-blue-800 hover:underline font-medium"
           target="_blank">
            {{ $branchName }}
        </a>
    @else
        {{ $branchName }}
    @endif
</div>
