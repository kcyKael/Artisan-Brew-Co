<?php

namespace Tests\Unit;

use App\Support\BusinessTime;
use App\Support\Money;
use Tests\TestCase;

class MoneyAndBusinessTimeTest extends TestCase
{
    public function test_centavo_conversions_and_formatting(): void
    {
        $this->assertSame(15550, Money::toCentavos('155.50'));
        $this->assertSame(15000, Money::toCentavos('150'));
        $this->assertSame('155.50', Money::fromCentavos(15550));
        $this->assertSame('₱1,250.75', Money::formatCentavos(125075));
    }

    public function test_manila_date_range_converts_accurately_to_utc_bounds(): void
    {
        [$startUtc, $endUtc] = BusinessTime::utcBoundsForManilaRange('2026-11-27', '2026-11-27');

        $this->assertSame('2026-11-26 16:00:00', $startUtc->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-27 15:59:59', $endUtc->format('Y-m-d H:i:s'));
    }
}
