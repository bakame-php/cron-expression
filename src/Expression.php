<?php

declare(strict_types=1);

namespace Bakame\Cron;

use JsonSerializable;

final class Expression implements CronExpression, JsonSerializable
{
    /**
     * Predefined aliases which can be used to substitute the CRON expression:.
     *
     * `@yearly`, `@annually` - Run once a year, midnight, Jan. 1 - 0 0 1 1 *
     * `@monthly` - Run once a month, midnight, first of month - 0 0 1 * *
     * `@weekly` - Run once a week, midnight on Sun - 0 0 * * 0
     * `@daily`, `@midnight` - Run once a day, midnight - 0 0 * * *
     * `@hourly` - Run once an hour, first minute - 0 * * * *
     */
    private const DEFAULT_ALIASES = [
        '@yearly' => '0 0 1 1 *',
        '@annually' => '0 0 1 1 *',
        '@monthly' => '0 0 1 * *',
        '@weekly' => '0 0 * * 0',
        '@daily' => '0 0 * * *',
        '@midnight' => '0 0 * * *',
        '@hourly' => '0 * * * *',
    ];

    private static array $registeredAliases = self::DEFAULT_ALIASES;

    /**
     * @throws ExpressionAliasError
     */
    public static function registerAlias(string $alias, string $expression): void
    {
        try {
            self::fromString($expression);
        } catch (SyntaxError $exception) {
            throw ExpressionAliasError::dueToInvalidExpression($expression, $exception);
        }

        $alias = strtolower($alias);

        match (true) {
            1 !== preg_match('/^@\w+$/', $alias) => throw ExpressionAliasError::dueToInvalidName($alias),
            isset(self::$registeredAliases[$alias]) => throw ExpressionAliasError::dueToDuplicateEntry($alias),
            default => self::$registeredAliases[$alias] = $expression,
        };
    }

    public static function unregisterAlias(string $alias): void
    {
        if (isset(self::DEFAULT_ALIASES[$alias])) {
            throw ExpressionAliasError::dueToForbiddenAliasRemoval($alias);
        }

        unset(self::$registeredAliases[$alias]);
    }

    public static function supportsAlias(string $alias): bool
    {
        return isset(self::$registeredAliases[$alias]);
    }

    /**
     * Returns all registered aliases as an associated array where the aliases are the key
     * and their associated expressions are the values.
     *
     * @return array<string, string>
     */
    public static function aliases(): array
    {
        return self::$registeredAliases;
    }

    public function __construct(
        private MinuteField $minute,
        private HourField $hour,
        private DayOfMonthField $dayOfMonth,
        private MonthField $month,
        private DayOfWeekField $dayOfWeek,
    ) {
    }

    public static function __set_state(array $properties): self
    {
        return new self(
            $properties['minute'],
            $properties['hour'],
            $properties['dayOfMonth'],
            $properties['month'],
            $properties['dayOfWeek'],
        );
    }

    public static function fromString(string $expression): self
    {
        $expression = self::$registeredAliases[strtolower($expression)] ?? $expression;
        /** @var array<int, string> $fields */
        $fields = preg_split('/\s/', $expression, -1, PREG_SPLIT_NO_EMPTY);
        if (5 !== count($fields)) {
            throw SyntaxError::dueToInvalidExpression($expression);
        }

        return new self(
            new MinuteField($fields[0]),
            new HourField($fields[1]),
            new DayOfMonthField($fields[2]),
            new MonthField($fields[3]),
            new DayOfWeekField($fields[4]),
        );
    }

    /**
     * Returns an instance from an associative array.
     *
     * @param array<string, string|int|CronField> $fields
     */
    public static function fromFields(array $fields): self
    {
        static $defaultValues;
        $defaultValues ??= [
            ExpressionField::MINUTE->value =>'*',
            ExpressionField::HOUR->value => '*',
            ExpressionField::DAY_OF_MONTH->value => '*',
            ExpressionField::MONTH->value => '*',
            ExpressionField::DAY_OF_WEEK->value => '*',
        ];

        if ([] !== array_diff_key($fields, $defaultValues)) {
            throw SyntaxError::dueToInvalidBuildFields(array_keys($defaultValues));
        }

        $fields = array_map(
            fn (CronField|string|int $field): string => $field instanceof CronField ? $field->toString() : (string) $field,
            $fields + $defaultValues
        );

        return new self(
            new MinuteField($fields[ExpressionField::MINUTE->value]),
            new HourField($fields[ExpressionField::HOUR->value]),
            new DayOfMonthField($fields[ExpressionField::DAY_OF_MONTH->value]),
            new MonthField($fields[ExpressionField::MONTH->value]),
            new DayOfWeekField($fields[ExpressionField::DAY_OF_WEEK->value]),
        );
    }

