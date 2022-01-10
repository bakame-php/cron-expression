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

Add the dependency to your project:

```bash
composer require bakame-php/cron
```

## Usage

### Calculating the running time

#### Instantiating the Scheduler immutable Value Object

To determine the next running time for a CRON expression the package uses the `Bakame\Cron\Scheduler` class.  
To work as expected this class requires:

- a CRON Expression ( as a string or as a `Expression` object)
- a timezone as a PHP `DateTimeZone` instance or the timezone string name.
- to know if the `startDate` if eligible should be present in the results via a `StartDatePresence` enum with two values `StartDatePresence::INCLUDED` and `StartDatePresence::EXCLUDED`.

To ease instantiating the `Scheduler`, it comes bundle with two named constructors around timezone usage:

- `Scheduler::fromUTC`: instantiate a scheduler using the `UTC` timezone.
- `Scheduler::fromSystemTimezone`: instantiate a scheduler using the underlying system timezone

**Both the named constructors exclude by default the start date from the results.**

```php
<?php

use Bakame\Cron\Expression;
use Bakame\Cron\Scheduler;
use Bakame\Cron\StartDatePresence;

require_once '/vendor/autoload.php';

// You can define all properties on instantiation
$expression = '0 7 * * *';
$timezone = 'UTC';
$scheduler1 = new Scheduler(Expression::fromString($expression),  new DateTimeZone($timezone), StartDatePresence::INCLUDED);
$scheduler2 = new Scheduler($expression,  $timezone, StartDatePresence::INCLUDED);
$scheduler3 = Scheduler::fromUTC($expression)->includeStartDate();

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
$scheduler = new Scheduler('@daily', 'Africa/Kigali', StartDatePresence::EXCLUDED);
$run = $scheduler->run(new Carbon\CarbonImmutable('now'));
echo $run->format('Y-m-d H:i:s, e'), PHP_EOL;
//display 2021-12-29 00:00:00, Africa/Kigali
echo $run::class;
//display Carbon\CarbonImmutable
```

The `Scheduler::run` method allows specifying the number of matches to skip before calculating the next run.

```php
$scheduler = new Scheduler(Expression::daily(), 'Africa/Kigali', StartDatePresence::EXCLUDED);
echo $scheduler->run('now', 3)->format('Y-m-d H:i:s, e'), PHP_EOL;
//display 2022-01-01 00:00:00, Africa/Kigali
```

The `Scheduler::run` method accepts negative number if you want to get a run date in the past.

```php
$scheduler = new Scheduler(Expression::daily(), 'Africa/Kigali', StartDatePresence::EXCLUDED);
echo $scheduler->run('2022-01-01 00:00:00', -2)->format('Y-m-d H:i:s, e'), PHP_EOL;
//display 2021-12-31 00:00:00, Africa/Kigali
```

Use the `StartDatePresence` enum on `Scheduler` instantiation or the appropriate configuration method
to allow `Scheduler` methods to include the start date in their result if eligible.

- `Scheduler::includeStartDate` (will include the start date if eligible)
- `Scheduler::encludeStartDate` (will exclude the start date)

Because the `Scheduler` is an immutable object anytime a configuration settings is changed a new object is
returned instead of modifying the current object.

```php
$date = new DateTimeImmutable('2022-01-01 00:04:00', new DateTimeZone('Asia/Shanghai'));
$scheduler = new Scheduler('4-59/2 * * * *', 'Asia/Shanghai', StartDatePresence::EXCLUDED);
echo $scheduler->run($date)->format('Y-m-d H:i:s, e'), PHP_EOL;
//display 2022-01-01 00:06:00, Asia/Shanghai
echo $scheduler->includeStartDate()->run($date)->format('Y-m-d H:i:s, e'), PHP_EOL;
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
$scheduler = Scheduler::fromSystemTimezone('30 0 1 * 1')->includeStartDate();
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
$scheduler = Scheduler::fromSystemTimezone('30 0 1 * 1')->includeStartDate();
$runs = $scheduler->yieldRunsBackward(new DateTime('2019-10-10 23:20:00'), 5);
```

#### Iterating using a starting date and an interval

The interval value should always be a positive `DateInterval`. Any negative value will trigger an exception.

```php
$scheduler = Scheduler::fromSystemTimezone('30 0 1 * 1')->includeStartDate();
$runs = $scheduler->yieldRunsAfter('2019-10-10 23:20:00', new DateInterval('P1D'));
```

#### Iterating using an end date and an interval

The interval value should always be a positive `DateInterval`. Any negative value will trigger an exception.

```php
$scheduler = Scheduler::fromSystemTimezone('30 0 1 * 1')->includeStartDate();
$runs = $scheduler->yieldRunsBefore('2019-10-10 23:20:00', '1 DAY');
```

#### Iterating using a starting date and an end date

If the start date in greater than the end date the `DateTimeImmutable` objects will be returned backwards.

```php
$scheduler = Scheduler::fromSystemTimezone('30 0 1 * 1')->includeStartDate();
$runs = $scheduler->yieldRunsBetween('2019-10-10 23:20:00', '2019-09-09 00:30:00');
```

The `Bakame\Cron\Scheduler` object exposes the CRON expression using the `Bakame\Cron\Expression` immutable value object.

### The CRON Expression Immutable Value Object

#### Instantiating the object

To ease manipulating a CRON expression the package comes bundle with a `Expression` immutable value object
representing a CRON expression.

