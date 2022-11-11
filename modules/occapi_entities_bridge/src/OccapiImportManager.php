<?php

namespace Drupal\occapi_entities_bridge;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\ewp_institutions_get\InstitutionManager;
use Drupal\occapi_client\DataFormatter;
use Drupal\occapi_client\Entity\OccapiProvider;
use Drupal\occapi_client\JsonDataFetcher;
use Drupal\occapi_client\JsonDataProcessor;
use Drupal\occapi_client\OccapiFieldManager;
use Drupal\occapi_client\OccapiProviderManager;

/**
 * Service for managing OCCAPI entity imports.
 */
class OccapiImportManager {

  use StringTranslationTrait;

  // Machine names of OCCAPI entity types.
  const PROGRAMME_ENTITY  = 'programme';
  const COURSE_ENTITY     = 'course';

  // Machine name of the entity label.
  const LABEL_KEY         = 'label';

  // Machine names of entity reference fields.
  const REF_HEI           = 'hei';
  const REF_PROGRAMME     = 'related_programme';

  // Machine names of OCCAPI extra fields.
  const REMOTE_ID         = 'remote_id';
  const REMOTE_URL        = 'remote_url';
  const JSON_META         = 'meta';

  // TempStore key suffix for external resources.
  const EXT_SUFFIX       = 'external';

  /**
  * Data formatting service.
  *
  * @var \Drupal\occapi_client\DataFormatter
  */
  protected $dataFormatter;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
  * EWP Institutions manager service.
  *
  * @var \Drupal\ewp_institutions_get\InstitutionManager
  */
  protected $heiManager;

  /**
  * JSON data fetching service.
  *
  * @var \Drupal\occapi_client\JsonDataFetcher
  */
  protected $jsonDataFetcher;

  /**
  * JSON data processing service.
  *
  * @var \Drupal\occapi_client\JsonDataProcessor
  */
  protected $jsonDataProcessor;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * OCCAPI provider manager service.
   *
   * @var \Drupal\occapi_client\OccapiProviderManager
   */
  protected $providerManager;

  /**
   * The constructor.
   *
   * @param \Drupal\occapi_client\DataFormatter $data_formatter
   *   Data formatting service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\ewp_institutions_get\InstitutionManager $hei_manager
   *   EWP Institutions manager service.
   * @param \Drupal\occapi_client\JsonDataFetcher $json_data_fetcher
   *   JSON data fetching service.
   * @param \Drupal\occapi_client\JsonDataProcessor $json_data_processor
   *   JSON data processing service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\occapi_client\OccapiProviderManager $provider_manager
   *   The provider manager service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(
    DataFormatter $data_formatter,
    EntityTypeManagerInterface $entity_type_manager,
    InstitutionManager $hei_manager,
    JsonDataFetcher $json_data_fetcher,
    JsonDataProcessor $json_data_processor,
    LoggerChannelFactoryInterface $logger_factory,
    MessengerInterface $messenger,
    OccapiProviderManager $provider_manager,
    TranslationInterface $string_translation
  ) {
    $this->dataFormatter      = $data_formatter;
    $this->entityTypeManager  = $entity_type_manager;
    $this->heiManager         = $hei_manager;
    $this->jsonDataFetcher    = $json_data_fetcher;
    $this->jsonDataProcessor  = $json_data_processor;
    $this->logger             = $logger_factory->get('occapi_entities_bridge');
    $this->messenger          = $messenger;
    $this->providerManager    = $provider_manager;
    $this->stringTranslation  = $string_translation;
  }

  /**
   * Validate the existence of an Institution entity.
   *
   * @param string $hei_id
   *   Institution ID to validate.
   *
   * @return array $result
   *   An array containing the validation status and related message.
   */
  public function validateInstitution(string $hei_id): array {
    // Check if an entity with the same hei_id already exists
    $exists = $this->heiManager
      ->getInstitution($hei_id);

    if (!empty($exists)) {
      foreach ($exists as $id => $hei) {
        $link = $hei->toLink();
        $renderable = $link->toRenderable();
      }

      $message = $this->t('Institution with ID <code>@hei_id</code> already exists: @link', [
        '@hei_id' => $hei_id,
        '@link' => render($renderable),
      ]);

      $status = TRUE;
    }
    else {
      $message = $this->t('Institution with ID <code>@hei_id</code> does not exist!', [
        '@hei_id' => $hei_id,
      ]);

      $status = FALSE;
    }

    $result['status'] = $status;
    $result['message'] = $message;

    return $result;
  }

