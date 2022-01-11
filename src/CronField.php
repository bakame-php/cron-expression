<?php

namespace Bakame\Cron;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * CRON field validator interface.
 */
interface CronField
{
    /**
     * Returns the CRON expression field string representation.
     */
    public function toString(): string;

    /**
     * Tells whether the specified DateTimeInterface object satisfies the CRON expression field.
     */
    public function isSatisfiedBy(DateTimeInterface $date): bool;

    /**
     * Increment a DateTimeInterface object by the unit of the CRON expression field.
     */
    public function increment(DateTimeInterface $date): DateTimeImmutable;

    /**
     * Decrement a DateTimeInterface object by the unit of the CRON expression field.
     */
    public function decrement(DateTimeInterface $date): DateTimeImmutable;
}
