import { expect, Locator, Page } from '@playwright/test'
import { test } from '../fixtures/loader'
import { Drupal } from '../objects/Drupal'
import config from '../playwright.config.loader'

// Browser coverage for the icon picker modal, the grid alternative to the
// autocomplete. Everything the modal does is ajax, so it cannot be covered by
// the IconSelectFormTest kernel test.
// The icon pack and its icons come from
// tests/modules/ui_icons_test/ui_icons_test.icons.yml.
// @see \Drupal\ui_icons_picker\Form\IconSelectForm
// @see \Drupal\ui_icons_picker\Element\IconPicker

const PACK_ID = 'test_path'
const BUNDLE_ID = 'page'
const FIELD_NAME = 'field_icon'

/**
 * Create a content type with a ui_icon field edited through the picker.
 *
 * The displays are set outside the field guard, so the beforeEach hook puts
 * the icon_picker selector back for every test. Workers share one site and
 * the config runs fullyParallel, so a test that switched the selector must
 * not decide what the next one gets.
 *
 * @param {Drupal} drupal - The Drupal object.
 * @returns {Promise<void>}
 */
async function createIconField (drupal: Drupal): Promise<void> {
  const php = [
    `if (!\\Drupal\\node\\Entity\\NodeType::load('${BUNDLE_ID}')) {`,
    `\\Drupal\\node\\Entity\\NodeType::create(['type' => '${BUNDLE_ID}', 'name' => 'Page'])->save();`,
    `}`,
    `if (!\\Drupal\\field\\Entity\\FieldConfig::loadByName('node', '${BUNDLE_ID}', '${FIELD_NAME}')) {`,
    `\\Drupal\\field\\Entity\\FieldStorageConfig::create(['field_name' => '${FIELD_NAME}', 'entity_type' => 'node', 'type' => 'ui_icon'])->save();`,
    `\\Drupal\\field\\Entity\\FieldConfig::create(['field_name' => '${FIELD_NAME}', 'entity_type' => 'node', 'bundle' => '${BUNDLE_ID}', 'label' => 'Icon', 'settings' => ['allowed_icon_pack' => ['${PACK_ID}' => '${PACK_ID}']]])->save();`,
    `}`,
    `\\Drupal::service('entity_display.repository')->getFormDisplay('node', '${BUNDLE_ID}')`,
    `->setComponent('${FIELD_NAME}', ['type' => 'icon_widget', 'settings' => ['icon_selector' => 'icon_picker']])->save();`,
    `\\Drupal::service('entity_display.repository')->getViewDisplay('node', '${BUNDLE_ID}')`,
    `->setComponent('${FIELD_NAME}', ['label' => 'hidden', 'type' => 'icon_formatter'])->save();`,
  ].join(' ')
  await drupal.drush(`php:eval "${php}"`)
}

/**
 * Switch the field widget over to the autocomplete selector.
 *
 * The grid is then reached from the preview box instead of the input, which
 * keeps typing a name available. Runs after createIconField(), which the
 * beforeEach hook re-runs to put the icon_picker selector back.
 *
 * @param {Drupal} drupal - The Drupal object.
 * @returns {Promise<void>}
 */
async function useAutocompleteWidget (drupal: Drupal): Promise<void> {
  const php = [
    `\\Drupal::service('entity_display.repository')->getFormDisplay('node', '${BUNDLE_ID}')`,
    `->setComponent('${FIELD_NAME}', ['type' => 'icon_widget', 'settings' => ['icon_selector' => 'icon_autocomplete']])->save();`,
  ].join(' ')
  await drupal.drush(`php:eval "${php}"`)
}

/**
 * The autocomplete input holding the selected value on the node form.
 *
 * @param {Page} page - The Playwright page.
 * @returns {Locator} The input locator.
 */
function autocompleteInput (page: Page): Locator {
  return page.locator('.ui-icons-wrapper input[name$="[icon_id]"]')
}

/**
 * The preview box of the autocomplete, doubling as the grid trigger.
 *
 * @param {Page} page - The Playwright page.
 * @returns {Locator} The preview locator.
 */
