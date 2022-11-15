<?php

namespace Drupal\occapi_entities_bridge;

use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\occapi_client\OccapiDataLoaderInterface;
use Drupal\occapi_client\JsonDataProcessorInterface;
use Drupal\occapi_client\OccapiProviderManagerInterface;
use Drupal\occapi_client\OccapiTempStoreInterface;
use Drupal\occapi_entities_bridge\OccapiEntityManagerInterface;

/**
 * Manages OCCAPI import functionality.
 */
class OccapiImportManager implements OccapiImportManagerInterface {

  use StringTranslationTrait;

  const PARAM_PROVIDER = OccapiTempStoreInterface::PARAM_PROVIDER;
  const PARAM_FILTER_TYPE = OccapiTempStoreInterface::PARAM_FILTER_TYPE;
  const PARAM_FILTER_ID = OccapiTempStoreInterface::PARAM_FILTER_ID;
  const PARAM_RESOURCE_TYPE = OccapiTempStoreInterface::PARAM_RESOURCE_TYPE;
  const PARAM_RESOURCE_ID = OccapiTempStoreInterface::PARAM_RESOURCE_ID;
  const PARAM_EXTERNAL = OccapiTempStoreInterface::PARAM_EXTERNAL;

  const TYPE_OUNIT = OccapiTempStoreInterface::TYPE_OUNIT;

  /**
   * The OCCAPI provider.
   *
   * @var \Drupal\occapi_client\OccapiProvider
   */
  protected $provider;

  /**
   * The OCCAPI data loader.
   *
   * @var \Drupal\occapi_client\OccapiDataLoaderInterface
   */
  protected $dataLoader;

