<?php

namespace Drupal\occapi_entities_bridge\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Set new permission for the Programme and Course add forms.
    if (\in_array($route, [
       $collection->get('entity.programme.add_form'),
       $collection->get('entity.course.add_form'),
      ])) {
      $route->setRequirements([
        '_permission' => 'bypass import occapi entities',
      ]);
    }
  }

}
