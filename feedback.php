<?php
/*
 * Copyright 2012 Madhu GB <madhuvana@gmail.com>,
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * 
 */
  
/*   
 * Core Settings
 */ 
define("ASANA_API_VERSION",  "1.0");
define("ABI_BASE_URL",       "https://app.asana.com/api/".ASANA_API_VERSION."/");
define("API_TASKS_URL",      ABI_BASE_URL."tasks");
define("API_USERS_URL",      ABI_BASE_URL."users");
define("API_PROJECTS_URL",   ABI_BASE_URL."projects");
define("API_WORKSPACES_URL", ABI_BASE_URL."workspaces");
define("API_STORIES_URL",    ABI_BASE_URL."stories");
define("API_TAGS_URL",       ABI_BASE_URL."tags");
define("POST_REQUEST",       1);
define("PUT_REQUEST",        2);
define("GET_REQUEST",        3);

/*
 *  Asana Class
 */ 
class Asana 
{
  private $timeout = 15;
  private $debug   = false;
  private $responseCode;
  private $response;
  private $api_key;
  private $workspaceId;  
  private $projectId;
  
  public function __construct($settings) 
  {    
    $this->api_key = $settings['api_key'];      
    if(isset($settings['workspace']))
      $this->workspaceId = $settings['workspace'];
      
    if(isset($settings['projectid']))
      $this->projectId = $settings['projectid'];
  }
  
  
  public function encode($data)
  {
    return json_encode($data);
  }
  
  public function decode($data)
  {
    return json_decode($data, true);
  }
  
  /*
   *  Main Request function which interacts with asana server
   */ 
  private function request($url, $data = null, $method = GET_REQUEST)
  {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->timeout);
    curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);
    curl_setopt($curl, CURLOPT_FAILONERROR, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);    
    curl_setopt($curl, CURLOPT_USERPWD, $this->api_key);
    curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));    
    if ($this->debug)
    {
      curl_setopt($curl, CURLOPT_HEADER, true);
      curl_setopt($curl, CURLOPT_VERBOSE, true);
    }
    
    if ($method == POST_REQUEST)
    {
      curl_setopt($curl, CURLOPT_POST, true);      
    } 
    else if ($method == PUT_REQUEST)
    {
      curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT"); 
    }
    
    if (!is_null($data) && ($method == POST_REQUEST || $method == PUT_REQUEST))
    {
      curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    }

    try 
    {
      $return = curl_exec($curl);
      $this->responseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

      if($this->debug)
      {
        echo "<pre>"; 
        print_r(curl_getinfo($curl)); 
        echo "</pre>";
      }
    } 
    catch(Exception $ex)
    {
      if($this->debug)
      {
        echo "<br>cURL error num : ".curl_errno($curl);
        echo "<br>cURL error desc: ".curl_error($curl);
      }      
      $return = null;
    }
    curl_close($curl);
    return array("data" => $return, "response" => $this->responseCode);
  }
  
  public function getWorkspaces()
  {
    return $this->encode($this->request(API_WORKSPACES_URL));
  }
  
  public function createTask($data)
  {
    // Create task
    $taskdata = $this->encode(array("data" => $data));    
    $result   = $this->request(API_TASKS_URL, $taskdata, POST_REQUEST);          
    if (is_array($result) && $result['response'] == '201') // response for object creation    
      return $this->decode($result['data']);    
    else
      return false;
  }
  
  public function addToProject($taskId, $projectid)
  {
    $task     = array("data" => array("project" => $projectid));
    $data     = json_encode($task);
    $result   = $this->request(API_TASKS_URL. "/". $taskId."/addProject" , $data, POST_REQUEST);      
    if (is_array($result) && $result['response'] == '200')   // Response for update
      return true;    
    else
      return false;
  }
  
  /*
   * Create task and add it to a project
   */ 
  public function createTaskInProject($data)
  {        
    $task_data = $this->createTask($data);              
    $taskId    = $task_data['data']['id'];    
    $result    = $this->addToProject($taskId,$this->projectId);
    $response  = array('status'=> '');
    if($result)    
      $response['status'] = 'success';
    else
      $response['status'] = 'error';
    return $response;  
  }
}

class Test extends Asana
{
  protected $workspace;
  
  public function __construct($options) 
  {    
    $this->workspace = $options["workspace"];
    //Important: Remember to call this with valid options  
    parent::__construct($options);    
  }
  
  protected function render($response)
  {
    printf("%s", json_encode($response));
  }
    
  public function reportfeedback() 
  {    
    // Create your own description using post data
    $notes    = "Feedback From: ". $_POST['name'] ." (".$_POST['email'].") \n";
    $notes   .= "Description  : " .$_POST['description'];            
    $taskdata = array
    (
      "workspace" => $this->workspace, 
      "name"      => $_POST['subject'],  
      "notes"     => $notes
    );        
    
    // Create a task with appropriate details.
    $response  = $this->createTaskInProject($taskdata);
    $this->render($response);    
  }
}

$options = array
(
  "api_key"   => "YOUR_API_KEY_GIVEN_BY_ASANA",
  "workspace" => "YOUR_WORKSPACE_ID",
  "projectid" => "YOUR_FEEDBACK_PROJECT_ID"
);  

// Call test class
$test = new Test($options);
$test->reportfeedback();

?>
