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

  const NOT_AVAILABLE   = '<em>n/a</em>';

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
   * Gather resource collection titles.
   */
  public function collectionTitles($collection) {
    $titles = [];

    foreach ($collection as $i => $resource) {
      $id = $resource[Json::ID_KEY];
      $title = $this->jsonDataProcessor
        ->extractTitle($resource[Json::ATTR_KEY]);
      $title = ($title === self::NOT_AVAILABLE) ? $id : $title;

      $titles[$id] = $title;
    }

    return $titles;
  }

  /**
   * Gather resource collection links.
   */
  public function collectionLinks($collection) {
    $links = [];

    foreach ($collection as $i => $resource) {
      $id = $resource[Json::ID_KEY];
      $uri = $resource[Json::LINKS_KEY][Json::SELF_KEY][Json::HREF_KEY];

      $links[$id] = $uri;
    }

    return $links;
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

    foreach ($collection as $i => $resource) {
      $uri = $resource[Json::LINKS_KEY][Json::SELF_KEY][Json::HREF_KEY];
      $options = ['attributes' => ['target' => '_blank']];

      $row = [
        $resource[Json::TYPE_KEY],
        $resource[Json::ID_KEY],
        $this->jsonDataProcessor
          ->extractTitle($resource[Json::ATTR_KEY]),
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
      $resource[Json::DATA_KEY][Json::TYPE_KEY],
      $resource[Json::DATA_KEY][Json::ID_KEY],
      $this->jsonDataProcessor
        ->extractTitle($resource[Json::DATA_KEY][Json::ATTR_KEY]),
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

  /**
   * Extract title from attributes.
   */
  private function extractTitle($attributes) {
    $title = self::NOT_AVAILABLE;

    if (\array_key_exists(Json::TITLE_KEY, $attributes)) {
      // Enforce an array of title objects.
      if (! \array_key_exists(0, $attributes[Json::TITLE_KEY])) {
        $title_array = [$attributes[Json::TITLE_KEY]];
      } else {
        $title_array = $attributes[Json::TITLE_KEY];
      }

      // Sort the title objects by prefered lang value.
      $title_primary = [];
      $title_fallback = [];
      $title_ordered = [];

      foreach ($title_array as $i => $arr) {
        if (
          \array_key_exists(Json::LANG_KEY, $arr) &&
          $arr[Json::LANG_KEY] === Json::LANG_PREF
        ) {
          \array_push($title_primary, $arr);
        } else {
          \array_push($title_fallback, $arr);
        }
        $title_ordered = \array_merge($title_primary, $title_fallback);
      }

      if (count($title_ordered) > 0) {
        $title = $title_ordered[0][Json::VALUE_KEY];
      }
    }

    return $title;
  }

}
