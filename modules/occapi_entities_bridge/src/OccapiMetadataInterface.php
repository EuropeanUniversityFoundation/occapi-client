<?php

namespace Drupal\occapi_entities_bridge;

/**
 * Defines an interface for handling OCCAPI metadata.
 */
interface OccapiMetadataInterface {

  // OCCAPI metadata keys.
  const SCOPE = 'scope';

  const SCOPE_GLOBAL = 'global';
  const META_GLOBAL_EQF = 'eqfLevel';

  const SCOPE_PROGRAMME = 'programme';
  const META_PROGRAMME_ID = 'programmeId';
  const META_PROGRAMME_MC = 'mandatoryCourse';

  const META_YEAR = 'year';

  // OCCAPI Programme fields.
  const FIELD_PROGRAMME_EQF = 'eqf_level_provided';

  // OCCAPI Course fields.
  const FIELD_COURSE_TERM = 'academic_term';

  /**
   * Gets all Courses that reference the Programme as related.
   *
   * @param \Drupal\occapi_entities\Entity\Programme $programme
   *   An OCCAPI Programme entity.
   *
   * @return \Drupal\occapi_entities\Entity\Course[] $courses
   *   An array of OCCAPI Course entities keyed by entity ID.
   */
  public function relatedCourses(Programme $programme): array;

  /**
   * Gets all Programmes referenced by the Course as related.
   *
   * @param \Drupal\occapi_entities\Entity\Course $course
   *   An OCCAPI Programme entity.
   *
   * @return \Drupal\occapi_entities\Entity\Programme[] $programmes
   *   An array of OCCAPI Course entities keyed by entity ID.
   */
  public function relatedProgrammes(Course $course): array;

  /**
   * Get the metadata for all Programmes related to a Course.
   *
   * @param \Drupal\occapi_entities\Entity\Course $course
   *   An OCCAPI Programme entity.
   * @param \Drupal\occapi_entities\Entity\Programme[] $programmes
   *   An array of OCCAPI Programme entities.
   *
   * @return $metadata
   *   An array of metadata keyed by programme ID.
   */
  public function getMetaByCourse(Course $course, array $programmes): array;

  /**
   * Get the metadata for all Courses related to a Programme.
   *
   * @param \Drupal\occapi_entities\Entity\Programme $programme
   *   An OCCAPI Programme entity.
   * @param \Drupal\occapi_entities\Entity\Course[] $courses
   *   An array of OCCAPI Course entities.
   *
   * @return $metadata
   *   An array of metadata keyed by course ID.
   */
  public function getMetaByProgramme(Programme $programme, array $courses): array;

  /**
   * Format metadata by entity type as HTML table.
   *
   * @param array $metadata
   *   An array containing a JSON:API resource collection.
   * @param string $entity_type_id
   *   The entity type ID to format the primary column.
   *
   * @return string
   *   Rendered table markup.
   */
  public function metaTable(array $metadata, string $entity_type_id): string;

}
