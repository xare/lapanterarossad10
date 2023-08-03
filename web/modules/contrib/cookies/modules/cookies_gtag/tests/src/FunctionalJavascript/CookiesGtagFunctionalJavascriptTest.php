<?php

namespace Drupal\Tests\cookies_gtag\FunctionalJavascript;

use Drupal\cookies\Constants\CookiesConstants;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\google_tag\Entity\Container;
use Drupal\Tests\cookies\Traits\CookiesCacheClearTrait;

/**
 * Tests cookies_gtag Javascript related functionalities.
 *
 * @group cookies_gtag
 */
class CookiesGtagFunctionalJavascriptTest extends WebDriverTestBase {
  use CookiesCacheClearTrait;

  /**
   * An admin user with all permissions.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $adminUser;

  /**
   * The user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $user;

  /**
   * A Google Tag manager container.
   *
   * @var \Drupal\google_tag\Entity\Container
   */
  protected $container;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'test_page_test',
    'filter_test',
    'block',
    'cookies',
    'google_tag',
    'cookies_gtag',
  ];

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->config('system.site')->set('page.front', '/test-page')->save();
    $this->user = $this->drupalCreateUser([]);
    $this->adminUser = $this->drupalCreateUser([]);
    $this->adminUser->addRole($this->createAdminRole('admin', 'admin'));
    $this->adminUser->save();
    $this->drupalLogin($this->adminUser);
    $this->drupalPlaceBlock('cookies_ui_block');
    // Create Google Tag Container:
    $this->container = new Container([], 'google_tag_container');
    $this->container->enforceIsNew();
    $this->container->set('id', 'test_container');
    $this->container->set('container_id', 'GTM-xxxxxx');
    $this->container->set('path_list', '');
    $this->container->save();
    $this->clearBackendCaches();
  }

  /**
   * Tests if the cookies ga javascript file is correctly knocked in / out.
   */
  public function testGtagJsCorrectlyKnocked() {
    $session = $this->assertSession();

    $this->drupalGet('<front>');
    // Consent not given, expects:
    // @codingStandardsIgnoreStart
    // <script src="/sites/default/files/google_tag/test/google_tag.script.js?XXXXXXXXXX" defer="" id="cookies_gtag" type="CookiesConstants::COOKIES_SCRIPT_KO_TYPE"></script>
    // @codingStandardsIgnoreEnd
    // Ensure the blocked script ID exists and is blocked:
    $session->elementAttributeContains('css', 'script#cookies_gtag', 'type', CookiesConstants::COOKIES_SCRIPT_KO_TYPE);
    // Ensure the original doesn't exist anymore:
    $session->elementNotExists('css', 'script[src="https://www.googletagmanager.com/gtm.js?id=GTM-xxxxxx"]');

    // Fire consent script, accept all cookies:
    $script = "var options = { all: true };
        document.dispatchEvent(new CustomEvent('cookiesjsrSetService', { detail: options }));";
    $this->getSession()->getDriver()->executeScript($script);

    $this->clearBackendCaches();

    $this->drupalGet('<front>');
    // Consent given, expects:
    // @codingStandardsIgnoreStart
    // <script src="https://www.googletagmanager.com/gtm.js?id=GTM-xxxxxx" async=""></script>
    // <script src="/sites/default/files/google_tag/test/google_tag.script.js?XXXXXXXXXX" defer=""></script>
    // @codingStandardsIgnoreEnd
    $session->elementExists('css', 'script[src="https://www.googletagmanager.com/gtm.js?id=GTM-xxxxxx"]');
    $session->elementExists('css', 'script[src*="google_tag.script.js"]');
    $session->elementAttributeNotExists('css', 'script[src*="google_tag.script.js"]', 'type');
  }

}
