<div class="leading-tight">
    @if($providerId)
        <a href="{{ route('filament.admin.resources.providers.overview', $providerId) }}" 
           class="text-blue-600 hover:text-blue-800 hover:underline font-medium text-sm"
           target="_blank">
            {{ $providerName }}
        </a>
    @else
        <span class="text-sm">{{ $providerName }}</span>
    @endif
</div>
