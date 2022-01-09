<?php

namespace Bakame\Cron;

enum ExpressionField: string
{
    case MINUTE = 'minute';
    case HOUR = 'hour';
    case DAY_OF_MONTH = 'dayOfMonth';
    case MONTH = 'month';
    case DAY_OF_WEEK = 'dayOfWeek';

    /**
     * Named constructor from Array parsing integer offset.
     */
    public static function fromOffset(int $position): self
    {
        return match ($position) {
            0 => ExpressionField::MINUTE,
            1 => ExpressionField::HOUR,
            2 => ExpressionField::DAY_OF_MONTH,
            3 => ExpressionField::MONTH,
            4 => ExpressionField::DAY_OF_WEEK,
            default => throw SyntaxError::dueToInvalidPosition($position),
        };
    }

    /**
     * Returns the expression field validator.
     */
    public function newCronField(string $expression): CronField
    {
        return match ($this) {
            self::MINUTE => new MinuteField($expression),
            self::HOUR => new HourField($expression),
            self::DAY_OF_MONTH => new DayOfMonthField($expression),
            self::MONTH => new MonthField($expression),
            default => new DayOfWeekField($expression), // self::DAY_OF_WEEK
        };
    }
}
