<?php

namespace Bakame\Cron;

use LogicException;

final class RegistrationError extends LogicException implements CronError
{
}
