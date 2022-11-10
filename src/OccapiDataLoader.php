<?php

namespace Drupal\occapi_client;

/**
 * Service for loading OCCAPI data.
 */
class OccapiDataLoader implements OccapiDataLoaderInterface {

  const PARAM_PROVIDER = OccapiTempStoreInterface::PARAM_PROVIDER;
  const PARAM_FILTER_TYPE = OccapiTempStoreInterface::PARAM_FILTER_TYPE;
  const PARAM_FILTER_ID = OccapiTempStoreInterface::PARAM_FILTER_ID;
  const PARAM_RESOURCE_TYPE = OccapiTempStoreInterface::PARAM_RESOURCE_TYPE;
  const PARAM_RESOURCE_ID = OccapiTempStoreInterface::PARAM_RESOURCE_ID;

  const TYPE_HEI = OccapiTempStoreInterface::TYPE_HEI;
  const TYPE_OUNIT = OccapiTempStoreInterface::TYPE_OUNIT;
  const TYPE_PROGRAMME = OccapiTempStoreInterface::TYPE_PROGRAMME;
  const TYPE_COURSE = OccapiTempStoreInterface::TYPE_COURSE;

  const DATA_KEY = JsonDataSchemaInterface::JSONAPI_DATA;
  const LINKS_KEY = JsonDataSchemaInterface::JSONAPI_LINKS;
  const SELF_KEY = JsonDataSchemaInterface::JSONAPI_SELF;

  /**
  * The JSON data fetcher.
  *
  * @var \Drupal\occapi_client\JsonDataFetcherInterface
  */
  protected $jsonDataFetcher;

  /**
  * The JSON data processor.
  *
  * @var \Drupal\occapi_client\JsonDataProcessorInterface
  */
  protected $jsonDataProcessor;

  /**
   * The OCCAPI provider manager.
   *
   * @var \Drupal\occapi_client\OccapiProviderManagerInterface
   */
  protected $providerManager;

  /**
   * The shared TempStore key manager.
   *
   * @var \Drupal\occapi_client\OccapiTempStoreInterface
   */
  protected $occapiTempStore;

  /**
   * The constructor.
   *
   * @param \Drupal\occapi_client\JsonDataFetcherInterface $json_data_fetcher
   *   The JSON data fetcher.
   * @param \Drupal\occapi_client\JsonDataProcessorInterface $json_data_processor
   *   The JSON data processor.
   * @param \Drupal\occapi_client\OccapiProviderManagerInterface $provider_manager
   *   The OCCAPI provider manager.
   * @param \Drupal\occapi_client\OccapiTempStoreInterface $occapi_tempstore
   *   The shared TempStore key manager.
   */
  public function __construct(
    JsonDataFetcherInterface $json_data_fetcher,
    JsonDataProcessorInterface $json_data_processor,
    OccapiProviderManager $provider_manager,
    OccapiTempStoreInterface $occapi_tempstore
  ) {
    $this->jsonDataFetcher   = $json_data_fetcher;
    $this->jsonDataProcessor = $json_data_processor;
    $this->providerManager   = $provider_manager;
    $this->occapiTempStore   = $occapi_tempstore;
  }

  /**
   * Load Institution resource.
   *
   * @param string $provider_id
   *   The OCCAPI provider ID.
   *
   * @return array|null $data
   *   An array containing the JSON:API Institution resource data.
   */
  public function loadInstitution(string $provider_id): ?array {
    $provider = $this->providerManager->getProvider($provider_id);

    $temp_store_params = [
      self::PARAM_PROVIDER => $provider_id,
      self::PARAM_FILTER_TYPE => NULL,
      self::PARAM_FILTER_ID => NULL,
      self::PARAM_RESOURCE_TYPE => self::TYPE_HEI,
      self::PARAM_RESOURCE_ID => $provider->heiId(),
    ];

    $temp_store_key = $this->occapiTempStore
      ->keyFromParams($temp_store_params);

    $endpoint = $provider->baseUrl();

    $response = $this->jsonDataFetcher
      ->load($temp_store_key, $endpoint);

    $data = \json_decode($response, TRUE);

    return $data;
  }

