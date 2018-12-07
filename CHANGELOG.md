# Relabel Changelog

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
