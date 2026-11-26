<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;

final class BusinessTime
{
    public static function timezone(): string
    {
        return (string) config('pos.timezone', 'Asia/Manila');
    }

    public static function nowManila(): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezone());
    }

    public static function toManila(DateTimeInterface|string|null $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value->toDateTimeImmutable())->setTimezone(self::timezone());
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->setTimezone(self::timezone());
        }

        return CarbonImmutable::parse($value, 'UTC')->setTimezone(self::timezone());
    }

    public static function format(DateTimeInterface|string|null $value, string $format = 'M j, Y g:i A'): string
    {
        $manila = self::toManila($value);

        return $manila ? $manila->format($format) : '—';
    }

    public static function utcBoundsForManilaRange(string $fromDate, string $toDate): array
    {
        $tz = self::timezone();
        $startManila = CarbonImmutable::createFromFormat('Y-m-d', $fromDate, $tz)->startOfDay();
        $endManila = CarbonImmutable::createFromFormat('Y-m-d', $toDate, $tz)->endOfDay();

        return [$startManila->setTimezone('UTC'), $endManila->setTimezone('UTC')];
    }

    public static function utcBoundsForToday(): array
    {
        $today = self::nowManila()->toDateString();

        return self::utcBoundsForManilaRange($today, $today);
    }
}
