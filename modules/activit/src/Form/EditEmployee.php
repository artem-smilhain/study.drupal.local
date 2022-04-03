<?php
namespace Drupal\activit\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use JetBrains\PhpStorm\NoReturn;
use Drupal\Core\Database\Database;

class EditEmployee extends FormBase{
  /**
   *{@inheritdoc}
   */
  public function getFormId()
  {
    return 'drupal_form_edit';
  }

  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $id = \Drupal::routeMatch()->getParameter('id');
    $query = \Drupal::database();
    $data = $query->select('employees', 'e')
      ->fields('e', ['id', 'name', 'gender', 'city', 'about'])
      ->condition('e.id', $id, "=")
      ->execute()
      ->fetchAll(\PDO::FETCH_OBJ);
    //dd($data);

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
      '#default_value' => $data[0]->name,
      '#attributes'=>[
        'placeholder' => 'Name:'
      ]
    ];
    $form['gender']=[
      '#type' => 'select',
      '#title' => 'Gender',
      '#required' => TRUE,
      '#default_value' => $data[0]->gender,
      '#options' => $genderOptions
    ];
    $form['city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Your city:'),
      '#description' => 'Please enter Your city',
      '#required' => TRUE,
      '#default_value' => $data[0]->city,
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
    $form['about']=[
      '#type' => 'textarea',
      '#title' => 'About Employee',
      '#required' => TRUE,
      '#default_value' => $data[0]->about,
      '#attributes'=>[
        'placeholder' => 'About:'
      ]
    ];
    $form['update']=[
      '#type' => 'submit',
      '#value' => 'Update',
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
    $id = \Drupal::routeMatch()->getParameter('id');

    $postData = $form_state->getValues();
    //то, что не будем заливать в бд
    unset(
      $postData['update'],
      $postData['form_build_id'],
      $postData['form_id'],
      $postData['form_token'],
      $postData['op']
    );
    $query = \Drupal::database();
    $query->update('employees')
      ->fields($postData)
      ->condition('id', $id, '=')
      ->execute();

    $response = new \Symfony\Component\HttpFoundation\RedirectResponse('/get-employee');
    $response->send();

    $this->messenger()->addMessage('Data updated!', 'status', TRUE);

    //$this->messenger()->addMessage('Date saved!', 'error', TRUE);
    //$this->messenger()->addMessage('Date saved!', 'info', TRUE);
    //$this->messenger()->addMessage('Date saved!', 'warning', TRUE);

    /*echo '<pre>';
    print_r($postData);
    echo '</pre>';
    exit;*/
  }
}
