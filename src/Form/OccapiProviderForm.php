<?php

namespace Drupal\occapi_client\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * OCCAPI provider form.
 *
 * @property \Drupal\occapi_client\OccapiProviderInterface $entity
 */
class OccapiProviderForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {

    $form = parent::form($form, $form_state);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#description' => $this->t('Label for the OCCAPI provider.'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => '\Drupal\occapi_client\Entity\OccapiProvider::load',
      ],
      '#disabled' => !$this->entity->isNew(),
    ];

    $form['api_params'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('API parameters'),
      '#tree' => FALSE,
    ];

    $form['api_params']['base_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Base URL'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->get('base_url'),
      '#description' => $this->t('Format: <em>https://domain.tld/occapi</em>'),
      '#required' => TRUE,
    ];

    $form['api_params']['hei_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Institution ID'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->get('hei_id'),
      '#description' => $this->t('Format: <em>domain.tld</em>'),
      '#required' => TRUE,
    ];

    $form['api_params']['ounit_filter'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Filter by Organizational Unit'),
      '#default_value' => $this->entity->get('ounit_filter'),
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $this->entity->status(),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->entity->get('description'),
      '#description' => $this->t('Description of the OCCAPI provider.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $result = parent::save($form, $form_state);
    $message_args = ['%label' => $this->entity->label()];
    $message = $result == SAVED_NEW
      ? $this->t('Created new OCCAPI provider %label.', $message_args)
      : $this->t('Updated OCCAPI provider %label.', $message_args);
    $this->messenger()->addStatus($message);
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

}
