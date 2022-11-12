<?php

namespace Drupal\occapi_entities_bridge;

/**
 * Defines an interface for an OCCAPI remote data handler.
 */
interface OccapiRemoteDataInterface {

  const FIELD_REMOTE_ID = 'remote_id';
  const FIELD_REMOTE_URL = 'remote_url';
  const FIELD_META = 'meta';

  /**
   * Add new base fields to the OCCAPI entity types to store remote API data.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return array
   *   The entity fields.
   */
  public function attachBaseFields(EntityTypeInterface $entity_type): array;

  /**
   * Add new base fields to the OCCAPI entity types to store remote API data.
   *
   * @param array $entity_types
   *   An array of entity types.
   */
  public function addEntityForms(array &$entity_types): void;

  /**
   * Performs the actual form alter for the common fields.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The FormState object.
   */
  public function apiFieldsFormAlter(&$form, FormStateInterface $form_state): void;

  /**
   * Performs the actual form alter for the Course metadata field.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The FormState object.
   */
  public function apiMetadataFormAlter(&$form, FormStateInterface $form_state): void;

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