function previewTrigger (page: Page): Locator {
  return page.locator('.ui-icons-preview--picker')
}

/**
 * The picker input holding the selected value on the node form.
 *
 * @param {Page} page - The Playwright page.
 * @returns {Locator} The input locator.
 */
function pickerInput (page: Page): Locator {
  return page.locator('input.form-icon-dialog')
}

/**
 * The radio input of an icon in the grid.
 *
 * @param {Page} page - The Playwright page.
 * @param {string} value - The radio value, an icon full id or '_none_'.
 * @returns {Locator} The radio locator.
 */
function iconRadio (page: Page, value: string): Locator {
  return page.locator(`input[name="icon_full_id"][value="${value}"]`)
}

/**
 * Open the node form and click the picker input to open the modal.
 *
 * @param {Page} page - The Playwright page.
 * @returns {Promise<void>}
 */
async function openPicker (page: Page): Promise<void> {
  await page.goto(config.contentTypeAdd.replace('{bundle}', BUNDLE_ID))

  const input = pickerInput(page)
  await expect(input).toBeVisible()
  await input.click()

  await expect(page.locator('.icon-library-widget-modal')).toBeVisible()
  await expect(page.locator('.icon-picker-modal__content')).toBeVisible()
}

/**
 * Pick an icon from the grid.
 *
 * The radio itself is `display: none`, the user clicks its label. Picking is
 * what submits the modal: js/library.js clicks the hidden select button on any
 * radio click, so there is no separate confirmation step.
 *
 * @param {Page} page - The Playwright page.
 * @param {string} value - The radio value, an icon full id or '_none_'.
 * @returns {Promise<void>}
 */
async function pickIcon (page: Page, value: string): Promise<void> {
  // Labels start as a spinner and js/icon.preview.js swaps every label's
  // innerHTML in once the preview endpoint answers. Clicking during that pass
  // detaches the node mid-click, so wait for the whole grid to settle first.
  await expect(page.locator('.icon-picker-modal__content img[src*="spinner.svg"]')).toHaveCount(0)

  // Resolve the label through the radio value rather than a captured `for` id.
  // Reopening the modal renders a fresh grid with new ids, and this locator
  // re-resolves on Playwright's click retries instead of going stale.
  await page
    .locator(`.icon-picker-modal__content .form-item:has(input[name="icon_full_id"][value="${value}"]) label`)
    .click()

  await expect(page.locator('.icon-library-widget-modal')).toBeHidden()
}

/**
 * Apply the name filter and wait for the grid to come back.
 *
 * Enter searches at once and drops the pending debounced search, so a single
 * request goes out.
 *
 * @param {Page} page - The Playwright page.
 * @param {string} query - The filter query.
 * @returns {Promise<void>}
 */
async function applyFilter (page: Page, query: string): Promise<void> {
  const filter = page.locator('input[name="filter"]')
  await filter.fill(query)
  const search = page.waitForResponse(isFormAjax)
  await filter.press('Enter')
  await search
  await page.locator('.ajax-progress, .ajax-progress-throbber').first().waitFor({ state: 'hidden' }).catch(() => {})
}

/**
 * Whether a response or request is an ajax post of the picker form.
 *
 * @param {{ url: () => string }} message - The response or request.
 * @returns {boolean} TRUE for a form ajax post.
 */
function isFormAjax (message: { url: () => string }): boolean {
  return message.url().includes('_wrapper_format=drupal_ajax')
}

test.beforeEach('Setup', async ({ drupal }) => {
  await drupal.installModules([ 'node', 'field', 'ui_icons_field', 'ui_icons_picker', 'ui_icons_test' ])
  await createIconField(drupal)
  await drupal.loginAsAdmin()
})

