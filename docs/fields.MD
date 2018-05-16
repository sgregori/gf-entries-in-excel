# Fields

- [Introduction](#introduction)
- [Using a custom field](#custom-field)
- [Registering a custom field](#registering-custom-field)

<a name="introduction"></a>
## Introduction
As a form is based around fields, so is this plugin. To make a nice row in Excel, we find all the fields for a form, and assert what type of fields they are. Based on that information, we map the original fields via a transformer, and return a column or a set of columns. These columns are then added to the row. This process makes it very scalable, and also very easy for you to manipulate.

<a name="custom-field"></a>
## Using a custom field
If you made a custom field for Gravity Forms, changes are it works right out of the box with the plugin. The default way the plugins renders the output, is by calling `get_value_export` on the field. And since every field is required to implement this function, make sure you implement it well. By default the result is combined into one column per row. 


<a name="registering-custom-field"></a>
## Registering a custom field
If you need more flexibilty, or your field contains subfields that need their own column, you can add a custom (plugin) field.

1. Make a class that
    - extends `GFExcel\Field\BaseField` (recommended) 
    - extends `GFExcel\Field\AbstractField`
    - implements `GFExcel\Field\FieldInterface`
1. Return your needed columns and cells by implementing `getColumns` and `getCells`. (See `AddressField` for some inspiration)
1. Add your class

```php
// for example in your functions.php 

apply_filter('gfexcel_transformer_fields', function($fields) {
    $fields['field-type'] = MyTheme\Field\MyAwesomeField::class;
    return $fields;
});
```
