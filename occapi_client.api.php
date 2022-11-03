<?php

/**
 * @file
 * Hooks for the occapi_client module.
 */

/**
* Alter the OCCAPI data before saving it to temporary storage.
*
* @param string $data
*   Data being retrieved.
* @param array $context
*   Context containing unalterable $temp_store_key.
 */
function hook_occapi_data_get_alter(string &$data, array $context) {
  // Count the number of resources in the data set.
  $resource_types = ['programme', 'course'];

  foreach ($resource_types as $resource_type) {
    $type_string = '"type":"' . $resource_type . '"';

    $count = substr_count($data, $type_string);

    if ($count > 0) {
      $message = t('Retrieved @count %type resources.', [
        '@count' => $count,
        '%type' => $resource_type,
      ]);

      \Drupal::logger('my_module')->info($message);
    }
  }
}

/**
 * Alter the OCCAPI data once loaded from temporary storage.
 *
 * @param string $data
 *   Data being loaded.
 * @param array $context
 *   Context containing unalterable $temp_store_key.
 */
function hook_occapi_data_load_alter(string &$data, array $context) {
  // Fix a known typo while it gets fixed.
  $replacements = [
    'programmme' => 'programme',
  ];

  foreach ($replacements as $wrong => $right) {
    $data = str_replace($wrong, $right, $data, $count);
  }
}
