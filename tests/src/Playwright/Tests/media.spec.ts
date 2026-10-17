import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import { Drupal } from '../objects/Drupal'
import config from '../playwright.config.loader'

// Media library flow of the Icon media source. The icons come from
// tests/modules/ui_icons_test/ui_icons_test.icons.yml.
// @see \Drupal\ui_icons_media\Form\IconMediaAddForm

const BUNDLE_ID = 'page'
const FIELD_NAME = 'field_media'

/**
 * Create the Icon media type and a node field using the media library.
 *
 * @param {Drupal} drupal - The Drupal object.
 * @returns {Promise<void>}
 */
async function createMediaField (drupal: Drupal): Promise<void> {
  // drush runs through a shell: PHP variables are escaped as \\$.
  const php = [
    `if (!\\Drupal\\media\\Entity\\MediaType::load('icon')) {`,
    `\\$type = \\Drupal\\media\\Entity\\MediaType::create(['id' => 'icon', 'label' => 'Icon', 'source' => 'ui_icon']);`,
    `\\$type->save();`,
    `\\$field = \\$type->getSource()->createSourceField(\\$type);`,
    `\\$field->getFieldStorageDefinition()->save();`,
    `\\$field->save();`,
    `\\$type->set('source_configuration', ['source_field' => \\$field->getName()])->save();`,
    `}`,
    `if (!\\Drupal\\node\\Entity\\NodeType::load('${BUNDLE_ID}')) {`,
    `\\Drupal\\node\\Entity\\NodeType::create(['type' => '${BUNDLE_ID}', 'name' => 'Page'])->save();`,
    `}`,
    `if (!\\Drupal\\field\\Entity\\FieldConfig::loadByName('node', '${BUNDLE_ID}', '${FIELD_NAME}')) {`,
    `\\Drupal\\field\\Entity\\FieldStorageConfig::create(['field_name' => '${FIELD_NAME}', 'entity_type' => 'node', 'type' => 'entity_reference', 'settings' => ['target_type' => 'media']])->save();`,
    `\\Drupal\\field\\Entity\\FieldConfig::create(['field_name' => '${FIELD_NAME}', 'entity_type' => 'node', 'bundle' => '${BUNDLE_ID}', 'label' => 'Media', 'settings' => ['handler' => 'default:media', 'handler_settings' => ['target_bundles' => ['icon' => 'icon']]]])->save();`,
    `\\Drupal::service('entity_display.repository')->getFormDisplay('node', '${BUNDLE_ID}')->setComponent('${FIELD_NAME}', ['type' => 'media_library_widget'])->save();`,
    `}`,
  ].join(' ')
  await drupal.drush(`php:eval "${php}"`)
}

test.beforeEach('Setup', async ({ drupal }) => {
  await drupal.installModules([ 'node', 'field', 'media', 'media_library', 'ui_icons_media', 'ui_icons_test' ])
  await createMediaField(drupal)
  await drupal.loginAsAdmin()
})

for (const iconId of [ 'test_path:foo', 'test_svg:foo' ]) {
  test(`Add an icon media from the media library: ${iconId}`, { tag: [ '@base' ] }, async ({ page }) => {
    const errors: string[] = []
    page.on('pageerror', (error) => errors.push(error.message))
    const modal = page.locator('#drupal-modal')

    await page.goto(config.contentTypeAdd.replace('{bundle}', BUNDLE_ID))
    await page.getByRole('button', { name: 'Add media' }).click()
    await expect(modal.locator('[name="icon[icon_id]"]')).toBeVisible()

    await modal.locator('[name="icon[icon_id]"]').fill(iconId)
    const results = page.locator('ul.ui-autocomplete li')
    await expect(results).toHaveCount(1)
    await results.first().click()
    await expect(modal.locator('.ui-icons-preview-icon').locator('img, svg')).toBeVisible()

    await modal.getByRole('button', { name: 'Add', exact: true }).click()
    await page.locator('.ui-dialog-buttonpane').getByRole('button', { name: 'Save', exact: true }).click()
    await page.locator('.ui-dialog-buttonpane').getByRole('button', { name: 'Insert selected' }).click()
    await expect(modal).toBeHidden()
    await expect(page.locator(`[data-drupal-selector="edit-${FIELD_NAME.replace('_', '-')}-selection-0"]`)).toBeVisible()

    expect(errors).toEqual([])
  })
}
