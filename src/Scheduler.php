<?php

namespace Bakame\Cron;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Generator;
use Throwable;

final class Scheduler implements CronScheduler
{
    public const EXCLUDE_START_DATE = 0;
    public const INCLUDE_START_DATE = 1;

    private CronExpression $expression;
    private DateTimeZone $timezone;
    private int $startDatePresence;

    /** Internal variables to optimize runs calculation */

    /** @var array<string, array{0:string, 1:CronFieldValidator}>  */
    private array $calculatedFields;
    private bool $includeDayOfWeekAndDayOfMonthExpression;
    private string|null $minuteFieldExpression;

    public function __construct(
        CronExpression|string $expression,
        DateTimeZone|string $timezone,
        int $startDatePresence = Scheduler::EXCLUDE_START_DATE
    ) {
        $this->expression = $this->filterExpression($expression);
        $this->timezone = $this->filterTimezone($timezone);
        $this->startDatePresence = $this->filterStartDatePresence($startDatePresence);

        $this->initialize();
    }

    private function filterExpression(CronExpression|string $expression): CronExpression
    {
        if (!$expression instanceof CronExpression) {
            return new Expression($expression);
        }

        return $expression;
    }

    private function filterTimezone(DateTimeZone|string $timezone): DateTimeZone
    {
        if (!$timezone instanceof DateTimeZone) {
            return new DateTimeZone($timezone);
        }

        return $timezone;
    }

    private function filterStartDatePresence(int $startDatePresence): int
    {
        if (!in_array($startDatePresence, [self::EXCLUDE_START_DATE, self::INCLUDE_START_DATE], true)) {
            throw SyntaxError::dueToInvalidStartDatePresence();
        }

        return $startDatePresence;
    }

    private function initialize(): void
    {
        // We don't have to satisfy * fields
        $this->calculatedFields = [];
        $expressionFields = $this->expression->fields();
        foreach (ExpressionField::orderedFields() as $field) {
            $fieldExpression = $expressionFields[$field->value];
            if ('*' !== $fieldExpression) {
                $this->calculatedFields[$field->value] = [$fieldExpression, $field->validator()];
            }
        }

        $this->includeDayOfWeekAndDayOfMonthExpression = isset(
            $this->calculatedFields[ExpressionField::DAY_OF_MONTH->value],
            $this->calculatedFields[ExpressionField::DAY_OF_WEEK->value]
        );
        $this->minuteFieldExpression = $this->calculatedFields[ExpressionField::MINUTE->value][0] ?? null;
    }

    public static function fromUTC(CronExpression|string $expression): self
    {
        return new self($expression, new DateTimeZone('UTC'));
    }

    public static function fromSystemTimezone(CronExpression|string $expression): self
    {
        return new self($expression, new DateTimeZone(date_default_timezone_get()));
    }

    public function expression(): CronExpression
    {
        return $this->expression;
    }

    public function timezone(): DateTimeZone
    {
        return $this->timezone;
    }

    public function isStartDateExcluded(): bool
    {
        return self::EXCLUDE_START_DATE === $this->startDatePresence;
    }

    public function withExpression(CronExpression|string $expression): self
    {
        $expression = $this->filterExpression($expression);
        if ($expression->toString() === $this->expression->toString()) {
            return $this;
        }

        return new self($expression, $this->timezone, $this->startDatePresence);
    }

    public function withTimezone(DateTimeZone|string $timezone): self
    {
        $timezone = $this->filterTimezone($timezone);
        if ($timezone->getName() === $this->timezone->getName()) {
            return $this;
        }

        return new self($this->expression, $timezone, $this->startDatePresence);
    }

    public function includeStartDate(): self
    {
        if (self::INCLUDE_START_DATE === $this->startDatePresence) {
            return $this;
        }

        return new self($this->expression, $this->timezone, self::INCLUDE_START_DATE);
    }

    public function excludeStartDate(): self
    {
        if (self::EXCLUDE_START_DATE === $this->startDatePresence) {
            return $this;
        }

        return new self($this->expression, $this->timezone, self::EXCLUDE_START_DATE);
    }

