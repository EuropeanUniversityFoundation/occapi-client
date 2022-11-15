<?php

namespace Drupal\occapi_entities_bridge;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\occapi_client\JsonDataProcessorInterface;
use Drupal\occapi_client\JsonDataSchemaInterface;
use Drupal\occapi_client\OccapiTempStoreInterface;

/**
 * Manages OCCAPI entities.
 */
class OccapiEntityManager implements OccapiEntityManagerInterface {

  const TYPE_HEI = OccapiTempStoreInterface::TYPE_HEI;
  const TYPE_OUNIT = OccapiTempStoreInterface::TYPE_OUNIT;
  const TYPE_PROGRAMME = OccapiTempStoreInterface::TYPE_PROGRAMME;
  const TYPE_COURSE = OccapiTempStoreInterface::TYPE_COURSE;

  const TYPE_ENTITY = [
    self::TYPE_HEI => self::ENTITY_HEI,
    self::TYPE_OUNIT => self::ENTITY_OUNIT,
    self::TYPE_PROGRAMME => self::ENTITY_PROGRAMME,
    self::TYPE_COURSE => self::ENTITY_COURSE,
  ];

  const FIELD_REMOTE_ID = OccapiRemoteDataInterface::FIELD_REMOTE_ID;
  const FIELD_REMOTE_URL = OccapiRemoteDataInterface::FIELD_REMOTE_URL;
  const FIELD_META = OccapiRemoteDataInterface::FIELD_META;

  const JSONAPI_REL = JsonDataSchemaInterface::JSONAPI_REL;
  const JSONAPI_ATTR = JsonDataSchemaInterface::JSONAPI_ATTR;
  const JSONAPI_META = JsonDataSchemaInterface::JSONAPI_META;
  const JSONAPI_SELF = JsonDataSchemaInterface::JSONAPI_SELF;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The OCCAPI field map service.
   *
   * @var \Drupal\occapi_entities_bridge\OccapiFieldMapInterface
   */
  protected $occapiFieldMap;

  /**
   * The JSON data processor.
   *
   * @var \Drupal\occapi_client\JsonDataProcessorInterface
   */
  protected $jsonDataProcessor;

  /**
   * Constructs an OccapiEntityManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\occapi_entities_bridge\OccapiFieldMapInterface $occapi_field_map
   *   The JSON data processor.
   * @param \Drupal\occapi_client\JsonDataProcessorInterface $json_data_processor
   *   The JSON data processor.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    OccapiFieldMapInterface $occapi_field_map,
    JsonDataProcessorInterface $json_data_processor
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->occapiFieldMap    = $occapi_field_map;
    $this->jsonDataProcessor = $json_data_processor;
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

  /**
   * Look for an existing entity with the same unique ID.
   *
   * @param array $resource
   *   The data item to prepare.
   *
   * @return array
   *   The prepared entity data.
   */
  public function checkExistingEntity(array $resource): ?array {
    $resource_id = $this->jsonDataProcessor->getResourceId($resource);
    $resource_type = $this->jsonDataProcessor->getResourceType($resource);

    $entity_type = self::TYPE_ENTITY[$resource_type];

    return $this->getEntityByUniqueId($entity_type, $resource_id);
  }

  /**
   * Prepare data to build an entity.
   *
   * @param array $resource
   *   The data item to prepare.
   * @param string $hei_id
   *   The Institution ID for the primary entity reference.
   *
   * @return array
   *   The prepared entity data.
   */
  public function prepareEntityData(array $resource, string $hei_id): array {
    $resource_data = $this->jsonDataProcessor->getResourceData($resource);
    $resource_type = $this->jsonDataProcessor->getResourceType($resource);
    $resource_id = $this->jsonDataProcessor->getResourceId($resource);

    $entity_type = self::TYPE_ENTITY[$resource_type];

    $attribute_data = $this->occapiFieldMap
      ->buildEntityData($entity_type, $resource_data[self::JSONAPI_ATTR] ?? []);

    $label = $this->jsonDataProcessor->getResourceTitle($resource);

    $field_data = \array_merge([
      'label' => $label,
      'unique_id' => $resource_id,
      'entity_type' => $entity_type,
    ], $attribute_data);

    $relationships = $resource_data[self::JSONAPI_REL] ?? [];

    $references = $this->buildEntityReferences($hei_id, $relationships);

    $extra_fields = $this->buildExtraFields($resource);

    $entity_data = \array_merge($field_data, $references, $extra_fields);

    return $entity_data;
  }

