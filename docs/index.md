# Introduction

The UI Icons module leverages Drupal's [icon API](https://www.drupal.org/project/drupal/issues/3471494).

It provides form elements to search and select an icon, and integrations with
fields, menus, text formats, CKEditor 5, Media, UI Patterns and Drupal Canvas.

## Requirements

- Drupal 11.3 or later, or Drupal 12. Every info file declares
  `core_version_requirement: ^11.3 || ^12.0`.
- The Icon API is part of Drupal core, there is nothing else to install for it.
- At least one icon pack, see [Starter for icon pack provider](#starter-for-icon-pack-provider).

Each submodule requires the module it integrates with, as listed below.

## Installation

Install as you would normally install a contributed Drupal module.  
See: [Installing Modules](https://www.drupal.org/docs/extending-drupal/installing-modules)
for further information.

!!! note "Drupal compatibility"

    The **2.0.x** branch is for **Drupal 11.3+ and 12**. It no longer ships
    the empty backport and Iconify submodules: when upgrading from 1.1.x, run
    the database updates, `ui_icons_update_11201` removes them.

    The **1.0.x** branch is for **Drupal 10.4 to 11.0** and include a backport
    of Drupal core Icon API.  
    This branch will be **minimally** maintained.

    The **1.1.x** branch is for **Drupal 11.1+** and upgrade from previous
    version.  
    This branch include empty backport and Iconify sub modules to be removed.

## Usage

See the [Drupal Icon API documentation](https://www.drupal.org/docs/develop/drupal-apis/icon-api) to add your icon pack.  
Links to quick examples of icon packs are provided below.

### Starter for icon pack provider

A module or a theme declares its icon packs in a `MY_EXTENSION.icons.yml` file
at its root. An icon is referenced by its full id, `pack_id:icon_id`. Core
provides the `path`, `svg` and `svg_sprite` extractors, and `UI Icons Web fonts`
adds `font`, see [Web font extractor](#web-font-extractor).

```yaml
my_icon_pack:
  label: 'My icons'
  extractor: svg
  config:
    sources:
      - icons/*.svg
  settings:
    size:
      title: 'Size'
      type: 'integer'
      default: 24
  template: >
    <svg {{ attributes.setAttribute('width', size|default(24)).setAttribute('height', size|default(24)) }}>
      {{ content }}
    </svg>
```

Icon settings saved in configuration, such as formatter settings, field default
values or UI Patterns sources, are keyed by pack id, under the
`ui_icons.icon_pack_settings` schema type, see
[ui_icons.schema.yml](https://git.drupalcode.org/project/ui_icons/-/blob/2.0.x/config/schema/ui_icons.schema.yml).

UI Icons builds the schema of each pack from its `settings`: `integer`,
`number`, `boolean`, anything else as a string. To replace it, declare the
`ui_icons.icon_pack_options.<pack_id>` type in a `config/schema/*.schema.yml`
file of the extension that declares the pack. Do it when a theme ships default
config with these settings: the theme config is checked before its packs are
discovered.

```yaml
ui_icons.icon_pack_options.my_icon_pack:
  type: mapping
  label: 'My icons settings'
  mapping:
    size:
      type: integer
      label: 'Size'
```

More examples are in the
[test module schema](https://git.drupalcode.org/project/ui_icons/-/blob/2.0.x/tests/modules/ui_icons_test/config/schema/ui_icons_test.schema.yml).

Third party themes include definition for some icon pack:

- [UI Suite Bootstrap 5.x](https://www.drupal.org/project/ui_suite_bootstrap) include [Bootstrap icons](https://git.drupalcode.org/project/ui_suite_bootstrap/-/blob/5.1.x/ui_suite_bootstrap.icons.yml)
- [UI Suite DSFR](https://www.drupal.org/project/ui_suite_dsfr) include [DSFR icons](https://git.drupalcode.org/project/ui_suite_dsfr/-/blob/1.1.x/ui_suite_dsfr.icons.yml)
- [UI Suite USWDS](https://www.drupal.org/project/ui_suite_uswds) include [USWDS icons](https://git.drupalcode.org/project/ui_suite_uswds/-/blob/4.0.x/ui_suite_uswds.icons.yml)
- [UI Suite DaisyUI](https://www.drupal.org/project/ui_suite_daisyui) include [Hero icons](https://git.drupalcode.org/project/ui_suite_daisyui/-/blob/4.0.x/ui_suite_daisyui.icons.yml)

You can find examples in the external project [UI Icons Example](https://gitlab.com/ui-icons/ui-icons-example).

These examples serve as a starting point for adding icons to your Drupal installation.  
We strive to provide ready-to-use examples that can be easily adapted to your use case.

These examples include widely used third-party icon packs like:

- [Bootstrap Icons](https://icons.getbootstrap.com)
- [FontAwesome](https://fontawesome.com/icons)
- [Feather Icons](https://feathericons.com)
- [Heroicons](https://heroicons.com)
- [Material Symbols](https://fonts.google.com/icons)
- [Octicons](https://primer.style/foundations/icons)
- [Phosphor Icons](https://phosphoricons.com)
- [Remix icon](https://remixicon.com)
- [Delta icons](https://delta-icons.github.io)
- [Evil icons](https://evil-icons.io)
- [Maki icons](https://labs.mapbox.com/maki-icons)
- [Lucide icons](https://lucide.dev)

Icon pack builders:

- [IcoMoon](https://icomoon.io) - Import your icons or choose from existing sets

Specific Design System Icons:

- [USWDS](https://designsystem.digital.gov) - US federal government Design System
- [DSFR](https://www.systeme-de-design.gouv.fr) - French government Design System

We even make the [Drupal core Icon](https://gitlab.com/ui-icons/ui-icons-example/-/tree/main/ui_icons_drupal) available!

You can contribute a pull request (PR) for any third-party icon pack to the project!

### Web font extractor

Enable the `UI Icons Web fonts` module to declare packs with `extractor: font`.
Each file listed in `config: sources` adds icons:

- `.ttf` and `.woff` files: the glyph names. This needs the
  [dompdf/php-font-lib](https://github.com/dompdf/php-font-lib) library, the
  status report warns when it is missing.
- `.codepoints` files: one name and code point pair per line, separated by a
  space. The code point is passed to the template as `content`.
- `.json`, `.yml` and `.yaml` files: the top level keys.

An optional `config: offset` skips that number of icons from the start of the
list.

## Drupal Implementations

Different submodules provide implementations for Fields, Field Links, Menus,
text formats, CKEditor 5 and Media.

### Field UI

Enable the `UI Icons Fields` module to add a new field of type **Icon**. Its
**Allowed icon packs** setting limits the selection, none selected allows all.

The `Icon` widget sets the **Icon selector**, `Autocomplete`, or `Picker` when
`UI Icons Picker` is enabled. It also sets the autocomplete **Result format**,
`List` or `Grid`, and the **Maximum results**. The `Icon` formatter sets the
icon settings of the allowed packs.

For integration with fields of type **Link**, be sure to select the `Link icon`
widget and formatter under **Manage form display** and **Manage display**.

The `Link icon` widget has the same selector settings, plus **Icon required**,
**Allow icon display position selection** and **Allowed icon packs**. The
`Link icon` formatter sets the icon settings, and the **Icon display**,
`Before`, `After` or `Icon only`, when editors cannot select a position.

### Menu

Enable the `UI Icons for Menu` module to be able to add an Icon to a menu item.
After enabling the module, edit a menu item to access the Icon selection and the
icon settings.

The module replaces the widget of the menu link **Link** field with `Link icon`,
or `Link icon (with attributes)` when `UI Icons Link Attributes` is enabled.

Each menu sets how its links select an icon: edit the menu and open **Icons
settings**. The settings are the **Icon selector**, **Result format**,
**Maximum results**, **Icon required**, **Allow icon display position
selection** and **Allowed icon packs**. They are saved as third party settings
of the menu. A menu without its own settings uses the autocomplete as a list of
24 results, an optional icon, a position select and all packs.

Compatibility with Drupal core Navigation module requires your icon pack
template to print a `class` variable: the module passes `toolbar-button__icon`
in it. For example, declare it as a setting in `*.icons.yml`, as the core
`navigation` pack does:

```yaml
my_icon_pack:
  settings:
    class:
      title: 'Class'
      type: 'string'
  template: >
    <svg {{ attributes.setAttribute('class', class) }}>{{ content }}</svg>
```

There is no implementation from the Node edit form yet.

### Text filter

Enable the `UI Icons Text` module to add the `Embed icon` filter to text
formats. It renders `<drupal-icon data-icon-id="pack_id:icon_id">` tags as the
icon, wrapped in `<span class="drupal-icon">`. An optional
`data-icon-settings` attribute holds the icon settings as JSON.

On the text format, place `Embed icon` after **Limit allowed HTML tags and
correct faulty HTML** and **Convert line breaks into HTML**, and do not enable
**Display any HTML as plain text**. With **Limit allowed HTML tags and correct
faulty HTML** enabled, allow
`<drupal-icon data-icon-id data-icon-settings class aria-label aria-hidden role>`.

The filter settings are **Icon Pack selectable**, **Selector format**,
`Autocomplete` or `Picker`, **Result format** and **Maximum results**. The
CKEditor 5 dialog uses them. The filter also removes icons from packs outside
the selection.

#### Accessibility

The filter moves the `class`, `role` and `aria-label` attributes of the
`<drupal-icon>` tag to the wrapper. With `role="presentation"` or
`role="none"`, it drops `aria-label`, as such an element must not have an
accessible name. The CKEditor 5 dialog does not set these attributes, add them
in the **Source** editing mode.

The icon markup inside the wrapper comes from the pack template. Mark
decorative icons there, as the core `navigation` pack does with
`aria-hidden="true"` on its `<svg>`.

### CKEditor5

Enable the `UI Icons for CKEditor 5` module, it requires `UI Icons Text`, then
navigate to:

- Administration >> Configuration >> Content authoring

Configure your text format to add the `Icon` button and enable the `Embed icon`
filter.

The button opens a dialog with the selector from the filter settings and the
icon settings. It falls back to the autocomplete when `UI Icons Picker` is not
enabled. To change an icon, select it in the content and click **Edit** in its
toolbar.

!!! warning "Third party incompatibility"

    `UI Icons CKEditor 5` is not compatible with [`ckeditor5_icons` module](https://www.drupal.org/project/ckeditor5_icons)

### Media

Enable the submodule `UI Icons Media` to allow usage with Media.

A media type Icon can be created with source **Icon**, its source field is an
**Icon** field. The source supports the Media Library.

Its metadata can be mapped to fields: pack ID, label and license, icon ID, full
ID, group and source. Icons from the `path` and `svg` extractors with a local
file get a thumbnail, copied to `public://ui_icons_thumbnails`.

### Icon selector

The default Icon selector is the `icon_autocomplete` form element, an
autocomplete Drupal field.

The submodule `UI Icons Picker` provides a more advanced selector of type
`icon_picker`: a modal grid of icons with a filter by name. Arrow keys move in
the grid, Enter or Space picks the icon.

Enabling it also makes the preview box of the default `icon_autocomplete`
selector clickable, opening the same grid of icons. Both ways of choosing then
work on the one element: type a name to search, or click the preview to browse.
Set `#show_picker` to `FALSE` on the element to keep a plain autocomplete.

Both elements accept these properties:

- `#allowed_icon_pack`: pack ids to limit the selection, empty allows all.
- `#show_settings`: show the icon settings of the selected icon pack, default
  `FALSE`.
- `#default_settings`: icon settings keyed by pack id, for example
  `['my_icon_pack' => ['size' => 32]]`.
- `#settings_title`: title of the settings details, default `Settings`.
- `#return_id`: return `['target_id' => 'pack_id:icon_id', 'settings' => ...]`
  instead of `['icon' => $icon, 'settings' => ...]`, where `$icon` is an
  `IconDefinitionInterface`. Settings are keyed by pack id.

`icon_autocomplete` also accepts `#result_format`, `list` by default or `grid`,
and `#max_result`, 24 by default.

```php
$form['icon'] = [
  '#type' => 'icon_autocomplete',
  '#title' => $this->t('Icon'),
  '#default_value' => 'my_icon_pack:home',
  '#allowed_icon_pack' => ['my_icon_pack'],
  '#show_settings' => TRUE,
  '#default_settings' => ['my_icon_pack' => ['size' => 32]],
  '#result_format' => 'grid',
];
```

### Icon Library

Enable the submodule `UI Icons Library` to browse your icons:

- Administration >> Appearance >> UI libraries >> Icons

It requires the `Access icons library` permission. Each pack card links to a
page to search the pack icons by keyword and group. A link on the overview
shows or hides the disabled packs, for the current user only.

## Modules Implementations

Different submodules provide implementations for third party modules.

### Link Attributes

For support with the [Link Attributes widget](https://www.drupal.org/project/link_attributes),
enable the `UI Icons Link Attributes` module, then select the
`Link icon (with attributes)` widget.

### Linkit

For support with [Linkit](https://www.drupal.org/project/linkit), enable the
`UI Icons Linkit` module for the `Linkit icon` widget and formatter. The
`UI Icons Linkit Attributes` module adds the `Linkit icon (with attributes)`
widget.

### Field Sample Value

Core sample values of a **Link** field never have an icon. With
[Field Sample Value](https://www.drupal.org/project/field_sample_value)
enabled, edit the field, check **Set sample value** and select the
`Random link with icon` generator. It adds an icon from the packs the widget
allows, for every module building its sample entities through Field Sample
Value. Icon fields need nothing: their default sample is already an icon.

### UI Patterns

Enable the submodule `UI Icons for UI Patterns` to allow usage with
[UI Patterns 2](https://www.drupal.org/project/ui_patterns). UI Patterns 1 is
not supported anymore.

It provides the `icon` prop type, an object with `pack_id`, `icon_id` and
optional `settings`, and these sources:

- `Icon` (`icon`): select an icon for an `icon` prop.
- `Icon` (`icon_renderable`): render a selected icon in a slot.
- `Icon (Link type field)` (`link_icon`): the icon of a link field item.
- `Icon (field)` (`field_icon`): the value of an Icon field, for an `icon` prop
  or a slot.

The two `Icon` sources are hidden when no icon pack has icons. An `enum` on the
`pack_id` property of a prop limits the packs offered by the `Icon` source.

```yaml
props:
  type: object
  properties:
    icon:
      title: 'Icon'
      $ref: 'ui-patterns://icon'
```

In the component template, render it with
`{{ icon(icon.pack_id, icon.icon_id, icon.settings) }}`.

!!! warning "Data from field"

    UI Patterns field source is considered experimental.  
    Using Data from another field for an icon property is subject to change and
    should be used for tests only.

### Drupal Canvas

Enable the submodule `UI Icons Canvas` to edit icons in
[Drupal Canvas](https://www.drupal.org/project/canvas). It requires Canvas and
`UI Icons Fields`.

A component string prop marked `x-canvas-prop: ui-icon` is edited with the
`Icon` widget and stored as an Icon field value. The component receives the
icon full id, `pack_id:icon_id`, without icon settings.

```yaml
props:
  type: object
  properties:
    icon:
      type: string
      title: 'Icon'
      x-canvas-prop: ui-icon
```

## Maintainers

Current maintainers:

- Jean Valverde - [mogtofu33](https://www.drupal.org/u/mogtofu33)
- Pierre Dureau - [pdureau](https://www.drupal.org/user/1903334)
- Florent Torregrosa - [Grimreaper](https://www.drupal.org/user/2388214)

Supporting organizations:

- [Beyris](https://www.drupal.org/beyris) - We are leading impactful open-source
projects and we are providing coding, training, audit and consulting.
