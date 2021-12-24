<?php

declare(strict_types=1);

namespace Bakame\Cron\Validator;

use DateTime;
use PHPUnit\Framework\TestCase;

/**
 * @author Michael Dowling <mtdowling@gmail.com>
 */
final class MonthTest extends TestCase
{
    /**
     * @covers \Bakame\Cron\Validator\Month::validate
     */
    public function testValidatesField(): void
    {
        $f = new Month();
        self::assertTrue($f->validate('12'));
        self::assertTrue($f->validate('*'));
        self::assertFalse($f->validate('*/10,2,1-12'));
        self::assertFalse($f->validate('1.fix-regexp'));
    }

    /**
     * @covers \Bakame\Cron\Validator\Month::increment
     */
    public function testIncrementsDate(): void
    {
        $d = new DateTime('2011-03-15 11:15:00');
        $f = new Month();
        self::assertSame('2011-04-01 00:00:00', $f->increment($d)->format('Y-m-d H:i:s'));

        $d = new DateTime('2011-03-15 11:15:00');
        self::assertSame('2011-02-28 23:59:00', $f->increment($d, true)->format('Y-m-d H:i:s'));
    }

    /**
     * @covers \Bakame\Cron\Validator\Month::increment
     */
    public function testIncrementsDateWithThirtyMinuteTimezone(): void
    {
        $tz = date_default_timezone_get();
        date_default_timezone_set('America/St_Johns');
        $d = new DateTime('2011-03-31 11:59:59');
        $f = new Month();
        self::assertSame('2011-04-01 00:00:00', $f->increment($d)->format('Y-m-d H:i:s'));

        $d = new DateTime('2011-03-15 11:15:00');
        self::assertSame('2011-02-28 23:59:00', $f->increment($d, true)->format('Y-m-d H:i:s'));
        date_default_timezone_set($tz);
    }

    /**
     * @covers \Bakame\Cron\Validator\Month::increment
     */
    public function testIncrementsYearAsNeeded(): void
    {
        $f = new Month();
        $d = new DateTime('2011-12-15 00:00:00');
        self::assertSame('2012-01-01 00:00:00', $f->increment($d)->format('Y-m-d H:i:s'));
    }

    /**
     * @covers \Bakame\Cron\Validator\Month::increment
     */
    public function testDecrementsYearAsNeeded(): void
    {
        $f = new Month();
        $d = new DateTime('2011-01-15 00:00:00');
        self::assertSame('2010-12-31 23:59:00', $f->increment($d, true)->format('Y-m-d H:i:s'));
    }
}