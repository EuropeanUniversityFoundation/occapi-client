<?php

namespace Drupal\occapi_entities_bridge;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\occapi_client\JsonDataFetcherInterface;
use Drupal\occapi_client\OccapiTempStoreInterface;

/**
 * Service description.
 */
class OccapiRemoteData implements OccapiRemoteDataInterface {

  /**
   * The JSON data fetcher.
   *
   * @var \Drupal\occapi_client\JsonDataFetcherInterface
   */
  protected $jsonDataFetcher;

  /**
   * The shared TempStore key manager.
   *
   * @var \Drupal\occapi_client\OccapiTempStoreInterface
   */
  protected $occapiTempStore;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The constructor.
   *
   * @param \Drupal\occapi_client\JsonDataFetcherInterface $json_data_fetcher
   *   The JSON data fetcher.
   * @param \Drupal\occapi_client\OccapiTempStoreInterface $occapi_tempstore
   *   The shared TempStore key manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(
    JsonDataFetcherInterface $json_data_fetcher,
    OccapiTempStoreInterface $occapi_tempstore,
    MessengerInterface $messenger
  ) {
    $this->jsonDataFetcher = $json_data_fetcher;
    $this->occapiTempStore = $occapi_tempstore;
    $this->messenger = $messenger;
  }

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
  public function formatRemoteId(string $remote_id, string $remote_url): string {
    $markup = '';

    if (! empty($remote_id)) {
      $markup .= '<p><strong>Remote ID:</strong> ';

      if (empty($remote_url)) {
        $markup .= '<code>' . $remote_id . '</code>';
      }
      else {
        $url = Url::fromUri($remote_url, [
          'attributes' => ['target' => '_blank']
        ]);

        $link = Link::fromTextAndUrl($remote_id, $url)->toString();

        $markup .= '<code>' . $link . '</code>';
      }

      $markup .= '</p><hr />';
    }

    return $markup;
  }

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
  public function loadExternalCourse(string $temp_store_key, string $endpoint): array {
    $error = $this->occapiTempStore
      ->validateResourceTempstore($temp_store_key, self::TYPE_COURSE);

    if (empty($error)) {
      $response = $this->jsonDataFetcher->load($temp_store_key, $endpoint);

      return \json_decode($response, TRUE);
    }

    $this->messenger->addError($error);
    return [];
  }

}
