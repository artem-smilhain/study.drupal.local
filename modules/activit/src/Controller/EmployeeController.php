<?php
namespace Drupal\activit\Controller;
use Drupal\activit\Mail\MailHandler; //письмо
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use JetBrains\PhpStorm\NoReturn;
//use \Drupal\activit\Mail\MailHandler;

class EmployeeController extends ControllerBase{
  /*protected $mailHandler;
  public function __construct(MailHandler $mail_handler) {
    $this->mailHandler = $mail_handler;
  }*/ //mailHandler test
  public function createEmployee(){
    $form = \Drupal::formBuilder()->getForm('Drupal\activit\Form\EmployeeForm');
    $renderForm = \Drupal::service('renderer')->render($form);
    /*
    \Drupal::service('plugin.manager.mail')->mail($module, $key, $to, $langcode);
    \Drupal::service('plugin.manager.mail')->mail(
      'activit',
      'notice',
      'a.smilgain@gmial.com',
      'en',
      [
        'context' => [
        'subject' => 'Some subject',
        'message' => 'Some message',
      ]
    ]);

    $subject = new TranslatableMarkup('My first mail!');
    $body = [
      '#markup' => 'Hello World!',
    ];
    $mail_handler->sendMail('a.smilgain@gmail.com', $subject, $body);
    */ //тестовый вариант отправки email
    return[
      '#theme' => 'employee',
      '#items' => $form,
      '#title' => 'Employee Form Test'
    ];
  }
  //get employees data from DB
  public function getEmployee(){
    $query = \Drupal::database();
    $limit = 3;
    $result = $query->select('employees', 'e')
      ->fields('e', ['id', 'name', 'gender', 'city', 'about'])
      //->groupBy('nid')
      //->sort('created', 'DESC')
      //->range(0, 10)
      ->extend('\Drupal\Core\Database\Query\PagerSelectExtender')->limit($limit)
      ->execute()
      ->fetchAll(\PDO::FETCH_OBJ);

    //$serial_no = 1;
    //правильное отображение serial-number
    $params = \Drupal::request()->query->all();
    if (empty($params['page']) || $params['page'] == 0){
      $serial_no = 1;
    }
    else if($params['page'] == 1){
      $serial_no = $params['page'] + $limit;
    }
    else{
      $serial_no = $params['page']*$limit + 1;
    }

    //table header
    $header = array('#', 'ID', 'NAME', 'GENDER', 'CITY', 'ABOUT', 'EDIT', 'DELETE');
    //table data
    $data = [];
    foreach ($result as $row) {
      $data[] = [
        'serial_no' => '1',
        'id' => $row->id,
        'name' => $row->name,
        'gender' => $row->gender,
        'city' => $row->city,
        'about' => $row->about,
        'edit'=> $this->t("<a href='/edit-employee/$row->id'>Edit</a>"),
        'delete' => $this->t("<a href='/delete-employee/$row->id'>Delete</a>")
      ];
      $serial_no++;
    }
    //
    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $data
    ];
    $build['pager'] = [
      '#type' => 'pager'
    ];
    //
    return [
      $build,
      '#title' => 'Employee List'
    ];
  }
  //delete row in table
  public function deleteEmployee($id){
    $query = \Drupal::database();
    $query->delete('employees')
      ->condition('id', $id, '=')
      ->execute();
    //redirect to employees page
    $response = new \Symfony\Component\HttpFoundation\RedirectResponse('/get-employee');
    $response->send();
    //send email //MAIL DOES NOT WORK !!!!!!!!!!!!!!!!!!!
    $newMail = \Drupal::service('plugin.manager.mail');
    $params['email'] = 'test param';
    $newMail->mail('employee_mail', 'registerMail', 'artem.smilhain@student.tuke.sk', 'en', $params, $reply = NULL, $send = TRUE);
    //
    $this->messenger()->addMessage('Data deleted! Mail has been sent.', 'info', TRUE);
    //$this->messenger()->addMessage('Data deleted!', 'info', TRUE);
    return [];  //выдает ошибку, если не делать return
  }
}
