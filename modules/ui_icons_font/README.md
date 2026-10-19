## INTRODUCTION

The UI Icons Font module provides the `font` icon extractor for the Drupal core
Icon API, to support discovery of Icons with Font packs through TTF/Woff files
or metadata as codepoints, json or yml.

## INSTALLATION

Install as you would normally install a contributed Drupal module.
See: [Installing Modules](https://www.drupal.org/docs/extending-drupal/installing-modules) for further information.

For TTF and Woff files support, require library: [dompdf/php-font-lib](https://github.com/dompdf/php-font-lib)

Install from Drupal root with:

```shell
composer require dompdf/php-font-lib
```

The status report warns when the library is missing.

## DOCUMENTATION

See [online documentation](https://project.pages.drupalcode.org/ui_icons) or local [docs/index.md](../../docs/index.md).
