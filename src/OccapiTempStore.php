<?php

namespace Drupal\occapi_client;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Service for managing shared TempStore keys.
 */
class OccapiTempStore implements OccapiTempStoreInterface {

  use StringTranslationTrait;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The constructor.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(
    MessengerInterface $messenger,
    TranslationInterface $string_translation
  ) {
    $this->messenger         = $messenger;
    $this->stringTranslation = $string_translation;
  }

  /**
   * Extract parameters from a TempStore key.
   *
   * @param string $temp_store_key
   *   The TempStore key.
   *
   * @return array
   *   The TempStore parameters.
   */
  public function paramsFromKey(string $temp_store_key): array {
    // Handle the Institution scenario first: ID main contain separator.
    $parts = \explode(self::TEMPSTORE_KEY_SEPARATOR, $temp_store_key, 3);

    if ($parts[1] === self::TYPE_HEI) {
      $temp_store_params = [
        self::PARAM_PROVIDER => $parts[0],
        self::PARAM_FILTER_TYPE => NULL,
        self::PARAM_FILTER_ID => NULL,
        self::PARAM_RESOURCE_TYPE => $parts[1],
        self::PARAM_RESOURCE_ID => $parts[2] ?? NULL,
        self::PARAM_EXTERNAL => NULL,
      ];

      return $temp_store_params;
    }

    // Handle the generic scenario, check for filters.
    $parts = \explode(self::TEMPSTORE_KEY_SEPARATOR, $temp_store_key);

    $is_external = ($parts[\count($parts) - 1] === self::PARAM_EXTERNAL);
    $is_filtered = (!$is_external && \count($parts) > 3);

    $temp_store_params = [
      self::PARAM_PROVIDER => $parts[0],
      self::PARAM_FILTER_TYPE => ($is_filtered) ? $parts[1] : NULL,
      self::PARAM_FILTER_ID => ($is_filtered) ? $parts[2] : NULL,
      self::PARAM_RESOURCE_TYPE => ($is_filtered) ? $parts[3] : $parts[1],
      self::PARAM_RESOURCE_ID => ($is_filtered)
        ? $parts[4] ?? NULL
        : $parts[2] ?? NULL,
      self::PARAM_EXTERNAL => ($is_external) ? self::PARAM_EXTERNAL : NULL,
    ];

    return $temp_store_params;
  }

  /**
   * Build a TempStore key from parameters.
   *
   * @param array $temp_store_params
   *   The TempStore parameters.
   *
   * @return string
   *   The TempStore key.
   */
  public function keyFromParams(array $temp_store_params): string {
    $parts = [$temp_store_params[self::PARAM_PROVIDER]];

    $has_filter_type = !empty($temp_store_params[self::PARAM_FILTER_TYPE]);
    $has_filter_id = !empty($temp_store_params[self::PARAM_FILTER_ID]);

    if ($has_filter_type && $has_filter_id) {
      $parts[] = $temp_store_params[self::PARAM_FILTER_TYPE];
      $parts[] = $temp_store_params[self::PARAM_FILTER_ID];
    }

    $parts[] = $temp_store_params[self::PARAM_RESOURCE_TYPE];

    if (!empty($temp_store_params[self::PARAM_RESOURCE_ID])) {
      $parts[] = $temp_store_params[self::PARAM_RESOURCE_ID];
    }

    $is_external = !empty($temp_store_params[self::PARAM_EXTERNAL]);

    if ($is_external && !$has_filter_type && !$has_filter_id) {
      $parts[] = $temp_store_params[self::PARAM_EXTERNAL];
    }

    $temp_store_key = \implode(self::TEMPSTORE_KEY_SEPARATOR, $parts);

    return $temp_store_key;
  }

  /**
   * Validate a TempStore key by parameters.
   *
   * @param string $temp_store_key
   *   The TempStore key.
   * @param boolean $single
   *   Whether the key refers to a single resource (defaults to FALSE).
   *
   * @return bool
   *   Returns TRUE is validation passes, otherwise FALSE.
   */
  public function validateTempstoreKey(string $temp_store_key, bool $single = FALSE): bool {
    $error = NULL;

    $temp_store_params = $this->paramsFromKey($temp_store_key);

    if (empty($temp_store_params[self::PARAM_PROVIDER])) {
      $error = $this->t('Empty parameter: %param', [
        '%param' => self::PARAM_PROVIDER
      ]);
    }

    if (empty($temp_store_params[self::PARAM_RESOURCE_TYPE])) {
      $error = $this->t('Empty parameter: %param', [
        '%param' => self::PARAM_RESOURCE_TYPE
      ]);
    }

    if ($single && empty($temp_store_params[self::PARAM_RESOURCE_ID])) {
      $error = $this->t('Missing resource ID for single resource.');
    }

    if (!$single && !empty($temp_store_params[self::PARAM_RESOURCE_ID])) {
      $error = $this->t('Unexpected resource ID for resource collection.');
    }

    $has_filter_type = (!empty($temp_store_params[self::PARAM_FILTER_TYPE]));
    $has_filter_id = (!empty($temp_store_params[self::PARAM_FILTER_ID]));

    if (!$has_filter_type && $has_filter_id) {
      $error = $this->t('Filter type provided, missing filter ID.');
    }

    if ($has_filter_type && !$has_filter_id) {
      $error = $this->t('Filter ID provided, missing filter type.');
    }

    $is_external = !empty($temp_store_params[self::PARAM_EXTERNAL]);

    if ($is_external && ($has_filter_type || $has_filter_id)) {
      $error = $this->t('External keys cannot be filtered.');
    }

    if (!empty($error ?? NULL)) {
      $this->messenger->addError($error);

      return FALSE;
    }

    // No errors found.
    return TRUE;
  }

