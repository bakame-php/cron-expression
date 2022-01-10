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
    public function __construct(string $field)
    {
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
        foreach (array_map('trim', explode(',', $this->field)) as $expression) {
            if ($this->isSatisfiedExpression($expression, $date)) {
                return true;
            }
        }

        return false;
    }

    abstract protected function isSatisfiedExpression(string $fieldExpression, DateTimeInterface $date): bool;

    protected function validate(string $fieldExpression): void
    {
        $fieldExpression = $this->convertLiterals($fieldExpression);

        // All fields allow * as a valid value
        if ('*' === $fieldExpression) {
            return;
        }

        // Validate each chunk of a list individually
        if (str_contains($fieldExpression, ',')) {
            foreach (explode(',', $fieldExpression) as $listItem) {
                $this->wrapValidate($listItem, $fieldExpression);
            }
            return;
        }

        if (str_contains($fieldExpression, '/')) {
            [$range, $step] = explode('/', $fieldExpression);

            $this->wrapValidate($range, $fieldExpression);

            if (false === filter_var($step, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
                throw SyntaxError::dueToInvalidFieldExpression($fieldExpression, $this::class);
            }

            return;
        }

        if (str_contains($fieldExpression, '-')) {
            if (substr_count($fieldExpression, '-') > 1) {
                throw SyntaxError::dueToInvalidFieldExpression($fieldExpression, $this::class);
            }

            [$first, $last] = array_map([$this, 'convertLiterals'], explode('-', $fieldExpression));
            [$first, $last] = $this->formatFieldRanges($first, $last);
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

            return;
        }

        if (1 !== preg_match('/^\d+$/', $fieldExpression)
            || !in_array((int) $fieldExpression, $this->fullRanges(), true)) {
            throw SyntaxError::dueToInvalidFieldExpression($fieldExpression, $this::class);
        }
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
    protected function formatFieldRanges(string $first, string $last): array
    {
        return [$first, $last];
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
     * Test if a value is within a range.
     */
    protected function isInRange(int $dateValue, string $value): bool
    {
        [$first, $last] = array_map(fn (string $value): int => (int) $this->convertLiterals(trim($value)), explode('-', $value, 2));

        return $dateValue >= $first && $dateValue <= $last;
    }

    /**
     * Test if a value is within an increments of ranges (offset[-to]/step size).
     */
    protected function isInIncrementsOfRanges(int $dateValue, string $value): bool
    {
        [$range, $step] = array_map('trim', explode('/', $value, 2)) + [1 => '0'];

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
        if (str_contains($expression, ',')) {
            return array_reduce(
                explode(',', $expression),
                fn (array $values, string $range): array => array_merge($values, $this->getRangeValuesFromExpression($range, static::RANGE_END)),
                []
            );
        }

        if (!str_contains($expression, '-') && !str_contains($expression, '/')) {
            return [(int) $expression];
        }

        if (!str_contains($expression, '/')) {
            [$offset, $to] = array_map([$this, 'convertLiterals'], explode('-', $expression));
            $step = 1;
        } else {
            [$range, $step] = explode('/', $expression, 2) + [1 => 0];
            [$offset, $to] = explode('-', (string) $range, 2) + [1 => $max];
        }

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
}
