<?php

namespace Drupal\occapi_entities_bridge;

/**
 * Maps OCCAPI attributes to entity fields.
 */
class OccapiFieldMap implements OccapiFieldMapInterface {

  /**
   * Field mapping for Programme entities.
   *
   * @var array
   */
  protected $programmeFieldMap;

  /**
   * Field mapping for Course entities.
   *
   * @var array
   */
  protected $courseFieldMap;

  /**
   * Specifies the field mapping for Programme entities.
   *
   * @return array
   *   An array in the format [drupal_field => apiAttribute].
   */
  public static function programmeFieldMap(): array {
    return [
      'title' => 'title',
      'code' => 'code',
      'description' => 'description',
      'ects' => 'ects',
      'eqf_level_provided' => 'eqfLevelProvided',
      'isced_code' => 'iscedCode',
      'language_of_instruction' => 'languageOfInstruction',
      'length' => 'length',
      'url' => 'url',
    ];
  }

  /**
   * Get the field mapping for Programme entities.
   *
   * @return array
   *   An array in the format [drupal_field => apiAttribute].
   */
  public function getProgrammeFieldMap(): array {
    if (!isset($this->programmeFieldMap)) {
      $this->programmeFieldMap = static::programmeFieldMap();
    }
    return $this->programmeFieldMap;
  }

  /**
   * Specifies the field mapping for Course entities.
   *
   * @return array
   *   An array in the format [drupal_field => apiAttribute].
   */
  public static function courseFieldMap(): array {
    return [
      'title' => 'title',
      'code' => 'code',
      'description' => 'description',
      'learning_outcomes' => 'learningOutcomes',
      'academic_term' => 'academicTerm',
      'ects' => 'ects',
      'language_of_instruction' => 'languageOfInstruction',
      'isced_code' => 'iscedCode',
      'subject_area' => 'subjectArea',
      'other_categorization' => 'otherCategorization',
      'url' => 'url',
    ];
  }

  /**
   * Get the field mapping for Course entities.
   *
   * @return array
   *   An array in the format [drupal_field => apiAttribute].
   */
  public function getCourseFieldMap(): array {
    if (!isset($this->courseFieldMap)) {
      $this->courseFieldMap = static::courseFieldMap();
    }
    return $this->courseFieldMap;
  }

  /**
   * Get the field mapping for an entity type.
   *
   * @param string $entity_type
   *   The entity type.
   *
   * @return array|null
   *   An array in the format [drupal_field => apiAttribute].
   */
  public function getFieldMap(string $entity_type): ?array {

    switch ($entity_type) {
      case OccapiEntityManagerInterface::ENTITY_PROGRAMME:
        return $this->getProgrammeFieldMap();

      case OccapiEntityManagerInterface::ENTITY_COURSE:
        return $this->getCourseFieldMap();

      default:
        return NULL;
    }
  }

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
  public function buildEntityData(string $entity_type, array $attributes): array {
    $entity_data = [];

    $field_map = $this->getFieldMap($entity_type);

    if (!empty($field_map) && !empty($attributes)) {
      foreach ($field_map as $field => $source) {
        $entity_data[$field] = $attributes[$source] ?? NULL;
      }
    }

    foreach ($entity_data as $field => $value) {
      if (empty($value)) { unset($entity_data[$field]); }
    }

    return $entity_data;
  }

}
