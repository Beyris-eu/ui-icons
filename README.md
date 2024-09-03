## INTRODUCTION

The UI Icons module is a generic icon manager.

## INSTALLATION

Install as you would normally install a contributed Drupal module.
See: [Installing Modules](https://www.drupal.org/docs/extending-drupal/installing-modules) for further information.

## USAGE

To add an **Icon Pack**, you need to declare in your module or theme a specific
file:

- my_module_name.ui_icons.yml
- my_theme_name.ui_icons.yml

This module include a lot of examples, it's recommended to use them as a
starting point.

### ADD AN ICON PACK

The definition file can one or multiple definitions following this structure:

```yaml
ICON_PACK_MACHINE_NAME:
  label: STRING # REQUIRED
  description: STRING # Optional
  enabled: BOOL # Optional
  extractor: PLUGIN_ID # REQUIRED, included: path, svg, svg_sprite
  config:
    sources: ARRAY # REQUIRED for extractors: path, svg, svg_sprite
    # ... Other keys for a custom extractor plugin.
  settings: # Optional, add a form with values for the template
    FORM_KEY:
      KEY: VALUE
    # ... Any other keys
  template: STRING # Optional, Twig template to render the Icon
  library: STRING # Optional, Drupal library machine name
```

This module provide multiple extractors, you an provide other extractor with
other modules or custom code, @see IconExtractorPluginBaseInterface.

Available extractors with this module:

- `path`: icons as images files (png, jpeg...)
- `svg`: icons as svg files
- `svg_sprite`: icons in a svg sprite file

For these extractors, you must provide a `config:sources` array to indicate the
physical path(s) to the icons.

If the path do not start with a slash `/`, it will resolve to the module or
theme, else it will resolve to Drupal web root.

It must include the keyword `{icon_id}` to identify icons name and optionally
`{group}` to group icons. For example:

```yaml
- /libraries/icon_pack/icons/{icon_id}.svg
```

Icons located in the Drupal web root `libraries` folder.

```yaml
- assets/icons/{icon_id}.svg
```

Icons located in the Module or Theme where the *.ui_icons.yml file exist:
`my_module/assets/icons/`

The key `template` provide a Twig template to render the Icon.
Available variables in the template:

- `source`: The Icon path or url resolved
- `icon_id`: The Icon name extracted
- `icon_label`: The Icon label generated from the icon_id
- `icon_full_id`: The Icon ID as icon_pack_id:icon_id
- `icon_pack_label`: The Icon pack label
- `content`: For some extractors the HTML string content of the icon, used by
  `svg` extractors.
- Any other variable from `settings` definition, see below

The `settings` key allow to define any setting specific to the Icon Pack that
will be generated as a Drupal Form when the Icon is used and pass to the
Twig template. The format follow the [JSON Schema reference](https://json-schema.org/understanding-json-schema/reference/type).

For example a common usage is to include a `size` or `width` and `height` option
to control the icon. For example:

```yaml
settings:
  width:
    title: "Width"
    description: "Set a width for this icon."
    type: "integer"
    default: 40
  height:
    title: "Height"
    description: "Set a height for this icon."
    type: "integer"
    default: 40
```

This will allow the user to fill a `width` and `height` form alongside the Icon
form. And the value will be passed to the `template`, so you can use them:

```twig
<img class="icon icon-{{ icon_id }}" src="{{ source }}" width="{{ width|default(24) }}" height="{{ height|default(24) }}">
```

It is highly recommended to provide default in the Twig template as default
values in the `settings` form are just indicative.

## MAINTAINERS

Current maintainers:

- Jean Valverde - [mogtofu33](https://www.drupal.org/u/mogtofu33)
- Florent Torregrosa - [Grimreaper](https://www.drupal.org/user/2388214)
- Pierre Dureau - [pdureau](https://www.drupal.org/user/1903334)

Supporting organizations:

- [Beyris](https://www.drupal.org/beyris) - We are leading impactful open-source projects and we are providing coding, training, audit and consulting.
