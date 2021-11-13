<?php

namespace Drupal\occapi_client;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\occapi_client\DataFormatter;
use Drupal\occapi_client\JsonDataFetcher;
use Drupal\occapi_client\JsonDataProcessor as Json;

/**
 * Service for managing OCCAPI providers.
 */
class OccapiProviderManager {

  use StringTranslationTrait;

  const ENTITY_TYPE       = 'occapi_provider';

  const HEI_KEY           = 'hei';
  const OUNIT_KEY         = 'ounit';
  const PROGRAMME_KEY     = 'programme';
  const COURSE_KEY        = 'course';

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

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
   * The constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\occapi_client\DataFormatter $data_formatter
   *   Data formatting service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\occapi_client\JsonDataFetcher $json_data_fetcher
   *   JSON data fetching service.
   * @param \Drupal\occapi_client\JsonDataProcessor $json_data_processor
   *   JSON data processing service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(
      ConfigFactoryInterface $config_factory,
      DataFormatter $data_formatter,
      EntityTypeManagerInterface $entity_type_manager,
      JsonDataFetcher $json_data_fetcher,
      JsonDataProcessor $json_data_processor,
      LoggerChannelFactoryInterface $logger_factory,
      MessengerInterface $messenger,
      TranslationInterface $string_translation
  ) {
    $this->configFactory      = $config_factory;
    $this->dataFormatter      = $data_formatter;
    $this->entityTypeManager  = $entity_type_manager;
    $this->jsonDataFetcher    = $json_data_fetcher;
    $this->jsonDataProcessor  = $json_data_processor;
    $this->logger             = $logger_factory->get('occapi_client');
    $this->messenger          = $messenger;
    $this->stringTranslation  = $string_translation;
  }

  /**
   * Get a list of OCCAPI providers.
   */
  public function getProviders() {
    $providers = $this->entityTypeManager
      ->getStorage(self::ENTITY_TYPE)
      ->loadMultiple();

    return $providers;
  }

  /**
   * Get an OCCAPI provider by ID.
   */
  public function getProvider(string $id) {
    $providers = $this->entityTypeManager
      ->getStorage(self::ENTITY_TYPE)
      ->loadByProperties(['id' => $id]);

    foreach ($providers as $id => $object) {
      $provider = $object;
    }

    return $provider;
  }

  /**
  * Load Institution resource.
  *
  * @param string $provider_id
  *   The OCCAPI provider ID.
  *
  * @return array $data
  *   An array containing the JSON:API Institution resource data.
  */
  public function loadInstitution(string $provider_id) {
    $provider = $this->getProvider($provider_id);

    $hei_id   = $provider->get('hei_id');
    $base_url = $provider->get('base_url');

    $tempstore = $provider_id . '.' . self::HEI_KEY . '.' . $hei_id;

    $endpoint = $base_url . '/' . self::HEI_KEY . '/' . $hei_id;

    $response = $this->jsonDataFetcher
      ->load($tempstore, $endpoint);

    $data = \json_decode($response, TRUE);

    return $data;
  }

  /**
   * Load resource collection by type.
   *
   * @param string $provider_id
   *   The OCCAPI provider ID.
   * @param string $type
   *   The JSON:API resource type key.
   *
   * @return array $data
   *   An array containing the JSON:API resource collection data.
   */
  private function loadCollection(string $provider_id, string $type) {
    $provider = $this->getProvider($provider_id);

    if (
      empty($provider) ||
      ! \in_array($type, [
        self::OUNIT_KEY,
        self::PROGRAMME_KEY,
        self::COURSE_KEY
      ])
    ) {
      return [];
    }

    $tempstore = $provider_id . '.' . $type;

    // If data is present in TempStore the endpoint is ignored.
    $endpoint = '';

    if (empty($this->jsonDataFetcher->checkUpdated($tempstore))) {
      $hei_data = $this->loadInstitution($provider_id);

      if (! \in_array($type, $hei_data[Json::LINKS_KEY])) {
        return [];
      }

      $endpoint = $this->jsonDataProcessor
        ->getLink($hei_data, $type);
    }

    $response = $this->jsonDataFetcher
      ->load($tempstore, $endpoint);

    $data = \json_decode($response, TRUE);

    return $data;
  }

  /**
   * Load single resource by type and ID.
   *
   * @param string $provider_id
   *   The OCCAPI provider ID.
   * @param string $type
   *   The JSON:API resource type key.
   * @param string $id
   *   The JSON:API resource ID.
   *
   * @return array $data
   *   An array containing the JSON:API resource data.
   */
  private function loadResource(string $provider_id, string $type, string $id) {
    $collection = $this->loadCollection($provider_id, $type);

    if (empty($collection)) {
      return [];
    }

    $tempstore = $provider_id . '.' . $type . $id;

    // If data is present in TempStore the endpoint is ignored.
    $endpoint = '';

    if (empty($this->jsonDataFetcher->checkUpdated($tempstore))) {
      foreach ($collection as $i => $resource) {
        if ($this->jsonDataProcessor->getId($resource) === $id) {
          $endpoint = $this->jsonDataProcessor
            ->getLink($resource, self::SELF_KEY);
        }
      }

      if (empty($endpoint)) {
        return [];
      }
    }

    $response = $this->jsonDataFetcher
      ->load($tempstore, $endpoint);

    $data = \json_decode($response, TRUE);

    return $data;
  }

  /**
   * Load Organizational Unit collection.
   *
   * @param string $provider_id
   *   The OCCAPI provider ID.
   *
   * @return array
   */
  public function loadOunits(string $provider_id) {
    return $this->loadCollection($provider_id, self::OUNIT_KEY);
  }

  /**
   * Load Organizational Unit resource by ID.
   *
   * @param string $provider_id
   *   The OCCAPI provider ID.
   * @param string $id
   *   The JSON:API resource ID.
   *
   * @return array
   */
  public function loadOunit(string $provider_id, string $id) {
    return $this->loadResource($provider_id, self::OUNIT_KEY, $id);
  }

  /**
   * Load Programme collection.
   *
   * @param string $provider_id
   *   The OCCAPI provider ID.
   *
   * @return array
   */
  public function loadProgrammes(string $provider_id) {
    return $this->loadCollection($provider_id, self::PROGRAMME_KEY);
  }

  /**
   * Load Programme resource by ID.
   *
   * @param string $provider_id
   *   The OCCAPI provider ID.
   * @param string $id
   *   The JSON:API resource ID.
   *
   * @return array
   */
  public function loadProgramme(string $provider_id, string $id) {
    return $this->loadResource($provider_id, self::PROGRAMME_KEY, $id);
  }

  /**
   * Load Course collection.
   *
   * @param string $provider_id
   *   The OCCAPI provider ID.
   *
   * @return array
   */
  public function loadProgrammes(string $provider_id) {
    return $this->loadCollection($provider_id, self::COURSE_KEY);
  }

  /**
   * Load Course resource by ID.
   *
   * @param string $provider_id
   *   The OCCAPI provider ID.
   * @param string $id
   *   The JSON:API resource ID.
   *
   * @return array
   */
  public function loadProgramme(string $provider_id, string $id) {
    return $this->loadResource($provider_id, self::COURSE_KEY, $id);
  }

}
