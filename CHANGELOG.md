# Changelog

All notable changes to this project will be documented in this file.

## [1.1.2] - 2025-11-09

### Fixed
- fix: Código de moeda da transação para estornos deve ser preenchido sempre com valor “132”, Escudos de Cabo Verde, conforme a ISO 4217.
- fix: complains missing billling params  even after set them.

### Changed
- edit: getData() in RequestResponse now can receive a parameter to retrive specific param from postData param

## [1.1.1] - 2025-11-09

### Fixed
- fix: complains missing billling params even after set them.

## [1.1.0] - 2025-11-08

### Added
- Add .gitattributes to exclude unnecessary directories and files from Composer package Update .gitignore to include .idea and coverage directories
- feat: Update .gitignore to include build artifacts and documentation files

### Fixed
- Fix author information and update documentation comments in Vinti4PayClient

### Changed
- Improve Receit Generator
- Prepare for release
- update Readme
- Documentation fonts
- Document API Release
- Documentation Release
- Documentation Release
- remove phpunit cache

## [1.0.0] - 2025-11-08

### Added
- feat: Enhance ResponseResult and Vinti4PayClient with new methods and tests
- feat: Add Vinti4PayClient documentation and standalone tests
- feat: Add example client for Vinti4Pay integration
- feat: Add model Receipt
- Add CI workflow with Codecov
- Add Documentation Release

### Fixed
- fix: update DCC handling in Vinti4Pay class to improve data parsing
- Fixing Receipt and making its tests
- fixing namespace dir for Erilshk\Vinti4Pay

### Changed
- composer / readme
- readme
- test: covering Client testCases
- teste para ResponseResultTest
- test: PurchaseRequestTrait
- Vinti4Pay SDK for payment processing

