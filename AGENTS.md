# AGENTS.md

Guidance for working in the `ui_icons` Drupal module (project `drupal/ui_icons`).

## What this is

Generic icon manager for Drupal. It does not implement the icon system itself:
the Icon API lives in core since 11.1 (`Drupal\Core\Theme\Icon\*`, plugin
manager `plugin.manager.icon_pack`). This module adds the UI on top: a form
element to search and configure an icon, plus integrations (field, media, menu,
CKEditor 5, text filter, UI Patterns, Canvas).

Current branch line is **2.1.x**, Drupal 11.3+ and Drupal 12. The 1.x lines
shipped an API backport through `ui_icons_backport` and `ui_icons_iconify_api`,
both removed here. 1.1.x is deprecated and 1.0.x is no longer maintained.

`core_version_requirement: ^11.3 || ^12.0` in every `*.info.yml` outside
`tests/`, kept in sync.
The floor is the core support policy, not an API need: the Icon API this module
builds on landed in 11.1, but 11.1 and 11.2 lost security coverage in June 2026.
`composer.json` declares no `drupal/core` requirement on purpose: the
drupal.org packaging facade derives it from the info file, so a constraint here
would only be a second place to drift.

Icon packs are declared by any extension in `*.icons.yml`. An icon id is
`pack_id:icon_id`, built with `IconDefinition::createIconId()`. Extractors
`path`, `svg`, `svg_sprite` are core, `font` is provided here.

## Layout

The root module is only the autocomplete form element. Everything else is a
submodule.

| Path | Content |
| --- | --- |
| `src/Element/IconAutocomplete.php` | The `icon_autocomplete` form element. Central piece, rebuilds itself and the extractor settings sub-form through ajax on `autocompleteclose change`. |
| `src/IconSearch.php` | Search service `ui_icons.search`, backs the autocomplete route. |
| `src/IconPreview.php`, `src/Template/IconPreviewTwigExtension.php` | Preview rendering, exposed to Twig as `icon_preview()`. |
| `src/Controller/` | Ajax endpoints, see `ui_icons.routing.yml` (`/ui-icons/ajax/autocomplete/icons`, `/ui-icons/ajax/preview/icons`). |
| `templates/icon-selector.html.twig` | Markup of the element. Provides the `.ui-icons-preview-icon` and `.ui-icons-settings-wrapper` hooks used by tests and themes. |
| `src/Hook/UiIconsHooks.php` | `config_schema_info_alter`, `help`, `theme` and `preprocess_icon_selector`. The alter adds a schema key per icon pack to `ui_icons.icon_pack_settings`. |
| `css/*.icon.autocomplete.css` | Per admin theme overrides (default_admin, gin, dsfr, daisyui), picked by `UiIconsHooks::preprocessIconSelector()` via its `isThemeActive()` helper. |

Submodules worth knowing:

- `ui_icons_field`: field type `ui_icon`, widgets `icon_widget` / `icon_link_widget`, formatters `icon_formatter` / `icon_link_formatter`. The `allow_settings` field setting stores icon pack settings per value, in a `settings` column. `icon_link` is a Field Sample Value generator for link fields with an icon.
- `ui_icons_picker`: `icon_picker` element, a modal grid picker alternative to the autocomplete.
- `ui_icons_popover`: `icon_popover` element, an in-place popover selector with groups. Its ajax routes live under `/ui-icons/ajax/popover/`.
- `ui_icons_text`: `icon_embed` filter, turns `<drupal-icon data-icon-id data-icon-settings>` into rendered markup. Its `wrapper` setting, on by default, keeps the `<span>` around each icon.
- `ui_icons_ckeditor5`: toolbar button and widget producing that `<drupal-icon>` tag. JS source in `js/ckeditor5_plugins/icon/src/`, bundle committed in `js/build/icon.js` (webpack, rebuild after editing source).
- `ui_icons_patterns`: UI Patterns **2.x only**. An `icon` prop type (`IconPropType`) plus source plugins (`IconSource`, `IconRenderableSource`, `FieldIconSource`, `LinkIconSource`). UI Patterns 1.x support is gone: `ui_patterns_settings` 3.x dropped the whole `SettingType` plugin system, so the old `IconSettingType` was deleted. Never reintroduce a `Plugin/UiPatterns/SettingType/` plugin. `ui_icons_patterns.settings:icon_selector` picks the element `IconSource` uses, `icon_popover` by default.
- `ui_icons_canvas`, `ui_icons_media`, `ui_icons_menu`, `ui_icons_font`, `ui_icons_library`: integrations, one concern each.

## Tests

PHPUnit tests live in `tests/src/{Unit,Kernel,Functional}` per module and carry
`#[Group('ui_icons')]`, which is what CI filters on. Fixtures come from
`tests/modules/ui_icons_test`, its packs (`test_path`, `test_svg`,
`test_settings`, ...) are declared in `ui_icons_test.icons.yml`. Templates and
setting defaults there are asserted verbatim by tests, so changing that file
breaks tests on purpose.

Run PHPUnit from the Drupal root with core's config, on a directory or a single
file. Kernel and functional tests need `SIMPLETEST_DB`, functional tests also
`SIMPLETEST_BASE_URL`.

```
vendor/bin/phpunit -c web/core web/modules/contrib/ui_icons
vendor/bin/phpunit -c web/core web/modules/contrib/ui_icons/path/to/Test.php
```

Playwright covers the browser flows. Specs in `tests/src/Playwright/Tests`,
fixtures and the `Drupal` page object in `tests/src/Playwright/{fixtures,objects}`.

```
npm run test          # firefox, @base tagged tests
npm run test:headed   # same, visible browser
npx playwright test -c playwright.local.config.ts tests/src/Playwright/Tests/ckeditor.spec.ts --project=chromium
```

