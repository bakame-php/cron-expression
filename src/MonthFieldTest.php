<?php

declare(strict_types=1);

namespace Bakame\Cron;

use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Bakame\Cron\MonthField
 */
final class MonthFieldTest extends TestCase
{
    /**
     * @dataProvider validFieldExpression
     */
    public function testValidatesField(string $expression): void
    {
        $f = new MonthField($expression);

        self::assertSame($expression, $f->toString());
    }

    /**
     * @return array<array<string>>
     */
    public function validFieldExpression(): array
    {
        return [
            ['12'],
            ['*'],
            ['*/3,1,1-12'],
        ];
    }

    public function testFailingValidation(): void
    {
        $this->expectException(SyntaxError::class);

        new MonthField('1.fix-regexp');
    }

    public function testIncrementsDate(): void
    {
        $f = new MonthField('*');

        $d = new DateTime('2011-03-15 11:15:00');
        self::assertSame('2011-04-01 00:00:00', $f->increment($d)->format('Y-m-d H:i:s'));

        $d = new DateTime('2011-03-15 11:15:00');
        self::assertSame('2011-02-28 23:59:00', $f->decrement($d)->format('Y-m-d H:i:s'));
    }

    public function testIncrementsDateImmutable(): void
    {
        $f = new MonthField('*');

        $d = new DateTimeImmutable('2011-03-15 11:15:00');
        self::assertSame('2011-04-01 00:00:00', $f->increment($d)->format('Y-m-d H:i:s'));

        $d = new DateTimeImmutable('2011-03-15 11:15:00');
        self::assertSame('2011-02-28 23:59:00', $f->decrement($d)->format('Y-m-d H:i:s'));
    }

    public function testIncrementsDateWithThirtyMinuteTimezone(): void
    {
        $tz = date_default_timezone_get();
        date_default_timezone_set('America/St_Johns');
        $d = new DateTime('2011-03-31 11:59:59');
        $f = new MonthField('*');
        self::assertSame('2011-04-01 00:00:00', $f->increment($d)->format('Y-m-d H:i:s'));

        $d = new DateTime('2011-03-15 11:15:00');
        self::assertSame('2011-02-28 23:59:00', $f->decrement($d)->format('Y-m-d H:i:s'));
        date_default_timezone_set($tz);
    }

    public function testIncrementsYearAsNeeded(): void
    {
        $f = new MonthField('*');
        $d = new DateTime('2011-12-15 00:00:00');
        self::assertSame('2012-01-01 00:00:00', $f->increment($d)->format('Y-m-d H:i:s'));
    }

    public function testDecrementsYearAsNeeded(): void
    {
        $f = new MonthField('*');
        $d = new DateTime('2011-01-15 00:00:00');
        self::assertSame('2010-12-31 23:59:00', $f->decrement($d)->format('Y-m-d H:i:s'));
    }
}
