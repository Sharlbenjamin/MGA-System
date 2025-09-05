<div class="leading-tight">
    @if($branchId)
        <a href="{{ route('filament.admin.resources.provider-branches.overview', $branchId) }}" 
           class="text-blue-600 hover:text-blue-800 hover:underline font-bold text-sm"
           target="_blank">
            {{ $branchName }}
        </a>
    @else
        <span class="text-sm font-bold text-blue-600">{{ $branchName }}</span>
    @endif
</div>
