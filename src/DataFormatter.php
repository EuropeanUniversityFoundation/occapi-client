<?php

namespace Drupal\occapi_client;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\occapi_client\JsonDataProcessor as Json;
use Drupal\occapi_client\OccapiFieldManager as Fields;

/**
 * Service for formatting data.
 */
class DataFormatter {

  use StringTranslationTrait;

  /**
  * The JSON data processor.
  *
  * @var \Drupal\occapi_client\JsonDataProcessor
  */
  protected $jsonDataProcessor;

  /**
  * The OCCAPI field manager.
  *
  * @var \Drupal\occapi_client\OccapiFieldManager
  */
  protected $fieldManager;

  /**
   * The constructor.
   *
   * @param \Drupal\occapi_client\JsonDataProcessor $json_data_processor
   *   The JSON data processor.
   * @param \Drupal\occapi_client\OccapiFieldManager $field_manager
   *   The OCCAPI field manager.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(
    JsonDataProcessor $json_data_processor,
    OccapiFieldManager $field_manager,
    TranslationInterface $string_translation
  ) {
    $this->jsonDataProcessor  = $json_data_processor;
    $this->fieldManager       = $field_manager;
    $this->stringTranslation  = $string_translation;
  }

  /**
   * Format resource collection as HTML table.
   *
   * @param array $collection
   *   An array containing a JSON:API resource collection.
   *
   * @return string
   *   Rendered table markup.
   */
  public function collectionTable(array $collection): string {
    $header = [
      Json::TYPE_KEY,
      Json::ID_KEY,
      Json::TITLE_KEY,
      Json::LINKS_KEY
    ];

    $rows = [];

    $data = $collection[Json::DATA_KEY] ?? [];

    if (!empty($data)) {
      foreach ($data as $i => $resource) {
        $uri = $this->jsonDataProcessor
          ->getResourceLinkByType($resource, Json::SELF_KEY);

        $options = ['attributes' => ['target' => '_blank']];

        $row = [
          $this->jsonDataProcessor->getResourceType($resource),
          $this->jsonDataProcessor->getResourceId($resource),
          $this->jsonDataProcessor->getResourceTitle($resource),
          Link::fromTextAndUrl(Json::SELF_KEY, Url::fromUri($uri, $options))
        ];

        $rows[] = $row;
      }
    }

    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No data to display.'),
    ];

    if (array_key_exists('errors', $collection)) {
      $build['table']['#empty'] = $this->t('An error occurred.');
    }

    return render($build);
  }

  /**
   * Format single resource as HTML table.
   *
   * @param array $resource
   *   An array containing a JSON:API resource collection.
   *
   * @return string
   *   Rendered table markup.
   */
  public function resourceTable(array $resource): string {
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
      $this->jsonDataProcessor->getResourceType($resource),
      $this->jsonDataProcessor->getResourceId($resource),
      $this->jsonDataProcessor->getResourceTitle($resource),
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
   * Format single Programme resource as HTML table.
   *
   * @param array $resource
   *   An array containing a JSON:API resource collection.
   *
   * @return string
   *   Rendered table markup.
   */
  public function programmeResourceTable(array $resource): string {
    $programme_fields = OccapiFieldManager::getProgrammeFields();

    foreach ($programme_fields as $key => $value) {
      if (
        \is_array($value) && (
          \array_key_exists(Json::MLSTR_KEY, $value) ||
          \array_key_exists(Json::URI_KEY, $value)
        )
      ) {
        // Exclude long text and link fields.
        unset($programme_fields[$key]);
      }
    }

    $header = \array_keys($programme_fields);

    $header_len = \count($header);

    foreach ($resource[Json::LINKS_KEY] as $key => $link) {
      $header_text = (\count($header) === $header_len) ? Json::LINKS_KEY : '';
      $header[] = $header_text;
    }

    $rows = [];

    foreach ($programme_fields as $key => $value) {
      if ($key === Json::TITLE_KEY) {
        $title = $this->jsonDataProcessor->getResourceTitle($resource);
        $row[] = $title;
      } else {
        $attribute = $this->jsonDataProcessor
          ->getResourceAttribute($resource, $key);

        $row[] = $attribute[$key];
      }
    }

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
   * Format Course resource collection as HTML table.
   *
   * @param array $collection
   *   An array containing a JSON:API resource collection.
   *
   * @return string
   *   Rendered table markup.
   */
  public function courseCollectionTable(array $collection): string {
    $course_fields = OccapiFieldManager::getCourseFields();

    foreach ($course_fields as $key => $value) {
      if (
        \is_array($value) && (
          \array_key_exists(Json::MLSTR_KEY, $value) ||
          \array_key_exists(Json::URI_KEY, $value)
        )
      ) {
        // Exclude long text and link fields.
        unset($course_fields[$key]);
      }
    }

    $header = \array_keys($course_fields);

    $header[] = Json::LINKS_KEY;

    $rows = [];

    $data = $collection[Json::DATA_KEY];

    foreach ($data as $i => $resource) {
      $row = [];

      foreach ($course_fields as $key => $value) {
        if ($key === Json::TITLE_KEY) {
          $title = $this->jsonDataProcessor->getResourceTitle($resource);
          $row[] = $title;
        } else {
          $attribute = $this->jsonDataProcessor
            ->getResourceAttribute($resource, $key);

          $row[] = $attribute[$key];
        }
      }

      $uri = $this->jsonDataProcessor
        ->getResourceLinkByType($resource, Json::SELF_KEY);

      $options = ['attributes' => ['target' => '_blank']];

      $row[] = Link::fromTextAndUrl(Json::SELF_KEY, Url::fromUri($uri, $options));

      $rows[] = $row;
    }

    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];

    return render($build);
  }

}
