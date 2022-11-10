<?php

namespace Drupal\occapi_client;

use Drupal\occapi_client\Entity\OccapiProvider;

/**
 * Defines an interface for an OCCAPI data loader.
 */
interface OccapiDataLoaderInterface {

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
  public function loadCollection(string $provider_id, string $resource_type): array;

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
  public function loadFilteredCollection(string $provider_id, string $filter_type, string $filter_id, string $resource_type): array;

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
  public function loadResource(string $provider_id, string $resource_type, string $resource_id): array;

}
