<?php

namespace Drupal\occapi_entities_bridge;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Manages OCCAPI entities.
 */
class OccapiEntityManager {

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

}
