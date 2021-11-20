<?php

namespace Drupal\occapi_entities_bridge\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\occapi_entities_bridge\OccapiImportManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for OCCAPI entities bridge routes.
 */
class OccapiCourseImportController extends ControllerBase {

  /**
   * OCCAPI entity import manager service.
   *
   * @var \Drupal\occapi_entities_bridge\OccapiImportManager
   */
  protected $importManager;

  /**
   * Constructs an OccapiCourseImportController object.
   *
   * @param \Drupal\occapi_entities_bridge\OccapiImportManager $import_manager
   *   The OCCAPI entity import manager service.
   */
  public function __construct(
    OccapiImportManager $import_manager
  ) {
    $this->importManager = $import_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('occapi_entities_bridge.manager'),
    );
  }

  /**
   * Builds the response.
   */
  public function build() {
    dpm($this);

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];

    return $build;
  }

  /**
   * Automatically imports multiple Courses.
   *
   * @param string $tempstore
   *   The TempStore key.
   * @param string $filter
   *   OCCAPI entity type key used as filter.
   *
   * @return RedirectResponse
   */
  public function importMultiple(string $tempstore): RedirectResponse {
    // Parse the tempstore key to get the OCCAPI filter entity type.
    $components  = \explode('.', $tempstore);
    $filter = $components[1];

    $courses = $this->importManager
      ->getCourses($tempstore, $filter);

    return $this->redirect('<front>');
  }

}
