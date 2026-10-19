import AxeBuilder from '@axe-core/playwright'
import { chromium, expect, Locator, Page } from '@playwright/test'
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
// Not picker.spec.ts's field_icon: specs in a worker share one site, and the
// first to create the field would decide its allowed packs for the other.
const FIELD_NAME = 'field_icon_popover'
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

test('The search box has the focus as soon as the panel shows', { tag: [ '@base' ] }, async ({ page }) => {
  await openForm(page)

  // In the same task as the click: what the editor types next must reach it.
  const focused = await trigger(page).evaluate((button) => {
    button.click()
    return document.activeElement?.className
  })
  expect(focused).toBe('ui-icons-popover__search')
})

test('Words typed as soon as the panel shows are searched', { tag: [ '@base' ] }, async ({ page }) => {
  await openForm(page)

  // Typed before the browser fires the toggle event of the panel.
  await trigger(page).evaluate((button) => {
    button.click()
    const search = document.activeElement as HTMLInputElement
    search.value = 'arrow l'
    search.dispatchEvent(new Event('input', { bubbles: true }))
  })

  await expect(panel(page).getByRole('listbox').getByRole('option')).toHaveCount(1)
  await expect(panel(page).locator('.ui-icons-popover__search')).toHaveValue('arrow l')
})

test('Search and pick with the keyboard', { tag: [ '@base' ] }, async ({ page, drupal }) => {
  await test.step('Results narrow down and are announced', async () => {
    // Words typed while the index still loads must not be lost.
    await page.route('**/ui-icons/ajax/popover/index**', async (route) => {
      await new Promise((resolve) => setTimeout(resolve, 1000))
      await route.continue()
    })
    await openForm(page)
    await trigger(page).click()
    await page.keyboard.type('arrow l')

    await expect(panel(page).getByRole('listbox').getByRole('option')).toHaveCount(1)
    await expect(page.locator('#drupal-live-announce')).toContainText('1 icon')
  })

  await test.step('Down then Enter picks the result', async () => {
    await page.keyboard.press('ArrowDown')
    await page.keyboard.press('Enter')

    await expect(panel(page)).toBeHidden()
    await expect(input(page)).toHaveValue('popover_two:arrow-left')
    await expect(trigger(page)).toContainText('Arrow Left')
  })

  await test.step('A search matching nothing says so', async () => {
    await drupal.ajaxReady()
    await trigger(page).click()
    await page.keyboard.type('zzz')

    await expect(panel(page)).toContainText('No icon matches zzz.')
  })
})

test('Grid keyboard, edit mode, Escape, clear and Recent', { tag: [ '@base' ] }, async ({ page, drupal }) => {
  await test.step('Tab reaches the rail then the grid, arrows move, Enter picks', async () => {
    await openForm(page)
    await trigger(page).click()
    await expect(panel(page).getByRole('tab', { name: /^All/ })).toHaveAttribute('aria-selected', 'true')

    await page.keyboard.press('Tab')
    await page.keyboard.press('ArrowDown')
    await expect(panel(page).getByRole('tab', { name: /^Arrows/ })).toBeFocused()
    // Manual activation: moving the focus does not switch the view.
    await expect(panel(page).getByRole('tab', { name: /^All/ })).toHaveAttribute('aria-selected', 'true')
    await page.keyboard.press('Enter')
    await expect(panel(page).getByRole('tab', { name: /^Arrows/ })).toHaveAttribute('aria-selected', 'true')

    await page.keyboard.press('Tab')
    await expect(tile(page, 'popover_one:arrow-down')).toBeFocused()
    await page.keyboard.press('ArrowRight')
    await expect(tile(page, 'popover_two:arrow-left')).toBeFocused()
    await page.keyboard.press('Enter')

    await expect(input(page)).toHaveValue('popover_two:arrow-left')
  })

  await test.step('Editing opens on the icon group, the icon marked', async () => {
    await drupal.ajaxReady()
    await trigger(page).click()

    await expect(panel(page).getByRole('tab', { name: /^Arrows/ })).toHaveAttribute('aria-selected', 'true')
    await expect(tile(page, 'popover_two:arrow-left')).toHaveAttribute('aria-current', 'true')
    await expect(tile(page, 'popover_two:arrow-left')).toBeInViewport()
  })

  await test.step('Escape closes and gives the focus back', async () => {
    await page.keyboard.press('Escape')

    await expect(panel(page)).toBeHidden()
    await expect(trigger(page)).toBeFocused()
  })

  await test.step('Clearing empties the value, Recent then opens first', async () => {
    await page.locator('.ui-icons-popover__clear').click()
    await expect(input(page)).toHaveValue('')
    await drupal.ajaxReady()

    await trigger(page).click()
    await expect(panel(page).getByRole('tab', { name: /^Recent/ })).toHaveAttribute('aria-selected', 'true')
    await expect(tiles(page)).toHaveCount(1)
  })
})

