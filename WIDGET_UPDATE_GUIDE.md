# Dashboard Widget Update Guide

## Overview
The dashboard now has a new filtering system with two dropdowns:
1. **Duration**: Month or Year
2. **Period**: Specific month/year selection

All widgets should be updated to use this new filtering system and show comparison data with the previous period.

## Files Updated
✅ `FileStatsOverview.php` - Updated with comparison functionality
✅ `MonthlyProfit.php` - Updated to use new filters
✅ `TotalFile.php` - Updated to use new filters
✅ `FilesPerMonth.php` - Updated to use new filters
✅ `DashboardFilterWidget.php` - New filter widget
✅ `HasDashboardFilters.php` - New trait for filtering functionality

## Remaining Widgets to Update
- `CasesPerMonthStatus.php`
- `FilesPerClient.php`
- `FilesPerCountry.php`
- `FilesPerServiceType.php`
- `FilesPerStatus.php`
- `TaxSummaryWidget.php`
- `NotificationsWidget.php`

## Update Pattern for Chart Widgets

### 1. Add the trait
```php
use App\Filament\Widgets\Traits\HasDashboardFilters;

class YourWidget extends ChartWidget
{
    use HasDashboardFilters;
    // ... rest of the class
}
```

### 2. Update getData() method
```php
protected function getData(): array
{
    $filters = $this->getDashboardFilters();
    $dateRange = $this->getDateRange();
    
    if ($filters['duration'] === 'Month') {
        // For monthly view, show daily data
        $data = YourModel::whereBetween('created_at', [
            $dateRange['current']['start'],
            $dateRange['current']['end']
        ])
        ->selectRaw('COUNT(*) as count, DATE(created_at) as day')
        ->groupBy('day')
        ->orderBy('day')
        ->get();

        $labels = [];
        $counts = [];
        
        $currentDate = $dateRange['current']['start']->copy();
        $endDate = $dateRange['current']['end'];
        
        while ($currentDate <= $endDate) {
            $labels[] = $currentDate->format('M d');
            $dayData = $data->where('day', $currentDate->format('Y-m-d'))->first();
            $counts[] = $dayData ? $dayData->count : 0;
            $currentDate->addDay();
        }
    } else {
        // For yearly view, show monthly data
        $data = YourModel::whereBetween('created_at', [
            $dateRange['current']['start'],
            $dateRange['current']['end']
        ])
        ->selectRaw('COUNT(*) as count, DATE_FORMAT(created_at, "%Y-%m") as month')
        ->groupBy('month')
        ->orderBy('month')
        ->get();

        $labels = [];
        $counts = [];
        
        $currentMonth = $dateRange['current']['start']->copy();
        $endMonth = $dateRange['current']['end'];
        
        while ($currentMonth <= $endMonth) {
            $labels[] = $currentMonth->format('M Y');
            $monthData = $data->where('month', $currentMonth->format('Y-m'))->first();
            $counts[] = $monthData ? $monthData->count : 0;
            $currentMonth->addMonth();
        }
    }

    return [
        'datasets' => [
            [
                'label' => 'Your Label',
                'data' => $counts,
                'backgroundColor' => '#197070',
                'borderColor' => '#197070',
                'tension' => 0.3,
            ],
        ],
        'labels' => $labels,
    ];
}
```

### 3. Add getOptions() method (if not exists)
```php
protected function getOptions(): array
{
    return [
        'scales' => [
            'y' => [
                'beginAtZero' => true,
            ],
        ],
        'plugins' => [
            'legend' => [
                'display' => false,
            ],
        ],
    ];
}
```

## Update Pattern for Stats Widgets

### 1. Add the trait
```php
use App\Filament\Widgets\Traits\HasDashboardFilters;

class YourStatsWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters, HasDashboardFilters;
    // ... rest of the class
}
```

### 2. Update getStats() method
```php
protected function getStats(): array
{
    $filters = $this->getDashboardFilters();
    $dateRange = $this->getDateRange();
    
    // Current period calculation
    $currentValue = YourModel::whereBetween('created_at', [
        $dateRange['current']['start'],
        $dateRange['current']['end']
    ])->count();
    
    // Previous period calculation
    $previousValue = YourModel::whereBetween('created_at', [
        $dateRange['previous']['start'],
        $dateRange['previous']['end']
    ])->count();
    
    // Calculate comparison
    $comparison = $this->calculateComparison($currentValue, $previousValue);
    
    $periodLabel = $filters['duration'] === 'Month' ? 'Month' : 'Year';
    
    return [
        Stat::make("Your Stat this {$periodLabel}", $currentValue)
            ->description($this->formatComparisonDescription($comparison))
            ->descriptionIcon($comparison['trend'] === 'up' ? 'heroicon-m-arrow-trending-up' : ($comparison['trend'] === 'down' ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-minus'))
            ->color($this->getComparisonColor($comparison)),
    ];
}
```

## Features Added

### 1. Filter Dropdowns
- **Duration**: Month or Year selection
- **Period**: Specific month/year selection (last 2 years + next year)

### 2. Comparison Functionality
- All stats now show comparison with previous period
- Visual indicators (arrows) showing trend direction
- Color coding (green for up, red for down, gray for neutral)
- Percentage change calculation

### 3. Responsive Charts
- Monthly view shows daily data
- Yearly view shows monthly data
- Charts update automatically when filters change

### 4. Session Persistence
- Filter selections are stored in session
- Widgets remember the last selected filters
- Consistent state across page refreshes

## Testing
1. Go to the dashboard
2. You should see the filter dropdowns at the top
3. Change the duration and period
4. All widgets should update automatically
5. Stats should show comparison data with previous period
6. Charts should show data for the selected period 