  /**
   * Get the ID of a Programme entity;
   *   optionally, create a new entity from a TempStore.
   *
   * @param string $temp_store_key
   *   TempStore key with programme data.
   *
   * @return array|NULL
   *   An array of [id => Drupal\occapi_entities\Entity\Programme].
   */
  public function getProgramme(string $temp_store_key): ?array {
    // Validate the tempstore parameter.
    $error = $this->providerManager
      ->validateResourceTempstore(
        $temp_store_key,
        OccapiProviderManager::PROGRAMME_KEY
      );

    if ($error) {
      $this->messenger->addError($error);
      return NULL;
    }

    // Parse the tempstore key to get the OCCAPI provider and the resource ID.
    $components  = \explode('.', $temp_store_key);
    $provider_id = $components[0];
    $resource_id = $components[2];

    // Check if an entity with the same remote ID already exists.
    $exists = $this->entityTypeManager
      ->getStorage(self::PROGRAMME_ENTITY)
      ->loadByProperties([self::REMOTE_ID => $resource_id]);

    // Create a new entity if none exists.
    if (empty($exists)) {
      $new = $this->createProgrammme($provider_id, $resource_id);

      if (! empty($new)) {
        $exists = $this->entityTypeManager
          ->getStorage(self::PROGRAMME_ENTITY)
          ->loadByProperties([self::REMOTE_ID => $resource_id]);

        foreach ($exists as $id => $entity) {
          $renderable = $entity->toLink()->toRenderable();
        }
        $message = $this->t('Programme successfully created: @link', [
          '@link' => render($renderable),
        ]);
        $this->messenger->addMessage($message);
      }
      else {
        $message = $this->t('Programme cannot be created');
        $this->messenger->addError($message);
      }
    }
    else {
      foreach ($exists as $id => $entity) {
        $renderable = $entity->toLink()->toRenderable();
      }
      $message = $this->t('Programme already exists: @link', [
        '@link' => render($renderable),
      ]);
      $this->messenger->addWarning($message);
    }

    return $exists;
  }

  /**
   * Create a new Programme entity.
   *
   * @param string $provider_id
   *   The OCCAPI provider ID.
   * @param string $programme_id
   *   Key found in the HEI list.
   *
   * @return array|NULL
   *   An array of [id => Drupal\occapi_entities\Entity\Programme].
   */
  private function createProgrammme(string $provider_id, string $programme_id): ?array {
    $provider = $this->providerManager
      ->getProvider($provider_id);

    $hei_id = $provider->get('hei_id');

    // Check if the Institution is present in the system.
    $result = $this->validateInstitution($hei_id);

    if (! $result['status']) {
      $this->messenger->addError($result['message']);
      return NULL;
    }
    else {
      $this->messenger->addMessage($result['message']);
    }

    // Load Programme data.
    $resource = $this->providerManager
      ->loadProgramme($provider_id, $programme_id);

    if (empty($resource)) {
      $this->messenger->addError($this->t('Missing programme data!'));
      return NULL;
    }

    $data = (\array_key_exists(JsonDataProcessor::DATA_KEY, $resource)) ?
      $resource[JsonDataProcessor::DATA_KEY] :
      $resource;

    if (! \array_key_exists(JsonDataProcessor::ATTR_KEY, $data)) {
      $this->messenger->addError($this->t('Missing programme attributes!'));
      return NULL;
    }

    $attributes = $data[JsonDataProcessor::ATTR_KEY];

    // Assemble data array for the new entity.
    $entity_data = [];

    // Start with the label.
    $entity_data[self::LABEL_KEY] = $this->jsonDataProcessor
      ->getTitle($resource);

    // Handle the attributes.
    $field_map = $this->programmeFieldMap();

    foreach ($field_map as $key => $value) {
      $entity_data[$value] = $attributes[$key];
    }

    // Handle the entity references.
    $hei = $this->heiManager
      ->getInstitution($hei_id);

    foreach ($hei as $id => $value) {
      $entity_data[self::REF_HEI] = ['target_id' => $id];
    }

    // Finally the remote API fields.
    $entity_data[self::REMOTE_ID] = $this->jsonDataProcessor
      ->getResourceId($resource);

    $entity_data[self::REMOTE_URL] = $this->jsonDataProcessor
      ->getResourceLinkByType($resource, JsonDataProcessor::SELF_KEY);

    // Create the new entity.
    $new_entity = $this->entityTypeManager
      ->getStorage(self::PROGRAMME_ENTITY)
      ->create($entity_data);
    $new_entity->save();

    $created = $this->entityTypeManager
      ->getStorage(self::PROGRAMME_ENTITY)
      ->loadByProperties([self::REMOTE_ID => $programme_id]);

    return $created;
  }