  /**
   * Build entity references from resource relationships.
   *
   * @param string $hei_id
   *   The Institution ID for the primary entity reference.
   * @param array $relationships
   *   The resource relationships.
   *
   * @return array
   *   The entity references.
   */
  public function buildEntityReferences(string $hei_id, array $relationships): array {
    $references[self::ENTITY_REF[self::ENTITY_HEI]][] = [
      'target_id' => \array_keys($this->getHeiByHeiId($hei_id))[0],
    ];

    foreach ($relationships as $key => $relationship) {
      $rel_data = $this->jsonDataProcessor->getResourceData($relationship);

      if (\array_key_exists('id', $rel_data)) { $rel_data = [$rel_data]; }

      foreach ($rel_data as $rel_item) {
        $rel_type = $this->jsonDataProcessor->getResourceType($rel_item);
        $rel_id = $this->jsonDataProcessor->getResourceId($rel_item);

        $entity_type = self::TYPE_ENTITY[$rel_type];

        $entities = $this->getEntityByUniqueId($entity_type, $rel_id) ?? [];

        foreach ($entities as $id => $entity) {
          $references[self::ENTITY_REF[$entity_type]][] = [
            'target_id' => $id
          ];
        }
      }
    }

    return $references;
  }

  /**
   * Build extra fields for API data and metadata.
   *
   * @param array $resource
   *   The resource.
   *
   * @return array
   *   The entity references.
   */
  public function buildExtraFields(array $resource): array {
    $resource_id = $this->jsonDataProcessor->getResourceId($resource);
    $resource_data = $this->jsonDataProcessor->getResourceData($resource);

    $extra_fields[self::FIELD_REMOTE_ID] = $resource_id;

    $extra_fields[self::FIELD_REMOTE_URL] = $this->jsonDataProcessor
      ->getResourceLinkByType($resource, self::JSONAPI_SELF);

    if (\array_key_exists(self::JSONAPI_META, $resource_data)) {
      $json_metadata = \json_encode($resource_data[self::JSONAPI_META]);
      $extra_fields[self::FIELD_META] = $json_metadata;
    }

    return $extra_fields;
  }

  /**
   * Creates an entity of a given type from generated data.
   *
   * @param string $entity_type
   *   The entity type to create..
   * @param array $entity_data
   *   The generated data.
   *
   * @return array|null
   *   The created entity, indexed by Drupal ID.
   */
  public function createEntity(string $entity_type, array $entity_data): ?array {
    $unique_id = $entity_data['unique_id'];
    unset($entity_data['unique_id']);

    $entity_type = $entity_data['entity_type'];
    unset($entity_data['entity_type']);

    $new_entity = $this->entityTypeManager
      ->getStorage($entity_type)
      ->create($entity_data);
    $new_entity->save();

    $created = $this->entityTypeManager
      ->getStorage($entity_type)
      ->loadByProperties([self::UNIQUE_ID[$entity_type] => $unique_id]);

    return $created;
  }

  /**
   * Updates an entity with generated data.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The generated data.
   * @param array $entity_data
   *   The generated data.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The updated entity.
   */
  public function updateEntity(EntityInterface $entity, array $entity_data): EntityInterface {
    // Update entity references.
    foreach (self::TYPE_ENTITY as $entity_type) {
      $reference_field = self::ENTITY_REF[$entity_type] ?? NULL;

      if (\array_key_exists($reference_field, $entity->getFieldDefinitions())) {
        $referenced = $entity->get($reference_field)->referencedEntities();

        $referenced_ids = [];

        foreach ($referenced as $i => $target) {
          $referenced_ids[] = $target->id();
        }

        $new_references = $entity_data[self::JSONAPI_REL][$reference_field] ?? [];

        foreach ($new_references as $field_value) {
          if (!\in_array($field_value['target_id'], $referenced_ids)) {
            $new_item = ['target_id' => $field_value['target_id']];

            $entity->get($reference_field)->appendItem($new_item);
            $entity->save();
          }
        }
      }
    }

    return $entity;
  }

}
