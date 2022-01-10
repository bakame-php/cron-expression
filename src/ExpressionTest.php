<?php

declare(strict_types=1);

namespace Bakame\Cron;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Bakame\Cron\Expression
 */
final class ExpressionTest extends TestCase
{
    public function testFactoryRecognizesTemplates(): void
    {
        self::assertSame('0 0 1 1 *', Expression::yearly()->toString());
        self::assertSame('0 0 1 * *', Expression::monthly()->toString());
        self::assertSame('0 0 * * 0', Expression::weekly()->toString());
        self::assertSame('0 0 * * *', Expression::daily()->toString());
        self::assertSame('0 * * * *', Expression::hourly()->toString());
    }

    public function testParsesCronSchedule(): void
    {
        // '2010-09-10 12:00:00'
        $cron = Expression::fromString('1 2-4 * 4,5,6 */3');
        self::assertSame('1', $cron->minute()->toString());
        self::assertSame('2-4', $cron->hour()->toString());
        self::assertSame('*', $cron->dayOfMonth()->toString());
        self::assertSame('4,5,6', $cron->month()->toString());
        self::assertSame('*/3', $cron->dayOfWeek()->toString());
        self::assertSame('1 2-4 * 4,5,6 */3', $cron->toString());
        self::assertSame('1 2-4 * 4,5,6 */3', $cron->toString());
        self::assertSame(['1', '2-4', '*', '4,5,6', '*/3'], array_values(array_map(fn (CronField $f): string => $f->toString(), $cron->fields())));
        self::assertSame('"1 2-4 * 4,5,6 *\/3"', json_encode($cron));
    }

    public function testParsesCronScheduleThrowsAnException(): void
    {
        $this->expectException(SyntaxError::class);

        Expression::fromString('A 1 2 3 4');
    }

    /**
     * @dataProvider scheduleWithDifferentSeparatorsProvider
     */
    public function testParsesCronScheduleWithAnySpaceCharsAsSeparators(string $schedule, array $expected): void
    {
        $cron = Expression::fromString($schedule);

        self::assertSame($expected[0], $cron->minute()->toString());
        self::assertSame($expected[1], $cron->hour()->toString());
        self::assertSame($expected[2], $cron->dayOfMonth()->toString());
        self::assertSame($expected[3], $cron->month()->toString());
        self::assertSame($expected[4], $cron->dayOfWeek()->toString());
    }

    /**
     * Data provider for testParsesCronScheduleWithAnySpaceCharsAsSeparators.
     */
    public static function scheduleWithDifferentSeparatorsProvider(): array
    {
        return [
            ["*\t*\t*\t*\t*\t", ['*', '*', '*', '*', '*', '*']],
            ['*  *  *  *  *  ', ['*', '*', '*', '*', '*', '*']],
            ["* \t * \t * \t * \t * \t", ['*', '*', '*', '*', '*', '*']],
            ["*\t \t*\t \t*\t \t*\t \t*\t \t", ['*', '*', '*', '*', '*', '*']],
        ];
    }

    public function testUpdateExpressionPartReturnsTheSameInstance(): void
    {
        $cron = Expression::fromString('23 0-23/2 * * *');

        self::assertEquals($cron, $cron->withMinute($cron->minute()));
        self::assertEquals($cron, $cron->withHour('0-23/2'));
        self::assertEquals($cron, $cron->withMonth($cron->month()));
        self::assertEquals($cron, $cron->withDayOfMonth('*'));
        self::assertEquals($cron, $cron->withDayOfWeek($cron->dayOfWeek()));
    }

    public function testUpdateExpressionPartReturnsADifferentInstance(): void
    {
        $cron = Expression::fromString('23 0-23/2 * * *');

        self::assertNotEquals($cron, $cron->withMinute('22'));
        self::assertNotEquals($cron, $cron->withHour('12'));
        self::assertNotEquals($cron, $cron->withDayOfMonth('28'));
        self::assertNotEquals($cron, $cron->withMonth('12'));
        self::assertNotEquals($cron, $cron->withDayOfWeek('Fri'));
    }

