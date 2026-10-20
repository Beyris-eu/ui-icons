<?php

declare(strict_types=1);

namespace Drupal\Tests\ui_icons_field\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\link\LinkItemInterface;
use Drupal\ui_icons_field\Plugin\Field\SampleValueGenerator\IconLink;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the IconLink sample value generator.
 *
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(IconLink::class)]
#[Group('ui_icons')]
class IconLinkSampleValueKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'field_sample_value',
    'link',
    'system',
    'user',
    'entity_test',
    'ui_icons',
    'ui_icons_field',
    'ui_icons_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test');

    FieldStorageConfig::create([
      'field_name' => 'field_link',
      'entity_type' => 'entity_test',
      'type' => 'link',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_link',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'settings' => ['link_type' => LinkItemInterface::LINK_EXTERNAL],
      'third_party_settings' => [
        'field_sample_value' => [
          'id' => 'icon_link',
          'configuration' => ['prevent_save' => FALSE],
        ],
      ],
    ])->save();
  }

  /**
   * Tests the sample link carries an icon from a pack the widget allows.
   */
  public function testIconFromAllowedPack(): void {
    $item = $this->generateSampleLink(['test_svg' => 'test_svg']);

    $this->assertNotEmpty($item->uri);
    $this->assertStringStartsWith('test_svg:', $item->options['icon']['target_id']);
  }

  /**
   * Tests the sample link stays without icon when no allowed icon is left.
   */
  public function testNoIconLeft(): void {
    $item = $this->generateSampleLink(['removed_pack' => 'removed_pack']);

    $this->assertNotEmpty($item->uri);
    $this->assertArrayNotHasKey('icon', $item->options);
  }

  /**
   * Generate a sample entity and return its link item.
   *
   * @param array $allowed_icon_pack
   *   The icon link widget allowed icon packs.
   *
   * @return \Drupal\link\LinkItemInterface
   *   The sample link item.
   */
  private function generateSampleLink(array $allowed_icon_pack): LinkItemInterface {
    $this->container->get('entity_display.repository')
      ->getFormDisplay('entity_test', 'entity_test')
      ->setComponent('field_link', [
        'type' => 'icon_link_widget',
        'settings' => ['allowed_icon_pack' => $allowed_icon_pack],
      ])
      ->save();

    $entity = $this->container->get('field_sample_value.generator')
      ->createWithSampleValues('entity_test', 'entity_test');

    return $entity->get('field_link')->first();
  }

}
