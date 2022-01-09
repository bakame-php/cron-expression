<?php

declare(strict_types=1);

namespace Bakame\Cron;

use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Bakame\Cron\DayOfWeekField
 */
final class DayOfWeekFieldTest extends TestCase
{
    /**
     * @dataProvider validFieldExpression
     */
    public function testValidatesField(string $expression): void
    {
        $f = new DayOfWeekField($expression);

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
            ['SUN-2'],
            ['MON#1'],
            ['TUE#2'],
            ['WED#3'],
            ['THU#4'],
            ['FRI#5'],
            ['SAT#1'],
            ['SUN#3'],
            ['MON#1,MON#3'],
        ];
    }

    public function testChecksIfSatisfied(): void
    {
        $f = new DayOfWeekField('?');
        self::assertTrue($f->isSatisfiedBy(new DateTime()));
    }

    public function testIncrementsDate(): void
    {
        $f = new DayOfWeekField('?');
        self::assertSame('2011-03-16 00:00:00', $f->increment(new DateTime('2011-03-15 11:15:00'))->format('Y-m-d H:i:s'));

        self::assertSame('2011-03-14 23:59:00', $f->decrement(new DateTime('2011-03-15 11:15:00'))->format('Y-m-d H:i:s'));
    }

    public function testIncrementsDateImmutable(): void
    {
        $f = new DayOfWeekField('?');
        self::assertSame('2011-03-16 00:00:00', $f->increment(new DateTimeImmutable('2011-03-15 11:15:00'))->format('Y-m-d H:i:s'));

        self::assertSame('2011-03-14 23:59:00', $f->decrement(new DateTimeImmutable('2011-03-15 11:15:00'))->format('Y-m-d H:i:s'));
    }

    /**
     * @covers \Bakame\Cron\SyntaxError
     */
    public function testValidatesHashValueWeekday(): void
    {
        $this->expectException(SyntaxError::class);

        new DayOfWeekField('12#1');
    }

    /**
     * @covers \Bakame\Cron\SyntaxError
     */
    public function testValidatesHashValueNth(): void
    {
        $this->expectException(SyntaxError::class);

        new DayOfWeekField('3#6');
    }

    /**
     * @dataProvider validateSatisfiedBy
     */
    public function testHandlesZeroAndSevenDayOfTheWeekValues(string $expression): void
    {
        $f = new DayOfWeekField($expression);
        self::assertTrue($f->isSatisfiedBy(new DateTime('2011-09-04 00:00:00')));
    }

    /**
     * @return array<array<string>>
     */
    public function validateSatisfiedBy(): array
    {
        return [
            ['0-2'],
            ['6-0'],
            ['SUN'],
        ];
    }

    /**
     * @dataProvider validationFailedForSatisfiedBy
     */
    public function testHandlesZeroAndSevenDayOfTheWeekValuesFails(string $expression): void
    {
        $f = new DayOfWeekField($expression);
        self::assertFalse($f->isSatisfiedBy(new DateTime('2011-09-04 00:00:00')));
    }

    /**
     * @return array<array<string>>
     */
    public function validationFailedForSatisfiedBy(): array
    {
        return [
            ['0#3'],
            ['7#3'],
            ['SUN#3'],
        ];
    }

    /**
     * @dataProvider invalidFields
     */
    public function testIssue47(string $expression): void
    {
        $this->expectException(SyntaxError::class);
        new DayOfWeekField($expression);
    }

    /**
     * @return array<array<string>>
     */
    public function invalidFields(): array
    {
        return [
            ['mon,'],
            ['mon-'],
            ['*/2,'],
            ['-mon'],
            [',1'],
            ['*-'],
            [',-'],
            ['*/3,1,1-12'],
        ];
    }
}