  /**
   * Get the ID of a Course entity;
   *   optionally, create a new entity from a TempStore.
   *
   * @param string $temp_store_key
   *   TempStore key with course data.
   * @param string $filter
   *   OCCAPI entity type key used as filter.
   *
   * @return array|NULL
   *   An array of [id => Drupal\occapi_entities\Entity\Course].
   */
  public function getCourses(string $temp_store_key, string $filter): ?array {
    // Validate the tempstore parameter.
    $error = $this->providerManager
      ->validateCollectionTempstore(
        $temp_store_key,
        $filter,
        OccapiProviderManager::COURSE_KEY,
      );

    if ($error) {
      $this->messenger->addError($error);
      return NULL;
    }

    // Parse the tempstore key to get the OCCAPI provider and the resource ID.
    $components  = \explode('.', $temp_store_key);
    $provider_id = $components[0];
    $resource_id = $components[2];

    $existing_items = [];
    $updated_items = [];
    $created_items = [];
    $failed_items = [];

    if ($filter === self::PROGRAMME_ENTITY) {
      $collection = $this->providerManager
        ->loadProgrammeCourses($provider_id, $resource_id);

      if (
        ! empty($collection) &&
        \array_key_exists(JsonDataProcessor::DATA_KEY, $collection) &&
        ! empty($collection[JsonDataProcessor::DATA_KEY])
      ) {
        foreach ($collection[JsonDataProcessor::DATA_KEY] as $i => $item) {
          $item_id = $this->jsonDataProcessor->getResourceId($item);

          // Check if an entity with the same remote ID already exists.
          $exists = $this->entityTypeManager
            ->getStorage(self::COURSE_ENTITY)
            ->loadByProperties([self::REMOTE_ID => $item_id]);

          // Check if a Programme with the same remote ID already exists.
          $programme = $this->entityTypeManager
            ->getStorage(self::PROGRAMME_ENTITY)
            ->loadByProperties([self::REMOTE_ID => $resource_id]);

          foreach ($programme as $id => $entity) {
            $programme_id = $id;
          }

          // Create a new entity if none exists.
          if (empty($exists)) {
            $new = $this->createCourse($provider_id, $item, $programme_id);

            if (! empty($new)) {
              $exists = $this->entityTypeManager
                ->getStorage(self::COURSE_ENTITY)
                ->loadByProperties([self::REMOTE_ID => $item_id]);

              foreach ($exists as $id => $entity) {
                $created_items[$id] = $entity;
              }
            }
            else {
              $failed_items[] = $item_id;
            }
          }
          else {
            // Update entity reference field.
            foreach ($exists as $id => $entity) {
              $referenced = $entity->get(self::REF_PROGRAMME)
                ->referencedEntities();

              $referenced_ids = [];

              foreach ($referenced as $index => $programme) {
                $referenced_ids[] = $programme->id();
              }

              if (! \in_array($programme_id, $referenced_ids)) {
                $entity->get(self::REF_PROGRAMME)
                  ->appendItem(['target_id' => $programme_id]);

                $entity->save();

                $updated_items[$id] = $entity;
              }

              $existing_items[$id] = $entity;
            }
          }
        }
      }
    }

    // Count the occurrences and issue messages accordingly.
    if (! empty($created_items)) {
      $message = $this->t('Successfully created @count Courses.', [
        '@count' => \count($created_items),
      ]);
      $this->messenger->addMessage($message);
    }

    if (! empty($existing_items)) {
      $message = $this->t('Updated @up of @there existing Courses.', [
        '@up' => \count($updated_items),
        '@there' => \count($existing_items),
      ]);
      $this->messenger->addWarning($message);
    }

    if (! empty($failed_items)) {
      $message = $this->t('Failed to import @count Courses', [
        '@count' => \count($failed_items),
      ]);
      $this->messenger->addMessage($message);
    }

    $courses = $created_items + $existing_items;

    if (! empty($courses)) {
      return $courses;
    }

    return NULL;
  }

