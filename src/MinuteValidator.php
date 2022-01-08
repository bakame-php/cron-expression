<?php

declare(strict_types=1);

namespace Bakame\Cron;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Minutes field.  Allows: * , / -.
 */
final class MinuteValidator extends FieldValidator
{
    protected const RANGE_START = 0;
    protected const RANGE_END = 59;

    protected function isSatisfiedExpression(string $fieldExpression, DateTimeInterface $date): bool
    {
        return '?' === $fieldExpression
            || $this->isSatisfied((int) $date->format('i'), $fieldExpression);
    }

    public function increment(DateTimeInterface $date, string|null $fieldExpression = null): DateTimeImmutable
    {
        $date = $this->toDateTimeImmutable($date);
        if (null === $fieldExpression) {
            return $date->add(new DateInterval('PT1M'));
        }

        $currentMinute = (int) $date->format('i');
        $minute = $this->computeTimeFieldRangeValue($currentMinute, $fieldExpression, false);
        if ($currentMinute < $minute) {
            return $date->add(new DateInterval('PT'.($minute - $currentMinute).'M'));
        }

        return $date->add(new DateInterval('PT'.(60 - $currentMinute).'M'));
    }

    public function decrement(DateTimeInterface $date, string|null $fieldExpression = null): DateTimeImmutable
    {
        $date = $this->toDateTimeImmutable($date);
        if (null === $fieldExpression) {
            return $date->sub(new DateInterval('PT1M'));
        }

        $currentMinute = (int) $date->format('i');
        $minute = $this->computeTimeFieldRangeValue($currentMinute, $fieldExpression, true);
        if ($minute < $currentMinute) {
            return $date->sub(new DateInterval('PT'.($currentMinute - $minute).'M'));
        }

        return $date->sub(new DateInterval('PT'.($currentMinute + 1).'M'));
    }
}
