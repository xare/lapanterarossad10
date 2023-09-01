<?php

namespace Drupal\social_share_links\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Provides a 'SocialShareLinks' block.
 *
 * @Block(
 *  id = "social_share_links",
 *  admin_label = @Translation("Social Share Links"),
 * )
 */
class SocialShareLinksBlock extends BlockBase implements ContainerFactoryPluginInterface {

  protected $currentRouteMatch;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $current_route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentRouteMatch = $current_route_match;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // You can use the currentRouteMatch service to get the current URL or any other route parameters.
    $node = \Drupal::routeMatch()->getParameter('node');
    if ($node instanceof \Drupal\node\NodeInterface) {
        // You can get the nid and type too if you need.
        $nid = $node->id();
        $type = $node->getType();
    }
    $current_url = $node->toUrl('canonical', ['absolute' => TRUE])->toString();

    $facebook_url = 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode($current_url);
    $twitter_text = $node->getTitle()." - La Pantera rossa ";
    $twitter_url = 'https://twitter.com/intent/tweet?text=' . urlencode($twitter_text) . '&url=' . urlencode($current_url);
    $email_subject = "Look what I found!";
    $email_body = "I thought you might be interested in this: " . $current_url;
    $email_url = 'mailto:?subject=' . urlencode($email_subject) . '&body=' . urlencode($email_body);
    $meneame_url = 'https://www.meneame.net/submit.php?url=' . urlencode($current_url);


    // Here you'll generate the actual URLs for the sharing buttons, then add them to your render array.
    // As a starting point, you can create a list of links.
    // For now, let's just render a markup.
    return [
        '#theme' => 'item_list',
        '#title' => $this->t('Compartir'), // Add this line.
        '#items' => [
          'facebook' => $this->t('<a href="@url" class="facebook" target="_blank"></a>', ['@url' => $facebook_url]),
          'twitter' => $this->t('<a href="@url" class="twitter" target="_blank"></a>', ['@url' => $twitter_url]),
          'email' => $this->t('<a class="email" href="@url"></a>', ['@url' => $email_url]),
          'meneame' => $this->t('<a href="@url" class="meneame" target="_blank">Menéame</a>', ['@url' => $meneame_url]),
          // ... Add other networks ...
        ],
        '#attributes' => [
            'class' => ['rrss_sharelinks'],
        ],
    ];
  }
}
