<?php

namespace Drupal\occapi_entities_bridge;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
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
use Drupal\occapi_client\OccapiTempStoreInterface;
use Drupal\occapi_entities_bridge\OccapiEntityManagerInterface;

/**
 * Manages OCCAPI import functionality.
 */
class OccapiBloatedImportManager implements OccapiImportManagerInterface {

  use StringTranslationTrait;

  const PARAM_PROVIDER = OccapiTempStoreInterface::PARAM_PROVIDER;
  const PARAM_FILTER_TYPE = OccapiTempStoreInterface::PARAM_FILTER_TYPE;
  const PARAM_FILTER_ID = OccapiTempStoreInterface::PARAM_FILTER_ID;
  // const PARAM_RESOURCE_TYPE = OccapiTempStoreInterface::PARAM_RESOURCE_TYPE;
  // const PARAM_RESOURCE_ID = OccapiTempStoreInterface::PARAM_RESOURCE_ID;

  // const TYPE_HEI = OccapiTempStoreInterface::TYPE_HEI;
  const TYPE_OUNIT = OccapiTempStoreInterface::TYPE_OUNIT;
  // const TYPE_PROGRAMME = OccapiTempStoreInterface::TYPE_PROGRAMME;
  // const TYPE_COURSE = OccapiTempStoreInterface::TYPE_COURSE;

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
   * The OCCAPI entity manager.
   *
   * @var \Drupal\occapi_entities_bridge\OccapiEntityManagerInterface
   */
  protected $occapiEntityManager;

  /**
   * The shared TempStore key manager.
   *
   * @var \Drupal\occapi_client\OccapiTempStoreInterface
   */
  protected $occapiTempStore;

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
   * @param \Drupal\occapi_entities_bridge\OccapiEntityManagerInterface $occapi_entity_manager
   *   The OCCAPI entity manager.
   * @param \Drupal\occapi_client\OccapiTempStoreInterface $occapi_tempstore
   *   The shared TempStore key manager.
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
    OccapiEntityManagerInterface $occapi_entity_manager,
    OccapiTempStoreInterface $occapi_tempstore,
    TranslationInterface $string_translation
  ) {
    $this->dataFormatter       = $data_formatter;
    $this->entityTypeManager   = $entity_type_manager;
    $this->heiManager          = $hei_manager;
    $this->jsonDataFetcher     = $json_data_fetcher;
    $this->jsonDataProcessor   = $json_data_processor;
    $this->logger              = $logger_factory->get('occapi_entities_bridge');
    $this->messenger           = $messenger;
    $this->providerManager     = $provider_manager;
    $this->occapiEntityManager = $occapi_entity_manager;
    $this->occapiTempStore     = $occapi_tempstore;
    $this->stringTranslation   = $string_translation;
  }

  /**
   * Check if the current user has permission to bypass the import form.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user accessing the import form.
   */
  public function checkBypassPermission(AccountProxyInterface $current_user): void {
    // Give a user with permission the opportunity to add an entity manually.
    $can_add_programme = $current_user->hasPermission('create programme');
    $can_add_course = $current_user->hasPermission('create course');
    $can_bypass = $current_user->hasPermission('bypass import occapi entities');

    if ($can_add_programme && $can_add_course && $can_bypass) {
      $add_programme_text = $this->t('add a new Programme');
      $add_programme_link = Link::fromTextAndUrl($add_programme_text,
        Url::fromRoute('entity.programme.add_form'))->toString();

      $add_course_text = $this->t('add a new Course');
      $add_course_link = Link::fromTextAndUrl($add_course_text,
        Url::fromRoute('entity.course.add_form'))->toString();

      $notice = $this->t('You can @act and @add_prog or @add_course manually.',[
        '@act' => $this->t('bypass this form'),
        '@add_prog' => $add_programme_link,
        '@add_course' => $add_course_link
      ]);

      $this->messenger->addMessage($notice);
    }
  }

  /**
   * Check whether all prerequisites for import are met.
   *
   * @param string $temp_store_key
   *   TempStore key from which all parameters are derived.
   *
   * @return array
   *   Associative array with a message indexed by severity.
   */
  public function checkImportPrerequisites(string $temp_store_key): array {
    // Get the parameters from the TempStore key.
    $temp_store_params = $this->occapiTempStore->paramsFromKey($temp_store_key);

    // Throw error if the OCCAPI provider does not exist or is not enabled.
    $provider_id = $temp_store_params[self::PARAM_PROVIDER];
    $provider = $this->providerManager->getProvider($provider_id);

    if (empty($provider)) {
      $error = $this->t('OCCAPI provider does not exist.');

      return [self::VALIDATION_ERROR => $error];
    }

    if (!$provider->status()) {
      $error = $this->t('OCCAPI provider is not enabled.');

      return [self::VALIDATION_ERROR => $error];
    }

    // Throw error if the related Institution is not present in the system.
    $hei_id = $provider->heiId();

    if (empty($this->occapiEntityManager->getHeiByHeiId($hei_id))) {
      $error = $this->t('Institution with ID %id does not exist.', [
        '%id' => $hei_id,
      ]);

      return [self::VALIDATION_ERROR => $error];
    }

    // Issue warning if a filter entity cannot be imported.
    $param_filter_type = $temp_store_params[self::PARAM_FILTER_TYPE];

    if ($param_filter_type === self::TYPE_OUNIT) {
      $ounit_id = $temp_store_params[self::PARAM_FILTER_ID];

      if (empty($this->occapiEntityManager->getOunitByOunitId($ounit_id))) {
        $warning = $this->t('Organizational Unit with ID %id does not exist.', [
          '%id' => $ounit_id,
        ]);

        return [self::VALIDATION_WARNING => $warning];
      }
    }

    return [];
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
        '@link' => \Drupal::service('renderer')->render($renderable),
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

      if (!empty($new)) {
        $exists = $this->entityTypeManager
          ->getStorage(self::PROGRAMME_ENTITY)
          ->loadByProperties([self::REMOTE_ID => $resource_id]);

        foreach ($exists as $id => $entity) {
          $renderable = $entity->toLink()->toRenderable();
        }
        $message = $this->t('Programme successfully created: @link', [
          '@link' => \Drupal::service('renderer')->render($renderable),
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
        '@link' => \Drupal::service('renderer')->render($renderable),
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
        !empty($collection) &&
        \array_key_exists(JsonDataProcessor::DATA_KEY, $collection) &&
        !empty($collection[JsonDataProcessor::DATA_KEY])
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

            if (!empty($new)) {
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
    if (!empty($created_items)) {
      $message = $this->t('Successfully created @count Courses.', [
        '@count' => \count($created_items),
      ]);
      $this->messenger->addMessage($message);
    }

    if (!empty($existing_items)) {
      $message = $this->t('Updated @up of @there existing Courses.', [
        '@up' => \count($updated_items),
        '@there' => \count($existing_items),
      ]);
      $this->messenger->addWarning($message);
    }

    if (!empty($failed_items)) {
      $message = $this->t('Failed to import @count Courses', [
        '@count' => \count($failed_items),
      ]);
      $this->messenger->addMessage($message);
    }

    $courses = $created_items + $existing_items;

    if (!empty($courses)) {
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

    if (!empty($programme_id)) {
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