  /**
   * Load resource collection by type.
   *
   * @param string $provider_id
   *   The OCCAPI provider ID.
   * @param string $resource_type
   *   The JSON:API resource type key.
   *
   * @return array
   *   An array containing the JSON:API resource collection data.
   */
  public function loadCollection(string $provider_id, string $resource_type): array {
    $provider = $this->providerManager->getProvider($provider_id);

    $type_is_valid = \in_array($resource_type, [
      self::TYPE_OUNIT,
      self::TYPE_PROGRAMME,
      self::TYPE_COURSE
    ]);

    if (empty($provider) || !$type_is_valid ) { return []; }

    $temp_store_params = [
      self::PARAM_PROVIDER => $provider_id,
      self::PARAM_FILTER_TYPE => NULL,
      self::PARAM_FILTER_ID => NULL,
      self::PARAM_RESOURCE_TYPE => $resource_type,
      self::PARAM_RESOURCE_ID => NULL,
    ];

    $temp_store_key = $this->occapiTempStore
      ->keyFromParams($temp_store_params);

    // If data is present in TempStore the endpoint is ignored.
    $endpoint = '';

    if (empty($this->jsonDataFetcher->checkUpdated($temp_store_key))) {
      $hei_data = $this->loadInstitution($provider_id);
      $hei_links = $hei_data[self::LINKS_KEY];

      if (! \array_key_exists($resource_type, $hei_links)) { return []; }

      $endpoint = $this->jsonDataProcessor
        ->getResourceLinkByType($hei_data, $resource_type);
    }

    $response = $this->jsonDataFetcher
      ->load($temp_store_key, $endpoint);

    $collection = \json_decode($response, TRUE);

    return $collection;
  }

  /**
   * Load resource collection by type, filtered by parent type and id.
   *
   * @param string $provider_id
   *   The OCCAPI provider ID.
   * @param string $filter_type
   *   The JSON:API resource type key to filter by.
   * @param string $filter_id
   *   The JSON:API resource ID to filter by.
   * @param string $resource_type
   *   The JSON:API resource type key.
   *
   * @return array
   *   An array containing the JSON:API resource collection data.
   */
  public function loadFilteredCollection(string $provider_id, string $filter_type, string $filter_id, string $resource_type): array {
    $provider = $this->providerManager->getProvider($provider_id);

    $filter_type_is_valid = \in_array($filter_type, [
      self::TYPE_OUNIT,
      self::TYPE_PROGRAMME
    ]);

    $filter_is_valid = ($filter_type_is_valid && !empty($filter_id));

    $type_is_valid = \in_array($resource_type, [
      self::TYPE_PROGRAMME,
      self::TYPE_COURSE
    ]);

    $params_are_valid = ($filter_is_valid && $type_is_valid);

    if (empty($provider) || !$params_are_valid) { return []; }

    $temp_store_params = [
      self::PARAM_PROVIDER => $provider_id,
      self::PARAM_FILTER_TYPE => $filter_type,
      self::PARAM_FILTER_ID => $filter_id,
      self::PARAM_RESOURCE_TYPE => $resource_type,
      self::PARAM_RESOURCE_ID => NULL,
    ];

    $temp_store_key = $this->occapiTempStore
      ->keyFromParams($temp_store_params);

    // If data is present in TempStore the endpoint is ignored.
    $endpoint = '';

    if (empty($this->jsonDataFetcher->checkUpdated($temp_store_key))) {
      $filter_data = $this->loadResource($provider_id, $filter_type, $filter_id);
      $filter_links = $filter_data[self::LINKS_KEY];

      if (! \array_key_exists($resource_type, $filter_links)) { return []; }

      $endpoint = $this->jsonDataProcessor
        ->getResourceLinkByType($filter_data, $resource_type);
    }

    $response = $this->jsonDataFetcher
      ->load($temp_store_key, $endpoint);

    $collection = \json_decode($response, TRUE);

    return $collection;
  }

  /**
   * Load single resource by type and ID.
   *
   * @param string $provider_id
   *   The OCCAPI provider ID.
   * @param string $resource_type
   *   The JSON:API resource type key.
   * @param string $resource_id
   *   The JSON:API resource ID.
   *
   * @return array $resource
   *   An array containing the JSON:API resource data.
   */
  public function loadResource(string $provider_id, string $resource_type, string $resource_id): array {
    $collection = $this->loadCollection($provider_id, $resource_type);
    $collection_has_data = \array_key_exists(self::DATA_KEY, $collection);

    if (empty($collection) || !$collection_has_data ) { return []; }

    $data = $collection[self::DATA_KEY];

    $temp_store_params = [
      self::PARAM_PROVIDER => $provider_id,
      self::PARAM_FILTER_TYPE => NULL,
      self::PARAM_FILTER_ID => NULL,
      self::PARAM_RESOURCE_TYPE => $resource_type,
      self::PARAM_RESOURCE_ID => $resource_id,
    ];

    $temp_store_key = $this->occapiTempStore
      ->keyFromParams($temp_store_params);


    // If data is present in TempStore the endpoint is ignored.
    $endpoint = '';

    if (empty($this->jsonDataFetcher->checkUpdated($temp_store_key))) {
      foreach ($data as $i => $resource) {
        // Only one item at most will pass this check.
        if ($this->jsonDataProcessor->getResourceId($resource) === $resource_id) {
          $endpoint = $this->jsonDataProcessor
            ->getResourceLinkByType($resource, self::SELF_KEY);
        }
      }

      if (empty($endpoint)) { return []; }
    }

    $response = $this->jsonDataFetcher
      ->load($temp_store_key, $endpoint);

    $resource = \json_decode($response, TRUE);

    return $resource;
  }

