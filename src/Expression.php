<?php

declare(strict_types=1);

namespace Bakame\Cron;

use JsonSerializable;
use Stringable;

final class Expression implements CronExpression, JsonSerializable, Stringable
{
    /** @var array<string, string> */
    private array $fields;

    public function __construct(string $expression)
    {
        $this->fields = ExpressionParser::parse($expression);
    }

    public static function __set_state(array $properties): self
    {
        return self::fromFields($properties['fields']);
    }

    /**
     * Returns an instance from an associative array.
     *
     * @param array<string, string|int> $fields
     *
     * @see ExpressionParser::build()
     */
    public static function fromFields(array $fields): self
    {
        return new self(ExpressionParser::build($fields));
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

    public function minute(): string
    {
        return $this->fields[ExpressionField::MINUTE->value];
    }

    public function hour(): string
    {
        return $this->fields[ExpressionField::HOUR->value];
    }

    public function dayOfMonth(): string
    {
        return $this->fields[ExpressionField::DAY_OF_MONTH->value];
    }

    public function month(): string
    {
        return $this->fields[ExpressionField::MONTH->value];
    }

    public function dayOfWeek(): string
    {
        return $this->fields[ExpressionField::DAY_OF_WEEK->value];
    }

    public function toString(): string
    {
        return implode(' ', $this->fields);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function jsonSerialize(): string
    {
        return $this->toString();
    }

    public function withMinute(string $fieldExpression): self
    {
        return $this->newInstance([ExpressionField::MINUTE->value => $fieldExpression] + $this->fields);
    }

    /**
     * @param array<string, string> $fields
     */
    private function newInstance(array $fields): self
    {
        $expression = ExpressionParser::build($fields);
        if ($expression === $this->toString()) {
            return $this;
        }

        return new self($expression);
    }

    public function withHour(string $fieldExpression): self
    {
        return $this->newInstance([ExpressionField::HOUR->value => $fieldExpression] + $this->fields);
    }

    public function withDayOfMonth(string $fieldExpression): self
    {
        return $this->newInstance([ExpressionField::DAY_OF_MONTH->value => $fieldExpression] + $this->fields);
    }

    public function withMonth(string $fieldExpression): self
    {
        return $this->newInstance([ExpressionField::MONTH->value => $fieldExpression] + $this->fields);
    }

    public function withDayOfWeek(string $fieldExpression): self
    {
        return $this->newInstance([ExpressionField::DAY_OF_WEEK->value => $fieldExpression] + $this->fields);
    }
}
