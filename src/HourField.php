<?php

declare(strict_types=1);

namespace Bakame\Cron;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Hours field.  Allows: * , / -.
 */
final class HourField extends ExpressionField
{
    protected const RANGE_START = 0;
    protected const RANGE_END = 23;

    protected function isExpressionSatisfiedBy(string $fieldExpression, DateTimeInterface $date): bool
    {
        return $this->isSatisfied((int) $date->format('H'), $fieldExpression);
    }

    public function increment(DateTimeInterface $date): DateTimeImmutable
    {
        $date = $this->toDateTimeImmutable($date);

        if ('*' === $this->field) {
            return $date
                ->sub(new DateInterval('PT'.(int) $date->format('j').'M'))
                ->setTimezone(new DateTimeZone('UTC'))
                ->add(new DateInterval('PT1H'))
                ->setTimezone($date->getTimezone());
        }

        $currentHour = (int) $date->format('H');
        $hour = $this->computeTimeFieldRangeValue($currentHour, $this->field, Direction::FORWARD);
        if ($hour < $currentHour) {
            return $date
                ->setTimezone(new DateTimeZone('UTC'))
                ->setTime(0, 0)
                ->add(new DateInterval('P1D'))
                ->setTimezone($date->getTimezone())
            ;
        }

        return $date->setTime($hour, 0);
    }

    public function decrement(DateTimeInterface $date): DateTimeImmutable
    {
        $date = $this->toDateTimeImmutable($date);

        if ('*' === $this->field) {
            return $date
                ->setTimezone(new DateTimeZone('UTC'))
                ->sub(new DateInterval('PT'.((int) $date->format('j') + 1).'M'))
                ->setTimezone($date->getTimezone())
            ;
        }

        $currentHour = (int) $date->format('H');
        $hour = $this->computeTimeFieldRangeValue($currentHour, $this->field, Direction::BACKWARD);
        if ($hour > $currentHour) {
            return $date
                ->setTimezone(new DateTimeZone('UTC'))
                ->setTime(0, 0)
                ->sub(new DateInterval('PT1M'))
                ->setTimezone($date->getTimezone())
            ;
        }

        $res = $date->setTime($hour, 59);
        if ($res != $date) {
            return $res;
        }

        return $date->setTime($hour - 1, 59);
    }
}
