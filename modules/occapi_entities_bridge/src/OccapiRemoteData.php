<?php

namespace Drupal\occapi_entities_bridge;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\occapi_client\JsonDataSchemaInterface;
use Drupal\occapi_client\OccapiTempStoreInterface;

/**
 * Handles OCCAPI remote data.
 */
class OccapiRemoteData implements OccapiRemoteDataInterface {

  use StringTranslationTrait;

  const ENTITY_PROGRAMME = OccapiEntityManagerInterface::ENTITY_PROGRAMME;
  const ENTITY_COURSE = OccapiEntityManagerInterface::ENTITY_COURSE;

  const TYPE_COURSE = OccapiTempStoreInterface::TYPE_COURSE;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

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
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   Account proxy for the currently logged-in user.
   * @param \Drupal\occapi_client\OccapiTempStoreInterface $occapi_tempstore
   *   The shared TempStore key manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(
    AccountProxyInterface $current_user,
    OccapiTempStoreInterface $occapi_tempstore,
    MessengerInterface $messenger,
    TranslationInterface $string_translation
  ) {
    $this->currentUser = $current_user;
    $this->occapiTempStore = $occapi_tempstore;
    $this->messenger = $messenger;
    $this->stringTranslation  = $string_translation;
  }

  /**
   * Add new base fields to the OCCAPI entity types to store remote API data.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return array
   *   The entity fields.
   */
  public function attachBaseFields(EntityTypeInterface $entity_type): array {
    $occapi_entity_types = [
      self::ENTITY_PROGRAMME,
      self::ENTITY_COURSE
    ];

    if (\in_array($entity_type->id(), $occapi_entity_types)) {
      $fields[self::FIELD_REMOTE_ID] = BaseFieldDefinition::create('string')
      ->setLabel($this->t('Remote ID'))
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 101,
      ])
      ->setDisplayConfigurable('form', FALSE);

      $fields[self::FIELD_REMOTE_URL] = BaseFieldDefinition::create('string')
      ->setLabel($this->t('Remote URL'))
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 102,
      ])
      ->setDisplayConfigurable('form', FALSE);

      if ($entity_type->id() === self::ENTITY_COURSE) {
        $fields[self::FIELD_META] = BaseFieldDefinition::create('json')
        ->setLabel($this->t('JSON metadata'))
        ->setDisplayOptions('form', [
          'type' => 'json_textarea',
          'weight' => 103,
        ])
        ->setDisplayConfigurable('form', FALSE);
      }
    }

    return $fields;
  }

  /**
   * Add new base fields to the OCCAPI entity types to store remote API data.
   *
   * @param array $entity_types
   *   An array of entity types.
   */
  public function addEntityForms(array &$entity_types): void {
    /** @var $entity_types \Drupal\Core\Entity\EntityTypeInterface[] */
    $entity_types[self::ENTITY_PROGRAMME]
      ->setFormClass('api', 'Drupal\\occapi_entities_bridge\\Form\\ProgrammeApiForm')
      ->setLinkTemplate('api-form', '/occapi/programme/{programme}/api');

    /** @var $entity_types \Drupal\Core\Entity\EntityTypeInterface[] */
    $entity_types[self::ENTITY_COURSE]
      ->setFormClass('api', 'Drupal\\occapi_entities_bridge\\Form\\CourseApiForm')
      ->setLinkTemplate('api-form', '/occapi/course/{course}/api');
  }

  /**
   * Performs the actual form alter for the common fields.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The FormState object.
   */
  public function apiFieldsFormAlter(&$form, FormStateInterface $form_state): void {
    $remote_id = $form[self::FIELD_REMOTE_ID];
    $remote_url = $form[self::FIELD_REMOTE_URL];

    $form['api_fields'] = [
      '#type' => 'details',
      '#title' => $this->t('API fields'),
      '#tree' => FALSE,
      '#weight' => 100
    ];

    if (!$this->currentUser->hasPermission('administer occapi fields')) {
      $remote_id['widget'][0]['value']['#attributes']['readonly']  = 'readonly';
      $remote_url['widget'][0]['value']['#attributes']['readonly'] = 'readonly';
    }

    $form['api_fields'][self::FIELD_REMOTE_ID] = $remote_id;
    $form['api_fields'][self::FIELD_REMOTE_URL] = $remote_url;

    unset($form[self::FIELD_REMOTE_ID]);
    unset($form[self::FIELD_REMOTE_URL]);
  }

  /**
   * Performs the actual form alter for the Course metadata field.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The FormState object.
   */
  public function apiMetadataFormAlter(&$form, FormStateInterface $form_state): void {
    $metadata = $form[self::FIELD_META];

    if (!$this->currentUser->hasPermission('administer occapi fields')) {
      $metadata['widget'][0]['value']['#attributes']['readonly']  = 'readonly';
    }

    $form['api_fields'][self::FIELD_META] = $metadata;

    unset($form[self::FIELD_META]);
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

    if (!empty($remote_id)) {
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

}
