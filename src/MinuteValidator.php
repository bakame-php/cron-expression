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
            return $date->setTime((int) $date->format('H'), $minute);
        }

        $date = $date->add(new DateInterval('PT1H'));

        return $date->setTime((int) $date->format('H'), 0);
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
            return $date->setTime((int) $date->format('H'), $minute);
        }

        return $date->sub(new DateInterval('PT'.($currentMinute + 1).'M'));
    }
}
