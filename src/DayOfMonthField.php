<?php

declare(strict_types=1);

namespace Cron;

use DateTime;
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
final class DayOfMonthField extends AbstractField
{
    protected int $rangeStart = 1;
    protected int $rangeEnd = 31;

    /**
     * Get the nearest day of the week for a given day in a month.
     *
     * @param int $currentYear  Current year
     * @param int $currentMonth Current month
     * @param int $targetDay    Target day of the month
     *
     * @return DateTime Returns the nearest date
     */
    private static function getNearestWeekday(int $currentYear, int $currentMonth, int $targetDay): DateTime
    {
        $tday = str_pad((string) $targetDay, 2, '0', STR_PAD_LEFT);
        /** @var DateTime $target */
        $target = DateTime::createFromFormat('Y-m-d', "$currentYear-$currentMonth-$tday");
        $currentWeekday = (int) $target->format('N');

        if ($currentWeekday < 6) {
            return $target;
        }

        $lastDayOfMonth = $target->format('t');

        foreach ([-1, 1, -2, 2] as $i) {
            $adjusted = $targetDay + $i;
            if ($adjusted > 0 && $adjusted <= $lastDayOfMonth) {
                $target->setDate($currentYear, $currentMonth, $adjusted);
                if ($target->format('N') < 6 && $target->format('m') == $currentMonth) {
                    return $target;
                }
            }
        }

        return $target;
    }

    public function isSatisfiedBy(DateTimeInterface $date, $value): bool
    {
        // ? states that the field value is to be skipped
        if ($value == '?') {
            return true;
        }

        $fieldValue = $date->format('d');

        // Check to see if this is the last day of the month
        if ($value == 'L') {
            return $fieldValue == $date->format('t');
        }

        // Check to see if this is the nearest weekday to a particular value
        $pos = strpos($value, 'W');
        if (false !== $pos) {
            // Find out if the current day is the nearest day of the week
            return $date->format('j') == self::getNearestWeekday(
                (int) $date->format('Y'),
                (int) $date->format('m'),
                (int) substr($value, 0, $pos) // Parse the target day
            )->format('j');
        }

        return $this->isSatisfied($date->format('d'), $value);
    }

    public function increment(DateTime $date, bool $invert = false, string $parts = null): self
    {
        if ($invert) {
            $date->modify('previous day');
            $date->setTime(23, 59);
        } else {
            $date->modify('next day');
            $date->setTime(0, 0);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function validate(string $value): bool
    {
        $basicChecks = parent::validate($value);

        // Validate that a list don't have W or L
        if (str_contains($value, ',') && (str_contains($value, 'W') || str_contains($value, 'L'))) {
            return false;
        }

        if (true === $basicChecks) {
            return $basicChecks;
        }

        if ($value === 'L') {
            return true;
        }

        if (1 === preg_match('/^(.*)W$/', $value, $matches)) {
            return $this->validate($matches[1]);
        }

        return false;
    }
}