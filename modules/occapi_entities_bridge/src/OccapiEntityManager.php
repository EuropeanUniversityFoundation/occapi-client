<?php

namespace Drupal\occapi_entities_bridge;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Manages OCCAPI entities.
 */
class OccapiEntityManager implements OccapiEntityManagerInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs an OccapiEntityManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

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
  public function getEntityByUniqueId(string $entity_type, string $unique): ?array {
    if (\array_key_exists($entity_type, self::UNIQUE_ID)) {
      $exists = $this->entityTypeManager
        ->getStorage($entity_type)
        ->loadByProperties([self::UNIQUE_ID[$entity_type] => $unique]);

      if (!empty($exists)) { return $exists; }
    }

    return NULL;
  }

  /**
   * Get an Institution entity based on its unique ID.
   *
   * @param string $hei_id
   *   The unique ID to look for.
   *
   * @return array|null
   *   An array containing the entity indexed by Drupal ID, or NULL.
   */
  public function getHeiByHeiId(string $hei_id): ?array {
    return $this->getEntityByUniqueId(self::ENTITY_HEI, $hei_id);
  }

  /**
   * Get an Organizational Unit entity based on its unique ID.
   *
   * @param string $ounit_id
   *   The unique ID to look for.
   *
   * @return array|null
   *   An array containing the entity indexed by Drupal ID, or NULL.
   */
  public function getOunitByOunitId(string $ounit_id): ?array {
    return $this->getEntityByUniqueId(self::ENTITY_OUNIT, $ounit_id);
  }

  /**
   * Get a Programme entity based on its unique ID.
   *
   * @param string $remote_id
   *   The unique ID to look for.
   *
   * @return array|null
   *   An array containing the entity indexed by Drupal ID, or NULL.
   */
  public function getProgrammeByRemoteId(string $remote_id): ?array {
    return $this->getEntityByUniqueId(self::ENTITY_PROGRAMME, $remote_id);
  }

  /**
   * Get a Course entity based on its unique ID.
   *
   * @param string $remote_id
   *   The unique ID to look for.
   *
   * @return array|null
   *   An array containing the entity indexed by Drupal ID, or NULL.
   */
  public function getCourseByRemoteId(string $remote_id): ?array {
    return $this->getEntityByUniqueId(self::ENTITY_COURSE, $remote_id);
  }

}
