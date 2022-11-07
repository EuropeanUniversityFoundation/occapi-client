<?php

namespace Drupal\occapi_client;

/**
 * Defines an interface for a Shared TempStore manager.
 */
interface OccapiTempStoreInterface {

  const JSONAPI_TYPE_HEI = 'hei';
  const JSONAPI_TYPE_OUNIT = 'ounit';
  const JSONAPI_TYPE_PROGRAMME = 'programme';
  const JSONAPI_TYPE_COURSE = 'course';

  const PARAM_PROVIDER = 'provider';
  const PARAM_FILTER_TYPE = 'filter_type';
  const PARAM_FILTER_ID = 'filter_id';
  const PARAM_RESOURCE_TYPE = 'resource_type';
  const PARAM_RESOURCE_ID = 'resource_id';

  const TEMPSTORE_KEY_SEPARATOR = '.';

  /**
   * Extract parameters from a TempStore key.
   *
   * @param string $temp_store_key
   *   The TempStore key.
   *
   * @return array
   *   The TempStore parameters.
   */
  public function paramsFromKey(string $temp_store_key): array;

  /**
   * Validate a TempStore key by parameters.
   *
   * @param string $temp_store_key
   *   The TempStore key.
   * @param bool $single
   *   Whether the key refers to a single resource (defaults to FALSE).
   *
   * @return string|null
   *   The error message if any error is detected.
   */
  public function validateTempstoreKey(string $temp_store_key, bool $single = FALSE): ?string;

  /**
   * Build a TempStore key from parameters.
   *
   * @param array $temp_store_params
   *   The TempStore parameters.
   *
   * @return string
   *   The TempStore key.
   */
  public function keyFromParams(array $temp_store_params): string;

  /**
   * Validate a collection TempStore key.
   *
   * @param string $temp_store_key
   *   TempStore key to validate.
   * @param string $resource_type|null
   *   OCCAPI entity type key to validate.
   * @param string $filter_type|null
   *   OCCAPI entity type key used as filter.
   *
   * @return string|null
   *   The error message if any error is detected.
   */
  public function validateCollectionTempstore(string $temp_store_key, ?string $resource_type = NULL, ?string $filter_type = NULL): ?string;

  /**
   * Validate a resource TempStore key.
   *
   * @param string $temp_store_key
   *   TempStore key to validate.
   * @param string $resource_type|null
   *   OCCAPI entity type key to validate.
   *
   * @return string|null
   *   The error message if any error is detected.
   */
  public function validateResourceTempstore(string $temp_store_key, string $resource_type): ?string;


  /**
   * Validate a resource type.
   *
   * @param string $resource_type
   *   Resource type to validate.
   * @param array $allowed_types
   *   Allowed resource types.
   *
   * @return string|null
   *   The error message if any error is detected.
   */
  public function validateResourceType(string $resource_type, array $allowed_types): ?string;

  /**
   * Check the TempStore for the updated date.
   *
   * @param string $temp_store_key
   *   The TempStore key.
   *
   * @return int|null
   *   A UNIX timestamp or NULL.
   */
  public function checkUpdated(string $temp_store_key): ?int;

}
