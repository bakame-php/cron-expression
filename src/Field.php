<?php

namespace Bakame\Cron;

enum Field: string
{
    case MINUTE = 'minute';
    case HOUR = 'hour';
    case DAY_OF_MONTH = 'dayOfMonth';
    case MONTH = 'month';
    case DAY_OF_WEEK = 'dayOfWeek';
}
