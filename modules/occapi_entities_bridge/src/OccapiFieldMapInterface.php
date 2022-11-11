<?php

namespace Drupal\occapi_entities_bridge;

/**
 * Defines an interface for an OCCAPI field map.
 */
interface OccapiFieldMapInterface {

  /**
   * Specifies the field mapping for Programme entities.
   *
   * @return array
   *   An array in the format [drupal_field => apiAttribute].
   */
  public static function programmeFieldMap(): array;

  /**
   * Get the field mapping for Programme entities.
   *
   * @return array
   *   An array in the format [drupal_field => apiAttribute].
   */
  public function getProgrammeFieldMap(): array;

  /**
   * Specifies the field mapping for Course entities.
   *
   * @return array
   *   An array in the format [drupal_field => apiAttribute].
   */
  public static function courseFieldMap(): array;

  /**
   * Get the field mapping for Course entities.
   *
   * @return array
   *   An array in the format [drupal_field => apiAttribute].
   */
  public function getCourseFieldMap(): array;

  /**
   * Get the field mapping for an entity type.
   *
   * @param string $entity_type
   *   The entity type.
   *
   * @return array|null
   *   An array in the format [drupal_field => apiAttribute].
   */
  public function getFieldMap($entity_type): ?array;

  /**
   * Build entity data from data attributes.
   *
   * @param string $entity_type
   *   The entity type.
   * @param array $attributes
   *   The data attributes.
   *
   * @return array
   *   The entity data.
   */
  public function buildEntityData(string $entity_type, array $attributes): array;

}
