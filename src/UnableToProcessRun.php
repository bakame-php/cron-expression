<?php

namespace Bakame\Cron;

use RuntimeException;

final class UnableToProcessRun extends RuntimeException implements CronError
{
    public static function dueToMaxIterationCountReached(int $iterationCount): self
    {
        return new self('Unable to perform the process as the max iteration count `'.$iterationCount.'` has been reached.');
    }
}
