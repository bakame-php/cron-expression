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

### Parsing a CRON Expression

This package resolves CRON Expression as they are described in the [CRONTAB documentation](https://www.unix.com/man-page/linux/5/crontab/)

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

The `Bakame\Cron\ExpressionParser` class is responsible for parsing a CRON expression and converting it into a PHP associative `array` as shown below:

```php
<?php

use Bakame\Cron\ExpressionParser;

require_once '/vendor/autoload.php';

var_export(ExpressionParser::parse('3-59/15 6-12 */15 1 2-5'));
// returns the following array
// array(
//   'minute' => '3-59/15',
//   'hour' => '6-12',
//   'dayOfMonth' => '*/15',
//   'month' => '1',
//   'dayOfWeek' => '2-5',
// )
```

Each array offset is representative of a cron expression field, The `Bakame\Cron\ExpressionField` backed enum exposes those 
offsets via descriptive cases, following the table below:

| CRON field   | array offset | ExpressionField Enum        |
|--------------|--------------|-----------------------------|
| minute       | `minute`     | `ExpressionField::MINUTE`   |
| hour         | `hour`       | `ExpressionField::HOUR`     |
| day of month | `dayOfMonth` | `ExpressionField::MONTHDAY` |
| month        | `month`      | `ExpressionField::MONTH`    |
| day of week  | `dayOfWeek`  | `ExpressionField::WEEKDAY`  |

```php
<?php

use Bakame\Cron\ExpressionParser;

require_once '/vendor/autoload.php';

echo ExpressionParser::parse('3-59/15 6-12 */15 1 2-5')[ExpressionField::MONTHDAY->value];
// display '*/15'
```

In case of error a `Bakame\Cron\ExpressionParser` exception will be thrown if the submitted string is not 
a valid CRON expression.

```php
<?php

ExpressionParser::parse('not a real CRON expression');
// throws a Bakame\Cron\SyntaxError with the following message 'Invalid CRON expression'
// calling SyntaxError::errors method will list the errors and the fields where it occurred.
```

### Validating a CRON Expression

Validating a CRON Expression is done using the `Bakame\Cron\ExpressionParser::isValid` method:

```php
<?php

ExpressionParser::isValid('not a real CRON expression'); // will return false
```

Validation of a specific CRON expression field can be done using the `ExpressionField::validator` method.  
This method returns the corresponding `CronFieldValidator` object for a given `ExpressionField` enum which validates 
the requested field:

```php
<?php
$fieldValidator = ExpressionField::MONTH->validator(); 
$fieldValidator->isValid('JAN'); //return true `JAN` is a valid month field value
$fieldValidator->isValid(23);    //return false `23` is invalid for the month field
```

### The CRON Expression Immutable Value Object

#### Instantiating the object

To ease manipulating a CRON expression the package comes bundle with a `Expression` immutable value object
representing a CRON expression. This class uses under the hood the `ExpressionParser` class.

```php
<?php

use Bakame\Cron\Expression;

$cron = new Expression('3-59/15 6-12 */15 1 2-5');
echo $cron->minute();     //displays '3-59/15'
echo $cron->hour();       //displays '6-12'
echo $cron->dayOfMonth(); //displays '*/15'
echo $cron->month();      //displays '1'
echo $cron->dayOfWeek();  //displays '2-5'
var_export($cron->fields());
//returns 
//array (
//  'minute' => '3-59/15',
//  'hour' => '6-12',
//  'dayOfMonth' => '*/15',
//  'month' => '1',
//  'dayOfWeek' => '2-5',
//)
```

It is possible to also use an associative array using the same index as the one returned by `ExpressionParser::parse` method

```php
<?php

use Bakame\Cron\Expression;

$cron = Expression::fromFields(['minute' => 7, 'dayOfWeek' => '5']);
echo $cron->minute();     //displays '7'
echo $cron->hour();       //displays '*'
echo $cron->dayOfMonth(); //displays '*'
echo $cron->month();      //displays '*'
echo $cron->dayOfWeek();  //displays '5'
```

**If a field is not provided it will be replaced by the `*` character.**
**If unknown field are provided a `SyntaxError` exception will be thrown.**

#### Special expressions

Just like the `ExpressionParser` the `Expression` class is able to handle special CRON expression:

| Special expression | meaning              | Expression constructor | Expression shortcut     |
|--------------------|----------------------|------------------------|-------------------------|
| `@reboot`          | Run once, at startup | **Not supported**      | **Not supported**       |
| `@yearly`          | Run once a year      | `0 0 1 1 *`            | `Expression::yearly()`  |
| `@annually`        | Run once a year      | `0 0 1 1 *`            | `Expression::yearly()`  |
| `@monthly`         | Run once a month     | `0 0 1 * *`            | `Expression::monthly()` |
| `@weekly`          | Run once a week      | `0 0 * * 0`            | `Expression::weekly()`  |
| `@daily`           | Run once a day       | `0 0 * * *`            | `Expression::daily()`   |
| `@midnight`        | Run once a day       | `0 0 * * *`            | `Expression::daily()`   |
| `@hourly`          | Run once a hour      | `0 * * * *`            | `Expression::hourly()`  |

```php
<?php

use Bakame\Cron\Expression;

echo Expression::daily()->toString();         // displays "0 0 * * *"
echo (new Expression('@DAILY'))->toString();  // displays "0 0 * * *"
```

#### Updating the object

Apart from exposing getter methods you can also easily update the CRON expression via its `with*` methods where the `*`
is replaced by the corresponding CRON field.

```php
<?php

use Bakame\Cron\Expression;

$cron = new Expression('3-59/15 6-12 */15 1 2-5');
echo $cron->withMinute('2')->toString();     //displays '2 6-12 */15 1 2-5'
echo $cron->withHour('2')->toString();       //displays '3-59/15 2 */15 1 2-5'
echo $cron->withDayOfMonth('2')->toString(); //displays '3-59/15 6-12 2 1 2-5'
echo $cron->withMonth('2')->toString();      //displays '3-59/15 6-12 */15 2 2-5'
echo $cron->withDayOfWeek('2')->toString();  //displays '3-59/15 6-12 */15 1 2'
```

#### Formatting the object

The value object implements the `JsonSerializable` and the `Stringable` interfaces to ease interoperability.

```php
<?php

use Bakame\Cron\Expression;

$cron = new Expression('3-59/15 6-12 */15 1 2-5');
echo $cron->toString();  //display '3-59/15 6-12 */15 1 2-5'
echo $cron;              //display '3-59/15 6-12 */15 1 2-5'
echo json_encode($cron); //display '"3-59\/15 6-12 *\/15 1 2-5"'
```

### Calculating the running time

#### Instantiating the Scheduler immutable Value Object

To determine the next running time for a CRON expression the package uses the `Bakame\Cron\Scheduler` class.  
To work as expected this class needs:

- a CRON Expression ( as a string or as a `Expression` object)
- the Scheduler working timezone as a PHP `DateTimeZone` or  the timezone string name.
- to know if the `startDate` if eligible should be present in the results.
- the maximum iteration count to use to resolve the running time. If this value is exceeded the calculation will bail out.

To ease instantiating the `Scheduler`, it comes bundle with two named constructors around timezone usage:  

- `Scheduler::fromUTC`: instantiate a scheduler using the `UTC` timezone.
- `Scheduler::fromSystemTimezone`: instantiate a scheduler using the underlying system timezone

```php
<?php

use Bakame\Cron\Expression;
use Bakame\Cron\Scheduler;

require_once '/vendor/autoload.php';

// You can define all properties on instantiation
$scheduler = new Scheduler(
    new Expression('0 7 * * *'), 
    new DateTimeZone('UTC'),
    Scheduler::INCLUDE_START_DATE,
    2000
 );

// Or we can use a named constructor and the scheduler configuration methods
$scheduler = Scheduler::fromUTC('0 7 * * *')
    ->includeStartDate()
    ->withMaxIterationCount(2000);

//both instantiated object are equals.
```

#### Finding a running date for a CRON expression

**The `Scheduler` public API methods accept `string`, `DateTime` or `DateTimeImmutable` object but will always return `DateTimeImmutable` objects with the Scheduler specified `DateTimeZone`.**

Once instantiated you can use the `Scheduler` to find the run date according to a specific date.

```php
<?php

use Bakame\Cron\Expression;
use Bakame\Cron\Scheduler;

require_once '/vendor/autoload.php';

$scheduler = new Scheduler(Expression::daily(), 'Africa/Kigali');
echo $scheduler->run('now')->format('Y-m-d H:i:s, e'), PHP_EOL;
//display 2021-12-29 00:00:00, Africa/Kigali
```

You can specify the number of matches to skip before calculating the next run.

```php
$scheduler = new Scheduler(Expression::daily(), 'Africa/Kigali');
echo $scheduler->run('now', 3)->format('Y-m-d H:i:s, e'), PHP_EOL;
//display 2022-01-01 00:00:00, Africa/Kigali
```

You can specify the starting date for calculating the next run.

```php
$scheduler = new Scheduler(Expression::daily(), 'Africa/Kigali');
echo $scheduler->run('2022-01-01 00:00:00')->format('Y-m-d H:i:s, e'), PHP_EOL;
//display 2022-01-02 00:00:00, Africa/Kigali
```

If you want to get a date in the past just use a negative number.

```php
$scheduler = new Scheduler(Expression::daily(), 'Africa/Kigali');
echo $scheduler->run('2022-01-01 00:00:00', -2)->format('Y-m-d H:i:s, e'), PHP_EOL;
//display 2021-12-31 00:00:00, Africa/Kigali
```

If you do not explicitly require it the start date will not be eligible to be added to the results.  
Use the constructor `$startDatePresence` variable or the `includeStartDate` configuration method to update the settings.
Because the `Scheduler` is an immutable object anytime a configuration settings is changed a new object is
returned instead of modifying the current object.

```php
$dateTime = new DateTime('2022-01-01 00:04:00', new DateTimeZone('Africa/Kigali'));
$dateTimeImmutable = DateTimeImmutable::createFromInterface($dateTime);
$scheduler = new Scheduler('4-59/2 * * * *', 'Africa/Kigali');
echo $scheduler->run($dateTime)->format('Y-m-d H:i:s, e'), PHP_EOL;
//display 2022-01-01 00:06:00, Africa/Kigali
echo $scheduler->includeStartDate()->run($dateTime)->format('Y-m-d H:i:s, e'), PHP_EOL;
//display 2022-01-01 00:04:00, Africa/Kigali
```

#### Knowing if a CRON expression will run at a specific date

The `Scheduler` class can also tell whether a specific CRON is due to run on a specific date.

```php
$scheduler = Scheduler::fromSystemTimezone(new Expression('* * * * MON#1'));
$scheduler->isDue(new DateTime('2014-04-07 00:00:00')); // returns true
$scheduler->isDue('NOW'); // returns false 
```

It is possible to validate a Date against a specific field expression using a `CronFieldValidator` object.

```php
use Bakame\Cron\ExpressionField;

$hourValidator = ExpressionField::HOUR->validator();
$hourValidator->isSatisfiedBy('*/3', new DateTime('2014-04-07 00:00:00')); // returns true
```

**NOTICE: Field validator do not take into account the `DateTimeInterface` object timezone**

### Iterating over multiple runs

You can iterate over a set of recurrent date where the cron is supposed to run.
The iteration can be done forward or backward depending on the endpoints provided.
Like for other methods, the inclusion of the start date still depends on the scheduler configuration.

All listed methods returns a generator containing `DateTimeImmutable` objects.

#### Iterating forward using recurrences

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

```php
$scheduler = Scheduler::fromSystemTimezone('30 0 1 * 1')->includeStartDate();
$runs = $scheduler->yieldRunsBackward(new DateTime('2019-10-10 23:20:00'), 5);
```

#### Iterating using a starting date and an interval

```php
$scheduler = Scheduler::fromSystemTimezone('30 0 1 * 1')->includeStartDate();
$runs = $scheduler->yieldRunsAfter('2019-10-10 23:20:00', new DateInterval('P1D'));
```

#### Iterating using an end date and an interval

```php
$scheduler = Scheduler::fromSystemTimezone('30 0 1 * 1')->includeStartDate();
$runs = $scheduler->yieldRunsBefore('2019-10-10 23:20:00', '1 DAY');
```

#### Iterating using a starting date and an end date

```php
$scheduler = Scheduler::fromSystemTimezone('30 0 1 * 1')->includeStartDate();
$runs = $scheduler->yieldRunsBetween('2019-10-10 23:20:00', '2019-09-09 00:30:00');
```

**NOTICE:** If the end date is lower than the start date then the iteration will be backward.

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

**HAPPY CODING!**
