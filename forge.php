<?php
error_reporting(error_reporting() & ~E_NOTICE & ~E_WARNING);
declare(ticks=1);
pcntl_signal(SIGHUP,  'handleSigHup');
pcntl_signal(SIGINT,  'handleSigInt');
pcntl_signal(SIGTERM, 'handleSigTerm');
$config = include('config.php');
if (!file_exists('../lisk-php/main.php')) {
  die("\n\nLisk-PHP is missing! Please install it with:\nbash setup.sh\n\n");
}
require_once('../lisk-php/main.php');
require_once('logging.php');
const SERVICE_NAME = "forging";
const FORGING_NODE_NOT_ALLOCATED = -125;
$df = 0;
$GLOBALS['protocol'] = $config['protocol'];
$GLOBALS['daemon_interval'] = $config['daemon_interval'];
$GLOBALS['PublicKey'] = $config['PublicKey'];
$GLOBALS['DecryptionPhrase'] = $config['DecryptionPhrase'];
$GLOBALS['ForgingNodes'] = $config['nodes'];
$lastForgingId = FORGING_NODE_NOT_ALLOCATED;

clog("[".$df."] Forging failover script starts...",SERVICE_NAME);

while(1) {
  $newForgedId = FORGING_NODE_NOT_ALLOCATED;
  $start_time = time();
  $df++;
  clog("[".$df."] Primary forging node: ".$GLOBALS['ForgingNodes'][max(array_keys($GLOBALS['ForgingNodes']))],SERVICE_NAME);
  $NodesCount = count($GLOBALS['ForgingNodes']);
  clog("[".$df."] Forging Nodes count:".$NodesCount,SERVICE_NAME);

  if ($NodesCount > 1) {
    if ($lastForgingId == FORGING_NODE_NOT_ALLOCATED) {
      clog("[".$df."] Forging not yet enabled!",SERVICE_NAME);
    } else {
      clog("[".$df."] Currently forging node id:".$lastForgingId,SERVICE_NAME);
    }
    $heightArray = array();
    $consensusArray = array();
    for ($i=0; $i < $NodesCount; $i++) { 
      $serverAddress = getServer($i,$GLOBALS['ForgingNodes'],$GLOBALS['protocol']);
      $StatusResponse = NodeStatus($serverAddress);
      $height = getHeight($StatusResponse);
      $consensus = getConsensus($StatusResponse);
      array_push($heightArray, $height);
      array_push($consensusArray, $consensus);
      clog("[".$df."] ".$serverAddress." -> Height:".$height." Consensus:".$consensus."%",SERVICE_NAME);
    }
    echo "\nHeightArray->";var_dump($heightArray);
    echo "\nConsensusArray->";var_dump($consensusArray);
    $IsHeightUnique = isArrayUnique($heightArray);
    $IsConsensusUnique = isArrayUnique($consensusArray);
    echo "\nIsHeightUnique->";var_dump($IsHeightUnique);
    echo "\nIsConsensusUnique->";var_dump($IsConsensusUnique);
    if ($IsHeightUnique) {
      $BestHeightValue = max($heightArray);
      $BestHeightKey = array_search($BestHeightValue, $heightArray);
      clog("[".$df."] Best Height id:".$BestHeightKey." with value:".$BestHeightValue,SERVICE_NAME);
    } else {
      clog("[".$df."] Height is the same",SERVICE_NAME);
    }
    if ($IsConsensusUnique) {
      $BestConsensusValue = max($consensusArray);
      $BestConsensusKey = array_search($BestConsensusValue, $consensusArray);
      clog("[".$df."] Best Consensus id:".$BestConsensusKey." with value:".$BestConsensusValue,SERVICE_NAME);
    } else {
      clog("[".$df."] Consensus is the same",SERVICE_NAME);
    }
    if ($IsHeightUnique && $IsConsensusUnique) {
      echo "\nConsensus and height unique";
      if ($BestHeightKey == $BestConsensusKey) {
        echo "\nNewForgingID should be allocated to:".$BestConsensusKey;
        $newForgedId = $BestConsensusKey;
      }
    } else if (!$IsHeightUnique && !$IsConsensusUnique){
      $newForgedId = max(array_keys($GLOBALS['ForgingNodes']));
      clog("[".$df."] Identical sync on all nodes, picking master node.",SERVICE_NAME);
    } else {
      echo "\nSync is unique";
      if ($IsHeightUnique) {
        $newForgedId = $BestHeightKey;
      } else if ($IsConsensusUnique) {
        $newForgedId = $BestConsensusKey;
      }
    }
    echo "\nNewForgedID->";var_dump($newForgedId);
    if ($newForgedId != FORGING_NODE_NOT_ALLOCATED) {
      $prediectedNode = getServer($newForgedId,$GLOBALS['ForgingNodes'],$GLOBALS['protocol']);
      clog("[".$df."] After evaluation best node to forging appears to be: ".$prediectedNode." with id:".$newForgedId,SERVICE_NAME);
      if ($lastForgingId == FORGING_NODE_NOT_ALLOCATED || $lastForgingId != $newForgedId) {
        clog("[".$df."] Checking if node is forging already",SERVICE_NAME);
        $isPredictedNodeForging = isForging(ForgingStatus($GLOBALS['PublicKey'],$prediectedNode));
        if ($isPredictedNodeForging == 'yes') {
          $lastForgingId = $newForgedId;
          clog("[".$df."] Selected Node is already forging!",SERVICE_NAME);
        } else {
          clog("[".$df."] Forging disabled on this node, as precaution lets make sure all other nodes are not forging as well.",SERVICE_NAME);
          DisableForgingOnAllNodes($df);
          clog("[".$df."] Finally enabling forging on selected node.",SERVICE_NAME);
          while ($isPredictedNodeForging == 'no') {
            $lastForgingId = $newForgedId;
            $isPredictedNodeForging = isForging(ToggleForging(true,$GLOBALS['DecryptionPhrase'],$GLOBALS['PublicKey'],$prediectedNode));
            clog("[".$df."] IsPredictedNodeForging: ".$isPredictedNodeForging,SERVICE_NAME);
          }
        }
      } else {
        clog("[".$df."] Doing nothing, predicted node is the same as currently forging",SERVICE_NAME);
      }
    } else {
      clog("[".$df."] Sync status is unclear, skip",SERVICE_NAME);
    }
  } else {
    clog("[".$df."] Only one node specified, script will make sure forging is enabled.",SERVICE_NAME);
    $serverAddress = getServer(0,$GLOBALS['ForgingNodes'],$GLOBALS['protocol']);
    $StatusResponse = NodeStatus($serverAddress);
    $height = getHeight($StatusResponse);
    $consensus = getConsensus($StatusResponse);
    $isForging = isForging(ForgingStatus($GLOBALS['PublicKey'],$serverAddress));
    clog("[".$df."] ".$serverAddress." -> Height:".$height." Consensus:".$consensus."% IsForging:".$isForging,SERVICE_NAME);
    if ($isForging != "yes") {
      clog("[".$df."] Enable forging",SERVICE_NAME);
      $isForgingAfterToggle = isForging(ToggleForging(true,$GLOBALS['DecryptionPhrase'],$GLOBALS['PublicKey'],$serverAddress));
      clog("[".$df."] Is forging enabled: ".$isForgingAfterToggle,SERVICE_NAME);
    }
  }
  $end_time = time();
  $took = $end_time - $start_time;
  $time_sleep = $GLOBALS['daemon_interval']-$took;
  if ($time_sleep < 1) {
    $time_sleep = 0;
  }
  clog("[".$df."] Took:".$took." sleep:".$time_sleep,SERVICE_NAME);
  csleep($time_sleep);
}

