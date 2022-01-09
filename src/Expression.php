<?php

declare(strict_types=1);

namespace Bakame\Cron;

use JsonSerializable;
use Stringable;

final class Expression implements CronExpression, JsonSerializable, Stringable
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

    /** @var array<string, CronField> */
    private array $fields;

    /**
     * @throws ExpressionAliasError
     */
    public static function registerAlias(string $alias, string $expression): void
    {
        try {
            self::parse($expression);
        } catch (SyntaxError $exception) {
            throw ExpressionAliasError::dueToInvalidExpression($expression, $exception);
        }

        $alias = strtolower($alias);

        match (true) {
            1 !== preg_match('/^@([a-z0-9])+$/', $alias) => throw ExpressionAliasError::dueToInvalidName($alias),
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

    public function __construct(string $expression)
    {
        $this->fields = self::parse($expression);
    }

    /**
     * Parse a CRON expression string into its components.
     *
     * This method parses a CRON expression string and returns an associative array containing
     * all the CRON expression field.
     *
     * <code>
     * $fields = new Expression('3-59/15 2,6-12 *\/15 1 2-5');
     * var_export($fields->toArray());
     * //will display
     * array (
     *   'minute' => "3-59/15",   // CRON expression minute field
     *   'hour' => "2,6-12",      // CRON expression hour field
     *   'dayOfMonth' => "*\/15", // CRON expression day of month field
     *   'month' => "1",          // CRON expression month field
     *   'dayOfWeek' => "2-5",    // CRON expression day of week field
     * )
     * </code>
     *
     * @param string $expression The CRON expression to create.
     *
     * @throws SyntaxError If the string is invalid or unsupported
     *
     * @return array<string, CronField>
     */
    private static function parse(string $expression): array
    {
        $expression = self::$registeredAliases[strtolower($expression)] ?? $expression;
        /** @var array<int, string> $fields */
        $fields = preg_split('/\s/', $expression, -1, PREG_SPLIT_NO_EMPTY);
        if (count($fields) !== 5) {
            throw SyntaxError::dueToInvalidExpression($expression);
        }

        $errors = [];
        $computedFields = [];
        foreach ($fields as $position => $fieldExpression) {
            $offset = ExpressionField::fromOffset($position);
            try {
                $computedFields[$offset->value] = $offset->newCronField($fieldExpression);
            } catch (SyntaxError) {
                $errors[$offset->value] = $fieldExpression;
            }
        }

        if ([] !== $errors) {
            throw SyntaxError::dueToInvalidFieldExpression($errors);
        }

        return $computedFields;
    }

    /**
     * Generate an CRON expression from its parsed representation.
     *
     * If you supply your own array, you are responsible for providing
     * valid fields. If a required field is missing it will be replaced by the special `*` character
     *
     * @param array<string, string|int|CronField> $fields
     *
     * @throws SyntaxError If the fields array contains unknown or unsupported key fields
     */
    private static function build(array $fields): string
    {
        $fields = array_map(
            fn (CronField|string|int $f): string => $f instanceof CronField ? $f->toString() : (string) $f,
            $fields
        );

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

        $fields += $defaultValues;

        return implode(' ', [
            $fields[ExpressionField::MINUTE->value],
            $fields[ExpressionField::HOUR->value],
            $fields[ExpressionField::DAY_OF_MONTH->value],
            $fields[ExpressionField::MONTH->value],
            $fields[ExpressionField::DAY_OF_WEEK->value],
        ]);
    }

    public static function __set_state(array $properties): self
    {
        return self::fromFields($properties['fields']);
    }

    /**
     * Returns an instance from an associative array.
     *
     * @param array<string, string|int|CronField> $fields
     */
    public static function fromFields(array $fields): self
    {
        return new self(self::build($fields));
    }

    /**
     * Returns the Cron expression for running once a year, midnight, Jan. 1 - 0 0 1 1 *.
     */
    public static function yearly(): self
    {
        return new self('@yearly');
    }

    /**
     * Returns the Cron expression for running once a month, midnight, first of month - 0 0 1 * *.
     */
    public static function monthly(): self
    {
        return new self('@monthly');
    }

    /**
     * Returns the Cron expression for running once a week, midnight on Sun - 0 0 * * 0.
     */
    public static function weekly(): self
    {
        return new self('@weekly');
    }

    /**
     * Returns the Cron expression for running once a day, midnight - 0 0 * * *.
     */
    public static function daily(): self
    {
        return new self('@daily');
    }

    /**
     * Returns the Cron expression for running once an hour, first minute - 0 * * * *.
     */
    public static function hourly(): self
    {
        return new self('@hourly');
    }

    public function fields(): array
    {
        return $this->fields;
    }

    public function minute(): CronField
    {
        return $this->fields[ExpressionField::MINUTE->value];
    }

    public function hour(): CronField
    {
        return $this->fields[ExpressionField::HOUR->value];
    }

    public function dayOfMonth(): CronField
    {
        return $this->fields[ExpressionField::DAY_OF_MONTH->value];
    }

    public function month(): CronField
    {
        return $this->fields[ExpressionField::MONTH->value];
    }

    public function dayOfWeek(): CronField
    {
        return $this->fields[ExpressionField::DAY_OF_WEEK->value];
    }

    public function toString(): string
    {
        return implode(' ', $this->toArray());
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function jsonSerialize(): string
    {
        return $this->toString();
    }

    /**
     * @return array<string>
     */
    public function toArray(): array
    {
        return array_map(fn (CronField $f): string => $f->toString(), $this->fields);
    }

    public function withMinute(CronField|string $fieldExpression): self
    {
        $fieldExpression = match (true) {
            $fieldExpression instanceof MinuteField => $fieldExpression,
            $fieldExpression instanceof CronField => new MinuteField($fieldExpression->toString()),
            default => new MinuteField($fieldExpression),
        };

        return $this->newInstance([ExpressionField::MINUTE->value => $fieldExpression] + $this->fields);
    }

    /**
     * @param array<string, CronField> $fields
     */
    private function newInstance(array $fields): self
    {
        $expression = self::build($fields);
        if ($expression === $this->toString()) {
            return $this;
        }

        return new self($expression);
    }

    public function withHour(CronField|string $fieldExpression): self
    {
        $fieldExpression = match (true) {
            $fieldExpression instanceof HourField => $fieldExpression,
            $fieldExpression instanceof CronField => new HourField($fieldExpression->toString()),
            default => new HourField($fieldExpression),
        };

        return $this->newInstance([ExpressionField::HOUR->value => $fieldExpression] + $this->fields);
    }

    public function withDayOfMonth(CronField|string $fieldExpression): self
    {
        $fieldExpression = match (true) {
            $fieldExpression instanceof DayOfMonthField => $fieldExpression,
            $fieldExpression instanceof CronField => new DayOfMonthField($fieldExpression->toString()),
            default => new DayOfMonthField($fieldExpression),
        };

        return $this->newInstance([ExpressionField::DAY_OF_MONTH->value => $fieldExpression] + $this->fields);
    }

    public function withMonth(CronField|string $fieldExpression): self
    {
        $fieldExpression = match (true) {
            $fieldExpression instanceof MonthField => $fieldExpression,
            $fieldExpression instanceof CronField => new MonthField($fieldExpression->toString()),
            default => new MonthField($fieldExpression),
        };

        return $this->newInstance([ExpressionField::MONTH->value => $fieldExpression] + $this->fields);
    }

    public function withDayOfWeek(CronField|string $fieldExpression): self
    {
        $fieldExpression = match (true) {
            $fieldExpression instanceof DayOfWeekField => $fieldExpression,
            $fieldExpression instanceof CronField => new DayOfWeekField($fieldExpression->toString()),
            default => new DayOfWeekField($fieldExpression),
        };

        return $this->newInstance([ExpressionField::DAY_OF_WEEK->value => $fieldExpression] + $this->fields);
    }
}
