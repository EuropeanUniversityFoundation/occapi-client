<?php

namespace Drupal\occapi_entities_bridge;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\occapi_entities\Entity\Course;
use Drupal\occapi_entities\Entity\Programme;
use Drupal\occapi_entities_bridge\OccapiImportManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Service for managing OCCAPI metadata.
 */
class OccapiMetaManager {

  use StringTranslationTrait;

  // OCCAPI metadata keys.
  const SCOPE_GLOBAL      = 'global';
  const META_GLOBAL_EQF   = 'eqfLevel';

  const SCOPE_PROGRAMME   = 'programme';
  const META_PROGRAMME_ID = 'programmeId';
  const META_PROGRAMME_MC = 'mandatoryCourse';

  const META_YEAR         = 'year';

  /**
   * The OCCAPI Course entity.
   *
   * @var \Drupal\occapi_entities\Entity\Course
   */
  protected $course;

  /**
   * The OCCAPI Programme entity.
   *
   * @var \Drupal\occapi_entities\Entity\Programme
   */
  protected $programme;

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
   * Constructs an OccapiMetaManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\occapi_entities_bridge\OccapiImportManager $import_manager
   *   The OCCAPI entity import manager service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    OccapiImportManager $import_manager,
    TranslationInterface $string_translation
  ) {
    $this->entityTypeManager  = $entity_type_manager;
    $this->importManager      = $import_manager;
    $this->stringTranslation  = $string_translation;
  }

  /**
   * Gets all Courses that reference the Programme as related.
   *
   * @param Programme $programme
   *   An OCCAPI Programme entity.
   *
   * @return Course[] $courses
   *   An array of OCCAPI Course entities keyed by entity ID.
   */
  public function relatedCourses(Programme $programme): array {
    $this->programme = $programme;

    $entity_id = $this->programme->id();
    $remote_id = $this->programme->get(OccapiImportManager::REMOTE_ID)->value;

    $courses = $this->entityTypeManager
      ->getStorage(OccapiImportManager::COURSE_ENTITY)
      ->loadByProperties([OccapiImportManager::REF_PROGRAMME => $entity_id]);

    return $courses;
  }

  /**
   * Gets all Programmes referenced by the Course as related.
   *
   * @param Course $course
   *   An OCCAPI Programme entity.
   *
   * @return Programme[] $programmes
   *   An array of OCCAPI Course entities keyed by entity ID.
   */
  public function relatedProgrammes(Course $course): array {
    $this->course = $course;

    $programmes = [];

    $referenced = $this->course
      ->get(OccapiImportManager::REF_PROGRAMME)
      ->referencedEntities();

    foreach ($referenced as $i => $programme) {
      $programmes[$programme->id()] = $programme;
    }

    return $programmes;
  }

}
