<x-filament-panels::page>
    @if(method_exists($this, 'table'))
        {{ $this->table }}
    @elseif(method_exists($this, 'form'))
        {{ $this->form }}
    @endif
</x-filament-panels::page>
