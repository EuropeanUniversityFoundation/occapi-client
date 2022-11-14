<?php

namespace Drupal\occapi_client;

/**
 * Defines an interface for a shared TempStore key manager.
 */
interface OccapiTempStoreInterface {

  const PARAM_PROVIDER = 'provider';
  const PARAM_FILTER_TYPE = 'filter_type';
  const PARAM_FILTER_ID = 'filter_id';
  const PARAM_RESOURCE_TYPE = 'resource_type';
  const PARAM_RESOURCE_ID = 'resource_id';
  const PARAM_EXTERNAL = 'external';

  const TEMPSTORE_KEY_SEPARATOR = '.';

  const TYPE_HEI = 'hei';
  const TYPE_OUNIT = 'ounit';
  const TYPE_PROGRAMME = 'programme';
  const TYPE_COURSE = 'course';

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
   * Validate a TempStore key by parameters.
   *
   * @param string $temp_store_key
   *   The TempStore key.
   *
   * @return bool
   *   Returns TRUE is validation passes, otherwise FALSE.
   */
  public function validateTempstoreKey(string $temp_store_key): bool;

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
   * @return bool
   *   Returns TRUE is validation passes, otherwise FALSE.
   */
  public function validateCollectionTempstore(string $temp_store_key, ?string $resource_type = NULL, ?string $filter_type = NULL): bool;

  /**
   * Validate a resource TempStore key.
   *
   * @param string $temp_store_key
   *   TempStore key to validate.
   * @param string $resource_type|null
   *   OCCAPI entity type key to validate.
   *
   * @return bool
   *   Returns TRUE is validation passes, otherwise FALSE.
   */
  public function validateResourceTempstore(string $temp_store_key, string $resource_type): bool;


  /**
   * Validate a resource type.
   *
   * @param string $resource_type
   *   Resource type to validate.
   * @param array $allowed_types
   *   Allowed resource types.
   *
   * @return bool
   *   Returns TRUE is validation passes, otherwise FALSE.
   */
  public function validateResourceType(string $resource_type, array $allowed_types): bool;

}
