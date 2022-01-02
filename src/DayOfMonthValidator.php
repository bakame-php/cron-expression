<?php

declare(strict_types=1);

namespace Bakame\Cron;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Day of month field.  Allows: * , / - ? L W.
 *
 * 'L' stands for "last" and specifies the last day of the month.
 *
 * The 'W' character is used to specify the weekday (Monday-Friday) nearest the
 * given day. As an example, if you were to specify "15W" as the value for the
 * day-of-month field, the meaning is: "the nearest weekday to the 15th of the
 * month". So if the 15th is a Saturday, the trigger will fire on Friday the
 * 14th. If the 15th is a Sunday, the trigger will fire on Monday the 16th. If
 * the 15th is a Tuesday, then it will fire on Tuesday the 15th. However if you
 * specify "1W" as the value for day-of-month, and the 1st is a Saturday, the
 * trigger will fire on Monday the 3rd, as it will not 'jump' over the boundary
 * of a month's days. The 'W' character can only be specified when the
 * day-of-month is a single day, not a range or list of days.
 *
 * @author Michael Dowling <mtdowling@gmail.com>
 */
final class DayOfMonthValidator extends FieldValidator
{
    protected const RANGE_START = 1;
    protected const RANGE_END = 31;

    /**
     * Get the nearest day of the week for a given day in a month.
     */
    private static function isNearestWeekday(DateTimeInterface $date, string $fieldExpression, int $position): bool
    {
        $currentYear = (int) $date->format('Y');
        $currentMonth = (int) $date->format('m');
        $currentDay = $date->format('j');
        $targetDay = (int) substr($fieldExpression, 0, $position);

        /** @var DateTime $target */
        $target = DateTime::createFromFormat('Y-n-j', "$currentYear-$currentMonth-$targetDay");
        $lastDayOfMonth = (int) $target->format('t');
        foreach ([0, -1, 1, -2, 2] as $i) {
            $adjusted = $targetDay + $i;
            if ($adjusted > 0 && $adjusted <= $lastDayOfMonth) {
                $target->setDate($currentYear, $currentMonth, $adjusted);
                if (6 > (int) $target->format('N') && (int) $target->format('m') == $currentMonth) {
                    return $target->format('j') === $currentDay;
                }
            }
        }

        return $target->format('j') === $currentDay;
    }

    protected function isSatisfiedExpression(string $fieldExpression, DateTimeInterface $date): bool
    {
        $fieldValue = $date->format('d');

        return match (true) {
            '?' === $fieldExpression => true,
            'L' === $fieldExpression => $fieldValue === $date->format('t'),
            false !== ($position = strpos($fieldExpression, 'W')) => self::isNearestWeekday($date, $fieldExpression, $position),
            default => $this->isSatisfied((int) $fieldValue, $fieldExpression),
        };
    }

    public function increment(DateTimeInterface $date, string|null $fieldExpression = null): DateTimeImmutable
    {
        return $this->toDateTimeImmutable($date)->setTime(0, 0)->add(new DateInterval('P1D'));
    }

    public function decrement(DateTimeInterface $date, string|null $fieldExpression = null): DateTimeImmutable
    {
        return $this->toDateTimeImmutable($date)->setTime(0, 0)->sub(new DateInterval('PT1M'));
    }

    /**
     * @inheritDoc
     */
    public function isValid(string $fieldExpression): bool
    {
        return match (true) {
            str_contains($fieldExpression, ',') && (str_contains($fieldExpression, 'W') || str_contains($fieldExpression, 'L')) => false,
            true === parent::isValid($fieldExpression) || in_array($fieldExpression, ['?', 'L'], true) => true,
            default => 1 === preg_match('/^(?<expression>.*)W$/', $fieldExpression, $matches) && $this->isValid($matches['expression']),
        };
    }
}
