<?php

declare(strict_types=1);

namespace Bakame\Cron;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Minutes field.  Allows: * , / -.
 */
final class MinuteField extends Field
{
    protected const RANGE_START = 0;
    protected const RANGE_END = 59;

    protected function isSatisfiedExpression(string $fieldExpression, DateTimeInterface $date): bool
    {
        return '?' === $fieldExpression
            || $this->isSatisfied((int) $date->format('i'), $fieldExpression);
    }

    public function increment(DateTimeInterface $date): DateTimeImmutable
    {
        $date = $this->toDateTimeImmutable($date);
        if ('*' === $this->field) {
            return $date->add(new DateInterval('PT1M'));
        }

        $currentMinute = (int) $date->format('i');
        $minute = $this->computeTimeFieldRangeValue($currentMinute, $this->field, false);
        if ($currentMinute < $minute) {
            return $date->add(new DateInterval('PT'.($minute - $currentMinute).'M'));
        }

        return $date->add(new DateInterval('PT'.(60 - $currentMinute).'M'));
    }

    public function decrement(DateTimeInterface $date): DateTimeImmutable
    {
        $date = $this->toDateTimeImmutable($date);
        if ('*' === $this->field) {
            return $date->sub(new DateInterval('PT1M'));
        }

        $currentMinute = (int) $date->format('i');
        $minute = $this->computeTimeFieldRangeValue($currentMinute, $this->field, true);
        if ($minute < $currentMinute) {
            return $date->sub(new DateInterval('PT'.($currentMinute - $minute).'M'));
        }

        return $date->sub(new DateInterval('PT'.($currentMinute + 1).'M'));
    }
}