    public function run(DateTimeInterface|string $startDate, int $nth = 0): DateTimeImmutable
    {
        $run = $this->toDateTimeImmutable($startDate);
        if (0 > $nth) {
            foreach ($this->yieldRunsBackward($run, $nth * -1) as $date);

            return $date ?? $run;
        }

        foreach ($this->yieldRunsForward($run, ++$nth) as $date);

        return $date ?? $run;
    }

    public function isDue(DateTimeInterface|string $when): bool
    {
        try {
            $when = $this->toDateTimeImmutable($when);

            return $this->nextRun($when, self::INCLUDE_START_DATE, false) === $when;
        } catch (Throwable) {
            return false;
        }
    }

    public function yieldRunsForward(DateTimeInterface|string $startDate, int $recurrences): Generator
    {
        if (0 > $recurrences) {
            throw SyntaxError::dueToNegativeRecurrences();
        }
        $i = 0;
        $startDate = $this->toDateTimeImmutable($startDate);
        $startDatePresence = $this->startDatePresence;
        while ($i < $recurrences) {
            try {
                $run = $this->nextRun($startDate, $startDatePresence, false);
                // @codeCoverageIgnoreStart
            } catch (UnableToProcessRun) {
                break;
            }
            // @codeCoverageIgnoreEnd
            yield $run;

            $startDate = ExpressionField::MINUTE->validator()->increment($run, $this->minuteFieldExpression);
            $startDatePresence = self::INCLUDE_START_DATE;
            ++$i;
        }
    }

    public function yieldRunsBackward(DateTimeInterface|string $endDate, int $recurrences): Generator
    {
        if (0 > $recurrences) {
            throw SyntaxError::dueToNegativeRecurrences();
        }

        $i = 0;
        $endDate = $this->toDateTimeImmutable($endDate);
        $startDatePresence = $this->startDatePresence;
        while ($i < $recurrences) {
            try {
                $run = $this->nextRun($endDate, $startDatePresence, true);
                // @codeCoverageIgnoreStart
            } catch (UnableToProcessRun) {
                break;
            }
            // @codeCoverageIgnoreEnd
            yield $run;

            $endDate = ExpressionField::MINUTE->validator()->decrement($run, $this->minuteFieldExpression);
            $startDatePresence = self::INCLUDE_START_DATE;
            ++$i;
        }
    }

    public function yieldRunsAfter(DateTimeInterface|string $startDate, DateInterval|string $interval): Generator
    {
        $startDate = $this->toDateTimeImmutable($startDate);

        return $this->runsAfter($startDate, $startDate->add($this->toDateInterval($interval)));
    }

    public function yieldRunsBefore(DateTimeInterface|string $endDate, DateInterval|string $interval): Generator
    {
        $endDate = $this->toDateTimeImmutable($endDate);

        return $this->runsBefore($endDate, $endDate->sub($this->toDateInterval($interval)));
    }

    public function yieldRunsBetween(DateTimeInterface|string $startDate, DateTimeInterface|string $endDate): Generator
    {
        $startDate = $this->toDateTimeImmutable($startDate);
        $endDate = $this->toDateTimeImmutable($endDate);

        if ($startDate <= $endDate) {
            return $this->runsAfter($startDate, $endDate);
        }

        return $this->runsBefore($startDate, $endDate);
    }

    /**
     * @throws SyntaxError
     */
    private function toDateTimeImmutable(DateTimeInterface|string $date): DateTimeImmutable
    {
        try {
            $currentDate = match (true) {
                $date instanceof DateTimeImmutable => $date,
                $date instanceof DateTime => DateTimeImmutable::createFromInterface($date),
                default => new DateTimeImmutable($date),
            };
            $currentDate = $currentDate->setTimezone($this->timezone);

            return $currentDate->setTime((int) $currentDate->format('H'), (int) $currentDate->format('i'));
        } catch (Throwable $exception) {
            throw SyntaxError::dueToInvalidDateString($date, $exception);
        }
    }

    /**
     * @throws SyntaxError
     */
    private function toDateInterval(DateInterval|string $interval): DateInterval
    {
        if ($interval instanceof DateInterval) {
            return $interval;
        }

        if (false === ($res = DateInterval::createFromDateString($interval))) {
            throw SyntaxError::dueToInvalidDateIntervalString($interval);
        }

        return $res;
    }

