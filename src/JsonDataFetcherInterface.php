<?php

namespace Drupal\occapi_client;

/**
 * Defines an interface for a JSON:API data fetcher.
 */
interface JsonDataFetcherInterface {

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
  public function load(string $temp_store_key, string $endpoint, $refresh = FALSE): ?string;

  /**
   * Get JSON:API data from an external API endpoint.
   *
   * @param string $endpoint
   *   The endpoint from which to fetch data.
   *
   * @return string
   *   A string containing JSON data.
   */
  public function get(string $endpoint): string;

  /**
   * Get response code from an external API endpoint.
   *
   * @param string $endpoint
   *   The external API endpoint.
   *
   * @return int
   *   The response code.
   */
  public function getResponseCode(string $endpoint): int;

  /**
   * Preprocess data before storing it in the key_value_expire table.
   *
   * @param string $data
   *   The JSON:API data.
   * @param string $temp_store_key
   *   A key from the key_value_expire table.
   */
  public function preprocess(string &$data, string $temp_store_key): void;

  /**
   * Process data after retrieving it from the key_value_expire table.
   *
   * @param string $data
   *   The JSON:API data.
   * @param string $temp_store_key
   *   A key from the key_value_expire table.
   */
  public function process(string &$data, string $temp_store_key): void;

  /**
   * Check the tempstore for the updated date.
   *
   * @param string $temp_store_key
   *   A key from the key_value_expire table.
   *
   * @return int|null
   *   A UNIX timestamp or NULL.
   */
  public function checkUpdated(string $temp_store_key): ?int;

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
  public function getUpdated(string $temp_store_key, string $endpoint): ?string;


}
