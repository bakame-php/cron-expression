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
final class HourValidator extends FieldValidator
{
    protected const RANGE_START = 0;
    protected const RANGE_END = 23;

    protected function isSatisfiedExpression(string $fieldExpression, DateTimeInterface $date): bool
    {
        return $this->isSatisfied((int) $date->format('H'), $fieldExpression);
    }

    public function increment(DateTimeInterface $date, string|null $fieldExpression = null): DateTimeImmutable
    {
        $date = $this->toDateTimeImmutable($date);

        // Change timezone to UTC temporarily. This will
        // allow us to go back or forwards an hour even
        // if DST will be changed between the hours.
        if (in_array($fieldExpression, [null, '*'], true)) {
            return $date
                ->sub(new DateInterval('PT'.(int) $date->format('j').'M'))
                ->setTimezone(new DateTimeZone('UTC'))
                ->add(new DateInterval('PT1H'))
                ->setTimezone($date->getTimezone())
            ;
        }

        $hours = array_reduce(
            str_contains($fieldExpression, ',') ? explode(',', $fieldExpression) : [$fieldExpression],
            fn (array $hours, string $part): array => array_merge($hours, $this->getRangeForExpression($part, 23)),
            []
        );

        $currentHour = (int) $date->format('H');
        $hour = $hours[$this->computePosition($currentHour, $hours, false)];
        if ($hour < $currentHour) {
            return $date->setTime(0, 0)->add(new DateInterval('P1D'));
        }

        return $date->setTime($hour, 0);
    }

    public function decrement(DateTimeInterface $date, string|null $fieldExpression = null): DateTimeImmutable
    {
        $date = $this->toDateTimeImmutable($date);

        // Change timezone to UTC temporarily. This will
        // allow us to go back or forwards an hour even
        // if DST will be changed between the hours.
        if (in_array($fieldExpression, [null, '*'], true)) {
            return $date
                ->sub(new DateInterval('PT'.(int) $date->format('j').'M'))
                ->setTimezone(new DateTimeZone('UTC'))
                ->sub(new DateInterval('PT1M'))
                ->setTimezone($date->getTimezone());
        }

        /** @var array<int> $hours */
        $hours = array_reduce(
            str_contains($fieldExpression, ',') ? explode(',', $fieldExpression) : [$fieldExpression],
            fn (array $hours, string $part): array => array_merge($hours, $this->getRangeForExpression($part, 23)),
            []
        );

        $currentHour = (int) $date->format('H');
        $hour = $hours[$this->computePosition($currentHour, $hours, true)];
        if ($hour > $currentHour) {
            return $date->setTime(0, 0)->sub(new DateInterval('PT1M'));
        }

        return $date->setTime($hour, 59);
    }
}
