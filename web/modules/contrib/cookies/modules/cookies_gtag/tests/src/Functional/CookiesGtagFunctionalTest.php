<?php

namespace Drupal\Tests\cookies_gtag\Functional;

use Drupal\Core\Cache\Cache;
use Drupal\Tests\BrowserTestBase;
use Drupal\google_tag\Entity\Container;

/**
 * This class provides methods specifically for testing something.
 *
 * @group cookies_gtag
 */
class CookiesGtagFunctionalTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'node',
    'test_page_test',
    'cookies',
    'google_tag',
    'cookies_gtag',
  ];

  /**
   * A user with authenticated permissions.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $user;

  /**
   * A user with admin permissions.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->config('system.site')->set('page.front', '/test-page')->save();
    $this->user = $this->drupalCreateUser([]);
    $this->adminUser = $this->drupalCreateUser([]);
    $this->adminUser->addRole($this->createAdminRole('admin', 'admin'));
    $this->adminUser->save();
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests if the module installation, won't break the site.
   */
  public function testInstallation() {
    $session = $this->assertSession();
    $this->drupalGet('<front>');
    $session->statusCodeEquals(200);
  }

  /**
   * Tests if uninstalling the module, won't break the site.
   */
  public function testUninstallation() {
    // Go to uninstallation page an uninstall cookies_gtag:
    $session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $this->drupalGet('/admin/modules/uninstall');
    $session->statusCodeEquals(200);
    $page->checkField('edit-uninstall-cookies-gtag');
    $page->pressButton('edit-submit');
    $session->statusCodeEquals(200);
    // Confirm deinstall:
    $page->pressButton('edit-submit');
    $session->statusCodeEquals(200);
    $session->pageTextContains('The selected modules have been uninstalled.');
  }

  /**
   * Test to see if the noscript section is deleted when consent is required.
   */
  public function testNoscriptDeletedWithoutJavascript() {
    $session = $this->assertSession();
    // Place block:
    $this->drupalPlaceBlock('cookies_ui_block');
    // Create Google Tag Container:
    $container = new Container([], 'google_tag_container');
    $container->enforceIsNew();
    $container->set('id', 'test_container');
    $container->set('container_id', 'GTM-xxxxxx');
    $container->set('path_list', '');
    $container->save();

    // Service consent is required, the noscript section should be deleted:
    $this->drupalGet('<front>');
    $session->statusCodeEquals(200);
    $session->elementNotExists('css', 'body > noscript');

    // Uncheck gtag service entity "consent required":
    $cookies_gtag_service_entity = \Drupal::entityTypeManager()
      ->getStorage('cookies_service')
      ->load('gtag');
    $cookies_gtag_service_entity->set('consentRequired', FALSE);
    $cookies_gtag_service_entity->save();

    \Drupal::service('cache_tags.invalidator')->invalidateTags([
      'config:cookies.cookies_service',
    ]);

    // Service consent is not required anymore, the iframe should appear:
    $this->drupalGet('<front>');
    $session->statusCodeEquals(200);
    $session->elementExists('css', 'body > noscript > iframe[src *= "id=GTM-xxxxxx"]');
  }

}
