<?php

namespace Drupal\Tests\sharethis\Functional;

use Drupal\Tests\node\Functional\NodeTestBase;

/**
 * Tests if the sharethis block is available.
 *
 * @group sharethis
 */
class SharethisBlockTest extends NodeTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'node', 'system_test', 'block', 'user', 'sharethis', 'menu_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Create and login user.
    $admin_user = $this->drupalCreateUser([
      'administer blocks', 'administer site configuration',
      'access administration pages', 'administer sharethis', 'administer nodes',
    ]);
    $this->drupalLogin($admin_user);
  }

  /**
   * Test that the sharethis form block can be placed and works.
   */
  public function testSharethisBlock() {
    $edit['location'] = 'block';
    $this->drupalGet('admin/config/services/sharethis');
    $this->submitForm($edit, $this->t('Save configuration'));
    $this->assertSession()->pageTextContains($this->t('The configuration options have been saved.'));
    // Test availability of sharethis block in the admin 'Place blocks' list.
    \Drupal::service('theme_installer')->install(['bartik', 'seven', 'stark']);
    $theme_settings = $this->config('system.theme');
    foreach (['bartik', 'seven', 'stark'] as $theme) {
      $this->drupalGet('admin/structure/block/list/' . $theme);
      // Select the 'Sharethis' block to be placed.
      $block = [];
      $block['id'] = strtolower($this->randomMachineName());
      $block['theme'] = $theme;
      $block['region'] = 'content';
      $this->drupalGet('admin/structure/block/add/sharethis_block');
      $this->submitForm($block, $this->t('Save block'));
      $this->assertSession()->pageTextContains($this->t('The block configuration has been saved.'));
      // Set the default theme and ensure the block is placed.
      $theme_settings->set('default', $theme)->save();
      $this->drupalGet('');
      $result = $this->xpath('//div[@class=:class]', [':class' => 'sharethis-wrapper']);
      $this->assertEquals(count($result), 1, 'Sharethis links found');
    }
  }

}
