Bakame Cron Expression
==========================

[![Latest Version](https://img.shields.io/github/release/bakame-php/cron-expression.svg?style=flat-square)](https://github.com/bakame-php/cron-expression/releases)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Build](https://github.com/bakame-php/cron-expression/workflows/build/badge.svg)](https://github.com/bakame-php/cron-expression/actions?query=workflow%3A%22build%22)

**NOTE** This is a fork with a major rewrite of [https://github.com/dragonmantank/cron-expression](https://github.com/dragonmantank/cron-expression) which is in turned
a fork of the original [https://github.com/mtdowling/cron-expression](https://github.com/mtdowling/cron-expression) package.  

To know more about cron expression your can look at the [Unix documentation](https://www.unix.com/man-page/linux/5/crontab/)

## Motivation

This package would not exist if the two listed packages were not around. While those packages are well known and used 
throughout the community I wanted to see if I could present an alternate way of dealing with cron expression.

The reason a fork was created instead of submitting PRs is because the changes to the public API are 
so important that it would have warranted multiples PR with some passing and other not. Hopefully, some ideas 
develop here can be re-use in the source packages.

## System Requirements

You need **PHP >= 8.1** but the latest stable version of PHP is recommended.

## Installing

### Using composer

Add the dependency to your project:

```bash
composer require bakame-php/cron
```

### Going solo

You can also use `Bakame\Cron` without using Composer by downloading the library and:

- using any other [PSR-4](http://www.php-fig.org/psr/psr-4/) compatible autoloader.
- using the bundle autoloader script as shown below:

~~~php
require 'path/to/bakame/cron/repo/autoload.php';

use Bakame\Cron\Expression;

Expression::fromString('@daily')->toString(); //display '0 0 * * *'
~~~

where `path/to/bakame/cron/repo` represents the path where the library was extracted.

## Usage

The following example illustrates some features of the package.

```php
use Bakame\Cron\Scheduler;
use Bakame\Cron\Expression;

Expression::registerAlias('@every_half_hour', '*/30 * * * *');

$scheduler = Scheduler::fromSystemTimezone('@every_half_hour');

$scheduler->isDue('2022-03-15 14:30:01'); // returns true
$scheduler->isDue('2022-03-15 14:29:59'); // returns false

$runs = $scheduler->yieldRunsBetween(new DateTime('2019-10-10 23:29:25'), '2019-10-11 01:30:25');
var_export(array_map(
    fn (DateTimeImmutable $d): string => $d->format('Y-m-d H:i:s'), 
    iterator_to_array($runs, false)
));
// array (
//   0 => '2019-10-10 23:30:00',
//   1 => '2019-10-11 00:00:00',
//   2 => '2019-10-11 00:30:00',
//   3 => '2019-10-11 01:00:00',
//   4 => '2019-10-11 01:30:00',
// )
```

### Calculating the running time

#### Instantiating the Scheduler Immutable Value Object

To determine the next running time for a CRON expression the package uses the `Bakame\Cron\Scheduler` class.  
To work as expected this class requires:

- a CRON Expression ( as a string or as a `Expression` object)
- a timezone as a PHP `DateTimeZone` instance or the timezone string name.
- to know if the `startDate` if eligible should be present in the results via a `DatePresence` enum with two values `DatePresence::INCLUDED` and `DatePresence::EXCLUDED`.

To ease instantiating the `Scheduler`, it comes bundle with two named constructors around timezone usage:

- `Scheduler::fromUTC`: instantiate a scheduler using the `UTC` timezone.
- `Scheduler::fromSystemTimezone`: instantiate a scheduler using the underlying system timezone

**Both named constructors exclude by default the start date from the results.**

```php
<?php

use Bakame\Cron\Expression;
use Bakame\Cron\Scheduler;
use Bakame\Cron\DatePresence;

require_once '/vendor/autoload.php';

// You can define all properties on instantiation
$expression = '0 7 * * *';
$timezone = 'UTC';
$scheduler1 = new Scheduler(Expression::fromString($expression), new DateTimeZone($timezone), DatePresence::INCLUDED);
$scheduler2 = new Scheduler($expression, $timezone, DatePresence::INCLUDED);
$scheduler3 = Scheduler::fromUTC($expression, DatePresence::INCLUDED);

//all these instantiated object are equals.
```

The `Scheduler` public API methods accept:
- `string`, `DateTime` or `DateTimeImmutable` object to represent a date object;
- `string`, `DateInterval` object to represent a date interval object;
- positive integers or `0` to represent recurrences or skipped occurrences;

Any other type will trigger an exception on usage. And for the methods that do returns
date object they will always return `DateTimeImmutable` objects with the Scheduler specified `DateTimeZone`.

#### Knowing if a CRON expression will run at a specific date

The `Scheduler::isDue` method can tell whether a specific CRON is due to run on a specific date.

```php
$scheduler = Scheduler::fromSystemTimezone(Expression::fromString('* * * * MON#1'));
$scheduler->isDue(new DateTime('2014-04-07 00:00:00')); // returns true
$scheduler->isDue('NOW'); // returns false 
```

#### Finding a running date for a CRON expression

The `Scheduler::run` method allows finding the following run date according to a specific date.

```php
$scheduler = new Scheduler('@daily', 'Africa/Kigali', DatePresence::EXCLUDED);
$run = $scheduler->run(new Carbon\CarbonImmutable('now'));
echo $run->format('Y-m-d H:i:s, e'), PHP_EOL;
//display 2021-12-29 00:00:00, Africa/Kigali
echo $run::class;
//display Carbon\CarbonImmutable
```

The `Scheduler::run` method allows specifying the number of matches to skip before calculating the next run.

```php
$scheduler = new Scheduler(Expression::fromString('@daily'), 'Africa/Kigali', DatePresence::EXCLUDED);
echo $scheduler->run('now', 3)->format('Y-m-d H:i:s, e'), PHP_EOL;
//display 2022-01-01 00:00:00, Africa/Kigali
```

The `Scheduler::run` method accepts negative number if you want to get a run date in the past.

```php
$scheduler = new Scheduler(Expression::fromString('@daily'), 'Africa/Kigali', DatePresence::EXCLUDED);
echo $scheduler->run('2022-01-01 00:00:00', -2)->format('Y-m-d H:i:s, e'), PHP_EOL;
//display 2021-12-31 00:00:00, Africa/Kigali
```

Use the `DatePresence` enum on `Scheduler` instantiation or the appropriate configuration method
to allow `Scheduler` methods to include the start date in their result if eligible.

- `Scheduler::includeInitialDate` (will include the start date if eligible)
- `Scheduler::excludeInitialDate` (will exclude the start date)

Because the `Scheduler` is an immutable object anytime a configuration settings is changed a new object is
returned instead of modifying the current object.

```php
$date = new DateTimeImmutable('2022-01-01 00:04:00', new DateTimeZone('Asia/Shanghai'));
$scheduler = new Scheduler('4-59/2 * * * *', 'Asia/Shanghai', DatePresence::EXCLUDED);
echo $scheduler->run($date)->format('Y-m-d H:i:s, e'), PHP_EOL;
//display 2022-01-01 00:06:00, Asia/Shanghai
echo $scheduler->includeInitialDate()->run($date)->format('Y-m-d H:i:s, e'), PHP_EOL;
//display 2022-01-01 00:04:00, Asia/Shanghai
```

### Iterating over multiple runs

You can iterate over a set of recurrent dates where the cron is supposed to run.
The iteration can be done forward or backward depending on the endpoints provided.
Like for other methods, the inclusion of the start date still depends on the scheduler configuration.

All listed methods hereafter returns a generator containing `DateTimeImmutable` objects.

#### Iterating forward using recurrences

The recurrences value should always be a positive integer or `0`. Any negative value will trigger an exception.

```php
$scheduler = Scheduler::fromSystemTimezone('30 0 1 * 1')->includeInitialDate();
$runs = $scheduler->yieldRunsForward(new DateTime('2019-10-10 23:20:00'), 5);
var_export(array_map(fn (DateTimeImmutable $d): string => $d->format('Y-m-d H:i:s'), iterator_to_array($runs, false)));
//returns
//array (
//  0 => '2019-10-14 00:30:00',
//  1 => '2019-10-21 00:30:00',
//  2 => '2019-10-28 00:30:00',
//  3 => '2019-11-01 00:30:00',
//  4 => '2019-11-04 00:30:00',
//)
```

#### Iterating backward using recurrences

The recurrences value should always be a positive integer or `0`. Any negative value will trigger an exception.

```php
$scheduler = Scheduler::fromSystemTimezone('30 0 1 * 1')->includeInitialDate();
$runs = $scheduler->yieldRunsBackward(new DateTime('2019-10-10 23:20:00'), 5);
```

#### Iterating using a starting date and an interval

The interval value should always be a positive `DateInterval`. Any negative value will trigger an exception.

```php
$scheduler = Scheduler::fromSystemTimezone('30 0 1 * 1')->includeInitialDate();
$runs = $scheduler->yieldRunsAfter('2019-10-10 23:20:00', new DateInterval('P1D'));
```

#### Iterating using an end date and an interval

The interval value should always be a positive `DateInterval`. Any negative value will trigger an exception.

```php
$scheduler = Scheduler::fromSystemTimezone('30 0 1 * 1')->includeInitialDate();
$runs = $scheduler->yieldRunsBefore('2019-10-10 23:20:00', '1 DAY');
```

#### Iterating using a starting date and an end date

If the start date in greater than the end date the `DateTimeImmutable` objects will be returned backwards.

```php
$scheduler = Scheduler::fromSystemTimezone('30 0 1 * 1')->includeInitialDate();
$runs = $scheduler->yieldRunsBetween('2019-10-10 23:20:00', '2019-09-09 00:30:00');
```

The `Bakame\Cron\Scheduler` object exposes the CRON expression using the `Bakame\Cron\Expression` immutable value object.

### Handling CRON Expression

To work as intended, The `Bakame\Cron\Expression` resolves CRON Expression as they are described in the [CRONTAB documentation](https://www.unix.com/man-page/linux/5/crontab/)

A CRON expression is a string representing the schedule for a particular command to execute.  The parts of a CRON schedule are as follows:

    *    *    *    *    *
    -    -    -    -    -
    |    |    |    |    |
    |    |    |    |    |
    |    |    |    |    +----- day of week (0 - 7) (Sunday=0 or 7)
    |    |    |    +---------- month (1 - 12)
    |    |    +--------------- day of month (1 - 31)
    |    +-------------------- hour (0 - 23)
    +------------------------- min (0 - 59)

The `Expression` value object also supports the following notations:

- **L:** stands for "last" and specifies the last day of the month;
- **W:** is used to specify the weekday (Monday-Friday) nearest the given day;
- **#:** allowed for the `dayOfWeek` field, and must be followed by a number between one and five;
- range, split notations as well as the **?** character;

#### Instantiation

By instantiating an `Expression` object you are validating its associated CRON Expression Field. Each CRON expression field
is validated via a `CronField` implementing object:

```php
<?php
use Bakame\Cron\MonthField;
$field = new MonthField('JAN'); //!works
$field = new MonthField(23);    //will throw a SyntaxError
```

The package contains the following CRON expression Field value objects

- `MinuteField`
- `HourField`
- `DayOfMonthField`
- `MonthField`
- `DayOfWeekField`

It is possible to use those CRON expression Field value objects to instantiate an `Expression` instance:

```php
<?php
$expression = new Expression(
    new MinuteField('3-59/15'),
    new HourField('6-12'),
    new DayOfMonthField('*/15'),
    new MonthField('1'),
    new DayOfWeekField('2-5'),
);
$expression->toString(); // display 3-59/15 6-12 */15 1 2-5

// At every 15th minute from 3 through 59 past 
// every hour from 6 through 12
// on every 15th day-of-month
// if it's on every day-of-week from Tuesday through Friday
// in January.
```

To ease instantiation the `Expression` object exposes easier to use named constructors. 

`Expression::fromString` returns a new instance from a string.

```php
<?php

use Bakame\Cron\Expression;

$cron = Expression::fromString('3-59/15 6-12 */15 1 2-5');
echo $cron->toString();             //displays '33-59/15 6-12 */15 1 2-5'
echo $cron->minute->toString();     //displays '3-59/15'
echo $cron->hour->toString();       //displays '6-12'
echo $cron->dayOfMonth->toString(); //displays '*/15'
echo $cron->month->toString();      //displays '1'
echo $cron->dayOfWeek->toString();  //displays '2-5'
var_export($cron->toFields());
// returns
// array (
//   'minute' => '3-59/15',
//   'hour' => '6-12',
//   'dayOfMonth' => '*/15',
//   'month' => '1',
//   'dayOfWeek' => '2-5',
// )
```

`Expression::fromFields` returns a new instance from an associative array using the same index as the one returned by `Expression::toFields`.

```php
<?php

use Bakame\Cron\Expression;

$cron = Expression::fromFields(['minute' => 7, 'dayOfWeek' => '5']);
echo $cron->toString();             //displays '7 * * * 5'
echo $cron->minute->toString();     //displays '7'
echo $cron->hour->toString();       //displays '*'
echo $cron->dayOfMonth->toString(); //displays '*'
echo $cron->month->toString();      //displays '*'
echo $cron->dayOfWeek->toString();  //displays '5'
```

**If a field is not provided it will be replaced by the `*` character.**
**If unknown field are provided a `SyntaxError` exception will be thrown.**

#### Formatting

The value object implements the `JsonSerializable` interface to ease interoperability and a `toString` method to
return the CRON expression string representation. As previously shown in the examples above, each CRON expression field 
is represented by a public readonly property. Each of them also exposes a `toString` method 
and implements the `JsonSerializable` interface too.

```php
<?php

use Bakame\Cron\Expression;

$cron = Expression::fromString('3-59/15 6-12 */15 1 2-5');
echo $cron->minute->toString();  //display '3-59/15'
echo json_encode($cron->hour);  //display '"6-12"'

echo $cron->toString();  //display '3-59/15 6-12 */15 1 2-5'
echo json_encode($cron); //display '"3-59\/15 6-12 *\/15 1 2-5"'

echo json_encode($cron->toFields());  //display '{"minute":"3-59\/15","hour":"6-12","dayOfMonth":"*\/15","month":"1","dayOfWeek":"2-5"}'
```

- The `Expression::toFields` returns an associative array of CRON expression field string representation;

**Both methods produce the same JSON output string**

#### Updates

Updating the CRON expression is done via its `with*` methods where the `*` is replaced by the corresponding CRON expression field name.

Those methods expect a `CronField` instance, a string or an integer.

```php
<?php

use Bakame\Cron\Expression;

$cron = Expression::fromString('3-59/15 6-12 */15 1 2-5');
echo $cron->withMinute('2')->toString();        //displays '2 6-12 */15 1 2-5'
echo $cron->withHour($cron->month)->toString(); //displays '3-59/15 1 */15 1 2-5'
echo $cron->withDayOfMonth(2)->toString();      //displays '3-59/15 6-12 2 1 2-5'
echo $cron->withMonth('2')->toString();         //displays '3-59/15 6-12 */15 2 2-5'
echo $cron->withDayOfWeek(2)->toString();       //displays '3-59/15 6-12 */15 1 2'
```

#### Registering CRON Expression Aliases

The `Expression` class handles the following default aliases for CRON expression except `@reboot`.

| Alias       | meaning              | Expression constructor |
|-------------|----------------------|------------------------|
| `@reboot`   | Run once, at startup | **Not supported**      | 
| `@yearly`   | Run once a year      | `0 0 1 1 *`            |
| `@annually` | Run once a year      | `0 0 1 1 *`            |
| `@monthly`  | Run once a month     | `0 0 1 * *`            |
| `@weekly`   | Run once a week      | `0 0 * * 0`            |
| `@daily`    | Run once a day       | `0 0 * * *`            |
| `@midnight` | Run once a day       | `0 0 * * *`            |
| `@hourly`   | Run once a hour      | `0 * * * *`            |

```php
<?php

use Bakame\Cron\Expression;

echo Expression::fromString('@DaIlY')->toString();  // displays "0 0 * * *"
echo Expression::fromString('@DAILY')->toString();  // displays "0 0 * * *"
```

It is possible to register more expressions via an alias name. Once registered it will be available when using the `Expression` object
but also when instantiating the `Scheduler` class with a CRON expression string. 
An alias name needs to be a single word containing only ASCII letters and number and prefixed with the `@` character. They should be
associated with any valid expression. **Notice: Aliases are case insensitive**

```php
<?php

use Bakame\Cron\Expression;
use Bakame\Cron\Scheduler;

Expression::registerAlias('@every', '* * * * *');
Scheduler::fromUTC('@every')->run('TODAY', 2)->format('c');
// display 2022-01-08T00:03:00+00:00
````

At any given time it is possible to:

- list all registered expressions and their associated aliases
- remove the already registered aliases except for the default ones listed in the table above.

```php
<?php

use Bakame\Cron\Expression;
use Bakame\Cron\Scheduler;

if (!Expression::supportsAlias('@every')) {
    Expression::registerAlias('@every', '* * * * *');
}

Expression::aliases();
// returns
// array (
//   '@yearly' => '0 0 1 1 *',
//   '@annually' => '0 0 1 1 *',
//   '@monthly' => '0 0 1 * *',
//   '@weekly' => '0 0 * * 0',
//   '@daily' => '0 0 * * *',
//   '@midnight' => '0 0 * * *',
//   '@hourly' => '0 * * * *',
//   '@every' => '* * * * *',
// )
Expression::supportsAlias('@foobar'); //return false
Expression::supportsAlias('@daily');  //return true
Expression::supportsAlias('@every');  //return true
Scheduler::fromUTC('@every');        // works!

Expression::unregisterAlias('@every'); //return true
Expression::unregisterAlias('@every'); //return false

Expression::supportsAlias('@every');   //return false
Scheduler::fromUTC('@every');          //throws SyntaxError unknown or unsupported expression
Expression::unregisterAlias('@daily'); //throws RegistrationError exception
````

## Testing

The package has a :

- a [PHPUnit](https://phpunit.de) test suite
- a coding style compliance test suite using [PHP CS Fixer](https://cs.symfony.com/).
- a code analysis compliance test suite using [PHPStan](https://github.com/phpstan/phpstan).

To run the tests, run the following command from the project folder once it is cloned from its source repository and
the package is installed via composer.

```bash
composer test
```

## Contributing

Contributions are welcome and will be fully credited. Please see [CONTRIBUTING](.github/CONTRIBUTING.md) and [CONDUCT](.github/CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please email nyamsprod@gmail.com instead of using the issue tracker.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [Michael Dowling](https://github.com/mtdowling)
- [Chris Tankersley](https://github.com/dragonmantank)
- [Ignace Nyamagana Butera](https://github.com/nyamsprod)
- [All Contributors](https://github.com/bakame-php/cron-expression/graphs/contributors)

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
