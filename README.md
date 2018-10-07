# Relabel Plugin for Craft CMS 3.x


<img src="resources/img/icon.svg" alt="drawing" width="200"/>

## Requirements

This plugin requires Craft CMS 3.0.0 or later.

## Installation

To install the plugin, follow these instructions.

1. Open your terminal and go to your Craft project:

        cd /path/to/project

2. Then tell Composer to load the plugin:

        composer require anubarak/craft-relabel

3. In the Control Panel, go to Settings → Plugins and click the “Install” button for Relabel .

## Basic Hints

Relabel creates a custom Database table that stores the new labels. Default Craft fields are not touched in any way.
The strings are replaced by JavaScript so you can remove/uninstall the plugin whenever you want without breaking changes in your Control Panel.


## Usage

Go to your field layout, click on the wheel icon and select "Relabel"

![Screenshot](resources/img/Relabel.gif)

Your field layout will have a different label and description.

To receive error messages that contains your new relabel strings do

```php
$errors = Relabel::getErrors($element);
```

Or in Twig
```twig
{% set errors = relabel.getErrors(element) %}
```

## Register Relabel for custom element types

There is an event to register Relabel for a custom form, 

```PHP
use anubarak\relabel\services\RelabelService;

Event::on(
    RelabelService::class,
    RelabelService::EVENT_REGISTER_LABELS,
    function(RegisterLabelEvent $event) use($myCustomElement){
        $event->fieldLayoutId = $myCustomElement->fieldLayoutId;
    }
);
```

Currently supported Element Types are
- craft\elements\Entries
- craft\elements\Assets
- craft\elements\GlobalSets
- craft\elements\Categories
- craft\elements\Users

## Register custom labels after Ajax requests

Crafts entries are able to change the field layout by changing the entry type, if you want to be able to change the field layout for a custom element type via Javascript as well you need to include these lines

```PHP
$labelsForLayout = Relabel::getService()->getAllLabelsForLayout($layout->id);
Craft::$app->getView()->registerJs('Craft.relabel.changeEntryType(' . json_encode($labelsForLayout) . ');');
```