  /**
   * The JSON data processor.
   *
   * @var \Drupal\occapi_client\JsonDataProcessorInterface
   */
  protected $jsonDataProcessor;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * OCCAPI provider manager service.
   *
   * @var \Drupal\occapi_client\OccapiProviderManagerInterface
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
   * @param \Drupal\occapi_client\OccapiDataLoaderInterface $data_loader
   *   The OCCAPI data loader.
   * @param \Drupal\occapi_client\JsonDataProcessorInterface $json_data_processor
   *   The JSON data processor.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\occapi_client\OccapiProviderManagerInterface $provider_manager
   *   The provider manager service.
   * @param \Drupal\occapi_entities_bridge\OccapiEntityManagerInterface $occapi_entity_manager
   *   The OCCAPI entity manager.
   * @param \Drupal\occapi_client\OccapiTempStoreInterface $occapi_tempstore
   *   The shared TempStore key manager.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(
    OccapiDataLoaderInterface $data_loader,
    JsonDataProcessorInterface $json_data_processor,
    MessengerInterface $messenger,
    OccapiProviderManagerInterface $provider_manager,
    OccapiEntityManagerInterface $occapi_entity_manager,
    OccapiTempStoreInterface $occapi_tempstore,
    TranslationInterface $string_translation
  ) {
    $this->dataLoader          = $data_loader;
    $this->jsonDataProcessor   = $json_data_processor;
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
   * Validate whether all prerequisites for import are met.
   *
   * @param string $temp_store_key
   *   TempStore key from which all parameters are derived.
   *
   * @return bool
   *   Returns TRUE is validation passes, otherwise FALSE.
   */
  public function validateImportPrerequisites(string $temp_store_key): bool {
    // Get the parameters from the TempStore key.
    $temp_store_params = $this->occapiTempStore->paramsFromKey($temp_store_key);

    // Throw error if the OCCAPI provider does not exist or is not enabled.
    $provider_id = $temp_store_params[self::PARAM_PROVIDER];
    $provider = $this->providerManager->getProvider($provider_id);

    if (empty($provider)) {
      $error = $this->t('OCCAPI provider does not exist.');
      $this->messenger->addError($error);
      return FALSE;
    }

    if (!$provider->status()) {
      $error = $this->t('OCCAPI provider is not enabled.');
      $this->messenger->addError($error);
      return FALSE;
    }

    // Throw error if the related Institution is not present in the system.
    $hei_id = $provider->heiId();

    if (empty($this->occapiEntityManager->getHeiByHeiId($hei_id))) {
      $error = $this->t('Institution with ID %id does not exist.', [
        '%id' => $hei_id,
      ]);
      $this->messenger->addError($error);
      return FALSE;
    }

    // Issue warning if a filter entity cannot be imported.
    $param_filter_type = $temp_store_params[self::PARAM_FILTER_TYPE];

    if ($param_filter_type === self::TYPE_OUNIT) {
      $ounit_id = $temp_store_params[self::PARAM_FILTER_ID];

      if (empty($this->occapiEntityManager->getOunitByOunitId($ounit_id))) {
        $warning = $this->t('Organizational Unit with ID %id does not exist.', [
          '%id' => $ounit_id,
        ]);
        $this->messenger->addWarning($warning);
      }
    }

    return TRUE;
  }

  /**
   * Load data from a TempStore, depending on parameters.
   *
   * @param string $temp_store_key
   *   TempStore key from which all parameters are derived.
   *
   * @return array
   *   The loaded data.
   */
  public function loadData(string $temp_store_key): array {
    // Get the parameters from the TempStore key.
    $temp_store_params = $this->occapiTempStore->paramsFromKey($temp_store_key);

    $provider_id = $temp_store_params[self::PARAM_PROVIDER];
    $filter_type = $temp_store_params[self::PARAM_FILTER_TYPE];
    $filter_id = $temp_store_params[self::PARAM_FILTER_ID];
    $resource_type = $temp_store_params[self::PARAM_RESOURCE_TYPE];
    $resource_id = $temp_store_params[self::PARAM_RESOURCE_ID];

    $this->provider = $this->providerManager->getProvider($provider_id);

    if (!empty($resource_id)) {
      $raw[] = $this->dataLoader
        ->loadResource($provider_id, $resource_type, $resource_id);
    }
    elseif (!empty($filter_id)) {
      $raw = $this->dataLoader
        ->loadFilteredCollection($provider_id, $filter_type, $filter_id, $resource_type);
    }
    else {
      $raw = $this->dataLoader
        ->loadCollection($provider_id, $resource_type);
    }

    $data = $this->jsonDataProcessor->getResourceData($raw);

    return $data ?? [];
  }

  /**
   * Import data into the system.
   *
   * @param array $data
   *   Data to import.
   *
   * @return array
   *   The results of the import.
   */
  public function importData(array $data): array {
    $hei_id = $this->provider->heiId();
    $create = [];
    $update = [];

    foreach ($data as $i => $item) {
      $unique_id = $this->jsonDataProcessor->getResourceId($item);

      $exists = $this->occapiEntityManager->checkExistingEntity($item) ?? [];

      $entity_data = $this->occapiEntityManager
        ->prepareEntityData($item, $hei_id);

      if (empty($exists)) {
        $exists = $this->occapiEntityManager
          ->createEntity($entity_data['entity_type'], $entity_data);

        foreach ($exists as $key => $entity) {
          $create[$key] = $entity;
        }
      }
      else {
        foreach ($exists as $key => $entity) {
          $update[$key] = $this->occapiEntityManager
            ->updateEntity($entity, $entity_data);
        }
      }
    }

    $outcomes = ['created' => $create, 'updated' => $update];

    foreach ($outcomes as $change => $list) {
      if (\count($list) === 1) {
        foreach ($list as $entity) {
          $message = $this->t('Successfully @did %entity.', [
            '@did' => $change,
            '%entity' => $entity->label(),
          ]);

          $this->messenger->addMessage($message);
        }
      }
      elseif (\count($list) > 1) {
        $message = $this->t('Successfully @did @many entities.', [
          '@did' => $change,
          '@many' => \count($list),
        ]);

        $this->messenger->addMessage($message);
      }
    }

    return $outcomes;
  }

}
