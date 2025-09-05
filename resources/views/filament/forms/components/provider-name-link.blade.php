<div class="leading-tight">
    @if($providerId)
        <a href="{{ route('filament.admin.resources.providers.overview', $providerId) }}" 
           class="fi-link text-primary-600 hover:text-primary-700 hover:underline font-bold text-sm"
           target="_blank">
            {{ $providerName }}
        </a>
    @else
        <span class="text-sm font-bold text-primary-600">{{ $providerName }}</span>
    @endif
</div>
