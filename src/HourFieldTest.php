<?php

declare(strict_types=1);

namespace Bakame\Cron;

use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Bakame\Cron\HourField
 */
final class HourFieldTest extends TestCase
{
    /**
     * @dataProvider validFieldExpression
     */
    public function testValidatesField(string $expression, string $json): void
    {
        $f = new HourField($expression);

        self::assertSame($expression, $f->toString());
        self::assertSame($json, json_encode($f));
    }

    /**
     * @return array<array<string>>
     */
    public function validFieldExpression(): array
    {
        return [
            ['1', '"1"'],
            ['*', '"*"'],
            ['*/3,1,1-12', '"*\/3,1,1-12"'],
            ['5-7,11-13', '"5-7,11-13"'],
        ];
    }

    /**
     * @dataProvider providesDateObjects
     */
    public function testIncrementsDate(DateTimeImmutable|DateTime $d, string $increment, string $decrement): void
    {
        $f = new HourField('*');
        self::assertSame($increment, $f->increment($d)->format('Y-m-d H:i:s'));

        $d = $d->setTime(11, 15, 0);
        self::assertSame($decrement, $f->decrement($d)->format('Y-m-d H:i:s'));
    }

    public function providesDateObjects(): array
    {
        return [
            'DateTime' => [
                'date' => new DateTime('2011-03-15 11:15:00'),
                'increment' => '2011-03-15 12:00:00',
                'decrement' => '2011-03-15 10:59:00',
            ],
            'DateTimeImmutable' => [
                'date' => new DateTimeImmutable('2011-03-15 11:15:00'),
                'increment' => '2011-03-15 12:00:00',
                'decrement' => '2011-03-15 10:59:00',
            ],
        ];
    }

    public function testIncrementsDateWithThirtyMinuteOffsetTimezone(): void
    {
        $tz = date_default_timezone_get();
        date_default_timezone_set('America/St_Johns');
        $d = new DateTime('2011-03-15 11:15:00');
        $f = new HourField('*');
        self::assertSame('2011-03-15 12:00:00', $f->increment($d)->format('Y-m-d H:i:s'));

        $d->setTime(11, 15);
        self::assertSame('2011-03-15 10:59:00', $f->decrement($d)->format('Y-m-d H:i:s'));
        date_default_timezone_set($tz);
    }

    public function testIncrementDateWithFifteenMinuteOffsetTimezone(): void
    {
        $tz = date_default_timezone_get();
        date_default_timezone_set('Asia/Kathmandu');
        $d = new DateTime('2011-03-15 11:15:00');
        $f = new HourField('*');
        self::assertSame('2011-03-15 12:00:00', $f->increment($d)->format('Y-m-d H:i:s'));

        $d->setTime(11, 15, 0);
        self::assertSame('2011-03-15 10:59:00', $f->decrement($d)->format('Y-m-d H:i:s'));
        date_default_timezone_set($tz);
    }
}
