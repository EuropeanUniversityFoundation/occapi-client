<?php

namespace Drupal\occapi_client;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\occapi_client\OccapiTempStoreInterface;

/**
 * Service description.
 */
class OccapiRelationships {

  use StringTranslationTrait;

  /**
   * Shared TempStore manager.
   *
   * @var \Drupal\occapi_client\OccapiTempStoreInterface
   */
  protected $occapiTempStore;

  /**
   * Constructs an OccapiRelationships object.
   *
   * @param \Drupal\occapi_client\OccapiTempStoreInterface $occapi_tempstore
   *   Shared TempStore manager
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(
    OccapiTempStoreInterface $occapi_tempstore,
    TranslationInterface $string_translation
  ) {
    $this->occapiTempStore   = $occapi_tempstore;
    $this->stringTranslation = $string_translation;
  }

  /**
   * Add relationships to data based on the TempStore filter.
   *
   * @param string $data
   *   The JSON:API data.
   * @param string $temp_store_key
   *   The TempStore key.
   */
  public function addFromFilter(string &$data, string $temp_store_key): void {
    $temp_store_params = $this->occapiTempStore->paramsFromKey($temp_store_key);
    $resource_id_param = OccapiTempStoreInterface::PARAM_RESOURCE_ID;

    if (!empty($temp_store_params[$resource_id_param])) {
      $error = $this->tempStore->validateResourceTempstore($temp_store_key);
    } else {
      $error = $this->tempStore->validateCollectionTempstore($temp_store_key);
    }

    if (empty($error)) {
      $filter_type_param = OccapiTempStoreInterface::PARAM_FILTER_TYPE;
      $filter_type = $temp_store_params[$filter_type_param];
      $filter_id_param = OccapiTempStoreInterface::PARAM_FILTER_ID;
      $filter_id = $temp_store_params[$filter_id_param];

      if (empty($filter_type) && empty($filter_id)) {
        $error = $this->t('Filter unavailable.');
      }
    }

    if (empty($error)) {
      $decoded = \json_decode($data, TRUE);

      if (\array_key_exists(0, $decoded)) {
        foreach ($decoded as $i => $item) {
          $decoded[$i] = $this->add($item, $filter_type, $filter_id);
        }
      }
      else {
        $decoded = $this->add($item, $filter_type, $filter_id);
      }

      $data = \json_encode($decoded);
    }
  }

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
  public function add(array $item, string $type, string $id): array {
    if (!\array_key_exists('data', $item)) {
      return $item;
    }

    if (!\array_key_exists('relationships', $item['data'])) {
      $item['data']['relationships'] = [];
    }

    $exists = FALSE;

    foreach ($item['data']['relationships'] as $rel) {
      if ($rel['type'] === $type && $rel['id'] === $id) {
        $exists = TRUE;
      }

      if ($exists) { break; }
    }

    if (!$exists) {
      $item['data']['relationships'][] = [
        'type' => $type,
        'id' => $id,
      ];
    }

    return $item;
  }

}
