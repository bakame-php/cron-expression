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
    private Expression $expression;
    private DateTimeZone $timezone;
    private StartDatePresence $startDatePresence;

    /** Internal variables to optimize runs calculation */

    /** @var array<string, CronField>  */
    private array $calculatedFields;
    private bool $includeDayOfWeekAndDayOfMonthExpression;

    /**
     * @throws CronError
     */
    public function __construct(
        Expression|string $expression,
        DateTimeZone|string $timezone,
        StartDatePresence $startDatePresence
    ) {
        $this->expression = $this->filterExpression($expression);
        $this->timezone = $this->filterTimezone($timezone);
        $this->startDatePresence = $startDatePresence;

        $this->initialize();
    }

    /**
     * @throws CronError
     */
    private function filterExpression(Expression|string $expression): Expression
    {
        if (!$expression instanceof Expression) {
            return Expression::fromString($expression);
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

    private function initialize(): void
    {
        $this->calculatedFields = array_filter([
            ExpressionField::MONTH->value => $this->expression->month,
            ExpressionField::DAY_OF_MONTH->value => $this->expression->dayOfMonth,
            ExpressionField::DAY_OF_WEEK->value => $this->expression->dayOfWeek,
            ExpressionField::HOUR->value => $this->expression->hour,
            ExpressionField::MINUTE->value => $this->expression->minute,
        ], fn (CronField $f): bool => !in_array($f->toString(), ['*', '?'], true));

        $this->includeDayOfWeekAndDayOfMonthExpression = isset(
            $this->calculatedFields[ExpressionField::DAY_OF_MONTH->value],
            $this->calculatedFields[ExpressionField::DAY_OF_WEEK->value]
        );
    }

    /**
     * @throws CronError
     */
    public static function fromUTC(Expression|string $expression, StartDatePresence $startDatePresence = StartDatePresence::EXCLUDED): self
    {
        return new self($expression, new DateTimeZone('UTC'), $startDatePresence);
    }

    /**
     * @throws CronError
     */
    public static function fromSystemTimezone(Expression|string $expression, StartDatePresence $startDatePresence = StartDatePresence::EXCLUDED): self
    {
        return new self($expression, new DateTimeZone(date_default_timezone_get()), $startDatePresence);
    }

    public function expression(): Expression
    {
        return $this->expression;
    }

    public function timezone(): DateTimeZone
    {
        return $this->timezone;
    }

    public function isStartDateExcluded(): bool
    {
        return StartDatePresence::EXCLUDED === $this->startDatePresence;
    }

    /**
     * @throws CronError
     */
    public function withExpression(Expression|string $expression): self
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
        if (StartDatePresence::INCLUDED === $this->startDatePresence) {
            return $this;
        }

        return new self($this->expression, $this->timezone, StartDatePresence::INCLUDED);
    }

    public function excludeStartDate(): self
    {
        if (StartDatePresence::EXCLUDED === $this->startDatePresence) {
            return $this;
        }

        return new self($this->expression, $this->timezone, StartDatePresence::EXCLUDED);
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
            $date = $this->toDateTimeImmutable($when);

            return $this->nextRun($date, StartDatePresence::INCLUDED, Direction::FORWARD) === $date;
        } catch (Throwable) {
            return false;
        }
    }

    public function yieldRunsForward(DateTimeInterface|string $startDate, int $recurrences): Generator
    {
        return $this->runsFrom($this->toDateTimeImmutable($startDate), $recurrences, Direction::FORWARD);
    }

    public function yieldRunsBackward(DateTimeInterface|string $endDate, int $recurrences): Generator
    {
        return $this->runsFrom($this->toDateTimeImmutable($endDate), $recurrences, Direction::BACKWARD);
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

        set_error_handler(fn (int $errno, string $errstr): bool => true);
        if (false === ($res = DateInterval::createFromDateString($interval))) {
            throw SyntaxError::dueToInvalidDateIntervalString($interval);
        }
        restore_error_handler();

        return $res;
    }

    /**
     * @throws CronError
     */
    private function runsFrom(DateTimeImmutable $startDate, int $recurrences, Direction $direction): Generator
    {
        if (0 > $recurrences) {
            throw SyntaxError::dueToNegativeRecurrences();
        }

        $i = 0;
        $run = $startDate;
        $startDatePresence = $this->startDatePresence;
        $modifier = match ($direction) {
            Direction::BACKWARD => $this->expression->minute->decrement(...),
            Direction::FORWARD => $this->expression->minute->increment(...),
        };
        while ($i < $recurrences) {
            yield $this->nextRun($run, $startDatePresence, $direction);

            $run = $modifier($this->nextRun($run, $startDatePresence, $direction));
            $startDatePresence = StartDatePresence::INCLUDED;
            ++$i;
        }
    }

    /**
     * @throws CronError
     */
    private function runsAfter(DateTimeImmutable $startDate, DateTimeImmutable $endDate): Generator
    {
        if ($endDate < $startDate) {
            throw SyntaxError::dueToInvalidStartDate();
        }

        $startDatePresence = $this->startDatePresence;
        $run = $startDate;
        while ($endDate > $run) {
            $run = $this->nextRun($startDate, $startDatePresence, Direction::FORWARD);
            if ($endDate < $run) {
                break;
            }

            yield $run;

            $startDate = $this->expression->minute->increment($run);
            $startDatePresence = StartDatePresence::INCLUDED;
        }
    }

    /**
     * @throws CronError
     */
    private function runsBefore(DateTimeImmutable $endDate, DateTimeImmutable $startDate): Generator
    {
        if ($endDate < $startDate) {
            throw SyntaxError::dueToInvalidEndDate();
        }

        $startDatePresence = $this->startDatePresence;
        $run = $endDate;
        while ($startDate < $run) {
            $run = $this->nextRun($endDate, $startDatePresence, Direction::BACKWARD);
            if ($startDate > $run) {
                break;
            }

            yield $run;

            $endDate = $this->expression->minute->decrement($run);
            $startDatePresence = StartDatePresence::INCLUDED;
        }
    }

    /**
     * Get the next run date of the expression relative to a date.
     *
     * @throws CronError
     */
    private function nextRun(DateTimeImmutable $date, StartDatePresence $startDatePresence, Direction $direction): DateTimeImmutable
    {
        if ($this->includeDayOfWeekAndDayOfMonthExpression) {
            return $this->dayOfWeekAndDayOfMonthNextRun($date, $direction);
        }

        $modifier = match ($direction) {
            Direction::BACKWARD => $this->expression->minute->decrement(...),
            Direction::FORWARD => $this->expression->minute->increment(...),
        };

        $nextRun = $date;
        do {
            start:
            foreach ($this->calculatedFields as $field) {
                if (!$field->isSatisfiedBy($nextRun)) {
                    $nextRun = match ($direction) {
                        Direction::BACKWARD => $field->decrement($nextRun),
                        Direction::FORWARD => $field->increment($nextRun),
                    };
                    goto start;
                }
            }

            if ($startDatePresence === StartDatePresence::INCLUDED || $nextRun !== $date) {
                break;
            }
        } while ($nextRun = $modifier($nextRun));

        return $nextRun;
    }

    /**
     * Get the next run date of an expression containing a Day Of Week AND a Day of Month field relative to a date.
     *
     * @throws CronError
     */
    private function dayOfWeekAndDayOfMonthNextRun(DateTimeImmutable $startDate, Direction $direction): DateTimeImmutable
    {
        $dayOfWeekScheduler = $this->withExpression($this->expression->withDayOfWeek('*'));
        $dayOfMonthScheduler = $this->withExpression($this->expression->withDayOfMonth('*'));

        $combinedRuns = match ($direction) {
            Direction::BACKWARD => array_merge(
                iterator_to_array($dayOfMonthScheduler->yieldRunsBackward($startDate, 1), false),
                iterator_to_array($dayOfWeekScheduler->yieldRunsBackward($startDate, 1), false)
            ),
            Direction::FORWARD => array_merge(
                iterator_to_array($dayOfMonthScheduler->yieldRunsForward($startDate, 1), false),
                iterator_to_array($dayOfWeekScheduler->yieldRunsForward($startDate, 1), false)
            ),
        };

        usort(
            $combinedRuns,
            Direction::BACKWARD === $direction ?
                fn (DateTimeInterface $a, DateTimeInterface $b): int => $b <=> $a :
                fn (DateTimeInterface $a, DateTimeInterface $b): int => $a <=> $b
        );

        return $combinedRuns[0];
    }
}
