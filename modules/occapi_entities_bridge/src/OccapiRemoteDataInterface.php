<?php

namespace Drupal\occapi_entities_bridge;

/**
 * Defines an interface for an OCCAPI remote data handler.
 */
interface OccapiRemoteDataInterface {

  const TYPE_COURSE = OccapiTempStoreInterface::TYPE_COURSE;

  /**
   * Format remote API fields for display.
   *
   * @param string $remote_id
   *   Remote ID of an OCCAPI resource.
   * @param string $remote_url
   *   Remote URL of an OCCAPI resource.
   *
   * @return string
   *   Renderable markup.
   */
  public function formatRemoteId(string $remote_id, string $remote_url): string;

  /**
   * Load single Course resource directly from an external API.
   *
   * @param string $temp_store_key
   *   TempStore key for the Course resource.
   * @param string $endpoint
   *   The endpoint from which to fetch data.
   *
   * @return array
   *   An array containing the JSON:API resource data.
   */
  public function loadExternalCourse(string $temp_store_key, string $endpoint): array;

}
