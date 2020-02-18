# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [Unreleased]
- Refactored ExtendedInstaller class to be more reliable
- Extended resources installer page is now more user friendly
- Fixed product price JSON output if a currency field is missing
- Updated products package installer to add specific module config on install
- Small fixes and code enhancements

## [0.8.2] - 2020-02-08
### Added
- Added method to change cart and catalogue currency via GET, POST or SESSION
- Added module setting to choose GET, POST, SESSION parameter name for cart and catalogue currency

### Fixed
- Some small fixes and code enhancements

### Changed
- Updated CHANGELOG.md (this file)
- Updated README.md (added screenshot and GitHub badges)

## [0.8.1] - 2020-02-03
### Added
- Added requirement for PHP >= 7.0.0
- Added CHANGELOG.md (this file)

### Changed
- Moved all custom class files into custom namespaces

### Fixed
- Fixed a warning in AbandonedCarts->_renderTableCartSummary

## [0.8.0] - 2020-02-01
### Added
- First public beta release