<div class="leading-tight">
    @if($branchId)
        <a href="{{ route('filament.admin.resources.provider-branches.overview', $branchId) }}" 
           class="fi-link text-primary-600 hover:text-primary-700 hover:underline font-bold text-sm"
           target="_blank">
            {{ $branchName }}
        </a>
    @else
        <span class="text-sm font-bold text-primary-600">{{ $branchName }}</span>
    @endif
</div>