test('Pick an icon from the modal grid', { tag: [ '@base' ] }, async ({ page, drupal }) => {
  await test.step('The modal lists the icons of the allowed pack', async () => {
    await openPicker(page)

    // The empty option is always first so a selection can be cleared.
    await expect(iconRadio(page, '_none_')).toHaveCount(1)
    await expect(iconRadio(page, `${PACK_ID}:foo`)).toHaveCount(1)
  })

  await test.step('Picking an icon fills the input and closes the modal', async () => {
    await pickIcon(page, `${PACK_ID}:foo`)

    await expect(pickerInput(page)).toHaveValue(`${PACK_ID}:foo`)
    // Setting the value triggers `change`, which the element uses to rebuild
    // itself through ajax. Submitting before that lands drops the icon, so
    // wait for the widget preview that the rebuild brings in.
    await expect(page.locator('.ui-icons-preview-icon img[src$="foo.png"]')).toBeVisible()
  })

  await test.step('The saved node renders the picked icon', async () => {
    await page.locator('[name="title[0][value]"]').fill('Picked icon content')
    await page.locator('[data-drupal-selector="edit-submit"]').click()
    await drupal.expectMessage('has been created')

    await expect(page.locator('img[src$="foo.png"]')).toBeVisible()
  })
})

test('Filter the grid and clear a selection', { tag: [ '@base' ] }, async ({ page }) => {
  await test.step('Filtering narrows the grid down to the match', async () => {
    await openPicker(page)
    await expect(iconRadio(page, `${PACK_ID}:foo`)).toHaveCount(1)

    await applyFilter(page, 'bar')

    await expect(iconRadio(page, `${PACK_ID}:bar`)).toHaveCount(1)
    await expect(iconRadio(page, `${PACK_ID}:foo`)).toHaveCount(0)
  })

  await test.step('A filter matching nothing shows the empty state', async () => {
    await applyFilter(page, 'no_icon_matches_this')

    await expect(page.locator('.icon-library-widget-modal')).toContainText('No icon found')
    await expect(page.locator('input[name="icon_full_id"]')).toHaveCount(0)
  })

  await test.step('Clearing the filter brings the whole grid back', async () => {
    await applyFilter(page, '')

    await expect(iconRadio(page, `${PACK_ID}:foo`)).toHaveCount(1)
  })

  await test.step('The empty option clears an existing selection', async () => {
    await pickIcon(page, `${PACK_ID}:foo`)
    await expect(pickerInput(page)).toHaveValue(`${PACK_ID}:foo`)
    // Let the `change` ajax rebuild of the element land before reopening, it
    // replaces the very input the modal is opened from.
    await expect(page.locator('.ui-icons-preview-icon img[src$="foo.png"]')).toBeVisible()

    await pickerInput(page).click()
    await expect(page.locator('.icon-picker-modal__content')).toBeVisible()
    await pickIcon(page, '_none_')

    await expect(pickerInput(page)).toHaveValue('')
  })
})

// The auto-search used to replace the whole form, the filter input included,
// so whatever was typed while it ran landed before the first letters.
test('Typing in the filter survives the auto-search', { tag: [ '@base' ] }, async ({ page }) => {
  await openPicker(page)
  await page.locator('input[name="filter"]').click()

  const search = page.waitForResponse(isFormAjax)
  await page.keyboard.type('ba')
  await search
  await expect(page.locator('.ajax-progress, .ajax-progress-throbber')).toHaveCount(0)
  await page.keyboard.type('r')

  await expect(page.locator('input[name="filter"]')).toHaveValue('bar')
})

