<?php

declare(strict_types=1);

namespace Bakame\Cron;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Month field.  Allows: * , / -.
 */
final class MonthField extends Field
{
    protected const RANGE_START = 1;
    protected const RANGE_END = 12;
    protected array $literals = [
        1 => 'JAN',
        2 => 'FEB',
        3 => 'MAR',
        4 => 'APR',
        5 => 'MAY',
        6 => 'JUN',
        7 => 'JUL',
        8  => 'AUG',
        9 => 'SEP',
        10 => 'OCT',
        11 => 'NOV',
        12 => 'DEC',
    ];

    protected function isSatisfiedExpression(string $fieldExpression, DateTimeInterface $date): bool
    {
        return '?' === $fieldExpression
            || $this->isSatisfied((int) $date->format('m'), $this->convertLiterals($fieldExpression));
    }

    public function increment(DateTimeInterface $date): DateTimeImmutable
    {
        return  $this->toDateTimeImmutable($date)
            ->setDate((int) $date->format('Y'), (int) $date->format('n'), 1)
            ->setTime(0, 0)
            ->add(new DateInterval('P1M'));
    }

    public function decrement(DateTimeInterface $date): DateTimeImmutable
    {
        return  $this->toDateTimeImmutable($date)
            ->setDate((int) $date->format('Y'), (int) $date->format('n'), 1)
            ->setTime(0, 0)
            ->sub(new DateInterval('PT1M'));
    }
}
