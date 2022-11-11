<?php

namespace Drupal\occapi_entities_bridge\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\occapi_entities\Entity\Course;
use Drupal\occapi_entities_bridge\OccapiImportManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for OCCAPI entities bridge routes.
 */
class OccapiCourseImportController extends ControllerBase {

  /**
   * The OCCAPI Course entity.
   *
   * @var \Drupal\occapi_entities\Entity\Course
   */
  protected $entity;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * OCCAPI entity import manager service.
   *
   * @var \Drupal\occapi_entities_bridge\OccapiImportManager
   */
  protected $importManager;

  /**
   * The constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\occapi_entities_bridge\OccapiImportManager $import_manager
   *   The OCCAPI entity import manager service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    OccapiImportManager $import_manager
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->importManager     = $import_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('occapi_entities_bridge.manager')
    );
  }

  /**
   * Provides an API form title callback.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return string
   *   The title for the entity API form.
   */
  public function apiFormTitle($entity_type_id) {
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    return $this->t('API data for @entity-type', [
      '@entity-type' => $entity_type->getSingularLabel()
    ]);
  }

  /**
   * Automatically imports multiple Courses.
   *
   * @param string $temp_store_key
   *   The TempStore key.
   * @param string $filter
   *   OCCAPI entity type key used as filter.
   *
   * @return RedirectResponse
   */
  public function importMultiple(string $temp_store_key): RedirectResponse {
    // Parse the tempstore key to get the OCCAPI filter entity type.
    $components  = \explode('.', $temp_store_key);
    $filter = $components[1];

    $courses = $this->importManager
      ->getCourses($temp_store_key, $filter);

    return $this->redirect('<front>');
  }

}
