<?php

declare(strict_types=1);

namespace Bakame\Cron;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Bakame\Cron\DayOfMonthField
 */
final class DayOfMonthFieldTest extends TestCase
{
    /**
     * @dataProvider validFieldExpression
     */
    public function testValidatesField(string $expression): void
    {
        $f = new DayOfMonthField($expression);

        self::assertSame($expression, $f->toString());
    }

    /**
     * @return array<array<string>>
     */
    public function validFieldExpression(): array
    {
        return [
            ['1'],
            ['*'],
            ['L'],
            ['5W'],
        ];
    }

    public function testFailingValidation(): void
    {
        $this->expectException(SyntaxError::class);

        new MonthField('1.fix-regexp');
    }

    public function testChecksIfSatisfied(): void
    {
        $f = new DayOfMonthField('?');
        self::assertTrue($f->isSatisfiedBy(new DateTime()));
    }

    public function testIncrementsDate(): void
    {
        $f = new DayOfMonthField('*');
        $date = '2011-03-15 11:15:00';
        self::assertSame(
            '2011-03-16 00:00:00',
            $f->increment(new DateTime($date))->format('Y-m-d H:i:s')
        );

        $date = '2011-03-15 11:15:00';
        self::assertSame(
            '2011-03-14 23:59:00',
            $f->decrement(new DateTime($date))->format('Y-m-d H:i:s')
        );
    }

    public function testIncrementsDateImmutable(): void
    {
        $f = new DayOfMonthField('*');
        $date = '2011-03-15 11:15:00';
        self::assertSame(
            '2011-03-16 00:00:00',
            $f->increment(new DateTimeImmutable($date))->format('Y-m-d H:i:s')
        );

        $date = '2011-03-15 11:15:00';
        self::assertSame(
            '2011-03-14 23:59:00',
            $f->decrement(new DateTimeImmutable($date))->format('Y-m-d H:i:s')
        );
    }

    public function testDecrementsDate(): void
    {
        $f = new DayOfMonthField('*');
        $date = '2011-03-15 11:15:00';
        self::assertSame(
            '2011-03-14 23:59:00',
            $f->decrement(new DateTime($date))->format('Y-m-d H:i:s')
        );
    }

    /**
     * @dataProvider provideFailingExpression
     */
    public function testDoesNotAccept0Date(string $expression): void
    {
        $this->expectException(SyntaxError::class);

        new DayOfMonthField($expression);
    }

    /**
     * @return array<array<string>>
     */
    public function provideFailingExpression(): array
    {
        return [
            ['0'],
            ['5W,L'],
            ['1.'],
        ];
    }

    /**
     * @covers ::increment
     * @covers ::decrement
     */
    public function testIncrementAcrossDstChangeLondon(): void
    {
        $d = new DateTimeImmutable('2021-03-28 00:59:00', new DateTimeZone('Europe/London'));
        $f = new DayOfMonthField('*');

        $newD = $f->increment($d);
        self::assertSame('2021-03-29 00:00:00', $newD->format('Y-m-d H:i:s'));

        $resD = $f->increment($newD);
        self::assertSame('2021-03-30 00:00:00', $resD->format('Y-m-d H:i:s'));

        $altD = $f->decrement($resD);
        self::assertSame('2021-03-29 23:59:00', $altD->format('Y-m-d H:i:s'));

        $altD2 = $f->decrement($altD);
        self::assertSame('2021-03-28 23:59:00', $altD2->format('Y-m-d H:i:s'));

        self::assertSame('2021-03-27 23:59:00', $f->decrement($altD2)->format('Y-m-d H:i:s'));
    }
}
