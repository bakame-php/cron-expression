<?php

namespace Cron;

use DateTime;
use DateTimeInterface;

/**
 * CRON field interface.
 */
interface FieldInterface
{
    /**
     * Check if the respective value of a DateTime field satisfies a CRON exp.
     *
     * @param DateTimeInterface $date  DateTime object to check
     * @param string            $value CRON expression to test against
     *
     * @return bool Returns TRUE if satisfied, FALSE otherwise
     */
    public function isSatisfiedBy(DateTimeInterface $date, string $value): bool;

    /**
     * When a CRON expression is not satisfied, this method is used to increment
     * or decrement a DateTime object by the unit of the cron field.
     *
     * @param DateTime    $date   DateTime object to change
     * @param bool        $invert (optional) Set to TRUE to decrement
     * @param string|null $parts  (optional) Set to TRUE to decrement
     *
     */
    public function increment(DateTime $date, bool $invert = false, string $parts = null): FieldInterface;

    /**
     * Validates a CRON expression for a given field.
     *
     * @param string $value CRON expression value to validate
     *
     * @return bool Returns TRUE if valid, FALSE otherwise
     */
    public function validate(string $value): bool;
}