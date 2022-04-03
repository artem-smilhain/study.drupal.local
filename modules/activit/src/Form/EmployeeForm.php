<?php
namespace Drupal\activit\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use JetBrains\PhpStorm\NoReturn;
use Drupal\Core\Database\Database;

class EmployeeForm extends FormBase{
  /**
   *{@inheritdoc}
   */
  public function getFormId()
  {
    return 'drupal_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $genderOptions = [
      '0' => 'Select Gender',
      'Male' => 'Male',
      'Female' => 'Female',
      'Other' => 'Other'
    ];

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Your name:'),
      '#description' => 'Please enter Your name',
      '#required' => TRUE,
      '#default_value' => '',
      '#attributes'=>[
        'placeholder' => 'Name:'
      ]
    ];
    $form['city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Your city:'),
      '#description' => 'Please enter Your city',
      '#required' => TRUE,
      '#default_value' => '',
      // мой autocomplete
      '#autocomplete_route_name' => 'activit.autocomplete',
      '#autocomplete_route_parameters' => [
        'field_name' => 'city',
        'count' => 10
      ],
      //
      '#attributes'=>[
        'placeholder' => 'City:'
      ]
    ];
    $form['gender']=[
      '#type' => 'select',
      '#title' => 'Gender',
      '#required' => TRUE,
      '#options' => $genderOptions
    ];
    $form['about']=[
      '#type' => 'textarea',
      '#title' => 'About Employee',
      '#required' => TRUE,
      '#default_value' => '',
      '#attributes'=>[
        'placeholder' => 'About:'
      ]
    ];
    $form['save']=[
      '#type' => 'submit',
      '#title' => 'Save Employee',
      '#value' => $this->t('Save data'),
      '#button_type' => 'primary',
      '#attributes'=>[
        'name' => 'save'
      ]
    ];
    return $form;
  }
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    //name validation
    if (trim($form_state->getValue('name')) == ''){
      $form_state->setErrorByName(
        'name',
        $this->t('Name field is required')
      );
    }
    //city employee validation
    if (trim($form_state->getValue('city')) == ''){
      $form_state->setErrorByName(
        'about',
        $this->t('City field is required')
      );
    }
    //gender validation
    if (trim($form_state->getValue('gender')) == '0'){
      $form_state->setErrorByName(
        'gender',
        $this->t('Gender field is required')
      );
    }
    //about employee validation
    if (trim($form_state->getValue('about')) == ''){
      $form_state->setErrorByName(
        'about',
        $this->t('About Employee field is required')
      );
    }
  }
  #[NoReturn] public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $postData = $form_state->getValues();
    //то, что не будем заливать в бд
    unset(
      $postData['save'],
      $postData['form_build_id'],
      $postData['form_id'],
      $postData['form_token'],
      $postData['op']
    );
    $query = \Drupal::database();
    $query->insert('employees')->fields($postData)->execute();
    $this->messenger()->addMessage('Date saved!', 'status', TRUE);

    //$this->messenger()->addMessage('Date saved!', 'error', TRUE);
    //$this->messenger()->addMessage('Date saved!', 'info', TRUE);
    //$this->messenger()->addMessage('Date saved!', 'warning', TRUE);

    /*echo '<pre>';
    print_r($postData);
    echo '</pre>';
    exit;*/
  }
}