// The search used to fire on every keyup, arrows and Shift included, and
// Enter posted at once and then again on its keyup.
test('The filter searches on a changed value only', { tag: [ '@base' ] }, async ({ page }) => {
  await openPicker(page)
  const posts: string[] = []
  page.on('request', (request) => {
    if (isFormAjax(request)) {
      posts.push(request.url())
    }
  })
  const filter = page.locator('input[name="filter"]')
  await filter.click()

  await test.step('Keys that change nothing do not search', async () => {
    await page.keyboard.press('ArrowLeft')
    await page.keyboard.press('Shift')
    await page.keyboard.press('End')
    // Longer than the debounce.
    await page.waitForTimeout(1000)
    expect(posts).toHaveLength(0)
  })

  await test.step('A single character does not search', async () => {
    await page.keyboard.type('b')
    await page.waitForTimeout(1000)
    expect(posts).toHaveLength(0)
  })

  await test.step('Two characters search once the typing stops', async () => {
    const search = page.waitForResponse(isFormAjax)
    await page.keyboard.type('a')
    await search
    await expect(iconRadio(page, `${PACK_ID}:bar`)).toHaveCount(1)
    expect(posts).toHaveLength(1)
  })

  await test.step('Enter on an unchanged value does not search again', async () => {
    await page.keyboard.press('Enter')
    await page.waitForTimeout(1000)
    expect(posts).toHaveLength(1)
  })
})

// Ajax deep merges drupalSettings into the previous ones, so a search, or a
// shorter page, still sent the ids of the grid before it to the preview.
test('The preview loads the listed icons only', { tag: [ '@base' ] }, async ({ page }) => {
  await openPicker(page)
  await applyFilter(page, 'bar')
  await expect(page.locator('.icon-picker-modal__content img[src*="spinner.svg"]')).toHaveCount(0)

  const preview = page.waitForRequest((request) => request.url().includes('ui-icons/ajax/preview/icons'))
  await applyFilter(page, 'foo')
  // Search results are keyed by id, they reached the browser as an object.
  const posted: string[] = Object.values((await preview).postDataJSON().icon_full_ids)

  const listed = await page.locator('input[name="icon_full_id"]').evaluateAll(
    (radios) => radios.map((radio) => (radio as HTMLInputElement).value).filter((value) => value !== '_none_'),
  )
  expect(listed.length).toBeGreaterThan(0)
  expect([...posted].sort()).toEqual([...listed].sort())
})

test('Open the grid from the autocomplete preview', { tag: [ '@base' ] }, async ({ page, drupal }) => {
  await useAutocompleteWidget(drupal)
  await page.goto(config.contentTypeAdd.replace('{bundle}', BUNDLE_ID))

  await test.step('The empty preview offers itself as the trigger', async () => {
    await expect(previewTrigger(page)).toBeVisible()
    await expect(previewTrigger(page)).toHaveAttribute('role', 'button')
    await expect(previewTrigger(page)).toHaveAttribute('tabindex', '0')
    // Stands in for the icon that is not picked yet, an empty box reads as
    // nothing to click.
    await expect(previewTrigger(page).locator('.ui-icons-preview-placeholder')).toBeVisible()
    // Triggering from the preview rather than the input is what keeps the
    // autocomplete, the whole point over the icon_picker element.
    await expect(autocompleteInput(page)).toHaveAttribute('data-autocomplete-path', /autocomplete/)
  })

  await test.step('Clicking the preview opens the grid', async () => {
    await previewTrigger(page).click()

    await expect(page.locator('.icon-picker-modal__content')).toBeVisible()
    await expect(iconRadio(page, `${PACK_ID}:foo`)).toHaveCount(1)
  })

  await test.step('Picking fills the input and draws the preview', async () => {
    await pickIcon(page, `${PACK_ID}:foo`)

    await expect(autocompleteInput(page)).toHaveValue(`${PACK_ID}:foo`)
    // Setting the value triggers `change`, which the element uses to rebuild
    // itself through ajax and bring the preview in.
    await expect(page.locator('.ui-icons-preview-icon img[src$="foo.png"]')).toBeVisible()
    await expect(page.locator('.ui-icons-preview-placeholder')).toHaveCount(0)
  })

  await test.step('The keyboard reaches the grid as well', async () => {
    await previewTrigger(page).focus()
    await page.keyboard.press('Enter')

    await expect(page.locator('.icon-picker-modal__content')).toBeVisible()
  })
})

// Core disables the input that triggered an ajax request until it answers,
// and a disabled input is left out of the submitted form. The element's own
// request is held here, so Save always lands while it is in flight instead
// of depending on the server speed.
const fastSaveCases: { name: string; pickSuggestion: boolean }[] = [
  { name: 'after picking a suggestion', pickSuggestion: true },
  { name: 'straight from typing the id', pickSuggestion: false },
]

