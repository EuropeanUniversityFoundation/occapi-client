<?php

namespace Drupal\occapi_client;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\occapi_client\JsonDataProcessor as Json;

/**
 * Service for data formatting
 */
class DataFormatter {

  use StringTranslationTrait;

  /**
  * JSON data processing service.
  *
  * @var \Drupal\occapi_client\JsonDataProcessor
  */
  protected $jsonDataProcessor;

  /**
   * Constructs a new DataFormatter.
   *
   * @param \Drupal\occapi_client\JsonDataProcessor $json_data_processor
   *   JSON data fetching service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(
    JsonDataProcessor $json_data_processor,
    TranslationInterface $string_translation
  ) {
    $this->jsonDataProcessor = $json_data_processor;
    $this->stringTranslation = $string_translation;
  }

  /**
   * Format resource collection as HTML table.
   */
  public function collectionTable($collection) {
    $header = [
      Json::TYPE_KEY,
      Json::ID_KEY,
      Json::TITLE_KEY,
      Json::LINKS_KEY
    ];

    $rows = [];

    $data = $collection[Json::DATA_KEY];

    foreach ($data as $i => $resource) {
      $uri = $this->jsonDataProcessor->getLink($resource, Json::SELF_KEY);
      $options = ['attributes' => ['target' => '_blank']];

      $row = [
        $this->jsonDataProcessor->getType($resource),
        $this->jsonDataProcessor->getId($resource),
        $this->jsonDataProcessor->getTitle($resource),
        Link::fromTextAndUrl(Json::SELF_KEY, Url::fromUri($uri, $options))
      ];

      $rows[] = $row;
    }

    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];

    return render($build);
  }

  /**
   * Format single resource as HTML table.
   */
  public function resourceTable($resource) {
    $header = [
      Json::TYPE_KEY,
      Json::ID_KEY,
      Json::TITLE_KEY,
    ];

    $header_len = \count($header);

    foreach ($resource[Json::LINKS_KEY] as $key => $link) {
      $header_text = (\count($header) === $header_len) ? Json::LINKS_KEY : '';
      $header[] = $header_text;
    }

    $rows = [];

    $row = [
      $this->jsonDataProcessor->getType($resource),
      $this->jsonDataProcessor->getId($resource),
      $this->jsonDataProcessor->getTitle($resource),
    ];

    $options = ['attributes' => ['target' => '_blank']];
    foreach ($resource[Json::LINKS_KEY] as $key => $link) {
      $uri = $link[Json::HREF_KEY];
      $row[] = Link::fromTextAndUrl($key, Url::fromUri($uri, $options));
    }

    $rows[] = $row;

    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];

    return render($build);
  }

}