  /**
   * Validate a collection TempStore key.
   *
   * @param string $temp_store_key
   *   TempStore key to validate.
   * @param string $resource_type|null
   *   OCCAPI entity type key to validate.
   * @param string $filter_type|null
   *   OCCAPI entity type key used as filter.
   *
   * @return bool
   *   Returns TRUE is validation passes, otherwise FALSE.
   */
  public function validateCollectionTempstore(string $temp_store_key, ?string $resource_type = NULL, ?string $filter_type = NULL): bool {
    $error = NULL;

    $validated = $this->validateTempstoreKey($temp_store_key);

    if (!$validated) { return FALSE; }

    $temp_store_params = $this->paramsFromKey($temp_store_key);

    // Validate the provider.

    // Validate the resource type.
    if (!empty($resource_type)) {
      $allowed_types = [
        self::TYPE_PROGRAMME,
        self::TYPE_COURSE,
      ];

      $validated = $this->validateResourceType($resource_type, $allowed_types);

      if (!$validated) { return FALSE; }

      $param_resource_type = $temp_store_params[self::PARAM_RESOURCE_TYPE];

      if ($resource_type !== $param_resource_type) {
        $error = $this->t('Data contains %param instead of %type.', [
          '%param' => $param_resource_type,
          '%type' => $resource_type,
        ]);
      }
    }

    // Validate the filter type.
    if (empty($error ?? NULL) && !empty($filter_type)) {
      $allowed_types = [
        self::TYPE_OUNIT,
        self::TYPE_PROGRAMME
      ];

      $validated = $this->validateResourceType($filter_type, $allowed_types);

      if (!$validated) { return FALSE; }

      $param_filter_type = $temp_store_params[self::PARAM_FILTER_TYPE];

      if (!empty($filter_type) && ($filter_type !== $param_filter_type)) {
        $error = $this->t('Data is filtered by %param instead of %type.', [
          '%param' => $param_filter_type,
          '%type' => $filter_type,
        ]);
      }
    }

    if (!empty($error ?? NULL)) {
      $this->messenger->addError($error);

      return FALSE;
    }

    // No errors found.
    return TRUE;
  }

  /**
   * Validate a resource TempStore key.
   *
   * @param string $temp_store_key
   *   TempStore key to validate.
   * @param string $resource_type|null
   *   OCCAPI entity type key to validate.
   *
   * @return bool
   *   Returns TRUE is validation passes, otherwise FALSE.
   */
  public function validateResourceTempstore(string $temp_store_key, string $resource_type): bool {
    $error = NULL;

    $validated = $this->validateTempstoreKey($temp_store_key, TRUE);

    if (!$validated) { return FALSE; }

    $temp_store_params = $this->paramsFromKey($temp_store_key);

    // Validate the resource type.
    if (!empty($resource_type)) {
      $allowed_types = [
        self::TYPE_PROGRAMME,
        self::TYPE_COURSE,
      ];

      $validated = $this->validateResourceType($resource_type, $allowed_types);

      if (!$validated) { return FALSE; }

      $param_resource_type = $temp_store_params[self::PARAM_RESOURCE_TYPE];

      if ($resource_type !== $param_resource_type) {
        $error = $this->t('Data contains %param instead of %type.', [
          '%param' => $param_resource_type,
          '%type' => $resource_type,
        ]);
      }
    }

    if (!empty($error ?? NULL)) {
      $this->messenger->addError($error);

      return FALSE;
    }

    // No errors found.
    return TRUE;
  }

  /**
   * Validate a TempStore key resource type.
   *
   * @param string $resource_type
   *   Resource type to validate.
   * @param array $allowed_types
   *   Allowed resource types.
   *
   * @return bool
   *   Returns TRUE is validation passes, otherwise FALSE.
   */
  public function validateResourceType(string $resource_type, array $allowed_types): bool {
    $error = NULL;

    if (!\in_array($resource_type, $allowed_types)) {
      $error = $this->t('Resource type must be one of %allowed, %type given.', [
        '%allowed' => \implode(', ', $allowed_types),
        '%type' => $resource_type,
      ]);
    }

    if (!empty($error ?? NULL)) {
      $this->messenger->addError($error);

      return FALSE;
    }

    // No errors found.
    return TRUE;
  }

}
