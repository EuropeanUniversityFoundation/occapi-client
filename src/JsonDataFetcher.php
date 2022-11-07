<?php

namespace Drupal\occapi_client;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * JSON data fetcher.
 */
class JsonDataFetcher implements JsonDataFetcherInterface {

  use StringTranslationTrait;

  const INDEX_KEYWORD = 'index';

  /**
   * HTTP Client for API calls.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The module handler to invoke the alter hooks with.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * An instance of the key/value store.
   *
   * @var \Drupal\Core\TempStore\SharedTempStore
   */
  protected $tempStore;

  /**
   * Constructs a new JsonDataFetcher.
   *
   * @param \GuzzleHttp\Client $http_client
   *   HTTP client.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hooks with.
   * @param \Drupal\Core\TempStore\SharedTempStoreFactory $temp_store_factory
   *   The factory for the temp store object.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(
    Client $http_client,
    LoggerChannelFactoryInterface $logger_factory,
    ModuleHandlerInterface $module_handler,
    SharedTempStoreFactory $temp_store_factory,
    TranslationInterface $string_translation
  ) {
    $this->httpClient         = $http_client;
    $this->logger             = $logger_factory->get('occapi_client');
    $this->moduleHandler      = $module_handler;
    $this->tempStore          = $temp_store_factory->get('occapi_client');
    $this->stringTranslation  = $string_translation;
  }

  /**
   * Load JSON:API data from tempstore or external API endpoint.
   *
   * @param string $temp_store_key
   *   A key from the key_value_expire table.
   * @param string $endpoint
   *   The endpoint from which to fetch data.
   * @param boolean $refresh
   *   Whether to force a refresh of the stored data.
   *
   * @return string|null
   *   A string containing the stored data or NULL.
   */
  public function load(string $temp_store_key, string $endpoint, $refresh = FALSE): ?string {
    // If tempstore is empty OR should be refreshed.
    if (empty($this->tempStore->get($temp_store_key)) || $refresh) {
      // Get the data from the provided endpoint.
      $raw = $this->get($endpoint);

      // Preprocess the raw data before saving it.
      $this->preprocess($raw, $temp_store_key);

      // Save the data to tempstore.
      $this->tempStore->set($temp_store_key, $raw);

      $message = $this->t("Loaded @key into temporary storage", [
        '@key' => $temp_store_key
      ]);
      $this->logger->notice($message);
    }

    // Retrieve whatever is in storage.
    $data = $this->tempStore->get($temp_store_key);

    // Process the tempstore data before serving it.
    $this->process($data, $temp_store_key);

    return $data;
  }

  /**
   * Get JSON:API data from an external API endpoint.
   *
   * @param string $endpoint
   *   The endpoint from which to fetch data.
   *
   * @return string
   *   A string containing JSON data.
   */
  public function get(string $endpoint): string {
    // Prepare the JSON string.
    $json_data = '';

    $response = NULL;

    // Build the HTTP request.
    try {
      $request = $this->httpClient->get($endpoint);
      $response = $request->getBody();
    } catch (GuzzleException $e) {
      $response = $e->getResponse()->getBody();
    } catch (Exception $e) {
      watchdog_exception('occapi_client', $e->getMessage());
    }

    // Extract the data from the Guzzle Stream.
    $decoded = json_decode($response, TRUE);
    // Encode the data for persistency.
    $json_data = json_encode($decoded);

    // Return the data.
    return $json_data;
  }

  /**
   * Get response code from an external API endpoint.
   *
   * @param string $endpoint
   *   The external API endpoint.
   *
   * @return int
   *   The response code.
   */
  public function getResponseCode(string $endpoint): int {
    // Build the HTTP request.
    try {
      $request = $this->httpClient->get($endpoint);
      $code = $request->getStatusCode();
    } catch (GuzzleException $e) {
      $code = $e->getCode();
    } catch (Exception $e) {
      watchdog_exception('occapi_client', $e->getMessage());
    }

    return $code;
  }

  /**
   * Preprocess data before storing it in the key_value_expire table.
   *
   * @param string $data
   *   The JSON:API data.
   * @param string $temp_store_key
   *   A key from the key_value_expire table.
   */
  public function preprocess(string &$data, string $temp_store_key): void {
    $context = ['unalterable' => $temp_store_key];

    // Strip whitespace from the JSON data.
    $data = \json_encode(\json_decode($data));

    // Allow other modules to alter the raw data before saving it.
    $this->moduleHandler->alter('occapi_data_get', $data, $context);
  }

  /**
   * Process data after retrieving it from the key_value_expire table.
   *
   * @param string $data
   *   The JSON:API data.
   * @param string $temp_store_key
   *   A key from the key_value_expire table.
   */
  public function process(string &$data, string $temp_store_key): void {
    $context = ['unalterable' => $temp_store_key];

    // Allow other modules to alter the tempstore data before serving it.
    $this->moduleHandler->alter('occapi_data_load', $data, $context);
  }

  /**
   * Check the tempstore for the updated date.
   *
   * @param string $temp_store_key
   *   A key from the key_value_expire table.
   *
   * @return int|null
   *   A UNIX timestamp or NULL.
   */
  public function checkUpdated(string $temp_store_key): ?int {
    if (!empty($this->tempStore->get($temp_store_key))) {
      return $this->tempStore->getMetadata($temp_store_key)->getUpdated();
    } else {
      return NULL;
    }
  }

  /**
   * Get the updated value from an endpoint.
   *
   * @param string $temp_store_key
   *   A key from the key_value_expire table.
   * @param string $endpoint
   *   The endpoint from which to fetch data.
   *
   * @return string|null
   *   A string containing the stored data or NULL.
   */
  public function getUpdated(string $temp_store_key, string $endpoint): ?string {
    // Check when this item was last updated
    $item_updated = $this->checkUpdated($temp_store_key);

    if ($temp_store_key != self::INDEX_KEYWORD) {
      // Check when the index was last updated
      $index_updated = $this->checkUpdated(self::INDEX_KEYWORD);
    } else {
      // Assign for comparison
      $index_updated = $item_updated;
    }

    $refresh = ($item_updated && $index_updated <= $item_updated) ? FALSE : TRUE;

    return $this->load($temp_store_key, $endpoint, $refresh);
  }

}
