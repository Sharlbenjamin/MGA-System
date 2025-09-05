<div class="leading-tight">
    @if($branchId)
        <a href="{{ route('filament.admin.resources.provider-branches.overview', $branchId) }}" 
           class="text-blue-600 hover:text-blue-800 hover:underline font-medium text-sm"
           target="_blank">
            {{ $branchName }}
        </a>
    @else
        <span class="text-sm">{{ $branchName }}</span>
    @endif
</div>
