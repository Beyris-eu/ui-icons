import { expect, Locator, Page } from '@playwright/test'
import { test } from '../fixtures/loader'
import { Drupal } from '../objects/Drupal'
import config from '../playwright.config.loader'

// Browser coverage for the icon_autocomplete element: what it tells assistive
// technologies, where the focus goes, and when it rebuilds itself.
// The icon pack and its icons come from
// tests/modules/ui_icons_test/ui_icons_test.icons.yml.
// @see \Drupal\ui_icons\Element\IconAutocomplete
// @see js/icon.autocomplete.js

const PACK_ID = 'test_path'
const BUNDLE_ID = 'icon_autocomplete'
const FIELD_NAME = 'field_icon_autocomplete'

/**
 * Create a content type with a ui_icon field edited through the autocomplete.
 *
 * The form display is set outside the field guard: workers share one site, so
 * every test sets the result format it needs.
 *
 * @param {Drupal} drupal - The Drupal object.
 * @param {string} resultFormat - The suggestions format, 'list' or 'grid'.
 * @returns {Promise<void>}
 */
async function createIconField (drupal: Drupal, resultFormat: string = 'list'): Promise<void> {
  const php = [
    `if (!\\Drupal\\node\\Entity\\NodeType::load('${BUNDLE_ID}')) {`,
    `\\Drupal\\node\\Entity\\NodeType::create(['type' => '${BUNDLE_ID}', 'name' => 'Icon autocomplete'])->save();`,
    `}`,
    `if (!\\Drupal\\field\\Entity\\FieldConfig::loadByName('node', '${BUNDLE_ID}', '${FIELD_NAME}')) {`,
    `\\Drupal\\field\\Entity\\FieldStorageConfig::create(['field_name' => '${FIELD_NAME}', 'entity_type' => 'node', 'type' => 'ui_icon'])->save();`,
    `\\Drupal\\field\\Entity\\FieldConfig::create(['field_name' => '${FIELD_NAME}', 'entity_type' => 'node', 'bundle' => '${BUNDLE_ID}', 'label' => 'Icon', 'settings' => ['allowed_icon_pack' => ['${PACK_ID}' => '${PACK_ID}']]])->save();`,
    `}`,
    `\\Drupal::service('entity_display.repository')->getFormDisplay('node', '${BUNDLE_ID}')`,
    `->setComponent('${FIELD_NAME}', ['type' => 'icon_widget', 'settings' => ['icon_selector' => 'icon_autocomplete', 'result_format' => '${resultFormat}']])->save();`,
  ].join(' ')
  await drupal.drush(`php:eval "${php}"`)
}

/**
 * The autocomplete input of the field.
 *
 * @param {Page} page - The Playwright page.
 * @returns {Locator} The input locator.
 */
function iconInput (page: Page): Locator {
  return page.locator(`input[name="${FIELD_NAME}[0][value][icon_id]"]`)
}

/**
 * The suggestions menu of the autocomplete.
 *
 * @param {Page} page - The Playwright page.
 * @returns {Locator} The menu locator.
 */
function suggestions (page: Page): Locator {
  return page.locator('ul.ui-autocomplete', { has: page.locator('.ui-icons-result, .ui-icons-result-grid') })
}

/**
 * The preview of the picked icon, brought in by the element rebuild.
 *
 * @param {Page} page - The Playwright page.
 * @returns {Locator} The preview image locator.
 */
function preview (page: Page): Locator {
  return page.locator('.ui-icons-preview-icon img[src$="foo.png"]')
}

test.beforeEach('Setup', async ({ drupal }) => {
  await drupal.installModules([ 'node', 'field', 'ui_icons_field', 'ui_icons_test' ])
  await createIconField(drupal)
  await drupal.loginAsAdmin()
})

// Core disables the input while the element rebuilds itself, and the element
// opts out of core's refocus after the rebuild: the focus fell to the page.
test('Picking a suggestion with the keyboard keeps the focus', { tag: [ '@base' ] }, async ({ page }) => {
  await page.goto(config.contentTypeAdd.replace('{bundle}', BUNDLE_ID))
  await iconInput(page).click()
  await page.keyboard.type(`${PACK_ID}:foo`)
  await expect(suggestions(page).locator('li')).toHaveCount(1)

  await page.keyboard.press('ArrowDown')
  await page.keyboard.press('Enter')

  await expect(preview(page)).toBeVisible()
  await expect(iconInput(page)).toHaveValue(`${PACK_ID}:foo`)
  await expect(iconInput(page)).toBeFocused()
})

test('The input says why no suggestion shows', { tag: [ '@base' ] }, async ({ page }) => {
  await page.goto(config.contentTypeAdd.replace('{bundle}', BUNDLE_ID))
  const status = page.locator('.ui-icons-autocomplete-status')

  await test.step('Below the minimum length', async () => {
    await iconInput(page).click()
    await page.keyboard.type('f')

    await expect(status).toHaveAttribute('role', 'status')
    await expect(status).toHaveText('Type at least 2 characters.')
  })

  await test.step('When nothing matches', async () => {
    await page.keyboard.type('zzz')

    await expect(status).toHaveText('No icon matches fzzz.')
  })

  await test.step('Not once suggestions show', async () => {
    await iconInput(page).fill('fo')

    await expect(suggestions(page).locator('li').first()).toBeVisible()
    await expect(status).toBeEmpty()
  })
})

for (const resultFormat of [ 'list', 'grid' ]) {
  test(`The ${resultFormat} of suggestions is a listbox the input controls`, { tag: [ '@base' ] }, async ({ page, drupal }) => {
    await createIconField(drupal, resultFormat)
    await page.goto(config.contentTypeAdd.replace('{bundle}', BUNDLE_ID))
    const input = iconInput(page)

    await test.step('Closed', async () => {
      await expect(input).toHaveAttribute('role', 'combobox')
      await expect(input).toHaveAttribute('aria-autocomplete', 'list')
      await expect(input).toHaveAttribute('aria-expanded', 'false')
    })

    const listbox = page.locator('[role="listbox"]', { has: page.locator('.ui-icons-result, .ui-icons-result-grid') })
    const options = listbox.locator('[role="option"]')

    await test.step('Open', async () => {
      await input.click()
      await page.keyboard.type('ba')
      await expect(suggestions(page).locator('li').nth(1)).toBeVisible()

      await expect(input).toHaveAttribute('aria-expanded', 'true')
      await expect(input).toHaveAttribute('aria-controls', await listbox.getAttribute('id'))
      await expect(listbox).toHaveAttribute('aria-label', /.+/)
      await expect(options).toHaveCount(await suggestions(page).locator('li').count())
      await expect(input).not.toHaveAttribute('aria-activedescendant')
    })

    await test.step('The arrow keys move the active option', async () => {
      await page.keyboard.press('ArrowDown')
      await expect(input).toHaveAttribute('aria-activedescendant', await options.nth(0).getAttribute('id'))
      await expect(options.nth(0)).toHaveAttribute('aria-selected', 'true')

      await page.keyboard.press('ArrowDown')
      await expect(input).toHaveAttribute('aria-activedescendant', await options.nth(1).getAttribute('id'))
      await expect(options.nth(1)).toHaveAttribute('aria-selected', 'true')
      await expect(options.nth(0)).toHaveAttribute('aria-selected', 'false')
    })

    await test.step('Closed again', async () => {
      await page.keyboard.press('Escape')

      await expect(input).toHaveAttribute('aria-expanded', 'false')
      await expect(input).not.toHaveAttribute('aria-activedescendant')
    })
  })
}
