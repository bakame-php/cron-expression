<?php

namespace Bakame\Cron;

use DateTimeInterface;
use InvalidArgumentException;
use Throwable;

final class SyntaxError extends InvalidArgumentException implements CronError
{
    /** @var array<string, string> */
    private array $errors;

    private function __construct(string $message, array $errors = [], Throwable|null $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->errors = $errors;
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public static function dueToInvalidPosition(string|int $position): self
    {
        return new self('`'.((int) $position + 1).'` is not a valid CRON expression position.');
    }

    public static function dueToInvalidExpression(string $expression): self
    {
        return new self('`'.$expression.'` is not a valid or a supported CRON expression');
    }

    /**
     * @param array<string> $errors
     */
    public static function dueToInvalidFieldValue(array $errors): self
    {
        $exception = new self('Invalid CRON expression value');
        $exception->errors = array_map(fn (string $invalidFieldValue): string => 'Invalid or unsupported value `'.$invalidFieldValue.'`.', $errors);

        return $exception;
    }

    public static function dueToInvalidDateString(DateTimeInterface|string $date, Throwable $exception): self
    {
        if ($date instanceof DateTimeInterface) {
            $date = $date->format('c');
        }

        return new self('The string `'.$date.'` is not a valid `DateTimeImmutable:::__construct` input.', [], $exception);
    }

    public static function dueToInvalidDateIntervalString(string $interval): self
    {
        return new self('The string `'.$interval.'` is not a valid `DateInterval::createFromDateString` input.');
    }

    public static function dueToInvalidStartDatePresence(): self
    {
        return new self('Unsupported or invalid start date presence value.');
    }

    public static function dueToInvalidWeekday(int|string $nth): self
    {
        return new self("Hashed weekdays must be numeric, $nth given");
    }

    public static function dueToUnsupportedWeekday(int|string $weekday): self
    {
        return new self("Weekday must be a value between 0 and 7. $weekday given");
    }

    public static function dueToOutOfRangeWeekday(int $nth): self
    {
        return new self("There are never more than 5 or less than 1 of a given weekday in a month, $nth given");
    }

    public static function dueToInvalidMaxIterationCount(int $count): self
    {
        return new self("maxIterationCount must be an integer greater or equal to 0. $count given");
    }

    public static function dueToIntervalStartDate(): self
    {
        return new self('The start date MUST be lesser than or equal to the end date.');
    }

    public static function dueToIntervalEndDate(): self
    {
        return new self('The end date MUST be greater than or equal to the start date.');
    }

    /**
     * @param array<string> $fields
     */
    public static function dueToInvalidBuildFields(array $fields): self
    {
        return new self('The fields contain invalid offset names; expecting only the following fields: `'.implode('`, `', $fields).'`.');
    }
}
