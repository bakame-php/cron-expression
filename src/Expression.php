<?php

declare(strict_types=1);

namespace Bakame\Cron;

use JsonSerializable;

final class Expression implements JsonSerializable
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
     * Registered a user defined CRON Expression Alias.
     *
     * @throws AliasError If the expression or the alias name are invalid
     *                    or if the alias is already registered.
     */
    public static function registerAlias(string $alias, string $expression): void
    {
        try {
            self::fromString($expression);
        } catch (CronError $exception) {
            throw AliasError::dueToInvalidExpression($expression, $exception);
        }

        $shortcut = strtolower($alias);

        match (true) {
            1 !== preg_match('/^@\w+$/', $shortcut) => throw AliasError::dueToInvalidName($alias),
            isset(self::$registeredAliases[$shortcut]) => throw AliasError::dueToDuplicateEntry($alias),
            default => self::$registeredAliases[$shortcut] = $expression,
        };
    }

    /**
     * Unregistered a user defined CRON Expression Alias.
     *
     * @throws AliasError If the user tries to unregister a built-in alias
     */
    public static function unregisterAlias(string $alias): bool
    {
        $shortcut = strtolower($alias);
        if (isset(self::DEFAULT_ALIASES[$shortcut])) {
            throw AliasError::dueToForbiddenAliasRemoval($alias);
        }

        if (!isset(self::$registeredAliases[$shortcut])) {
            return false;
        }

        unset(self::$registeredAliases[$alias]);

        return true;
    }

    /**
     * Tells whether a CRON Expression alias is registered.
     */
    public static function supportsAlias(string $alias): bool
    {
        return isset(self::$registeredAliases[strtolower($alias)]);
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
        public readonly MinuteField $minute,
        public readonly HourField $hour,
        public readonly DayOfMonthField $dayOfMonth,
        public readonly MonthField $month,
        public readonly DayOfWeekField $dayOfWeek,
    ) {
    }

    /**
     * @param array{minute:MinuteField, hour:HourField, dayOfMonth:DayOfMonthField, month:MonthField, dayOfWeek:DayOfWeekField} $properties
     */
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

    /**
     * Returns a new instance from a string.
     *
     * @throws CronError
     */
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
     * Returns a new instance from an associative array.
     *
     * @param array<string, string|int|CronField> $fields
     *
     * @throws CronError
     */
    public static function fromFields(array $fields): self
    {
        static $defaultValues;
        $defaultValues ??= [
            Field::MINUTE->value =>'*',
            Field::HOUR->value => '*',
            Field::DAY_OF_MONTH->value => '*',
            Field::MONTH->value => '*',
            Field::DAY_OF_WEEK->value => '*',
        ];

        if ([] !== array_diff_key($fields, $defaultValues)) {
            throw SyntaxError::dueToInvalidBuildFields(array_keys($defaultValues));
        }

        $fields = array_map(
            fn (CronField|string|int $field): string => $field instanceof CronField ? $field->toString() : (string) $field,
            $fields + $defaultValues
        );

        return new self(
            new MinuteField($fields[Field::MINUTE->value]),
            new HourField($fields[Field::HOUR->value]),
            new DayOfMonthField($fields[Field::DAY_OF_MONTH->value]),
            new MonthField($fields[Field::MONTH->value]),
            new DayOfWeekField($fields[Field::DAY_OF_WEEK->value]),
        );
    }

    /**
     * Returns the CRON expression Fields string in an associative array.
     *
     * @return array{minute:string, hour:string, dayOfMonth:string, month:string, dayOfWeek:string}
     */
    public function toFields(): array
    {
        return array_map(fn (CronField $f): string => $f->toString(), [
            Field::MINUTE->value => $this->minute,
            Field::HOUR->value => $this->hour,
            Field::DAY_OF_MONTH->value => $this->dayOfMonth,
            Field::MONTH->value => $this->month,
            Field::DAY_OF_WEEK->value => $this->dayOfWeek,
        ]);
    }

    /**
     * Returns the CRON expression string representation.
     */
    public function toString(): string
    {
        return implode(' ', $this->toFields());
    }

    public function jsonSerialize(): string
    {
        return $this->toString();
    }

    /**
     * Return an instance with the specified CRON expression minute field.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified CRON expression minute field.
     *
     * @throws CronError
     */
    public function withMinute(CronField|string|int $fieldExpression): self
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

    /**
     * Return an instance with the specified CRON expression hour field.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified CRON expression hour field.
     *
     * @throws CronError
     */
    public function withHour(CronField|string|int $fieldExpression): self
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

    /**
     * Return an instance with the specified CRON expression day of month field.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified CRON expression day of month field.
     *
     * @throws CronError
     */
    public function withDayOfMonth(CronField|string|int $fieldExpression): self
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

    /**
     * Return an instance with the specified CRON expression month field.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified CRON expression month field.
     *
     * @throws CronError
     */
    public function withMonth(CronField|string|int $fieldExpression): self
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

    /**
     * Return an instance with the specified CRON expression day of week field.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified CRON expression day of week field.
     *
     * @throws CronError
     */
    public function withDayOfWeek(CronField|string|int $fieldExpression): self
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
