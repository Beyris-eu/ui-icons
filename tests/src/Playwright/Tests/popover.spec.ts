import { expect, Locator, Page } from '@playwright/test'
import { test } from '../fixtures/loader'
import { Drupal } from '../objects/Drupal'
import config from '../playwright.config.loader'

// Browser coverage for the icon popover selector. The packs and their groups
// come from
// modules/ui_icons_popover/tests/modules/ui_icons_popover_test/ui_icons_popover_test.icons.yml:
// 'arrows' in popover_one and 'Arrows' in popover_two merge into one group.
// @see \Drupal\ui_icons_popover\Element\IconPopover
// @see \Drupal\ui_icons_popover\Controller\IconPopoverController

const BUNDLE_ID = 'page'
const FIELD_NAME = 'field_icon'
const PACKS = [ 'popover_one', 'popover_two', 'popover_flat' ]

/**
 * Create a content type with a ui_icon field edited through the popover.
 *
 * The displays are set outside the field guard, so every test starts on the
 * popover selector whatever an earlier test in the worker changed.
 *
 * @param {Drupal} drupal - The Drupal object.
 * @returns {Promise<void>}
 */
async function createIconField (drupal: Drupal): Promise<void> {
  const allowed = PACKS.map((pack) => `'${pack}' => '${pack}'`).join(', ')
  const php = [
    `if (!\\Drupal\\node\\Entity\\NodeType::load('${BUNDLE_ID}')) {`,
    `\\Drupal\\node\\Entity\\NodeType::create(['type' => '${BUNDLE_ID}', 'name' => 'Page'])->save();`,
    `}`,
    `if (!\\Drupal\\field\\Entity\\FieldConfig::loadByName('node', '${BUNDLE_ID}', '${FIELD_NAME}')) {`,
    `\\Drupal\\field\\Entity\\FieldStorageConfig::create(['field_name' => '${FIELD_NAME}', 'entity_type' => 'node', 'type' => 'ui_icon'])->save();`,
    `\\Drupal\\field\\Entity\\FieldConfig::create(['field_name' => '${FIELD_NAME}', 'entity_type' => 'node', 'bundle' => '${BUNDLE_ID}', 'label' => 'Icon', 'settings' => ['allowed_icon_pack' => [${allowed}]]])->save();`,
    `}`,
    `\\Drupal::service('entity_display.repository')->getFormDisplay('node', '${BUNDLE_ID}')`,
    `->setComponent('${FIELD_NAME}', ['type' => 'icon_widget', 'settings' => ['icon_selector' => 'icon_popover']])->save();`,
    `\\Drupal::service('entity_display.repository')->getViewDisplay('node', '${BUNDLE_ID}')`,
    `->setComponent('${FIELD_NAME}', ['label' => 'hidden', 'type' => 'icon_formatter'])->save();`,
  ].join(' ')
  await drupal.drush(`php:eval "${php}"`)
}

/**
 * The input holding the value, hidden once the popover takes over.
 *
 * @param {Page} page - The Playwright page.
 * @param {number} delta - The field item delta.
 * @returns {Locator} The input locator.
 */
function input (page: Page, delta: number = 0): Locator {
  return page.locator(`input[name="${FIELD_NAME}[${delta}][value][icon_id]"]`)
}

/**
 * The button opening the popover.
 *
 * @param {Page} page - The Playwright page.
 * @param {number} delta - The field item delta.
 * @returns {Locator} The trigger locator.
 */
function trigger (page: Page, delta: number = 0): Locator {
  return page.locator('.ui-icons-popover__trigger').nth(delta)
}

/**
 * The popover panel.
 *
 * @param {Page} page - The Playwright page.
 * @param {number} delta - The field item delta.
 * @returns {Locator} The panel locator.
 */
function panel (page: Page, delta: number = 0): Locator {
  return page.locator('.ui-icons-popover__panel').nth(delta)
}

/**
 * The icon tiles shown in the open panel.
 *
 * @param {Page} page - The Playwright page.
 * @returns {Locator} The tiles locator.
 */
function tiles (page: Page): Locator {
  return page.locator('.ui-icons-popover__panel:popover-open .ui-icons-popover__tile')
}

/**
 * One icon tile of the open panel.
 *
 * @param {Page} page - The Playwright page.
 * @param {string} id - The icon full id.
 * @returns {Locator} The tile locator.
 */
function tile (page: Page, id: string): Locator {
  return page.locator(`.ui-icons-popover__panel:popover-open .ui-icons-popover__tile[data-icon-id="${id}"]`)
}

/**
 * Open the node add form.
 *
 * @param {Page} page - The Playwright page.
 * @returns {Promise<void>}
 */
async function openForm (page: Page): Promise<void> {
  await page.goto(config.contentTypeAdd.replace('{bundle}', BUNDLE_ID))
  await expect(trigger(page)).toBeVisible()
}

test.beforeEach('Setup', async ({ drupal }) => {
  await drupal.installModules([ 'node', 'field', 'ui_icons_field', 'ui_icons_popover', 'ui_icons_popover_test' ])
  await createIconField(drupal)
  await drupal.loginAsAdmin()
})

test('Browse a group and pick an icon', { tag: [ '@base' ] }, async ({ page, drupal }) => {
  await test.step('The trigger replaces the raw input', async () => {
    await openForm(page)
    await expect(input(page)).toBeHidden()
    await expect(trigger(page)).toContainText('Choose an icon')
  })

  await test.step('The rail lists the merged groups, All selected', async () => {
    await trigger(page).click()
    await expect(panel(page)).toBeVisible()
    // Recent is hidden while empty.
    await expect(panel(page).getByRole('tab')).toHaveText([ /^All\s*6$/, /^Arrows\s*4$/, /^Weather\s*1$/, /^Other\s*1$/ ])
    await expect(panel(page).getByRole('tab', { name: /^All/ })).toHaveAttribute('aria-selected', 'true')
  })

  await test.step('A group shows its icons from both packs', async () => {
    await panel(page).getByRole('tab', { name: /^Arrows/ }).click()
    await expect(tiles(page)).toHaveCount(4)
    await expect(tile(page, 'popover_two:dot.name').locator('img')).toHaveAttribute('src', /dot\.name\.svg$/)

    await tile(page, 'popover_one:arrow-up').hover()
    await expect(panel(page).locator('.ui-icons-popover__footer')).toHaveText('Arrow Up')
  })

  await test.step('Picking closes the panel and rebuilds the element', async () => {
    await tile(page, 'popover_two:dot.name').click()
    await expect(panel(page)).toBeHidden()
    await expect(input(page)).toHaveValue('popover_two:dot.name')
    // The rebuild renders the server preview again, in the new trigger.
    await expect(trigger(page).locator('img[src$="dot.name.svg"]')).toBeVisible()
    await expect(trigger(page)).toContainText('Dot Name')
    await drupal.ajaxReady()
    await expect(trigger(page)).toBeFocused()
  })

  await test.step('The saved node renders the picked icon', async () => {
    await page.locator('[name="title[0][value]"]').fill('Popover icon content')
    await page.locator('[data-drupal-selector="edit-submit"]').click()
    await drupal.expectMessage('has been created')
    await expect(page.locator('svg circle')).toBeVisible()
  })
})

test('The panel reports an index that fails to load', { tag: [ '@base' ] }, async ({ page }) => {
  await page.route('**/ui-icons/ajax/popover/index**', (route) => route.abort())
  await openForm(page)
  await trigger(page).click()

  await expect(panel(page)).toContainText('Icons could not be loaded.')
})
