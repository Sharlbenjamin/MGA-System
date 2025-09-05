<div>
    @if($providerId)
        <a href="{{ route('filament.admin.resources.providers.view', $providerId) }}" 
           class="text-blue-600 hover:text-blue-800 hover:underline font-medium"
           target="_blank">
            {{ $providerName }}
        </a>
    @else
        {{ $providerName }}
    @endif
</div>
