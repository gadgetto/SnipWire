# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [0.8.6] - 2020-04-06
### Added
- Finished subscriptions dashboard section
- Added support for Snipcart subscriptions
- Added necessary fields for subscription products
- Added debug output for webhooks request payload data

### Changed
- Updated MarkupSnipWire to output subscription data-item-* tags

### Fixed
- Fixed deletion of segmented caches
- Fixed display of empty image field in product details
- Fixed a problem with namespace in Countries.php
- Fixed a problem with duplicate SKU check on page save
- Fixed [#3] Installer error: Can’t save page 0: /custom-cart-fields/: It has no parent assigned

## [0.8.5] - 2020-03-21
### Added
- Added documentation (php comments) to Webhooks class and hookable event handler methods
- All Webhooks event handler methods now have a return value (Snipcart payload)

### Changed
- Replaced dirname(__FILE__) with __DIR__ in entire project

### Fixed
- Catch module settings access for non super users
- Fixes [#2] Dashboard not accessible for non SuperUsers

## [0.8.4] - 2020-03-03
### Fixed
- Improved compatibility for Windows based Systems
- Entirely removed useage of DIRECTORY_SEPARATOR due to problems on Windows based systems

## [0.8.3] - 2020-03-01
### Added
- Updated products package installer to add specific module config on install

### Changed
- Updated apexcharts.js vendor plugin to version 3.15.6

### Fixed
- The uninstallation process is now much more reliable
- FieldtypeSnipWireTaxeSelector is now uninstalled properly (existing fields are converted to FieldtypeText)
- Refactored ExtendedInstaller class to be more reliable
- Extended resources installer page is now more user friendly
- Fixed product price JSON output if a currency field is missing
- Small fixes and code enhancements

## [0.8.2] - 2020-02-08
### Added
- Added method to change cart and catalogue currency via GET, POST or SESSION
- Added module setting to choose GET, POST, SESSION parameter name for cart and catalogue currency

### Changed
- Updated CHANGELOG.md (this file)
- Updated README.md (added screenshot and GitHub badges)

### Fixed
- Some small fixes and code enhancements

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