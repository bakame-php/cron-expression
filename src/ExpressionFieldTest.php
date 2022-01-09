<?php

namespace Bakame\Cron;

use PHPUnit\Framework\TestCase;

final class ExpressionFieldTest extends TestCase
{
    public function testItWillThrowOnWrongOffest(): void
    {
        $this->expectException(SyntaxError::class);

        ExpressionField::fromOffset(42);
    }
}
