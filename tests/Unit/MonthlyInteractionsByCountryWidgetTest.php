<?php

namespace Tests\Unit;

use App\Filament\Resources\ProviderLeadResource\Widgets\MonthlyInteractionsByCountryWidget;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class MonthlyInteractionsByCountryWidgetTest extends TestCase
{
    public function test_monthly_interactions_widget_uses_five_columns(): void
    {
        $widget = (new ReflectionClass(MonthlyInteractionsByCountryWidget::class))->newInstanceWithoutConstructor();

        $columns = new ReflectionMethod(MonthlyInteractionsByCountryWidget::class, 'getColumns');
        $columns->setAccessible(true);

        $this->assertSame(5, $columns->invoke($widget));
    }

    public function test_france_is_listed_first_in_country_stats_labels(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2).'/app/Filament/Resources/ProviderLeadResource/Widgets/MonthlyInteractionsByCountryWidget.php'
        );

        $this->assertNotFalse($source);
        $francePos = strpos($source, "countryStat('France'");
        $ukPos = strpos($source, "countryStat('UK & Ireland'");

        $this->assertNotFalse($francePos);
        $this->assertNotFalse($ukPos);
        $this->assertLessThan($ukPos, $francePos);
    }
}
