<?php

declare(strict_types=1);

namespace Bakame\Cron;

use DateTimeImmutable;
use DateTimeInterface;
use JsonSerializable;

/**
 * Abstract CRON expression field.
 */
abstract class Field implements CronField, JsonSerializable
{
    protected const RANGE_START = 0;
    protected const RANGE_END = 0;

    /** Literal values we need to convert to integers. */
    protected array $literals = [];
    protected string $field;

    /**
     * @final
     */
    public function __construct(string|int $field)
    {
        $field = (string) $field;
        $this->validate($field);

        $this->field = $field;
    }

    /**
     * @final
     *
     * @param array{field:string} $properties
     */
    public static function __set_state(array $properties): static
    {
        return new static($properties['field']);
    }

    public function toString(): string
    {
        return $this->field;
    }

    public function jsonSerialize(): string
    {
        return $this->toString();
    }

    public function isSatisfiedBy(DateTimeInterface $date): bool
    {
        foreach (array_map(trim(...), explode(',', $this->field)) as $expression) {
            if ($this->isExpressionSatisfiedBy($expression, $date)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tells whether the specified date satisfies the field expression.
     */
    abstract protected function isExpressionSatisfiedBy(string $fieldExpression, DateTimeInterface $date): bool;

    /**
     * Validate a CRON expression field.
     */
    protected function validate(string $fieldExpression): void
    {
        $fieldExpression = $this->convertLiterals($fieldExpression);

        match (true) {
            '*' === $fieldExpression => null,
            str_contains($fieldExpression, ',') => $this->validateAllowedValues($fieldExpression),
            str_contains($fieldExpression, '/') => $this->validateIncrement($fieldExpression),
            str_contains($fieldExpression, '-') => $this->validateRange($fieldExpression),
            default => $this->validateDate($fieldExpression),
        };
    }

    final protected function wrapValidate(string $expression, string $sourceExpression): void
    {
        try {
            $this->validate($expression);
        } catch (SyntaxError $exception) {
            throw SyntaxError::dueToInvalidFieldExpression($sourceExpression, $this::class, $exception);
        }
    }

    /**
     * @return array<string>
     */
    protected function formatFieldRanges(string $fieldExpression): array
    {
        return array_map($this->convertLiterals(...), explode('-', $fieldExpression));
    }

    /**
     * @return array<int>
     */
    final protected function fullRanges(): array
    {
        return range(static::RANGE_START, static::RANGE_END);
    }

    /**
     * Check to see if a field is satisfied by a value.
     */
    final protected function isSatisfied(int $dateValue, string $value): bool
    {
        return match (true) {
            str_contains($value, '/') => $this->isInIncrementsOfRanges($dateValue, $value),
            str_contains($value, '-') => $this->isInRange($dateValue, $value),
            default => $value === '*' || $dateValue === (int) $value,
        };
    }

    /**
     * Tells whether a value is within a range.
     */
    protected function isInRange(int $dateValue, string $value): bool
    {
        [$first, $last] = array_map(fn (string $value): int => (int) $this->convertLiterals(trim($value)), explode('-', $value, 2));

        return $dateValue >= $first && $dateValue <= $last;
    }

    /**
     * Tells whether a value is within an increments of ranges (offset[-to]/step size).
     */
    protected function isInIncrementsOfRanges(int $dateValue, string $value): bool
    {
        [$range, $step] = array_map(trim(...), explode('/', $value, 2)) + [1 => '0'];

        $step = (int) $step;
        // Expand the * to a full range
        if ('*' === $range) {
            $range = static::RANGE_START.'-'.static::RANGE_END;
        }

        // Generate the requested small range
        [$rangeStart, $rangeEnd] = explode('-', $range, 2) + [1 => null];
        $rangeStart = (int) $rangeStart;
        $rangeEnd = (int) ($rangeEnd ?? $rangeStart);

        return in_array($dateValue, $this->activeRanges($rangeStart, $rangeEnd, $step), true);
    }

    /**
     * Steps larger than the range need to wrap around and be handled slightly differently than smaller steps.
     *
     * This is actually false. The C implementation will allow a
     * larger step as valid syntax, it never wraps around. It will stop
     * once it hits the end. Unfortunately this means in future versions
     * we will not wrap around. However, because the logic exists today
     * per the above documentation, fixing the bug from #89
     *
     * @return array<int>
     */
    protected function activeRanges(int $start, int $end, int $step): array
    {
        if ($step > static::RANGE_END) {
            $fullRange = $this->fullRanges();

            return [$fullRange[$step % count($fullRange)]];
        }

        if ($step > ($end - $start)) {
            return [$start];
        }

        return range($start, $end, $step);
    }

    protected function convertLiterals(string $value): string
    {
        if ([] === $this->literals) {
            return $value;
        }

        $key = array_search(strtoupper($value), $this->literals, true);
        if ($key === false) {
            return $value;
        }

        return (string) $key;
    }

    protected function computeTimeFieldRangeValue(int $currentValue, string $fieldExpression, bool $invert): int
    {
        /** @var array<int> $ranges */
        $ranges = array_reduce(
            str_contains($fieldExpression, ',') ? explode(',', $fieldExpression) : [$fieldExpression],
            fn (array $ranges, string $expression): array => array_merge($ranges, $this->getRangeValuesFromExpression($expression, static::RANGE_END)),
            []
        );

        return $ranges[$this->computeTimeFieldRangeOffset($currentValue, $ranges, $invert)];
    }

    /**
     * Returns a range of values for the given cron expression.
     *
     * @return array<int>
     */
    protected function getRangeValuesFromExpression(string $expression, int $max): array
    {
        $expression = $this->convertLiterals($expression);
        if (!str_contains($expression, '-') && !str_contains($expression, '/')) {
            return [(int) $expression];
        }

        if (!str_contains($expression, '/')) {
            [$offset, $to] = array_map($this->convertLiterals(...), explode('-', $expression));

            return $this->activeRanges((int) $offset, (int) $to, 1);
        }

        [$range, $step] = explode('/', $expression, 2) + [1 => 0];
        [$offset, $to] = explode('-', (string) $range, 2) + [1 => $max];

        return $this->activeRanges((int) $offset, (int) $to, (int) $step);
    }

    protected function computeTimeFieldRangeOffset(int $currentValue, array $references, bool $invert): int
    {
        $nbField = count($references);
        $position = $invert ? $nbField - 1 : 0;
        if ($nbField <= 1) {
            return $position;
        }

        for ($i = 0; $i < $nbField - 1; $i++) {
            if ((!$invert && $currentValue >= $references[$i] && $currentValue < $references[$i + 1]) ||
                ($invert && $currentValue > $references[$i] && $currentValue <= $references[$i + 1])) {
                return $invert ? $i : $i + 1;
            }
        }

        return $position;
    }

    final protected function toDateTimeImmutable(DateTimeInterface $date): DateTimeImmutable
    {
        if (!$date instanceof DateTimeImmutable) {
            return DateTimeImmutable::createFromInterface($date);
        }

        return $date;
    }

    protected function validateAllowedValues(string $fieldExpression): void
    {
        foreach (explode(',', $fieldExpression) as $listItem) {
            $this->wrapValidate($listItem, $fieldExpression);
        }
    }

    protected function validateIncrement(string $fieldExpression): void
    {
        [$range, $step] = explode('/', $fieldExpression);

        $this->wrapValidate($range, $fieldExpression);

        if (false === filter_var($step, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
            throw SyntaxError::dueToInvalidFieldExpression($fieldExpression, $this::class);
        }
    }

    protected function validateRange(string $fieldExpression): void
    {
        if (substr_count($fieldExpression, '-') > 1) {
            throw SyntaxError::dueToInvalidFieldExpression($fieldExpression, $this::class);
        }

        [$first, $last] = $this->formatFieldRanges($fieldExpression);
        if (in_array('*', [$first, $last], true)) {
            throw SyntaxError::dueToInvalidFieldExpression($fieldExpression, $this::class);
        }

        $this->wrapValidate($first, $fieldExpression);
        $this->wrapValidate($last, $fieldExpression);

        $firstInt = filter_var($first, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $lastInt = filter_var($last, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        if (false !== $firstInt && false !== $lastInt && ($lastInt < $firstInt)) {
            throw SyntaxError::dueToInvalidFieldExpression($fieldExpression, $this::class);
        }
    }

    protected function validateDate(string $fieldExpression): void
    {
        if (1 !== preg_match('/^\d+$/', $fieldExpression)
            || !in_array((int)$fieldExpression, $this->fullRanges(), true)) {
            throw SyntaxError::dueToInvalidFieldExpression($fieldExpression, $this::class);
        }
    }
}
