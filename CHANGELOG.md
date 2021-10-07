# Changelog

All notable changes to this project will be documented in this file, in reverse chronological order by release.

## 0.6.0 - 2021-10-07

### Added

- [#27](https://github.com/netglue/laminas-postmark-transport/pull/27) adds support for PHP 8.1 by @gsteel.

### Changed

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 0.5.0 - 2021-04-20

### Added

- Nothing.

### Changed

- Bumped minimum Postmark client lib to v4 and marked Guzzle 6 as conflict to support PHP 8.0.
- Changed CI to use the [laminas continuous integration matrix](https://github.com/laminas/laminas-continuous-integration-action).

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 0.4.0 - 2020-11-04

### Added

- `IsPermittedSender` validator that validates an email address as a string as a permitted sender or not.
- `NotSuppressed` validator that checks an email address is not in the list of suppressed addresses
- `SuppressionList` service for checking email addresses against Postmark's suppression lists.

### Changed

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 0.3.0 - 2020-09-11

### Added

- Nothing.

### Changed

- Updated constraints for the postmark client to allow version 2 or 3.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 0.2.0 - 2020-09-08

### Added

- Nothing.

### Changed

- Switched to doctrine coding standard 8
- Removed usage of prophecy in favour of PHPUnit mocks
- Upgraded PHPUnit configuration

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 0.1.0 - 2020-03-31

### Added

- Nothing.

### Changed

- Improvements to test coverage.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- The message body should not be mime-encoded.
- Incorrect Reply-To header name
- The Date header should be stripped from outbound messages

## 0.0.0 - 2020-03-20

### Added

- Everything.

### Changed

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.