```php
<?php

use Bakame\Cron\Expression;

$cron = Expression::fromString('3-59/15 6-12 */15 1 2-5');
echo $cron->toString();               //displays '33-59/15 6-12 */15 1 2-5'
echo $cron->minute()->toString();     //displays '3-59/15'
echo $cron->hour()->toString();       //displays '6-12'
echo $cron->dayOfMonth()->toString(); //displays '*/15'
echo $cron->month()->toString();      //displays '1'
echo $cron->dayOfWeek()->toString();  //displays '2-5'
```

It is possible to also use an associative array using the same index as the one returned by `ExpressionParser::parse` method

```php
<?php

use Bakame\Cron\Expression;

$cron = Expression::fromFields(['minute' => 7, 'dayOfWeek' => '5']);
echo $cron->toString();               //displays '7 * * * 5'
echo $cron->minute()->toString();     //displays '7'
echo $cron->hour()->toString();       //displays '*'
echo $cron->dayOfMonth()->toString(); //displays '*'
echo $cron->month()->toString();      //displays '*'
echo $cron->dayOfWeek()->toString();  //displays '5'
```

**If a field is not provided it will be replaced by the `*` character.**
**If unknown field are provided a `SyntaxError` exception will be thrown.**

#### Updating the object

Apart from exposing getter methods you can also easily update the CRON expression via its `with*` methods where the `*`
is replaced by the corresponding CRON field.

```php
<?php

use Bakame\Cron\Expression;

$cron = Expression::fromString('3-59/15 6-12 */15 1 2-5');
echo $cron->withMinute('2')->toString();     //displays '2 6-12 */15 1 2-5'
echo $cron->withHour('2')->toString();       //displays '3-59/15 2 */15 1 2-5'
echo $cron->withDayOfMonth('2')->toString(); //displays '3-59/15 6-12 2 1 2-5'
echo $cron->withMonth('2')->toString();      //displays '3-59/15 6-12 */15 2 2-5'
echo $cron->withDayOfWeek('2')->toString();  //displays '3-59/15 6-12 */15 1 2'
```

#### Formatting the object

The value object implements the `JsonSerializable` interface to ease interoperability.

```php
<?php

use Bakame\Cron\Expression;

$cron = Expression::fromString('3-59/15 6-12 */15 1 2-5');
echo $cron->toString();  //display '3-59/15 6-12 */15 1 2-5'
echo json_encode($cron); //display '"3-59\/15 6-12 *\/15 1 2-5"'
```

### Parsing a CRON Expression

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

The package also supports the following notation:

- **L:** stands for "last" and specifies the last day of the month;
- **W:** is used to specify the weekday (Monday-Friday) nearest the given day;
- **#:** allowed for the `dayOfWeek` field, and must be followed by a number between one and five;
- range, split notations as well as the **?** character;


### Validating a CRON Expression

By instantiating an `Expression` object you are validating its associated CRON Expression.

In case of error a `Bakame\Cron\SyntaxError` exception will be thrown if the submitted string is not
a valid CRON expression.

```php
Expression::fromString('not a real CRON expression');
// throws a Bakame\Cron\SyntaxError with the following message 'Invalid CRON expression'
// calling SyntaxError::errors method will list the errors and the fields where it occurred.
```

Validation of a specific CRON expression field can be done using a `CronField` implementing object:

```php
<?php
use Bakame\Cron\MonthField;
$field = new MonthField('JAN'); //!works
$field = new MonthField(23);    //will throw a SyntaxError
```

It is also possible to validate a date against a specific field expression using a `CronField` object.

```php
use Bakame\Cron\HourField;

$field = new HourField('*/3'); //!works
$field->isSatisfiedBy(new DateTime('2014-04-07 00:00:00')); // returns true
```

**NOTICE: Field validator do not take into account the `DateTimeInterface` object timezone**

### Registering CRON Expression Aliases

The `Expression` class handles by default the following aliases for CRON expression except `@reboot`.

You have them also exposed as named constructors.

| Alias       | meaning              | Expression constructor | Expression shortcut     |
|-------------|----------------------|------------------------|-------------------------|
| `@reboot`   | Run once, at startup | **Not supported**      | **Not supported**       |
| `@yearly`   | Run once a year      | `0 0 1 1 *`            | `Expression::yearly()`  |
| `@annually` | Run once a year      | `0 0 1 1 *`            | `Expression::yearly()`  |
| `@monthly`  | Run once a month     | `0 0 1 * *`            | `Expression::monthly()` |
| `@weekly`   | Run once a week      | `0 0 * * 0`            | `Expression::weekly()`  |
| `@daily`    | Run once a day       | `0 0 * * *`            | `Expression::daily()`   |
| `@midnight` | Run once a day       | `0 0 * * *`            | `Expression::daily()`   |
| `@hourly`   | Run once a hour      | `0 * * * *`            | `Expression::hourly()`  |

```php
<?php

use Bakame\Cron\Expression;
use Bakame\Cron\ExpressionParser;

echo Expression::daily()->toString();         // displays "0 0 * * *"
echo Expression::fromString('@DAILY')->toString();  // displays "0 0 * * *"
```

It is possible to register more expressions via an alias name. Once registered it will be available when using the `Expression` object
but also when instantiating the `Scheduler` class with a CRON expression string. 
An alias name needs to be a single word containing only ASCII letters and number and prefixed with the `@` character. They should be
associated with any valid expression.

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

Expression::unregisterAlias('@every');

Expression::supportsAlias('@every');   //return false
Expression::unregisterAlias('@daily'); //throws RegistrationError exception
````

## Testing

The package has a :

- a [PHPUnit](https://phpunit.de) test suite
- a coding style compliance test suite using [PHP CS Fixer](https://cs.symfony.com/).
- a code analysis compliance test suite using [PHPStan](https://github.com/phpstan/phpstan).

To run the tests, run the following command from the project folder.

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
