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
    private int $maxIterationCount;
    private int $startDatePresence;

    /** Internal variables to optimize calculating runs */

    /** @var array<string, array{0:string, 1:CronFieldValidator}>  */
    private array $calculatedFields;
    private bool $combineRuns = false;
    private string|null $minuteFieldExpression;

    public function __construct(
        CronExpression|string $expression,
        DateTimeZone|string $timezone,
        int $startDatePresence = Scheduler::EXCLUDE_START_DATE,
        int $maxIterationCount = 1000
    ) {
        $this->expression = $this->filterExpression($expression);
        $this->timezone = $this->filterTimezone($timezone);
        $this->startDatePresence = $this->filterStartDatePresence($startDatePresence);
        $this->maxIterationCount = $this->filterMaxIterationCount($maxIterationCount);
        $this->init();
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

    private function filterMaxIterationCount(int $maxIterationCount): int
    {
        if ($maxIterationCount < 0) {
            throw SyntaxError::dueToInvalidMaxIterationCount($maxIterationCount);
        }

        return $maxIterationCount;
    }

    private function filterStartDatePresence(int $startDatePresence): int
    {
        if (!in_array($startDatePresence, [self::EXCLUDE_START_DATE, self::INCLUDE_START_DATE], true)) {
            throw SyntaxError::dueToInvalidStartDatePresence();
        }

        return $startDatePresence;
    }

    private function init(): void
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

        $this->combineRuns = isset($this->calculatedFields[ExpressionField::MONTHDAY->value], $this->calculatedFields[ExpressionField::WEEKDAY->value]);
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

    public function maxIterationCount(): int
    {
        return $this->maxIterationCount;
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

        return new self($expression, $this->timezone, $this->startDatePresence, $this->maxIterationCount);
    }

    public function withTimezone(DateTimeZone|string $timezone): self
    {
        $timezone = $this->filterTimezone($timezone);
        if ($timezone->getName() === $this->timezone->getName()) {
            return $this;
        }

        return new self($this->expression, $timezone, $this->startDatePresence, $this->maxIterationCount);
    }

    public function withMaxIterationCount(int $maxIterationCount): self
    {
        if ($maxIterationCount === $this->maxIterationCount) {
            return $this;
        }

        return new self($this->expression, $this->timezone, $this->startDatePresence, $maxIterationCount);
    }

    public function includeStartDate(): self
    {
        if (self::INCLUDE_START_DATE === $this->startDatePresence) {
            return $this;
        }

        return new self($this->expression, $this->timezone, self::INCLUDE_START_DATE, $this->maxIterationCount);
    }

    public function excludeStartDate(): self
    {
        if (self::EXCLUDE_START_DATE === $this->startDatePresence) {
            return $this;
        }

        return new self($this->expression, $this->timezone, self::EXCLUDE_START_DATE, $this->maxIterationCount);
    }

    public function run(DateTimeInterface|string $startDate, int $nth = 0): DateTimeImmutable
    {
        $invert = false;
        if (0 > $nth) {
            $nth = ($nth * -1) - 1;
            $invert = true;
        }

        return $this->calculateRun($startDate, $nth, $this->startDatePresence, $invert);
    }

    public function isDue(DateTimeInterface|string $when): bool
    {
        try {
            return $this->calculateRun($when, 0, self::INCLUDE_START_DATE, false) == $this->toDateTimeImmutable($when);
        } catch (Throwable) {
            return false;
        }
    }

    public function yieldRunsForward(DateTimeInterface|string $startDate, int $recurrences): Generator
    {
        $max = max(0, $recurrences);
        $i = 0;
        $fieldValidator = ExpressionField::MINUTE->validator();
        while ($i < $max) {
            try {
                $res = $this->calculateRun($startDate, 0, $this->startDatePresence, false);
            } catch (UnableToProcessRun) {
                break;
            }
            yield $res;

            $startDate = $fieldValidator->increment($res, $this->minuteFieldExpression);
            ++$i;
        }
    }

    public function yieldRunsBackward(DateTimeInterface|string $endDate, int $recurrences): Generator
    {
        $max = max(0, $recurrences);
        $i = 0;
        $fieldValidator = ExpressionField::MINUTE->validator();
        while ($i < $max) {
            try {
                $res = $this->calculateRun($endDate, 0, $this->startDatePresence, true);
            } catch (UnableToProcessRun) {
                break;
            }
            yield $res;

            $endDate = $fieldValidator->decrement($res, $this->minuteFieldExpression);
            ++$i;
        }
    }

    public function yieldRunsAfter(DateTimeInterface|string $startDate, DateInterval|string $interval): Generator
    {
        $startDate = $this->toDateTimeImmutable($startDate);
        $endDate = $startDate->add($this->toDateInterval($interval));
        if ($endDate < $startDate) {
            throw new SyntaxError('The start date MUST be lesser than or equal to the end date.');
        }

        $fieldValidator = ExpressionField::MINUTE->validator();
        $presence = $this->startDatePresence;
        $nextRun = $startDate;
        while ($endDate > $nextRun) {
            try {
                $nextRun = $this->calculateRun($startDate, 0, $presence, false);
            } catch (UnableToProcessRun) {
                break;
            }

            if ($endDate < $nextRun) {
                break;
            }

            yield $nextRun;

            $startDate = $fieldValidator->increment($nextRun, $this->minuteFieldExpression);
            $presence = self::INCLUDE_START_DATE;
        }
    }

    public function yieldRunsBefore(DateTimeInterface|string $endDate, DateInterval|string $interval): Generator
    {
        $endDate = $this->toDateTimeImmutable($endDate);
        $startDate = $endDate->sub($this->toDateInterval($interval));
        if ($endDate < $startDate) {
            throw new SyntaxError('The end date MUST be greater than or equal to the start date.');
        }

        $fieldValidator = ExpressionField::MINUTE->validator();
        $presence = $this->startDatePresence;
        $nextRun = $endDate;
        while ($startDate < $nextRun) {
            try {
                $nextRun = $this->calculateRun($endDate, 0, $presence, true);
            } catch (UnableToProcessRun) {
                break;
            }

            if ($startDate > $nextRun) {
                break;
            }

            yield $nextRun;

            $endDate = $fieldValidator->decrement($nextRun, $this->minuteFieldExpression);
            $presence = self::INCLUDE_START_DATE;
        }
    }

    public function yieldRunsBetween(DateTimeInterface|string $startDate, DateTimeInterface|string $endDate): Generator
    {
        $startDate = $this->toDateTimeImmutable($startDate);
        $endDate = $this->toDateTimeImmutable($endDate);

        if ($endDate >= $startDate) {
            return $this->yieldRunsAfter($startDate, $startDate->diff($endDate));
        }

        return $this->yieldRunsBefore($startDate, $endDate->diff($startDate));
    }

    /**
     * Get the next or previous run date of the expression relative to a date.
     *
     * @param DateTimeInterface|string $startDate Relative calculation date
     * @param int $nth Number of matches to skip before returning
     * @param int $startDatePresence Set to self::INCLUDE_START_DATE to return the start date if eligible
     *                               Set to self::EXCLUDE_START_DATE to never return the start date
     * @param bool $invert Set to TRUE to go backwards
     *
     * @throws CronError on too many iterations
     */
    private function calculateRun(DateTimeInterface|string $startDate, int $nth, int $startDatePresence, bool $invert): DateTimeImmutable
    {
        $startDate = $this->toDateTimeImmutable($startDate);
        if ($this->combineRuns) {
            return $this->combineRuns($nth, $startDate, $invert);
        }

        $nextRun = $startDate;
        $i = 0;
        $minuteFieldValidator = ExpressionField::MINUTE->validator();
        while ($i < $this->maxIterationCount) {
            start:
            foreach ($this->calculatedFields as [$fieldExpression, $fieldValidator]) {
                if (!$fieldValidator->isSatisfiedBy($fieldExpression, $nextRun)) {
                    $nextRun = match ($invert) {
                        true => $fieldValidator->decrement($nextRun, $fieldExpression),
                        default => $fieldValidator->increment($nextRun, $fieldExpression),
                    };
                    ++$i;
                    goto start;
                }
            }

            if (($startDatePresence === self::INCLUDE_START_DATE || $nextRun != $startDate) && 0 > --$nth) {
                return $nextRun;
            }

            $nextRun = match ($invert) {
                true => $minuteFieldValidator->decrement($nextRun, $this->minuteFieldExpression),
                default => $minuteFieldValidator->increment($nextRun, $this->minuteFieldExpression),
            };
            ++$i;
        }

        // @codeCoverageIgnoreStart
        throw UnableToProcessRun::dueToMaxIterationCountReached($this->maxIterationCount);
        // @codeCoverageIgnoreEnd
    }

    /**
     * @throws CronError
     */
    private function combineRuns(int $nth, DateTimeImmutable $startDate, bool $invert): DateTimeImmutable
    {
        $dayOfWeekScheduler = $this->withExpression($this->expression->withDayOfWeek('*'));
        $dayOfMonthScheduler = $this->withExpression($this->expression->withDayOfMonth('*'));

        $combinedRuns = match ($invert) {
            true => array_merge(
                iterator_to_array($dayOfMonthScheduler->yieldRunsBackward($startDate, $nth + 1), false),
                iterator_to_array($dayOfWeekScheduler->yieldRunsBackward($startDate, $nth + 1), false)
            ),
            default => array_merge(
                iterator_to_array($dayOfMonthScheduler->yieldRunsForward($startDate, $nth + 1), false),
                iterator_to_array($dayOfWeekScheduler->yieldRunsForward($startDate, $nth + 1), false)
            ),
        };

        usort($combinedRuns, fn (DateTimeInterface $a, DateTimeInterface $b): int => $a <=> $b);

        return $combinedRuns[$nth];
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
            throw SyntaxError::dueToInvalidDate($date, $exception);
        }
    }

    private function toDateInterval(DateInterval|string $interval): DateInterval
    {
        if ($interval instanceof DateInterval) {
            return $interval;
        }

        if (false === ($res = DateInterval::createFromDateString($interval))) {
            throw new SyntaxError('The string is not a valid `DateInterval::createFromDateString` input.');
        }

        return $res;
    }
}
