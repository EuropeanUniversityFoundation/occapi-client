<?php

namespace Drupal\occapi_entities_bridge\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\occapi_entities\Entity\Course;
use Drupal\occapi_entities_bridge\OccapiImportManager;
use Drupal\occapi_entities_bridge\OccapiMetaManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for OCCAPI entities bridge routes.
 */
class OccapiCourseMetaController extends ControllerBase {

  /**
   * The OCCAPI Course entity.
   *
   * @var \Drupal\occapi_entities\Entity\Course
   */
  protected $entity;

  /**
   * OCCAPI metadata manager service.
   *
   * @var \Drupal\occapi_entities_bridge\OccapiMetaManager
   */
  protected $metaManager;

  /**
   * Constructs an OccapiCourseImportController object.
   *
   * @param \Drupal\occapi_entities_bridge\OccapiMetaManager $meta_manager
   *   The OCCAPI entity import manager service.
   */
  public function __construct(
    OccapiMetaManager $meta_manager
  ) {
    $this->metaManager = $meta_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('occapi_entities_bridge.meta')
    );
  }

  /**
   * Provides a title callback for related Programmes.
   *
   * @return string
   *   The title for the entity controller.
   */
  public function relatedProgrammesTitle() {
    return $this->t('Related programmes');
  }

  /**
   * Builds the response for related Programmes.
   */
  public function relatedProgrammes(Course $course) {
    $this->entity = $course;

    $programmes = $this->metaManager
      ->relatedProgrammes($this->entity);

    $metadata = $this->metaManager
      ->getMetaByCourse($this->entity, $programmes);

    $markup = $this->metaManager
      ->metaTable($metadata, OccapiImportManager::PROGRAMME_ENTITY);

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $markup,
    ];

    return $build;
  }

}
