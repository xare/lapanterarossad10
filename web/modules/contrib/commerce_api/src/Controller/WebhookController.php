<?php declare(strict_types = 1);

namespace Drupal\commerce_api\Controller;

use Drupal\commerce_api\Events\OrderWebhookEvent;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class WebhookController implements ContainerInjectionInterface {

  /**
   * Constructs a new WebhookController object.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   */
  public function __construct(private EventDispatcherInterface $eventDispatcher) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('event_dispatcher')
    );
  }

  /**
   * Handles the order transition webhook.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The order.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   */
  public function handleTransition(OrderInterface $commerce_order, Request $request, RouteMatchInterface $route_match) {
    $transitions = $commerce_order->getState()->getTransitions();
    $transition = $route_match->getParameter('transition');

    if (!isset($transitions[$transition])) {
      $message = sprintf('Cannot apply the "%s" transition to the order %s.', $transition, $commerce_order->id());
      return new JsonResponse(['message' => $message], 400);
    }
    try {
      $event = new OrderWebhookEvent($commerce_order, $request, $route_match);
      $this->eventDispatcher->dispatch($event, "commerce_api.webhook_order_$transition");
      // For backwards compatibility reason, dispatch an additional event for the
      // "fulfill" transition.
      // @todo remove after full release.
      if ($transition === 'fulfill') {
        $this->eventDispatcher->dispatch($event, 'commerce_api.webhook_order_fulfillment');
      }
      $commerce_order->getState()->applyTransitionById($transition);
      $commerce_order->save();
      return new JsonResponse(['message' => 'OK']);
    }
    catch (\Exception $e) {
      watchdog_exception('commerce_api', $e);
      return new JsonResponse(['message' => 'Bad request'], 400);
    }
  }

}
