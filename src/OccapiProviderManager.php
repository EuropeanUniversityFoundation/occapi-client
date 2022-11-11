<?php

namespace Drupal\occapi_client;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\occapi_client\Entity\OccapiProvider;


/**
 * Service for managing OCCAPI providers.
 */
class OccapiProviderManager implements OccapiProviderManagerInterface {

  use StringTranslationTrait;

  const ENTITY_TYPE = 'occapi_provider';

  const TYPE_HEI = OccapiTempStoreInterface::TYPE_HEI;
  const TYPE_OUNIT = OccapiTempStoreInterface::TYPE_OUNIT;
  const TYPE_PROGRAMME = OccapiTempStoreInterface::TYPE_PROGRAMME;
  const TYPE_COURSE = OccapiTempStoreInterface::TYPE_COURSE;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The shared TempStore key manager.
   *
   * @var \Drupal\occapi_client\OccapiTempStoreInterface
   */
  protected $occapiTempStore;

  /**
   * The constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\occapi_client\OccapiTempStoreInterface $occapi_tempstore
   *   The shared TempStore key manager.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    OccapiTempStoreInterface $occapi_tempstore
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->occapiTempStore   = $occapi_tempstore;
  }

  /**
   * Get a list of OCCAPI providers.
   *
   * @return \Drupal\occapi_client\Entity\OccapiProvider[]
   *   List of OCCAPI providers.
   */
  public function getProviders(): array {
    $providers = $this->entityTypeManager
      ->getStorage(self::ENTITY_TYPE)
      ->loadMultiple();

    return $providers;
  }

  /**
   * Get an OCCAPI provider by ID.
   *
   * @param string $id
   *   The OCCAPI provider ID.
   *
   * @return \Drupal\occapi_client\Entity\OccapiProvider|null
   *   The OCCAPI provider matching the ID, if any.
   */
  public function getProvider(string $id): ?OccapiProvider {
    $provider = NULL;

    $providers = $this->entityTypeManager
      ->getStorage(self::ENTITY_TYPE)
      ->loadByProperties(['id' => $id]);

    // The providers array will contain one item at most.
    foreach ($providers as $id => $object) {
      $provider = $object;
    }

    return $provider;
  }

  /**
   * Get a list of enabled OCCAPI providers by Institution ID.
   *
   * @param string $hei_id
   *   Institution ID to look up.
   *
   * @return \Drupal\occapi_client\Entity\OccapiProvider[]
   *   List of OCCAPI providers covering the Institution.
   */
  public function getProvidersByHeiId(string $hei_id): array {
    $providers = $this->entityTypeManager
      ->getStorage(self::ENTITY_TYPE)
      ->loadByProperties(['hei_id' => $hei_id, 'status' => TRUE]) ?? [];

    return $providers;
  }

  /**
   * Get a provider's Institution TempStore key from another key.
   *
   * @param string $temp_store_key
   *   The TempStore key.
   *
   * @return string
   *   The Institution TempStore key.
   */
  public function getHeiTempstoreKey(string $temp_store_key): string {
    $temp_store_params = $this->occapiTempStore
      ->paramsFromKey($temp_store_key);

    $provider_id = $temp_store_params[OccapiTempStoreInterface::PARAM_PROVIDER];

    $provider = $this->getProvider($provider_id);

    $hei_temp_store_params = [
      OccapiTempStoreInterface::PARAM_PROVIDER => $provider_id,
      OccapiTempStoreInterface::PARAM_FILTER_TYPE => NULL,
      OccapiTempStoreInterface::PARAM_FILTER_ID => NULL,
      OccapiTempStoreInterface::PARAM_RESOURCE_TYPE => self::TYPE_HEI,
      OccapiTempStoreInterface::PARAM_RESOURCE_ID => $provider->heiId(),
    ];

    $hei_temp_store_key = $this->occapiTempStore
      ->keyFromParams($hei_temp_store_params);

    return $hei_temp_store_key;
  }

}
