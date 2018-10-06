# relabel plugin for Craft CMS 3.x

Relabel Plugin Craft

![Screenshot](resources/img/icon.svg)

## Requirements

This plugin requires Craft CMS 3.0.0 or later.

## Installation

To install the plugin, follow these instructions.

1. Open your terminal and go to your Craft project:

        cd /path/to/project

2. Then tell Composer to load the plugin:

        composer require anubarak/craft-relabel

3. In the Control Panel, go to Settings → Plugins and click the “Install” button for Relabel .


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