test('Recent ignores junk in storage', { tag: [ '@base' ] }, async ({ page }) => {
  await test.step('A stored value that is no list counts as empty', async () => {
    await openForm(page)
    await page.evaluate(() => localStorage.setItem('ui_icons_popover.recent', '{"bad":1}'))
    await openForm(page)
    await trigger(page).click()

    await expect(panel(page).getByRole('tab', { name: /^All/ })).toHaveAttribute('aria-selected', 'true')
    await expect(panel(page).getByRole('tab', { name: /^Recent/ })).toHaveCount(0)
  })

  await test.step('Unknown ids and non strings are dropped', async () => {
    await page.evaluate(() => localStorage.setItem('ui_icons_popover.recent', JSON.stringify([ 'popover_one:gone', 42, 'popover_one:sun' ])))
    await openForm(page)
    await trigger(page).click()

    await expect(panel(page).getByRole('tab', { name: /^Recent/ })).toHaveAttribute('aria-selected', 'true')
    await expect(tiles(page)).toHaveCount(1)
    await expect(tile(page, 'popover_one:sun')).toHaveCount(1)
  })
})

test('Two popovers on one page stay independent', { tag: [ '@base' ] }, async ({ page, drupal }) => {
  const setCardinality = async (cardinality: number) => {
    await drupal.drush(`php:eval "\\Drupal\\field\\Entity\\FieldStorageConfig::loadByName('node', '${FIELD_NAME}')->setCardinality(${cardinality})->save();"`)
  }
  await setCardinality(2)
  try {
    await openForm(page)
    await trigger(page, 0).click()
    await expect(panel(page, 0)).toBeVisible()

    // The open panel covers the second trigger, so reach it with the keyboard.
    await trigger(page, 1).focus()
    await page.keyboard.press('Enter')
    await expect(panel(page, 0)).toBeHidden()
    await expect(panel(page, 1)).toBeVisible()

    await panel(page, 1).getByRole('tab', { name: /^Weather/ }).click()
    await tile(page, 'popover_one:sun').click()

    await expect(input(page, 1)).toHaveValue('popover_one:sun')
    await expect(input(page, 0)).toHaveValue('')
  }
  finally {
    await setCardinality(1)
  }
})

test('Small screens get a bottom sheet', { tag: [ '@base' ] }, async ({ page }) => {
  await page.setViewportSize({ width: 400, height: 800 })
  await openForm(page)
  await trigger(page).click()
  await expect(panel(page)).toBeVisible()

  const box = await panel(page).boundingBox()
  const viewport = await page.evaluate(() => ({ width: document.documentElement.clientWidth, height: window.innerHeight }))
  expect(Math.round(box?.width ?? 0)).toBe(viewport.width)
  expect(Math.round((box?.y ?? 0) + (box?.height ?? 0))).toBe(viewport.height)
})

test('The open popover has no axe violations on Default Admin', { tag: [ '@base' ] }, async ({ page, drupal }) => {
  // Workers run their tests one at a time, so putting the themes back keeps
  // the other specs on theirs.
  const themes = JSON.parse(await drupal.drush(`php:eval "echo json_encode(\\Drupal::config('system.theme')->get());"`))
  const setThemes = async (front: string, admin: string) => {
    await drupal.drush(`php:eval "\\Drupal::service('theme_installer')->install(['default_admin']); \\Drupal::configFactory()->getEditable('system.theme')->set('default', '${front}')->set('admin', '${admin}')->save();"`)
  }
  await setThemes('default_admin', 'default_admin')
  try {
    await openForm(page)
    await trigger(page).click()
    await expect(tiles(page).first()).toBeVisible()

    const results = await new AxeBuilder({ page }).include('.ui-icons-popover').analyze()
    expect(results.violations).toEqual([])
  }
  finally {
    await setThemes(themes.default, themes.admin ?? '')
  }
})

/**
 * Change a setting of the popover field for one test.
 *
 * @param {Drupal} drupal - The Drupal object.
 * @param {string} php - The method call on the field config, such as setRequired(TRUE).
 * @returns {Promise<void>}
 */
async function updateField (drupal: Drupal, php: string): Promise<void> {
  await drupal.drush(`php:eval "\\Drupal\\field\\Entity\\FieldConfig::loadByName('node', '${BUNDLE_ID}', '${FIELD_NAME}')->${php}->save();"`)
}

test('An empty required field is reported by the server', { tag: [ '@base' ] }, async ({ page, drupal }) => {
  await updateField(drupal, 'setRequired(TRUE)')
  try {
    await openForm(page)
    await page.locator('[name="title[0][value]"]').fill('No icon content')
    await page.locator('[data-drupal-selector="edit-submit"]').click()

    // The hidden input must not hold the browser validation back silently.
    await expect(page.getByRole('contentinfo', { name: 'Error message' })).toContainText('field is required')
    await expect(trigger(page)).toHaveAttribute('aria-invalid', 'true')
  }
  finally {
    await updateField(drupal, 'setRequired(FALSE)')
  }
})

