<?php

namespace Drupal\occapi_client;

use Drupal\occapi_client\Entity\OccapiProvider;

/**
 * Defines an interface for an OCCAPI provider manager.
 */
interface OccapiProviderManagerInterface {

  /**
   * Get a list of OCCAPI providers.
   *
   * @return \Drupal\occapi_client\Entity\OccapiProvider[]
   *   List of OCCAPI providers.
   */
  public function getProviders(): array;

  /**
   * Get an OCCAPI provider by ID.
   *
   * @param string $id
   *   The OCCAPI provider ID.
   *
   * @return \Drupal\occapi_client\Entity\OccapiProvider|null
   *   The OCCAPI provider matching the ID, if any.
   */
  public function getProvider(string $id): ?OccapiProvider;

  /**
   * Get a list of enabled OCCAPI providers by Institution ID.
   *
   * @param string $hei_id
   *   Institution ID to look up.
   *
   * @return \Drupal\occapi_client\Entity\OccapiProvider[]
   *   List of OCCAPI providers covering the Institution.
   */
  public function getProvidersByHeiId(string $hei_id): array;

  /**
   * Get a provider's Institution TempStore key from another key.
   *
   * @param string $temp_store_key
   *   The TempStore key.
   *
   * @return string
   *   The Institution TempStore key.
   */
  public function getHeiTempstoreKey(string $temp_store_key): string;

}
