<?php

declare(strict_types=1);

namespace Bakame\Cron;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Day of week field.  Allows: * / , - ? L #.
 *
 * Days of the week can be represented as a number 0-7 (0|7 = Sunday)
 * or as a three letter string: SUN, MON, TUE, WED, THU, FRI, SAT.
 *
 * 'L' stands for "last". It allows you to specify constructs such as
 * "the last Friday" of a given month.
 *
 * '#' is allowed for the day-of-week field, and must be followed by a
 * number between one and five. It allows you to specify constructs such as
 * "the second Friday" of a given month.
 */
final class DayOfWeekField extends Field
{
    protected const RANGE_START = 0;
    protected const RANGE_END = 7;

    protected array $literals = [
        1 => 'MON',
        2 => 'TUE',
        3 => 'WED',
        4 => 'THU',
        5 => 'FRI',
        6 => 'SAT',
        7 => 'SUN',
    ];

    protected function isSatisfiedExpression(string $fieldExpression, DateTimeInterface $date): bool
    {
        if ('?' === $fieldExpression) {
            return true;
        }

        // Convert text day of the week values to integers
        $fieldExpression = $this->convertLiterals($fieldExpression);

        $currentYear = (int) $date->format('Y');
        $currentMonth = (int) $date->format('m');
        $lastDayOfMonth = (int) $date->format('t');

        // Find out if this is the last specific weekday of the month
        $pos = strpos($fieldExpression, 'L');
        if (false !== $pos) {
            $weekday = $this->convertLiterals(str_replace('7', '0', substr($fieldExpression, 0, $pos)));
            $tempDate = DateTime::createFromInterface($date)->setDate($currentYear, $currentMonth, $lastDayOfMonth);
            while ($tempDate->format('w') !== $weekday) {
                $tempDate->setDate($currentYear, $currentMonth, --$lastDayOfMonth);
            }

            return $date->format('j') == $lastDayOfMonth;
        }

        // Handle # hash tokens
        if (str_contains($fieldExpression, '#')) {
            [$weekday, $nth] = explode('#', $fieldExpression);
            $nth = (int) $nth;
            if ($weekday === '0') {
                $weekday = '7';
            }

            $weekday = (int) $this->convertLiterals($weekday);

            // The current weekday must match the targeted weekday to proceed
            if ((int) $date->format('N') !== $weekday) {
                return false;
            }

            $tempDate = DateTime::createFromInterface($date)->setDate($currentYear, $currentMonth, 1);
            $dayCount = 0;
            $currentDay = 1;
            while ($currentDay < $lastDayOfMonth + 1) {
                if ((int) $tempDate->format('N') === $weekday) {
                    if (++$dayCount >= $nth) {
                        break;
                    }
                }
                $tempDate->setDate($currentYear, $currentMonth, ++$currentDay);
            }

            return (int) $date->format('j') === $currentDay;
        }

        // Handle day of the week values
        if (str_contains($fieldExpression, '-')) {
            [$first, $last] = $this->formatFieldRanges($fieldExpression);

            $fieldExpression = $first.'-'.$last;
        }

        // Test to see which Sunday to use -- 0 == 7 == Sunday
        $format = in_array('7', str_split($fieldExpression), true) ? 'N' : 'w';

        return $this->isSatisfied((int) $date->format($format), $fieldExpression);
    }

    /**
     * @return array<string>
     */
    protected function formatFieldRanges(string $fieldExpression): array
    {
        [$first, $last] = parent::formatFieldRanges($fieldExpression);

        if ($first === '7') {
            $first = '0';
        }

        if ($last === '0') {
            $last = '7';
        }

        return [$first, $last];
    }

    public function increment(DateTimeInterface $date): DateTimeImmutable
    {
        return $this->toDateTimeImmutable($date)->setTime(0, 0)->add(new DateInterval('P1D'));
    }

    public function decrement(DateTimeInterface $date): DateTimeImmutable
    {
        return $this->toDateTimeImmutable($date)->setTime(0, 0)->sub(new DateInterval('PT1M'));
    }

    protected function validate(string $fieldExpression): void
    {
        if ('?' === $fieldExpression) {
            return;
        }

        try {
            parent::validate($fieldExpression);

            return;
        } catch (CronError) {
        }

        if (str_contains($fieldExpression, '#')) {
            $this->handleSharpExpression($fieldExpression);

            return;
        }

        if (1 !== preg_match('/^(?<expression>.*)L$/', $fieldExpression, $matches)) {
            throw SyntaxError::dueToInvalidFieldExpression($fieldExpression, $this::class);
        }

        $this->wrapValidate($matches['expression'], $fieldExpression);
    }

    private function handleSharpExpression(string $fieldExpression): void
    {
        [$weekday, $nth] = explode('#', $fieldExpression);
        if (false === filter_var($nth, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]])) {
            throw SyntaxError::dueToInvalidFieldExpression($fieldExpression, $this::class);
        }

        // 0 and 7 are both Sunday, however 7 matches date('N') format ISO-8601
        if ($weekday === '0') {
            $weekday = '7';
        }

        if (false === filter_var($this->convertLiterals($weekday), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 7]])) {
            throw SyntaxError::dueToInvalidFieldExpression($fieldExpression, $this::class);
        }
    }
}
