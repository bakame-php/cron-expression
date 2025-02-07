<?php

namespace Bakame\Cron;

use LogicException;
use Throwable;

final class AliasError extends LogicException implements CronError
{
    public static function dueToInvalidName(string $alias): self
    {
        return new self("The alias `$alias` is invalid. It must start with an `@` character and contain alphanumeric (letters, numbers, regardless of case) plus underscore (_).");
    }

    public static function dueToDuplicateEntry(string $alias): self
    {
        return new self("The alias `$alias` is already registered.");
    }

    public static function dueToInvalidExpression(string $expression, Throwable $exception): self
    {
        return new self("The expression `$expression` is invalid", 0, $exception);
    }

    public static function dueToForbiddenAliasRemoval(string $alias): self
    {
        return new self("The alias `$alias` is a built-in alias; it can not be unregistered.");
    }
}
