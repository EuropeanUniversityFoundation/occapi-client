<?php

namespace Drupal\occapi_entities_bridge\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\occapi_entities_bridge\OccapiImportManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for OCCAPI entities bridge routes.
 */
class OccapiProgrammeImportController extends ControllerBase {

  /**
   * OCCAPI entity import manager service.
   *
   * @var \Drupal\occapi_entities_bridge\OccapiImportManager
   */
  protected $importManager;

  /**
   * Constructs an OccapiProgrammeImportController object.
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
      $container->get('occapi_entities_bridge.manager')
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
   * Automatically imports a Programme.
   *
   * @param string $tempstore
   *   The TempStore key.
   *
   * @return RedirectResponse
   */
  public function import(string $tempstore): RedirectResponse {
    $programme = $this->importManager
      ->getProgramme($tempstore);

    if (!empty($programme)) {
      foreach ($programme as $id => $value) {
        $params = [OccapiImportManager::PROGRAMME_ENTITY => $id];
      }
      $route = 'entity.' . OccapiImportManager::PROGRAMME_ENTITY . '.canonical';
      return $this->redirect($route, $params);
    }

    return $this->redirect('<front>');
  }

  /**
   * Automatically imports a Programme and its Courses.
   *
   * @param string $tempstore
   *   The TempStore key.
   *
   * @return RedirectResponse
   */
  public function importCourses(string $tempstore): RedirectResponse {
    $programme = $this->importManager
      ->getProgramme($tempstore);

    if (!empty($programme)) {
      $params = ['tempstore' => $tempstore];
      $route = 'occapi_entities_bridge.import_course_multiple';
      return $this->redirect($route, $params);
    }

    return $this->redirect('<front>');
  }

}