The local config boots a PHP built-in server, and each worker installs its own
Drupal with `core/scripts/test-site.php` driven by `.env` (copy `.env.dist`).
Test setup is done per test with `drupal.installModules()` and
`drupal.drush('php:eval "..."')`, so keep it idempotent: tests in a worker share
one site.

CKEditor 5 browser coverage is Playwright only (`Tests/ckeditor.spec.ts`), the
old `FunctionalJavascript/IconPluginTest.php` was removed. Kernel tests cover
`IconDialog` and `IconFilterController`.

## QA

Config is local to the module: `.phpcs.xml` (Drupal + DrupalPractice + strict
types required), `phpstan.neon` (level 5), `.phpmd.xml`. Spelling is configured
per job in `.gitlab-ci.yml` (`_CSPELL_IGNORE_PATHS`), with project words in
`.cspell-project-words.txt`. There is no `.cspell.json`. From the Drupal root:

```
vendor/bin/phpcs --standard=web/modules/contrib/ui_icons/.phpcs.xml web/modules/contrib/ui_icons
vendor/bin/phpstan analyse -c web/modules/contrib/ui_icons/phpstan.neon web/modules/contrib/ui_icons
```

Never run php-cs-fixer, even though `.php-cs-fixer.php` is in the repo. CI
does not run it, and its rules restyle code against the rest of the codebase:
`self::assert*` in tests, `final` test classes, `\` before native calls. Fix
style with `phpcbf` and the same standard as `phpcs`.

**There is no PHPStan baseline.** Level 5 is clean, and
`reportUnmatchedIgnoredErrors: true` makes a dead `ignoreErrors` entry an error,
so keep it that way instead of suppressing new findings. This only holds with
the module's require-dev installed: without `linkit`, `link_attributes` and
`field_sample_value` you get bogus `class.notFound` errors from the field
submodules.

`phpstan.neon` excludes the file depending on canvas, which is *not* in
require-dev. If you add a file depending on contrib outside require-dev, add it
there too.

`.phpmd.baseline.xml` holds exactly two entries, `CouplingBetweenObjects` on
`IconEmbed` and `IconAutocomplete`. Both are inherent to what those classes are
(a filter plugin and a form element referencing a lot of core), so they are
accepted rather than designed around. Everything else must stay under the
thresholds, do not grow the baseline. Its paths are stored `/builds/project/ui_icons/...`
and rewritten by CI, so it will not match when run locally.

## Conventions

- `declare(strict_types=1);` everywhere, enforced by phpcs.
- All hooks are `#[Hook]` classes in `src/Hook/<CamelCaseModule>Hooks.php`, one
  per module, autowired. `ui_icons_font` is the exception: its only hook,
  `runtime_requirements`, is in `UiIconsFontRequirements.php`. There is no
  `.module` file left in the project and none should come back.
- Icon settings are always keyed by pack id (`[$pack_id => $settings]`) when
  passed as `#default_settings`. Flattening them silently empties the extractor
  sub-form.
- Never hand write markup for an icon, render through the icon pack template.

## Writing code

- Write the minimum code that solves the issue: no speculative feature, no
  abstraction for a single use, no setting nobody asked for.
- Touch only what the issue needs and match the style around it. Mention
  unrelated dead code instead of deleting it, but remove what your own change
  left unused.
- Prefer Drupal APIs to plain PHP. Check an API, a version or a package name in
  the code or its docs before you rely on it.
- A bug fix starts with a test that fails without the fix.
- Comments explain why, never what. phpcs requires `@param` tags, so make each
  one say what the signature cannot. Wrap comments at 80 columns: `phpcbf` does
  not fix `Drupal.Files.LineLength.TooLong`.
- When a request is ambiguous, ask before writing code.
- Commits follow `<type>: #<issue> <Message>`, such as
  `fix: #3591912 Cannot clone ...`. Types in use: `fix`, `feat`, `tests`,
  `task`, `chore`, `docs`, `style`, `refactor`. `CHANGELOG.md` is generated
  from them by git-cliff (`cliff.toml`), never edit it by hand.

## Issues

Issues are GitLab work items of `project/ui_icons`:
https://git.drupalcode.org/project/ui_icons/-/work_items. Descriptions and
notes are Markdown. Link an issue as `#3591908` and a note by its URL plus
`#note_ID`.

Labels are mostly the state, such as `state::needsReview`. Add a priority only
above normal: `priority::major` or `priority::critical`. Leave out category,
version and any other label unless it is really required.

Branches are named after the work item, such as
`3591912-windows-invalid-filename`, on the project or on the issue fork. Forks
and push access are managed from the "Manage forks" link drupalbot posts on
every work item.

### Writing an issue, a comment or a merge request

Keep it short and direct. Use the Default template, fill only the sections
that apply and delete its HTML comments. Each section is one short paragraph
of plain statements. The problem says what is wrong and for whom, not the fix.

**A bug needs steps to reproduce**, unless they do not apply: the version, the
exact steps, the expected and the actual result.

When the code shows how to fix it, the proposed resolution is only a hint, such
as "Remove the unused fixture." Spell out only what is unclear or open to
debate, such as a choice between two approaches.

Write prose, with a list only for steps to reproduce and remaining tasks.
Quote only the relevant lines of a log, in a code block. A comment is 2 to 5
sentences, and a reply answers in its first sentence. No headings in a
comment, and no recap of the issue or of earlier comments. Every claim traces
to code you read or a test you ran. Write plain American English, with no
filler, no em-dashes and no emojis.

A potential security issue is never public: turn on confidentiality, as the
template says.
