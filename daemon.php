<?php
error_reporting(error_reporting() & ~E_NOTICE & ~E_WARNING);
const debug = false;
$config = include('config.php');
$df = 0;
$protocol = $config['protocol'];
$config_lisk_host = $config['lisk_host'];
$config_lisk_port = $config['lisk_port'];
$secret = $config['secret'];
$daemon_interval = $config['daemon_interval'];
$previous_node_host = '';
$previous_node_port = '';
$disable_forging = '/api/delegates/forging/disable';
$enable_forging = '/api/delegates/forging/enable';
$node_status = '/api/loader/status/sync';
$postjson = '{"secret":"'.$secret.'"}';
echo "\nStarting with: ".$postjson;

while(1) {
  $start_time = time();
  $df++;
  echo "\n/////////////////////////////////////////\nCurrent iteration: ".$df;
  echo "\nCurrent nodes count definied in config: ".count($config_lisk_host);
  if (count($config_lisk_host) > 1) {
    $heights = array();
    $node_port = array();
    $node_host = array();
    for ($i=0; $i < count($config_lisk_host); $i++) {
      $curr_host = $config_lisk_host[$i];
      $curr_port = $config_lisk_port[$i];
      echo "\n[".$i."]Checking node: ".$curr_host.':'.$curr_port;
      $data = request($protocol.'://'.$curr_host.':'.$curr_port.$node_status,'GET','');
      if ($data["consensus"] == 100) {
        array_push($heights, $data["height"]);
        array_push($node_host, $curr_host);
        array_push($node_port, $curr_port);
        echo "\nAdding node with height: ".$data["height"]." and consensus: ".$data["consensus"]."%";
      } else {
        echo "\nHeight: ".$data["height"]." and consensus: ".$data["consensus"]."% [wrong - under 100%]";
      }
    }
    $best_height = max($heights);
    $key = array_search($best_height, $heights);
    echo "\nBest height: ".$best_height;
    $best_host = $node_host[$key];
    $best_port = $node_port[$key];
    echo "\nBest node: ".$best_host.':'.$best_port;
    if ($previous_node_host == '') {
      echo "\nEnabling forging for first time - Disable everywhere";
      for ($i=0; $i < count($config_lisk_host); $i++) {
        $curr_host = $config_lisk_host[$i];
        $curr_port = $config_lisk_port[$i];
        $data = request($protocol.'://'.$curr_host.':'.$curr_port.$disable_forging,'POST',$postjson);
        echo "\nResponse: ".json_encode($data);
      }
      //Setting new
      $previous_node_host = $best_host;
      $previous_node_port = $best_port;
      echo "\nEnabling forging on: ".$best_host.':'.$best_port;
      $data = request($protocol.'://'.$best_host.':'.$best_port.$enable_forging,'POST',$postjson);
      echo "\nResponse: ".json_encode($data);
    } else {
      if ($best_host == '') {
        echo "\nIt seems all nodes are not in well sync, waiting...";
      } else {
        if ($previous_node_host == $best_host) {
          echo "\nBest node is still currently used one!";
        } else {
          echo "\nDisabling forging on previous node: ".$previous_node_host.':'.$previous_node_port;
          $data = request($protocol.'://'.$previous_node_host.':'.$previous_node_port.$disable_forging,'POST',$postjson);
          echo "\nResponse: ".json_encode($data);
          $previous_node_host = $best_host;
          $previous_node_port = $best_port;
          echo "\nEnabling forging on: ".$best_host.':'.$best_port;
          $data = request($protocol.'://'.$best_host.':'.$best_port.$enable_forging,'POST',$postjson);
          echo "\nResponse: ".json_encode($data);
        }
      }
    }
  } else {
    echo "\nNothing to do here... Setting only one as best";
    echo "\nCurrent lisk node is set to: ".$config_lisk_host[0].':'.$config_lisk_port[0];
  }

  $end_time = time();
  $took = $end_time - $start_time;
  $time_sleep = $daemon_interval-$took;
  if ($time_sleep < 1) {
    $time_sleep = 0;
  }
  echo "\n".'Took:'.$took.' sleep:'.$time_sleep;
  usleep($time_sleep*1000000);
}


function request($url,$type,$data_string,$wait){
  if ($type == 'POST') {
    $wait = 120;
  } else {
    $wait = 3;
  }
    if(debug){
    echo "\n".$type." - ".$url." Wait: ".$wait;
  }
  $ch1 = curl_init($url);                                                                      
  curl_setopt($ch1, CURLOPT_CUSTOMREQUEST, $type);
  if ($data_string) {
    curl_setopt($ch1, CURLOPT_POSTFIELDS, $data_string);                                                                 
    curl_setopt($ch1, CURLOPT_HTTPHEADER, array(                                                                          
    'Content-Type: application/json',                                                                                
    'Content-Length: ' . strlen($data_string)));                                                                                                                   
  }                                                                                
  curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch1, CURLOPT_SSL_VERIFYHOST, false);
  curl_setopt($ch1, CURLOPT_CONNECTTIMEOUT ,$wait); 
  curl_setopt($ch1, CURLOPT_TIMEOUT, $wait);    
  $result1 = curl_exec($ch1);
  $jsondict = json_decode($result1, true); 
  return $jsondict;
}

?>