test('Editing opens on the icon deep in a large group', { tag: [ '@base' ] }, async ({ page, drupal }) => {
  const allowed = [ ...PACKS, 'popover_big' ].map((pack) => `'${pack}' => '${pack}'`).join(', ')
  await updateField(drupal, `setSetting('allowed_icon_pack', [${allowed}])`)
  try {
    await openForm(page)
    await trigger(page).click()
    await page.keyboard.type('big 350')
    await expect(panel(page).getByRole('option')).toHaveCount(1)
    await page.keyboard.press('ArrowDown')
    await page.keyboard.press('Enter')
    await expect(input(page)).toHaveValue('popover_big:big-350')
    await drupal.ajaxReady()

    await trigger(page).click()
    // popover_big declares no group, its names all start with "big".
    await expect(panel(page).getByRole('tab', { name: /^Big\s*400$/ })).toHaveAttribute('aria-selected', 'true')
    await expect(tile(page, 'popover_big:big-350')).toBeInViewport()
    // The current icon is the grid tab stop: search, rail, then the grid.
    await page.keyboard.press('Tab')
    await page.keyboard.press('Tab')
    await expect(tile(page, 'popover_big:big-350')).toBeFocused()
  }
  finally {
    const packs = PACKS.map((pack) => `'${pack}' => '${pack}'`).join(', ')
    await updateField(drupal, `setSetting('allowed_icon_pack', [${packs}])`)
  }
})

test('The active search result is visible without admin theme colors', { tag: [ '@base' ] }, async ({ page }) => {
  // The test site theme defines none of the Default Admin or Gin tokens.
  await openForm(page)
  await trigger(page).click()
  await page.keyboard.type('arrow')
  await expect(panel(page).getByRole('option')).toHaveCount(4)
  await page.keyboard.press('ArrowDown')

  const active = panel(page).locator('[role="option"][aria-selected="true"]')
  await expect(active).toHaveCount(1)
  expect(await active.evaluate((element) => getComputedStyle(element).outlineStyle)).toBe('solid')
})

test('Tooltips name the icon, never its raw id', { tag: [ '@base' ] }, async ({ page }) => {
  await openForm(page)
  await trigger(page).click()
  await panel(page).getByRole('tab', { name: /^Arrows/ }).click()
  await expect(tile(page, 'popover_two:dot.name').locator('img')).toBeVisible()

  // A tooltip comes from the nearest title under the pointer.
  const tooltip = await tile(page, 'popover_two:dot.name').evaluate((element) => {
    const box = element.getBoundingClientRect()
    const target = document.elementFromPoint(box.left + box.width / 2, box.top + box.height / 2)
    return target?.closest('[title]')?.getAttribute('title') ?? null
  })
  expect(tooltip).toBe('Dot Name (Popover two)')
})

test('Rows stay on one line whether the grid scrolls or not', { tag: [ '@base' ] }, async ({ page, drupal, browserName, baseURL }) => {
  // Headless browsers draw scrollbars over the content. Only Chromium without
  // its --hide-scrollbars default shows the grid losing width to one.
  test.skip(browserName !== 'chromium', 'Only Chromium can draw such scrollbars headless.')
  const allowed = [ ...PACKS, 'popover_big' ].map((pack) => `'${pack}' => '${pack}'`).join(', ')
  await updateField(drupal, `setSetting('allowed_icon_pack', [${allowed}])`)
  const browser = await chromium.launch({ ignoreDefaultArgs: [ '--hide-scrollbars' ] })
  try {
    const context = await browser.newContext({ baseURL, storageState: await page.context().storageState() })
    const scrolling = await context.newPage()
    await openForm(scrolling)
    await trigger(scrolling).click()
    // A group too short to scroll, then one that scrolls: the columns of each
    // are counted while the other one is shown.
    for (const group of [ /^Weather/, /^Big/ ]) {
      await panel(scrolling).getByRole('tab', { name: group }).click()
      const lines = await panel(scrolling).evaluate((element) => [ ...element.querySelectorAll('[role="row"]') ].map((row) => {
        const tiles = [ ...row.querySelectorAll('.ui-icons-popover__tile') ] as HTMLElement[]
        return new Set(tiles.map((tile) => tile.offsetTop)).size
      }))
      expect(lines.filter((count) => count > 1), `${group}: rows wrapping onto a second line`).toEqual([])
    }
  }
  finally {
    await browser.close()
    const packs = PACKS.map((pack) => `'${pack}' => '${pack}'`).join(', ')
    await updateField(drupal, `setSetting('allowed_icon_pack', [${packs}])`)
  }
})