for (const data of fastSaveCases) {
  test(`Save ${data.name} while the element rebuilds keeps the icon`, { tag: [ '@base' ] }, async ({ page, drupal }) => {
    await useAutocompleteWidget(drupal)
    await page.goto(config.contentTypeAdd.replace('{bundle}', BUNDLE_ID))
    await page.locator('[name="title[0][value]"]').fill(`Fast save ${data.name}`)

    await page.route(/element_parents=/, async (route) => {
      await new Promise((resolve) => setTimeout(resolve, 3000))
      // The page may have moved on by then, which is the point.
      await route.continue().catch(() => {})
    })

    await autocompleteInput(page).fill(`${PACK_ID}:foo`)
    if (data.pickSuggestion) {
      const results = page.locator('ul.ui-autocomplete li')
      await expect(results).toHaveCount(1)
      await results.first().click()
    }
    await page.locator('[data-drupal-selector="edit-submit"]').click()

    await drupal.expectMessage('has been created')
    await expect(page.locator('img[src$="foo.png"]')).toBeVisible()
  })
}

// jQuery UI focuses the filter as the dialog opens, while its 95% height is
// still unresolved: core only converts it to pixels 20ms later. The browser
// scrolled the content to show the focused filter and never scrolled back.
test('The modal grid opens with its filter in view', { tag: [ '@base' ] }, async ({ page }) => {
  // Short enough for the grid of the test pack to overflow the dialog.
  await page.setViewportSize({ width: 600, height: 220 })
  await openPicker(page)
  await expect(page.locator('.icon-picker-modal__content img[src*="spinner.svg"]')).toHaveCount(0)

  await expect(page.locator('.icon-library-widget-modal [name="filter"]')).toBeFocused()
  const content = page.locator('.icon-library-widget-modal .ui-dialog-content')
  expect(await content.evaluate((el) => el.scrollTop)).toBe(0)
})

// Core disables the input while the element rebuilds itself, so the dialog
// could not hand the focus back to it, and the element opts out of core's
// refocus after the rebuild: the focus fell to the page.
test('Picking hands the focus back to the input', { tag: [ '@base' ] }, async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 300 })
  await page.goto(config.contentTypeAdd.replace('{bundle}', BUNDLE_ID))
  const input = pickerInput(page)
  await input.scrollIntoViewIfNeeded()
  const scrollY = await page.evaluate(() => window.scrollY)

  await input.click()
  await expect(page.locator('.icon-picker-modal__content')).toBeVisible()
  await pickIcon(page, `${PACK_ID}:foo`)
  await expect(page.locator('.ui-icons-preview-icon img[src$="foo.png"]')).toBeVisible()

  await expect(pickerInput(page)).toBeFocused()
  expect(await page.evaluate(() => window.scrollY)).toBe(scrollY)
})

// jQuery UI aligns the suggestions on the left of the input and does not keep
// them in the window: next to its right edge, as in a builder side panel, a
// menu wider than the input was cut off.
test('Suggestions stay inside the window next to its right edge', { tag: [ '@base' ] }, async ({ page, drupal }) => {
  await useAutocompleteWidget(drupal)
  await page.goto(config.contentTypeAdd.replace('{bundle}', BUNDLE_ID))
  await autocompleteInput(page).evaluate((input) => {
    input.style.cssText = 'position: fixed; top: 100px; right: 0; width: 60px;'
  })

  await autocompleteInput(page).fill(`${PACK_ID}:foo`)
  const menu = page.locator('ul.ui-autocomplete', { has: page.locator('.ui-icons-result') })
  await expect(menu.locator('li')).toHaveCount(1)

  const box = await menu.boundingBox()
  expect(box.width).toBeGreaterThan(100)
  expect(box.x + box.width).toBeLessThanOrEqual(page.viewportSize().width)
})
