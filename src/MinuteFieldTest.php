<?php

declare(strict_types=1);

namespace Bakame\Cron;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Bakame\Cron\MinuteField
 */
final class MinuteFieldTest extends TestCase
{
    /**
     * @dataProvider validFieldExpression
     */
    public function testValidatesField(string|int $expression): void
    {
        $f = new MinuteField($expression);

        self::assertSame((string) $expression, $f->toString());
    }

    /**
     * @return array<array<string|int>>
     */
    public function validFieldExpression(): array
    {
        return [
            [1],
            ['1'],
            ['*'],
            ['*/3,1,1-12'],
            ['5-7,11-13'],
        ];
    }

    public function testIncrementsDate(): void
    {
        $d = new DateTime('2011-03-15 11:15:00');
        $f = new MinuteField('*');
        $res = $f->increment($d);
        self::assertSame('2011-03-15 11:16:00', $res->format('Y-m-d H:i:s'));
        self::assertSame('2011-03-15 11:15:00', $f->decrement($res)->format('Y-m-d H:i:s'));
    }

    public function testIncrementsDateImmutable(): void
    {
        $d = new DateTimeImmutable('2011-03-15 11:15:00');
        $f = new MinuteField('*');
        $res = $f->increment($d);
        self::assertSame('2011-03-15 11:16:00', $res->format('Y-m-d H:i:s'));
        self::assertSame('2011-03-15 11:15:00', $f->decrement($res)->format('Y-m-d H:i:s'));
    }

    /**
     * @dataProvider failedExpressionProvider
     */
    public function testBadSyntaxesShouldNotValidate(string $expression): void
    {
        $this->expectException(SyntaxError::class);

        new MinuteField($expression);
    }

    public function failedExpressionProvider(): array
    {
        return [
            ['*-1'],
            ['1-2-3'],
            ['-1'],
            ['4-5/0'],
        ];
    }

    /**
     * @covers ::increment
     * @covers ::decrement
     */
    public function testIncrementAcrossDstChangeBerlin(): void
    {
        $d = new DateTimeImmutable('2021-03-28 01:59:00', new DateTimeZone('Europe/Berlin'));
        $f = new DayOfMonthField('*');

        $resD = $f->increment($d);
        self::assertSame('2021-03-29 00:00:00', $resD->format('Y-m-d H:i:s'));

        $newD = $f->decrement($resD);
        self::assertSame('2021-03-28 23:59:00', $newD->format('Y-m-d H:i:s'));

        self::assertSame('2021-03-27 23:59:00', $f->decrement($newD)->format('Y-m-d H:i:s'));
    }
}
