<?php

declare(strict_types=1);

namespace Bakame\Cron;

use DateTime;
use DateTimeImmutable;
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

    public function provideFailingExpression(): array
    {
        return [
            ['0'],
            ['5W,L'],
            ['1.'],
        ];
    }
}
