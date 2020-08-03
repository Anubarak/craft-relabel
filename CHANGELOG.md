# Relabel Changelog

## 2.0.0 - 2020-08-03

> {error} Craft 3.5.0 due to the field layout changes in Craft 3.5.0 Relabel is not longer required. This final version will create a migration that tries to convert relabels to native Craft CMS Field labeling. Use it on your own risk and double check if all labels are set correctly after the migration. please report any errors/issues you find and alter the rest of your fields if required. Make sure to backup your system before you run the migration.

### added

- final migration to convert custom labels into Craft CMS native labels

## 1.4.0 - 2020-07-15

> {warning} Craft 3.5.0 has implemented it's custom version for relabeling fields. In order to not lose your relabels you should update to this version. It will stop working, but I'm going to create a migration soon to migrate old relabels to the new Craft style, in the meantime you can either do nothing or see all relabels in a custom index page `admin/relabel`. But if you don't update to this version all relabels will be forever deleted as soon as you re-save a layout

### changed

- deactivate the plugin

### added

- added a custom index page to display all labels in use `settings/plugins/relabel`
- in progress -> create a migration to apply old relabel to new craft field layouts

## 1.3.5.4 - 2020-06-23

### fixed

- another try to change labels for all browser(s) and versions

## 1.3.5.3 - 2020-06-23

### added

- added support for new Craft 3.5 "entries per site" config -> check for the "site" query param to request the entry for the correct site
- added support for drafts -> those have only an url like "{id}" and not "{id}-{slug}"

## 1.3.5.2 - 2020-06-19

### fixed

- fixed a bug that prevented re-labeling in JS in the latest Chrome Version for whatever reason

## 1.3.5.1 - 2020-02-06

### fixed

- fixed a bug that prevented re-labeling for fields that come after a matrix field in another field layout tab, thanks @[https://github.com/mdominguez](Mattias Dominguez) [#16](https://github.com/Anubarak/craft-relabel/issues/16)

## 1.3.5 - 2020-02-04

### added 

- after a field layout was removed, all labels of this layout are now deleted

### fixed

- do not relabel fields in matrix blocks [#14](https://github.com/Anubarak/craft-relabel/issues/14)
- fixed a bug that could cause the project-config syncing to fail [12](https://github.com/Anubarak/craft-relabel/issues/12) & [#11](https://github.com/Anubarak/craft-relabel/issues/11)
- security fix by bencroker, thanks for this and sorry for being so late [13](https://github.com/Anubarak/craft-relabel/pull/13)

## 1.3.4 - 2019-09-30

### Fixed

- Add security check in `Relabel::getErrors`, only execute `getFieldLayout` if there is a valid fieldLayoutId


## 1.3.3 - 2019-09-16

### Fixed

- Fixes an issue with Craft loading all fields before Plugins are loaded that causes the currentUsers Field layout to be empty in certain cases https://github.com/craftcms/cms/issues/4944 

## 1.3.2.2 - 2019-07-12

### Changed

- Include `$element !== null` checks for all elements because Craft 3.2.0 so Craft throws exceptions since it can't find 
elements in some cases ¯\\\_(ツ)_/¯

## 1.3.2 - 2019-07-10

### Added

- Changed labels of `Craft.elementIndex.sortMenu` accordingly to the relabeled strings

## 1.3.1 - 2019-05-16
### Fixed
- Fixed a 500 error that happens when users change to fieldlayouts with matrix blocks without a field layout

## 1.3.0 - 2019-04-18
### Added
- Added changed labels for Categories and Entries in the Craft.elementIndex

## 1.2.7 - 2019-04-02
### Added
- Added support for Solspace Calendar [#8](https://github.com/Anubarak/craft-relabel/issues/8)

## 1.2.6 - 2019-04-02
### Added
- add matrix support
- include Craft Commerce Variant field labels

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
