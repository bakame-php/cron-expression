<?php

declare(strict_types=1);

namespace Bakame\Cron;

/**
 * CRON expression object.
 */
interface CronExpression
{
    /**
     * Returns the CRON expression fields as array.
     *
     * @return array<string, CronField>
     */
    public function fields(): array;

    /**
     * Returns the minute field of the CRON expression.
     */
    public function minute(): CronField;

    /**
     * Returns the hour field of the CRON expression.
     */
    public function hour(): CronField;

    /**
     * Returns the day of the month field of the CRON expression.
     */
    public function dayOfMonth(): CronField;

    /**
     * Returns the month field of the CRON expression.
     */
    public function month(): CronField;

    /**
     * Returns the day of the week field of the CRON expression.
     */
    public function dayOfWeek(): CronField;

    /**
     * Returns the string representation of the CRON expression.
     */
    public function toString(): string;

    /**
     * Set the minute field of the CRON expression.
     *
     * @throws CronError if the value is not valid for the part
     *
     */
    public function withMinute(CronField|string $fieldExpression): self;

    /**
     * Set the hour field of the CRON expression.
     *
     * @throws CronError if the value is not valid for the part
     *
     */
    public function withHour(CronField|string $fieldExpression): self;

    /**
     * Set the day of month field of the CRON expression.
     *
     * @throws CronError if the value is not valid for the part
     *
     */
    public function withDayOfMonth(CronField|string $fieldExpression): self;

    /**
     * Set the month field of the CRON expression.
     *
     * @throws CronError if the value is not valid for the part
     *
     */
    public function withMonth(CronField|string $fieldExpression): self;

    /**
     * Set the day of the week field of the CRON expression.
     *
     * @throws CronError if the value is not valid for the part
     *
     */
    public function withDayOfWeek(CronField|string $fieldExpression): self;
}