    /**
     * @throws CronError
     */
    private function runsAfter(DateTimeImmutable $startDate, DateTimeImmutable $endDate): Generator
    {
        if ($endDate < $startDate) {
            throw SyntaxError::dueToIntervalStartDate();
        }

        $startDatePresence = $this->startDatePresence;
        $run = $startDate;
        while ($endDate > $run) {
            try {
                $run = $this->nextRun($startDate, $startDatePresence, false);
                // @codeCoverageIgnoreStart
            } catch (UnableToProcessRun) {
                break;
            }
            // @codeCoverageIgnoreEnd

            if ($endDate < $run) {
                break;
            }

            yield $run;

            $startDate = ExpressionField::MINUTE->validator()->increment($run, $this->minuteFieldExpression);
            $startDatePresence = self::INCLUDE_START_DATE;
        }
    }

    /**
     * @throws CronError
     */
    private function runsBefore(DateTimeImmutable $endDate, DateTimeImmutable $startDate): Generator
    {
        if ($endDate < $startDate) {
            throw SyntaxError::dueToIntervalEndDate();
        }

        $startDatePresence = $this->startDatePresence;
        $run = $endDate;
        while ($startDate < $run) {
            try {
                $run = $this->nextRun($endDate, $startDatePresence, true);
                // @codeCoverageIgnoreStart
            } catch (UnableToProcessRun) {
                break;
            }
            // @codeCoverageIgnoreEnd

            if ($startDate > $run) {
                break;
            }

            yield $run;

            $endDate = ExpressionField::MINUTE->validator()->decrement($run, $this->minuteFieldExpression);
            $startDatePresence = self::INCLUDE_START_DATE;
        }
    }

    /**
     * Get the next or previous run date of the expression relative to a date.
     *
     * @param DateTimeImmutable $date Relative calculation date
     * @param int $startDatePresence Set to self::INCLUDE_START_DATE to return the start date if eligible
     *                               Set to self::EXCLUDE_START_DATE to never return the start date
     * @param bool $invert Set to TRUE to go backwards
     *
     * @throws CronError on too many iterations
     */
    private function nextRun(DateTimeImmutable $date, int $startDatePresence, bool $invert): DateTimeImmutable
    {
        if ($this->includeDayOfWeekAndDayOfMonthExpression) {
            return $this->dayOfWeekAndDayOfMonthNextRun($date, $invert);
        }

        $nextRun = $date;
        do {
            start:
            foreach ($this->calculatedFields as [$fieldExpression, $fieldValidator]) {
                if (!$fieldValidator->isSatisfiedBy($fieldExpression, $nextRun)) {
                    $nextRun = match ($invert) {
                        true => $fieldValidator->decrement($nextRun, $fieldExpression),
                        default => $fieldValidator->increment($nextRun, $fieldExpression),
                    };
                    goto start;
                }
            }

            if ($startDatePresence === self::INCLUDE_START_DATE || $nextRun !== $date) {
                return $nextRun;
            }

            $nextRun = match ($invert) {
                true => ExpressionField::MINUTE->validator()->decrement($nextRun, $this->minuteFieldExpression),
                default => ExpressionField::MINUTE->validator()->increment($nextRun, $this->minuteFieldExpression),
            };
        } while (1);
    }

    /**
     * @throws CronError
     */
    private function dayOfWeekAndDayOfMonthNextRun(DateTimeImmutable $startDate, bool $invert): DateTimeImmutable
    {
        $dayOfWeekScheduler = $this->withExpression($this->expression->withDayOfWeek('*'));
        $dayOfMonthScheduler = $this->withExpression($this->expression->withDayOfMonth('*'));

        $combinedRuns = match ($invert) {
            true => array_merge(
                iterator_to_array($dayOfMonthScheduler->yieldRunsBackward($startDate, 1), false),
                iterator_to_array($dayOfWeekScheduler->yieldRunsBackward($startDate, 1), false)
            ),
            default => array_merge(
                iterator_to_array($dayOfMonthScheduler->yieldRunsForward($startDate, 1), false),
                iterator_to_array($dayOfWeekScheduler->yieldRunsForward($startDate, 1), false)
            ),
        };

        usort(
            $combinedRuns,
            $invert ?
                fn (DateTimeInterface $a, DateTimeInterface $b): int => $b <=> $a :
                fn (DateTimeInterface $a, DateTimeInterface $b): int => $a <=> $b
        );

        return $combinedRuns[0];
    }
}