  /**
   * Create a new Course entity.
   *
   * @param string $provider_id
   *   The OCCAPI provider ID.
   * @param array $resource
   *   The resource array for the Course entity.
   * @param string $programme_id|NULL
   *   The resource ID for a related Programme entity.
   *
   * @return array|NULL
   *   An array of [id => Drupal\occapi_entities\Entity\Course].
   */
  private function createCourse(string $provider_id, array $resource, string $programme_id = NULL): ?array {
    if (empty($resource)) {
      return NULL;
    }

    $provider = $this->providerManager
      ->getProvider($provider_id);

    $hei_id = $provider->get('hei_id');

    // Check if the Institution is present in the system.
    $result = $this->validateInstitution($hei_id);

    if (! $result['status']) {
      return NULL;
    }

    $data = (\array_key_exists(JsonDataProcessor::DATA_KEY, $resource)) ?
      $resource[JsonDataProcessor::DATA_KEY] :
      $resource;

    if (! \array_key_exists(JsonDataProcessor::ATTR_KEY, $data)) {
      return NULL;
    }

    $attributes = $data[JsonDataProcessor::ATTR_KEY];

    // Assemble data array for the new entity.
    $entity_data = [];

    // Start with the label.
    $entity_data[self::LABEL_KEY] = $this->jsonDataProcessor
      ->getTitle($resource);

    // Handle the attributes.
    $field_map = $this->courseFieldMap();

    foreach ($field_map as $key => $value) {
      $entity_data[$value] = $attributes[$key];
    }

    // Handle the entity references.
    $hei = $this->heiManager
      ->getInstitution($hei_id);

    foreach ($hei as $id => $value) {
      $entity_data[self::REF_HEI] = ['target_id' => $id];
    }

    if (! empty($programme_id)) {
      $entity_data[self::REF_PROGRAMME] = ['target_id' => $programme_id];
    }

    // Finally the remote API fields.
    $resource_id = $this->jsonDataProcessor
      ->getResourceId($resource);

    $entity_data[self::REMOTE_ID] = $resource_id;

    $entity_data[self::REMOTE_URL] = $this->jsonDataProcessor
      ->getResourceLinkByType($resource, JsonDataProcessor::SELF_KEY);

    if (\array_key_exists(JsonDataProcessor::META_KEY, $data)) {
      $json_metadata = \json_encode($data[JsonDataProcessor::META_KEY]);
      $entity_data[self::JSON_META] = $json_metadata;
    }

    // Create the new entity.
    $new_entity = $this->entityTypeManager
      ->getStorage(self::COURSE_ENTITY)
      ->create($entity_data);
    $new_entity->save();

    $created = $this->entityTypeManager
      ->getStorage(self::COURSE_ENTITY)
      ->loadByProperties([self::REMOTE_ID => $resource_id]);

    return $created;
  }

}
