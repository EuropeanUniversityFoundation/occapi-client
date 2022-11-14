<?php

namespace Drupal\occapi_entities_bridge\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\occapi_entities\Entity\Course;
use Drupal\occapi_entities_bridge\OccapiEntityManagerInterface;
use Drupal\occapi_entities_bridge\OccapiMetadataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * @var \Drupal\occapi_entities_bridge\OccapiMetadataInterface
   */
  protected $metaManager;

  /**
   * The constructor.
   *
   * @param \Drupal\occapi_entities_bridge\OccapiMetadataInterface $meta_manager
   *   The OCCAPI entity import manager service.
   */
  public function __construct(
    OccapiMetadataInterface $meta_manager
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
  public function relatedProgrammesTitle(Course $course) {
    return $this->t('Programmes related to @course', [
      '@course' => $course->label()
    ]);
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
      ->metaTable($metadata, OccapiEntityManagerInterface::ENTITY_PROGRAMME);

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $markup,
    ];

    return $build;
  }

}
