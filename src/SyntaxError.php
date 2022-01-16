<?php

namespace Bakame\Cron;

use DateTimeInterface;
use InvalidArgumentException;
use Throwable;

final class SyntaxError extends InvalidArgumentException implements CronError
{
    private function __construct(string $message, Throwable|null $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function dueToInvalidExpression(string $expression): self
    {
        return new self("`$expression` is not a valid or a supported CRON expression.");
    }

    public static function dueToInvalidFieldExpression(string $fieldExpression, string $className, Throwable $throwable = null): self
    {
        return new self("Invalid or Unsupported CRON field expression value `$fieldExpression` according to `$className`.", $throwable);
    }

    public static function dueToInvalidDateString(DateTimeInterface|string $date, Throwable $exception): self
    {
        if ($date instanceof DateTimeInterface) {
            $date = $date->format('c');
        }

        return new self("The string `$date` is not a valid `DateTimeImmutable::__construct` input.", $exception);
    }

    public static function dueToInvalidDateTimeZoneString(string $timezone, Throwable $exception): self
    {
        return new self("The string `$timezone` is an unknown or a bad timezone name.", $exception);
    }


    public static function dueToInvalidDateIntervalString(string $interval): self
    {
        return new self('The string `'.$interval.'` is not a valid `DateInterval::createFromDateString` input.');
    }

    public static function dueToNegativeRecurrences(): self
    {
        return new self('The recurrence MUST be an integer greater or equal to 0.');
    }

    public static function dueToInvalidStartDate(): self
    {
        return new self('The start date MUST be lesser than or equal to the end date.');
    }

    public static function dueToInvalidEndDate(): self
    {
        return new self('The end date MUST be greater than or equal to the start date.');
    }

    /**
     * @param array<string|int> $fields
     */
    public static function dueToInvalidBuildFields(array $fields): self
    {
        return new self('The fields contain invalid offset names; expecting only the following fields: `'.implode('`, `', $fields).'`.');
    }
}
