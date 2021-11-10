<?php

namespace Drupal\occapi_client;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Service for data formatting
 */
class DataFormatter {

  use StringTranslationTrait;

  const NOT_AVAILABLE   = '<em>n/a</em>';

  // JSON:API primary keys.
  const DATA_KEY        = 'data';
  const REL_KEY         = 'relationships';
  const INC_KEY         = 'included';
  const LINKS_KEY       = 'links';

  // JSON:API data keys.
  const TYPE_KEY        = 'type';
  const ID_KEY          = 'id';
  const ATTR_KEY        = 'attributes';

  // OCCAPI title field.
  const TITLE_KEY       = 'title';
  const VALUE_KEY       = 'string';
  const LANG_KEY        = 'lang';
  const LANG_PREF       = 'en';

  // JSON:API link keys.
  const SELF_KEY        = 'self';
  const HREF_KEY        = 'href';

  /**
   * Constructs a new JsonDataProcessor.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(
    TranslationInterface $string_translation
  ) {
  }

  /**
   * Gather resource collection titles.
   */
  public function collectionTitles($collection) {
    $titles = [];

    foreach ($collection as $i => $resource) {
      $id = $resource[self::ID_KEY];
      $title = $this->extractTitle($resource[self::ATTR_KEY]);
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
      $id = $resource[self::ID_KEY];
      $uri = $resource[self::LINKS_KEY][self::SELF_KEY][self::HREF_KEY];

      $links[$id] = $uri;
    }

    return $links;
  }

  /**
   * Format resource collection as HTML table.
   */
  public function collectionTable($collection) {
    $header = [
      self::TYPE_KEY,
      self::ID_KEY,
      self::TITLE_KEY,
      self::LINKS_KEY
    ];

    $rows = [];

    foreach ($collection as $i => $resource) {
      $uri = $resource[self::LINKS_KEY][self::SELF_KEY][self::HREF_KEY];
      $options = ['attributes' => ['target' => '_blank']];

      $row = [
        $resource[self::TYPE_KEY],
        $resource[self::ID_KEY],
        $this->extractTitle($resource[self::ATTR_KEY]),
        Link::fromTextAndUrl(self::SELF_KEY, Url::fromUri($uri, $options))
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
      self::TYPE_KEY,
      self::ID_KEY,
      self::TITLE_KEY,
    ];

    $header_len = \count($header);

    foreach ($resource[self::LINKS_KEY] as $key => $link) {
      $header_text = (\count($header) === $header_len) ? self::LINKS_KEY : '';
      $header[] = $header_text;
    }

    $rows = [];

    $row = [
      $resource[self::DATA_KEY][self::TYPE_KEY],
      $resource[self::DATA_KEY][self::ID_KEY],
      $this->extractTitle($resource[self::DATA_KEY][self::ATTR_KEY]),
    ];

    $options = ['attributes' => ['target' => '_blank']];
    foreach ($resource[self::LINKS_KEY] as $key => $link) {
      $uri = $link[self::HREF_KEY];
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

    if (\array_key_exists(self::TITLE_KEY, $attributes)) {
      // Enforce an array of title objects.
      if (! \array_key_exists(0, $attributes[self::TITLE_KEY])) {
        $title_array = [$attributes[self::TITLE_KEY]];
      } else {
        $title_array = $attributes[self::TITLE_KEY];
      }

      // Sort the title objects by prefered lang value.
      $title_primary = [];
      $title_fallback = [];
      $title_ordered = [];

      foreach ($title_array as $i => $arr) {
        if (
          \array_key_exists(self::LANG_KEY, $arr) &&
          $arr[self::LANG_KEY] === self::LANG_PREF
        ) {
          \array_push($title_primary, $arr);
        } else {
          \array_push($title_fallback, $arr);
        }
        $title_ordered = \array_merge($title_primary, $title_fallback);
      }

      if (count($title_ordered) > 0) {
        $title = $title_ordered[0][self::VALUE_KEY];
      }
    }

    return $title;
  }

}
