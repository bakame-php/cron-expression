<?php

namespace Bakame\Cron;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Generator;

interface CronScheduler
{
    /**
     * Returns the CRON expression attached to the object.
     */
    public function expression(): Expression;

    /**
     * Returns the scheduler execution timezone.
     */
    public function timezone(): DateTimeZone;

    /**
     * Tells whether to include or not the relative time when calculating the next run If eligible.
     */
    public function isInitialDateExcluded(): bool;

    /**
     * Return an instance with the specified CRON expression.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified CRON expression.
     */
    public function withExpression(Expression $expression): self;

    /**
     * Return an instance with the specified Timezone.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified Timezone.
     */
    public function withTimezone(DateTimeZone $timezone): self;

    /**
     * Return an instance which includes the relative time when calculating the next run If eligible.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that includes the relative time when calculating the next run If eligible.
     */
    public function includeInitialDate(): self;

    /**
     * Return an instance which excludes the relative time when calculating the next run.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that excludes the relative time when calculating the next run.
     */
    public function excludeInitialDate(): self;

    /**
     * Determine if the cron is due to run based on a specific date.
     * This method assumes that the current number of seconds are irrelevant, and should be called once per minute.
     *
     * @param DateTimeInterface|string $when Specific date. If the date is expressed with a string,
     *                                       the scheduler will assume the date uses the underlying system timezone
     */
    public function isDue(DateTimeInterface|string $when): bool;

    /**
     * Get a run date relative to a specific date.
     *
     * @param DateTimeInterface|string $startDate Relative calculation date
     *                                            If the date is expressed with a string,
     *                                            the scheduler will assume the date uses the underlying system timezone
     *
     * @param int $nth Number of matches to skip before returning a matching next run date. 0, the default, will
     *                 return the current date and time if the next run date falls on the current date and time.
     *                 Setting this value to 1 will skip the first match and go to the second match.
     *                 Setting this value to 2 will skip the first 2 matches and so on.
     *                 If the number is negative the skipping will be done backward.
     *
     * @throws CronError on too many iterations
     */
    public function run(DateTimeInterface|string $startDate, int $nth = 0): DateTimeImmutable;

    /**
     * Returns multiple run dates ending at most at the specific ending date and ending at most after the specified
     * interval.
     *
     * @param DateTimeInterface|string $endDate End of calculation date If the date is expressed with a string,
     *                                          the scheduler will assume the date uses the underlying system timezone
     * @param DateInterval|string $interval Duration. If the interval is express with a string,
     *                                      the scheduler will resolve it using DateInterval::createFromDateString
     *
     * @throws CronError
     *
     * @return Generator<DateTimeImmutable>
     */
    public function yieldRunsBefore(DateTimeInterface|string $endDate, DateInterval|string $interval): Generator;

    /**
     * Returns multiple run dates starting at most at the specific starting date and ending at most after the specified
     * interval.
     *
     * @param DateTimeInterface|string $startDate Start of calculation date. If the date is expressed with a string,
     *                                            the scheduler will assume the date uses the underlying system timezone
     * @param DateInterval|string $interval Duration. If the interval is express with a string,
     *                                      the scheduler will resolve it using DateInterval::createFromDateString
     *
     * @throws CronError
     *
     * @return Generator<DateTimeImmutable>
     */
    public function yieldRunsAfter(DateTimeInterface|string $startDate, DateInterval|string $interval): Generator;

    /**
     * Returns multiple run between two endpoints: The generated runs will start at least at the specific starting date
     * and end at most at the specified end date. Depending on the start date and end date value the returned Generator
     * can list the runs backward.
     *
     * @param DateTimeInterface|string $startDate Start of calculation date. If the date is expressed with a string,
     *                                            the scheduler will assume the date uses the underlying system timezone
     * @param DateTimeInterface|string $endDate End of calculation date. If the date is expressed with a string,
     *                                          the scheduler will assume the date uses the underlying system timezone
     * @throws CronError
     *
     * @return Generator<DateTimeImmutable>
     */
    public function yieldRunsBetween(DateTimeInterface|string $startDate, DateTimeInterface|string $endDate): Generator;

    /**
     * Get multiple run dates starting at least at the current date or a specific date or after it.
     * The last generated date will be after or equal to the specified date.
     *
     * @param DateTimeInterface|string $startDate Relative calculation date. If the date is expressed with a string,
     *                                            the scheduler will assume the date uses the underlying system timezone
     * @param int $recurrences Set the total number of dates to calculate
     *
     * @throws CronError
     *
     * @return Generator<DateTimeImmutable>
     */
    public function yieldRunsForward(DateTimeInterface|string $startDate, int $recurrences): Generator;

    /**
     * Get multiple run dates ending at most at the specified start date or before it.
     * The last generated date will be before or equal to the specified date.
     *
     * @param DateTimeInterface|string $endDate Relative calculation date. If the date is expressed with a string,
     *                                          the scheduler will assume the date uses the underlying system timezone
     * @param int $recurrences Set the total number of dates to calculate
     *
     * @throws CronError
     * @return Generator<DateTimeImmutable>
     *
     *
     * @see CronScheduler::yieldRunsForward
     */
    public function yieldRunsBackward(DateTimeInterface|string $endDate, int $recurrences): Generator;
}
