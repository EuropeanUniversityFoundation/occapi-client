<?php

namespace Drupal\occapi_client;

/**
 * Defines an interface for a resource relationships manager.
 */
interface OccapiRelationshipsInterface {

  /**
   * Add relationships to data based on the TempStore filter.
   *
   * @param string $data
   *   The JSON:API data.
   * @param string $temp_store_key
   *   The TempStore key.
   */
  public function addFromFilter(string &$data, string $temp_store_key): void;

  /**
   * Add relationship to data item.
   *
   * @param array $item
   *   The JSON:API data item.
   * @param string $type
   *   The resource type of the relationship.
   * @param string $id
   *   The resource ID of the relationship.
   *
   * @return array
   *   The altered data item.
   */
  public function add(array $item, string $type, string $id): array;
  
}
