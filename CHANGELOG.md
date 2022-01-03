# Changelog

All Notable changes to `cron` will be documented in this file

## [Next] - TBD

### Added

- None

### Fixed

- Change Character precedence for `,` to allow more CRON expression. see [#122](https://github.com/dragonmantank/cron-expression/pull/122/)
- Improve results when both day of month and day of week are used. see [#121](https://github.com/dragonmantank/cron-expression/pull/121/)
- Improve `Scheduler::yield*` methods implementations.
- Improve `Scheduler` internal codebase.
- **[BC Break]** `ExpressionField::MONTHDAY` is renamed `ExpressionField::DAY_OF_MONTH`
- **[BC Break]** `ExpressionField::WEEKDAY` is renamed `ExpressionField::DAY_OF_WEEK`
- **[BC Break]** `SyntaxError` constructor is made private. It can only be accessed via named constructors. Named constructors are revised.

### Deprecated

- None

### Removed

- None

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

[Next]: https://github.com/bakame-php/cron-expression/compare/0.3.0...master
[0.3.0]: https://github.com/bakame-php/cron-expression/compare/0.2.0...0.3.0
[0.2.0]: https://github.com/bakame-php/cron-expression/compare/0.1.0...0.2.0
[0.1.0]: https://github.com/bakame-php/cron-expression/releases/tag/0.1.0
