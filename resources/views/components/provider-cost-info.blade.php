<div class="space-y-3">
    <div class="text-sm text-gray-600">
        <strong>{{ $branches->count() }} active provider(s) found</strong>
    </div>
    
    <div class="grid grid-cols-2 gap-2 text-xs">
        <div class="bg-blue-50 p-2 rounded">
            <div class="font-semibold text-blue-800">Day Cost</div>
            <div class="text-blue-600">€{{ number_format($avgDayCost, 2) }}</div>
        </div>
        
        <div class="bg-green-50 p-2 rounded">
            <div class="font-semibold text-green-800">Weekend Cost</div>
            <div class="text-green-600">€{{ number_format($avgWeekendCost, 2) }}</div>
        </div>
        
        <div class="bg-purple-50 p-2 rounded">
            <div class="font-semibold text-purple-800">Night Weekday</div>
            <div class="text-purple-600">€{{ number_format($avgNightCost, 2) }}</div>
        </div>
        
        <div class="bg-orange-50 p-2 rounded">
            <div class="font-semibold text-orange-800">Night Weekend</div>
            <div class="text-orange-600">€{{ number_format($avgWeekendNightCost, 2) }}</div>
        </div>
    </div>
    
    @if($branches->count() <= 5)
        <div class="mt-3">
            <div class="text-xs font-semibold text-gray-700 mb-1">Provider Details:</div>
            <div class="space-y-1">
                @foreach($branches as $branch)
                    <div class="text-xs text-gray-600">
                        <span class="font-medium">{{ $branch->provider->name ?? 'N/A' }}</span> - 
                        <span>{{ $branch->branch_name }}</span>
                        @if($branch->day_cost)
                            <span class="text-gray-500">(€{{ number_format($branch->day_cost, 2) }})</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="mt-2 text-xs text-gray-500">
            Showing average costs from {{ $branches->count() }} providers
        </div>
    @endif
</div> 