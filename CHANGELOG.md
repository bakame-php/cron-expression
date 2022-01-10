# Changelog

All Notable changes to `cron` will be documented in this file

## [Next] - TBD

### Added

- Registration mechanism added to `Expression` to allow the package to registered expressions aliases. see [#64](https://github.com/dragonmantank/cron-expression/pull/64/)
- `Expression::fromFields` accepts `string`, `int` and `CronField` objects.
- `Expression::toArray` returns fields as string in an associative array.
- **[BC Break]** `Expression::fields` returns fields as `CronField` objects.
- **[BC Break]** `Expression::__construct` expects CRON Fields as `CronField` objects.
- **[BC Break]** `Expression::fromString` expects a CRON Expression.

### Fixed

- Improve parser range validation the lower bound should always be lower or equal to the upper bound.
- Fix wrap around when steps are higher than the range see for reference [#88](https://github.com/dragonmantank/cron-expression/issue/88/)
- **[BC Break]** Internal rewrite CRON Fields are now value objects.
- **[BC Break]** `Expression` field related methods no longer returns string but `CronField` object.
- **[BC Break]** All validators are now value object for CRON field expression (ie: `CronFieldValidator` is now `CronField`)

### Deprecate

- None

### Removed

- `RangeError` exception is removed as it is no longer needed.
- **[BC Break]** `ExpressionParser` its capabilities are now bundle inside the `Expression` class.
- **[BC Break]** `CronFieldValidator` capabilities are removed and replaced by `CronField` value objects.
- **[BC Break]** `Expression::__toString`, The `Expression` class no longer implements the `Stringable` interface.
- **[BC Break]** `CronExpression` interface.

## [0.4.0] - 2022-01-03

### Added

- None

### Fixed

- Change Character precedence for `,` to allow more CRON expression. see [#122](https://github.com/dragonmantank/cron-expression/pull/122/)
- Improve results when both day of month and day of week are used. see [#121](https://github.com/dragonmantank/cron-expression/pull/121/)
- **[BC Break]** `ExpressionField::MONTHDAY` is renamed `ExpressionField::DAY_OF_MONTH`
- **[BC Break]** `ExpressionField::WEEKDAY` is renamed `ExpressionField::DAY_OF_WEEK`
- **[BC Break]** `SyntaxError` constructor is made private. It can only be accessed via named constructors. Named constructors are revised.
- Improve `Scheduler` internal codebase.
- **[BC Break]** `Scheduler::yield*` methods will throw if the `$recurrences` value is not a positive integer or `0`.
- **[BC Break]** `Scheduler::INCLUDE_START_DATE` and `Scheduler::EXCLUDE_START_DATE` are replaced by an Enum `StartDate`
- **[BC Break]** The `$startDatePresence` property is now required when instantiating the `Scheduler` class.

### Deprecated

- None

### Removed

- **[BC Break]** `Scheduler::maxIterationCount` is no longer needed with the new implementation.
- **[BC Break]** `UnableToProcessRun` exception class is no longer needed with the new implementation.

## [0.3.0] - 2021-12-31

### Added

- `CronScheduler::yieldRunsBefore` to returns runs between a specified end date and an interval
- `CronScheduler::yieldRunsAfter` to returns runs between a specified start date and an interval
- `CronScheduler::yieldRunsBetween` to returns runs between specified dates

### Fixed

- **[BC Break]** `CronFieldValidator::increment` and `CronFieldValidator::decrement` accept any `DateTimeInterface` implementing object but will always return a `DateTimeImmutable` object.
- **[BC Break]** `CronScheduler::run`, `CronScheduler::isDue`, `CronScheduler::yieldRunsForward`, `CronScheduler::yieldRunsBackward` signature is changed the start date is the first argument and is required

### Deprecated

- None

### Removed

- **[BC Break]** The not documented public API `FieldValidator::isSatisfied` is removed from public API.

## [0.2.0] - 2021-12-30

### Added

- `CronFieldValidator::decrement` to allow a field validator to decrement a `DateTimeInterface` object if it fails validation.

### Fixed

- **[BC Break]** `Scheduler` constructor variable `$timezone` MUST be provided. If you do not want to supply it use the class named constructors instead.
- Internal optimization of `Scheduler::calculateRun`, internally only `DateTimeImmutable` objects are used instead of the `DateTime` class.
- If the `$startDate` value is a string, the `Scheduler` will assume that the value has the same timezone as the underlying system. (Wording fix on the `CronScheduler` interface)

### Deprecated

- None

### Removed

- `CronFieldValidator::increment` no longer has a `$invert` boolean argument. It is dropped and a `CronFieldValidator::decrement` method is introduced instead.

## [0.1.0] - 2021-12-29

Initial Release of `cron`

[Next]: https://github.com/bakame-php/cron-expression/compare/0.4.0...master
[0.4.0]: https://github.com/bakame-php/cron-expression/compare/0.2.0...0.4.0
[0.3.0]: https://github.com/bakame-php/cron-expression/compare/0.2.0...0.3.0
[0.2.0]: https://github.com/bakame-php/cron-expression/compare/0.1.0...0.2.0
[0.1.0]: https://github.com/bakame-php/cron-expression/releases/tag/0.1.0