    public function testInstantiationFromFieldsList(): void
    {
        self::assertSame('* * * * *', Expression::fromFields([])->toString());
        self::assertSame('7 * * * 5', Expression::fromFields(['minute' => 7, 'dayOfWeek' => '5'])->toString());
    }

    public function testInstantiationFromFieldsListWillFail(): void
    {
        $this->expectException(SyntaxError::class);

        Expression::fromFields(['foo' => 'bar', 'minute' => '23']);
    }

    public function testExpressionInternalPhpMethod(): void
    {
        $cronOriginal = Expression::fromString('5 4 3 2 1');
        /** @var Expression $cron */
        $cron = eval('return '.var_export($cronOriginal, true).';');

        self::assertEquals($cronOriginal, $cron);
    }

    /**
     * @dataProvider validExpressionProvider
     */
    public function testDoubleZeroIsValid(string $expression): void
    {
        $obj = Expression::fromString($expression);

        self::assertSame($expression, $obj->toString());
    }

    public function validExpressionProvider(): array
    {
        return [
            ['00 * * * *'],
            ['01 * * * *'],
            ['* 00 * * *'],
            ['* 01 * * *'],
            ['* * * * 1'],
            ['* 1-3,6-12 * * *'],
        ];
    }

    /**
     * @dataProvider invalidExpression
     */
    public function testParsingFails(string $expression): void
    {
        $this->expectException(SyntaxError::class);

        Expression::fromString($expression);
    }

    public function invalidExpression(): array
    {
        return [
            'less than 5 fields' => ['* * * 1'],
            'more than 5 fields' => ['* * * * * *'],
            'invalid monthday field' => ['* * abc * *'],
            'invalid month field' => ['* * * 13 * '],
            'invalid minute field' => ['90 * * * *'],
            'invalid hour field value' => ['0 24 1 12 0'],
            'invalid weekday' => ['* 14 * * mon-fri0345345'],
            'invalid range in hour' => ['* 59-41 * * *'],
            'invalid range in weekday' => ['* * * * 8-3'],
            'invalid range in minute' => ['59-41/4 * * * *'],
            'invalid step' => ['1-8/0 * * * *'],
            'invalid day of week modifier' => ['* * * * 3#L'],
            'many errors' => ['990 14 * * mon-fri0345345'],
            'day of week modifier too high' => ['* * * * 8#3'],
        ];
    }

    public function testItCanRegisterAnValidExpression(): void
    {
        Expression::registerAlias('@every', '* * * * *');

        self::assertCount(8, Expression::aliases());
        self::assertArrayHasKey('@every', Expression::aliases());
        self::assertTrue(Expression::supportsAlias('@every'));
        self::assertEquals(Expression::fromString('@every'), Expression::fromString('* * * * *'));

        Expression::unregisterAlias('@every');

        self::assertCount(7, Expression::aliases());
        self::assertArrayNotHasKey('@every', Expression::aliases());
        self::assertFalse(Expression::supportsAlias('@every'));

        $this->expectException(SyntaxError::class);
        Expression::fromString('@every');
    }

    public function testItWillFailToRegisterAnInvalidExpression(): void
    {
        $this->expectException(ExpressionAliasError::class);

        Expression::registerAlias('@every', 'foobar');
    }

    public function testItWillFailToRegisterAnInvalidName(): void
    {
        $this->expectException(ExpressionAliasError::class);

        Expression::registerAlias('every', '* * * * *');
    }

    public function testItWillFailToRegisterAnInvalidName2(): void
    {
        $this->expectException(ExpressionAliasError::class);

        Expression::registerAlias('@évery', '* * * * *');
    }

    public function testItWillFailToRegisterAValidNameTwice(): void
    {
        Expression::registerAlias('@Ev_eR_y', '* * * * *');

        $this->expectException(ExpressionAliasError::class);
        Expression::registerAlias('@eV_Er_Y', '2 2 2 2 2');
    }

    public function testItWillFailToUnregisterADefaultExpression(): void
    {
        $this->expectException(ExpressionAliasError::class);

        Expression::unregisterAlias('@daily');
    }
}
