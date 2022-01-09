<?php

namespace Bakame\Cron;

enum ExpressionField: string
{
    case MINUTE = 'minute';
    case HOUR = 'hour';
    case DAY_OF_MONTH = 'dayOfMonth';
    case MONTH = 'month';
    case DAY_OF_WEEK = 'dayOfWeek';
}