  /**
   * Load Organizational Unit collection.
   *
   * @param string $provider_id
   *   The OCCAPI provider ID.
   *
   * @return array
   *   An array containing the JSON:API resource collection data.
   */
  public function loadOunits(string $provider_id): array {
    return $this->loadCollection($provider_id, self::TYPE_OUNIT);
  }

  /**
   * Load Organizational Unit resource by ID.
   *
   * @param string $provider_id
   *   The OCCAPI provider ID.
   * @param string $resource_id
   *   The JSON:API resource ID.
   *
   * @return array
   *   An array containing the JSON:API resource data.
   */
  public function loadOunit(string $provider_id, string $resource_id): array {
    return $this->loadResource($provider_id, self::TYPE_OUNIT, $resource_id);
  }

  /**
   * Load Programme collection.
   *
   * @param string $provider_id
   *   The OCCAPI provider ID.
   *
   * @return array
   *   An array containing the JSON:API resource collection data.
   */
  public function loadProgrammes(string $provider_id): array {
    return $this->loadCollection($provider_id, self::TYPE_PROGRAMME);
  }

  /**
   * Load Programme collection filtered by Organizational Unit.
   *
   * @param string $provider_id
   *   The OCCAPI provider ID.
   * @param string $ounit_id
   *   The JSON:API resource ID to filter by.
   *
   * @return array
   *   An array containing the JSON:API resource collection data.
   */
  public function loadOunitProgrammes(string $provider_id, string $ounit_id): array {
    return $this->loadFilteredCollection($provider_id, self::TYPE_OUNIT, $ounit_id, self::TYPE_PROGRAMME);
  }

  /**
   * Load Programme resource by ID.
   *
   * @param string $provider_id
   *   The OCCAPI provider ID.
   * @param string $resource_id
   *   The JSON:API resource ID.
   *
   * @return array
   *   An array containing the JSON:API resource data.
   */
  public function loadProgramme(string $provider_id, string $resource_id): array {
    return $this->loadResource($provider_id, self::TYPE_PROGRAMME, $resource_id);
  }

  /**
   * Load Course collection.
   *
   * @param string $provider_id
   *   The OCCAPI provider ID.
   *
   * @return array
   *   An array containing the JSON:API resource collection data.
   */
  public function loadCourses(string $provider_id): array {
    return $this->loadCollection($provider_id, self::TYPE_COURSE);
  }

  /**
   * Load Course collection filtered by Organizational Unit.
   *
   * @param string $provider_id
   *   The OCCAPI provider ID.
   * @param string $ounit_id
   *   The JSON:API resource ID to filter by.
   *
   * @return array
   *   An array containing the JSON:API resource collection data.
   */
  public function loadOunitCourses(string $provider_id, string $ounit_id): array {
    return $this->loadFilteredCollection($provider_id, self::TYPE_OUNIT, $ounit_id, self::TYPE_COURSE);
  }

  /**
   * Load Course collection filtered by Programme.
   *
   * @param string $provider_id
   *   The OCCAPI provider ID.
   * @param string $programme_id
   *   The JSON:API resource ID to filter by.
   *
   * @return array
   *   An array containing the JSON:API resource collection data.
   */
  public function loadProgrammeCourses(string $provider_id, string $programme_id): array {
    return $this->loadFilteredCollection($provider_id, self::TYPE_PROGRAMME, $programme_id, self::TYPE_COURSE);
  }

  /**
   * Load Course resource by ID.
   *
   * @param string $provider_id
   *   The OCCAPI provider ID.
   * @param string $resource_id
   *   The JSON:API resource ID.
   *
   * @return array
   *   An array containing the JSON:API resource data.
   */
  public function loadCourse(string $provider_id, string $resource_id): array {
    return $this->loadResource($provider_id, self::TYPE_COURSE, $resource_id);
  }

}
