<?php

namespace Drupal\occapi_entities_bridge;

/**
 * Defines an interface for an OCCAPI entity manager.
 */
interface OccapiEntityManagerInterface {

  const ENTITY_HEI = 'hei';
  const ENTITY_OUNIT = 'ounit';
  const ENTITY_PROGRAMME = 'programme';
  const ENTITY_COURSE = 'course';

  const ENTITY_REF = [
    self::ENTITY_HEI => 'hei',
    self::ENTITY_OUNIT => 'ounit',
    self::ENTITY_PROGRAMME => 'related_programme',
  ];

  const UNIQUE_ID = [
    self::ENTITY_HEI => 'hei_id',
    self::ENTITY_OUNIT => 'ounit_id',
    self::ENTITY_PROGRAMME => 'remote_id',
    self::ENTITY_COURSE => 'remote_id',
  ];

  /**
   * Get an entity of a given type based on a unique ID.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $unique
   *   The unique ID to look for.
   *
   * @return array|null
   *   An array containing the entity indexed by Drupal ID, or NULL.
   */
  public function getEntityByUniqueId(string $entity_type, string $unique): ?array;

}
