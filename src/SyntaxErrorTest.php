<?php

namespace Bakame\Cron;

use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;

class SyntaxErrorTest extends TestCase
{
    public function testItReturnsErrorInformations(): void
    {
        $exception = SyntaxError::dueToInvalidFieldExpression([
            'foo' => 'bar',
        ]);

        self::assertSame(['foo' => 'Invalid or unsupported value `bar`.'], $exception->errors());
    }

    public function testItCanHandleWrongDateIntervalWithWrongStringFormat(): void
    {
        $exception = SyntaxError::dueToInvalidDateIntervalString('foobar');

        self::assertSame('The string `foobar` is not a valid `DateInterval::createFromDateString` input.', $exception->getMessage());
    }

    public function testItCanHandleWrongDateWithDateTimeInterfaceValue(): void
    {
        $date = new DateTimeImmutable('2021-03-02');

        $exception = SyntaxError::dueToInvalidDateString($date, new LogicException());

        self::assertSame("The string `{$date->format('c')}` is not a valid `DateTimeImmutable:::__construct` input.", $exception->getMessage());
    }
}