function DisableForgingOnAllNodes($df=0){
  for ($i=0; $i < count($GLOBALS['ForgingNodes']); $i++) { 
    $serverAddress = getServer($i,$GLOBALS['ForgingNodes'],$GLOBALS['protocol']);
    $isForging = isForging(ForgingStatus($GLOBALS['PublicKey'],$serverAddress));
    clog("[".$df."] ".$serverAddress." -> IsForging: ".$isForging,SERVICE_NAME);
    while ($isForging == 'yes') {
      clog("[".$df."] Disable forging",SERVICE_NAME);
      $isForging = isForging(ToggleForging(false,$GLOBALS['DecryptionPhrase'],$GLOBALS['PublicKey'],$serverAddress));
      clog("[".$df."] IsForging: ".$isForging,SERVICE_NAME);
    }
  }
}

function getServer($index,$nodelist,$protocol){
  return $protocol."://".$nodelist[$index]."/";
}

function getHeight($json){
  return $json['data']['height'];
}

function getConsensus($json){
  return $json['data']['consensus'];
}

function isForging($json){
  if ($json['data'][0]['forging'] == true) {
    return 'yes';
  } else {
    return 'no';
  }
}

function isArrayUnique($array){
  $n=0;
  $t=0;
  $m=false;
  foreach ($array as $key => $value) {
    $n++;
    $t+=$value;
    if (!$m) {
      $m=$value;
    }
  }
  if ($m==$t/$n) {
    return false;
  } else {
    return true;
  }
}

function handleSigHup(){
  clog("Caught SIGHUP, terminating forging on all nodes and exiting this script...",SERVICE_NAME);
  DisableForgingOnAllNodes();
  echo "\n\n";
  exit(1);
}

function handleSigInt(){
  clog("Caught SIGINT, terminating forging on all nodes and exiting this script...",SERVICE_NAME);
  DisableForgingOnAllNodes();
  echo "\n\n";
  exit(1);
}

function handleSigTerm(){
  clog("Caught SIGTERM, terminating forging on all nodes and exiting this script...",SERVICE_NAME);
  DisableForgingOnAllNodes();
  echo "\n\n";
  exit(1);
}

function csleep($wait_time){
  $org_wait_time = $wait_time;
  $start_time = time();
  $chr = 50;
  echo "\n";
  $chars = array();
  $wait_time = $wait_time/$chr;
  for ($i=0; $i <= $chr; $i++) {
    $chars[] = "#";
    $count = count($chars);
    $string = '[';
    $string .= implode('',$chars);
    $empty = $chr-$count;
    for ($j=0; $j <= $empty; $j++) { 
      $string .= ' ';
    }
    $precent = (double)($i/$chr)*100;
    $current_time = time();
    $diff = $current_time-$start_time;
    $left = $org_wait_time-$diff;
    echo "\rSleeping ".$left."s [".$i."/".$chr."(".$wait_time."s)] ".$string."] ".$precent."%";
    if ($wait_time > 300) {
      sleep(floor($wait_time));
    } else {
      $u_wait_time = $wait_time*1000000;
      usleep($u_wait_time);
    }
  }
}

pcntl_signal(SIGHUP,  SIG_DFL);
pcntl_signal(SIGINT,  SIG_DFL);
pcntl_signal(SIGTERM, SIG_DFL);

?>
