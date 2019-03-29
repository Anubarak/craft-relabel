# Relabel Changelog

## 1.2.5 - 2019-03-29
### Added
- include `ProjectConfig::EVENT_REBUILD` support to rebuild the project config
- include `ProjectConfig::ensureAllFieldsProcessed();` to prevent possible bugs

## 1.2.4 - 2019-03-13
### Added
- Added Markdown support for field instructions if there is no html in it
- Make instruction textarea resizeable
### Changed
- Remove certain all `console.log()` uses

## 1.2.3 - 2019-02-21
### Added
- Included an additional Event `RegisterAdditionalLabelEvent` to add additional Labels together with the current field layout
- Parsed nested fields such as Neo to layer one
- added public available `Craft.relabel.refresh();` and `Craft.relabel.refreshFieldLayout();` functions
### Changed
- `handleAjaxRequest` is now fired after all plugins are loaded
- grab all field layouts on the site and not just the main one
- send multiple different Relabels for different field layouts and not just the main one
### Fixed
- fixed a bug that prevent some elements from firing their relabeled HUDs correctly
- added some exception handling

## 1.2.2 - 2019-01-31
### Fixed
- Fixed a bug that required 3rd party plugins to use their components without being initialized

## 1.2.1 - 2019-01-28
### Fixed
- Fixed a bug that prevented to fetch the relabels on update

## 1.2.0 - 2019-01-28
### Added
- Include support for project config

## 1.1.8 - 2019-01-25
### Changed
- Only showing field labels when there is a user logged in and not during loading screen

## 1.1.7 - 2018-12-18
### Fixed
- Included additional JavaScript checks to solve an undefined issue https://github.com/Anubarak/craft-relabel/issues/3

## 1.1.6 - 2018-12-14
### Added
- Craft Commerce, include relabel for Orders, not just Products

## 1.1.5 - 2018-12-10
### Fixed
- Fix in last commit


## 1.1.4 - 2018-12-07

### Added 
- Verbb Gift Voucher support https://github.com/verbb/gift-voucher

## 1.1.3 - 2018-10-28

### Added 
- Craft Commerce support

## 1.1.2 - 2018-10-07

### Fixed
- Fixed a wrong translation

### Changed
- Autofocus new popup window when relabeling a field
- Changed way to receive errors from elements in Twig please use `craft.relabel.getErrors(element)` now

### Deprecated  
- included the `anubarak\relabel\Variable.php` as behavior to `CraftVariable` rather than a global to Twig

## 1.1.1 - 2018-10-06

### Fixed
- Fixed a bug that prevents field layouts by rendered via ajax request to render properly

## 1.1.0 - 2018-10-06

### Added
- Displaying Relabels for error messages
- Release in plugin store

### Changed
- Changed the way to register labels in order to enable plugin events

## 1.0.0 - 2018-02-15

### Added
- Initial release