    /**
     * Returns the Cron expression for running once a year, midnight, Jan. 1 - 0 0 1 1 *.
     */
    public static function yearly(): self
    {
        return self::fromString('@yearly');
    }

    /**
     * Returns the Cron expression for running once a month, midnight, first of month - 0 0 1 * *.
     */
    public static function monthly(): self
    {
        return self::fromString('@monthly');
    }

    /**
     * Returns the Cron expression for running once a week, midnight on Sun - 0 0 * * 0.
     */
    public static function weekly(): self
    {
        return self::fromString('@weekly');
    }

    /**
     * Returns the Cron expression for running once a day, midnight - 0 0 * * *.
     */
    public static function daily(): self
    {
        return self::fromString('@daily');
    }

    /**
     * Returns the Cron expression for running once an hour, first minute - 0 * * * *.
     */
    public static function hourly(): self
    {
        return self::fromString('@hourly');
    }

    public function fields(): array
    {
        return [
            ExpressionField::MINUTE->value => $this->minute,
            ExpressionField::HOUR->value => $this->hour,
            ExpressionField::DAY_OF_MONTH->value => $this->dayOfMonth,
            ExpressionField::MONTH->value => $this->month,
            ExpressionField::DAY_OF_WEEK->value => $this->dayOfWeek,
        ];
    }

    public function minute(): CronField
    {
        return $this->minute;
    }

    public function hour(): CronField
    {
        return $this->hour;
    }

    public function dayOfMonth(): CronField
    {
        return $this->dayOfMonth;
    }

    public function month(): CronField
    {
        return $this->month;
    }

    public function dayOfWeek(): CronField
    {
        return $this->dayOfWeek;
    }

    /**
     * @return array<string>
     */
    public function toArray(): array
    {
        return array_map(fn (CronField $f): string => $f->toString(), $this->fields());
    }

    public function toString(): string
    {
        return implode(' ', $this->toArray());
    }

    public function jsonSerialize(): string
    {
        return $this->toString();
    }

    public function withMinute(CronField|string $fieldExpression): self
    {
        $fieldExpression = match (true) {
            $fieldExpression instanceof MinuteField => $fieldExpression,
            $fieldExpression instanceof CronField => new MinuteField($fieldExpression->toString()),
            default => new MinuteField($fieldExpression),
        };

        if ($fieldExpression->toString() === $this->minute->toString()) {
            return $this;
        }

        return new self($fieldExpression, $this->hour, $this->dayOfMonth, $this->month, $this->dayOfWeek);
    }

    public function withHour(CronField|string $fieldExpression): self
    {
        $fieldExpression = match (true) {
            $fieldExpression instanceof HourField => $fieldExpression,
            $fieldExpression instanceof CronField => new HourField($fieldExpression->toString()),
            default => new HourField($fieldExpression),
        };

        if ($fieldExpression->toString() === $this->hour->toString()) {
            return $this;
        }

        return new self($this->minute, $fieldExpression, $this->dayOfMonth, $this->month, $this->dayOfWeek);
    }

    public function withDayOfMonth(CronField|string $fieldExpression): self
    {
        $fieldExpression = match (true) {
            $fieldExpression instanceof DayOfMonthField => $fieldExpression,
            $fieldExpression instanceof CronField => new DayOfMonthField($fieldExpression->toString()),
            default => new DayOfMonthField($fieldExpression),
        };

        if ($fieldExpression->toString() === $this->dayOfMonth->toString()) {
            return $this;
        }

        return new self($this->minute, $this->hour, $fieldExpression, $this->month, $this->dayOfWeek);
    }

    public function withMonth(CronField|string $fieldExpression): self
    {
        $fieldExpression = match (true) {
            $fieldExpression instanceof MonthField => $fieldExpression,
            $fieldExpression instanceof CronField => new MonthField($fieldExpression->toString()),
            default => new MonthField($fieldExpression),
        };

        if ($fieldExpression->toString() === $this->month->toString()) {
            return $this;
        }

        return new self($this->minute, $this->hour, $this->dayOfMonth, $fieldExpression, $this->dayOfWeek);
    }

    public function withDayOfWeek(CronField|string $fieldExpression): self
    {
        $fieldExpression = match (true) {
            $fieldExpression instanceof DayOfWeekField => $fieldExpression,
            $fieldExpression instanceof CronField => new DayOfWeekField($fieldExpression->toString()),
            default => new DayOfWeekField($fieldExpression),
        };

        if ($fieldExpression->toString() === $this->dayOfWeek->toString()) {
            return $this;
        }

        return new self($this->minute, $this->hour, $this->dayOfMonth, $this->month, $fieldExpression);
    }
}
