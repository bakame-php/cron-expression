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
     * Returns the CRON field string representation.
     */
    public function toString(): string;

    /**
     * Check if the respective value of a DateTimeInterface object satisfies a CRON exp. field.
     */
    public function isSatisfiedBy(DateTimeInterface $date): bool;

    /**
     * This method is used to increment a DateTimeInterface object by the unit of the cron field.
     */
    public function increment(DateTimeInterface $date): DateTimeImmutable;

    /**
     * This method is used to decrement a DateTimeInterface object by the unit of the cron field.
     */
    public function decrement(DateTimeInterface $date): DateTimeImmutable;
}
