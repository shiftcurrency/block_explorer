<?php
class Controller
{

  private $config = null;
  private $view = null;
  private $model = null;
  private $template = '';

  public function __construct($request,$viewPath = null)
  {
    global $config;
    $this->viewPath = $viewPath;
    $this->config = $config;
    $this->request = $request;
    $this->template = 'home';
    $this->model = new Model();
    $this->requestType = 'web';
    $this->view = new View($this->viewPath);
  }

  public function display(){
    switch($this->viewPath)
    {
      case DS.'cli':
        $layout = 'ajax';
        break;
      case DS.'api':
        $layout = 'empty';
        break;
      default:
        $layout = $this->config['layout'];
    }

    if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')
    {
      $this->requestType = 'ajax';
      $layout = 'empty';
    }

    $this->view->setLayout($layout);

    if(isset($this->request['page']))
    {
      $this->template = str_replace('.','',$this->request['page']);
    }

    $this->innerView = new View($this->viewPath);
    $this->innerView->setTemplate($this->template);

    $this->_setParams();

    // $now = time();
    // $easterStart = 1397865601;
    // $easterEnd = 1398124799;
    // if($easterStart <= $now && $now <= $easterEnd &&
    //   $layout == $this->config['layout']
    // )
    // {
    //   $this->view->assign('easter',true);
    //   if(in_array($this->template,array('accounts','account','blocks','transactions','transaction','block','nodecheck','home')))
    //   {
    //     $this->easterEgg();
    //   }
    // }

    $this->innerView->assign('knownAddresses',$this->model->knownAddresses());
    $this->view->assign('layout_content', $this->innerView->loadTemplate());
    return $this->view->loadLayout();
  }

  public function home($searchfailed = false)
  {
    $blocks = $this->model->homeBlockData();
    $this->innerView->assign('blocks', $blocks);
    $this->innerView->assign('transactions', $this->model->homeTransactionData($blocks));
    $this->innerView->assign('searchfailed',$searchfailed);
    return $this->innerView;
  }

  public function homeBlocks() {
    $this->innerView->assign('blocks', $this->model->homeBlockData());
    return $this->innerView;
  }

  public function homeTransactions() {
    $this->innerView->assign('transactions', $this->model->homeTransactionData());
    return $this->innerView;
  }
  
//  public function homeAssets() {  // added by altsheets
//  	$this->innerView->assign('assets', $this->model->homeAssetData());
//  	return $this->innerView;
//  }  

  public function account($id, $timestamp = false, $direction = 'DESC')
  {
    $transactions = $this->model->getAccountTransactions($id,$timestamp,$direction);
    foreach($transactions as $transaction)
    {
      if(isset($transaction->ts))
      {
        for($i = count($transactions)-1; $i > 0; $i--)
        {
          if(isset($transactions[$i]->ts))
          {
            $this->innerView->assign('earlier',$transactions[$i]->ts);
            $i = 0;
          }
        }
        $this->innerView->assign('later',$transaction->ts);
        break;
      }
    }

    $this->innerView->assign('id',$id);
    $this->innerView->assign(
      'idrs',
      $this->model->find(
        'transactions',
        array(
          'fields'=>array('recipientrs'),
          'conditions'=>"recipient = '".$this->model->sqlEscape($id)."'",
          'limit'=>1
        )
      )[0]['recipientrs']
    );
    $this->innerView->assign('transactions',$transactions);
    $this->innerView->assign('balance',$this->model->calculateBalance($id));
    return $this->innerView;
  }

  public function blocks($height = false)
  {
    $blocks = $this->model->moreBlockData($height);

    $this->innerView->assign('blocks',$blocks);
    $this->innerView->assign('lastBlockHeight',$this->model->getLastBlockHeight());

    return $this->innerView;
  }

  public function block($height)
  {
    $this->innerView->assign('block',$this->model->getBlockData($height));
    $this->innerView->assign('lastBlockHeight',$this->model->getLastBlockHeight());
    return $this->innerView;
  }

  public function transactions($timestamp = false,$direction = 'DESC',$limit = 50)
  {
    $transactions = $this->model->moreTransactionData($timestamp,$direction,$limit);

    $this->innerView->assign('transactions',$transactions);
    return $this->innerView;
  }

  public function transaction($id)
  {
    $this->innerView->assign('transaction',$this->model->getTransactionData($id));
    return $this->innerView;
  }

  public function asset($id)   // added by altsheets
  {
  	$this->innerView->assign('asset',$this->model->getAssetData($id));
  	return $this->innerView;
  }
  
  public function search()
  {
    $result = $this->model->fullSearch($this->request['search']);
    if(isset($result['block']) && $result['block'] > 0)
    {
      echo '<meta http-equiv="refresh" content="0; URL=/?page=block&height='.$result['block'].'">';
    }
    elseif(isset($result['account']) && !empty($result['account']))
    {
      echo '<meta http-equiv="refresh" content="0; URL=/?page=account&id='.$result['account'].'">';
    }
    elseif(isset($result['transaction']) && !empty($result['transaction']))
    {
      echo '<meta http-equiv="refresh" content="0; URL=/?page=transaction&id='.$result['transaction'].'">';
    }
    else
    {
      echo '<meta http-equiv="refresh" content="0; URL=/">';
    }
    exit();
  }

  public function batchcheck($data)
  {
    if(isset($data[2]))
    {
      $data['nodes'] = $data[2];
    }
    $data['nodes'] = str_replace("\r\n","\n",$data['nodes']);
    $data['nodes'] = str_replace("\r","\n",$data['nodes']);
    $nodes = explode("\n",$data['nodes']);
    foreach($nodes as $key=>$node)
    {
      $node = gethostbyname($node);

      if(!empty($node))
      {
        $nodeResponse = $this->checkNode($node);
        $apiResponse = $this->checkServerApi($node);
        $this->logUndiscovered($node,$apiResponse,$nodeResponse);
        if($nodeResponse != '{}' && !empty($nodeResponse->announcedAddress))
        {
          $nodes[$key] = array('nodeResponse'=>$nodeResponse);
        }

        if(!empty($apiResponse->ip_address))
        {
          $nodes[$key]['apiResponse'] = $apiResponse;
        }
      }
      else
      {
        unset($nodes[$key]);
      }
    }

    if(!empty($nodes))
    {
      $this->innerView->assign('results',$nodes);
    }

    return $this->innerView;
  }

  public function nodecheck($data)
  {
    if(isset($data[2]))
    {
      $data['nodeAddress'] = $data[2];
    }

    if(isset($data['nodeAddress']))
    {
      $data['nodeAddress'] = gethostbyname($data['nodeAddress']);

      $result = $this->checkNode($data['nodeAddress']);

      if(!isset($result->announcedAddress) || empty($result->announcedAddress))
      {
        $nodeResponse = false;
      }
      else
      {
        $nodeResponse = $result;
      }

      $this->innerView->assign('nodeResponse',$nodeResponse);

      $result = $this->checkServerApi($data['nodeAddress']);
      if(empty($result))
      {
        echo 'No answer from node bounty server';
        exit();
      }

      $apiResponse = $result;
      $this->innerView->assign(
        'apiResponse',
        $apiResponse
      );

      // $this->logUndiscovered($data['nodeAddress'],$apiResponse,$nodeResponse);
    }

    if($this->requestType == 'ajax')
    {
      $this->innerView->assign('element',array('apiResponse'=>$apiResponse,'nodeResponse'=>$nodeResponse));
      $this->innerView->setTemplate('..'.DS.'elements'.DS.'nodestate');
    }

    return $this->innerView;
  }

  // public function logUndiscovered($address,$api,$node)
  // {
  //   $file  = $this->config['undiscoveredFile'];
  //   $lines = file($file);

  //   $fileHandle = fopen($file, 'w');
  //   if(empty($api->ip_address) && !empty($node->announcedAddress))
  //   {
  //     foreach($lines as $line)
  //     {
  //       $line = str_replace($address,'',$line);
  //       if($line != "\n")
  //       {
  //         fwrite($fileHandle, $line);
  //       }
  //     }

  //     fwrite($fileHandle, $address."\n");
  //     fclose($fileHandle);
  //     return true;
  //   }
  //   else
  //   {
  //     foreach($lines as $line)
  //     {
  //       $line = str_replace($address,'',$line);
  //       if($line != "\n")
  //       {
  //         fwrite($fileHandle, $line);
  //       }
  //     }

  //     fclose($fileHandle);
  //     return false;
  //   }
  // }

  public function checkNode($address)
  {
    return false;
    $this->innerView->assign('nodeAddress',$address);
      $url = 'http://'.$address.':7774/nhz';
      $postData = array(
        'platform'=>'BOUNTY',
        'protocol'=>1,
        'application'=>'NHZ',
        'requestType'=>'getInfo',
        'version'=>'BountySpider'
      );
      $content = json_encode($postData);
      $options = array(
          'http' => array(
              'header'=>"User-Agent: Java/1.7.0_45\r\n".
                "Content-type: application/json\r\n".
                "Accept: text/html, image/gif, image/jpeg, *; q=.2, */*; q=.2\r\n".
                "Connection: keep-alive\r\n".
                "Content-Length: ".strlen($content)."\r\n",
              'method'=>'POST',
              'content'=>$content,
              'timeout'=>7
          ),
      );
      $context = stream_context_create($options);
      $result = file_get_contents($url,false,$context);
      return json_decode($result);
  }

  public function checkServerApi($address)
  {
    $result = $this->model->getServerApiCache($address);
    if(!$result)
    {
      global $config;
      $result = json_decode(file_get_contents('http://'.$config['nodeBountyServer'].'/api/info?ip='.$address));
      if(isset($result->ip_address) && !empty($result->ip_address))
      {
        $this->model->saveServerApiCache($result);
      }
    }
    return $result;
  }

  public function accounts()
  {
    $this->innerView->assign('accounts',$this->model->getBalances());
    return $this->innerView;
  }

  public function graphs($graph = null,$offset = '7d')
  {
    if(!empty($graph))
    {
      switch($graph)
      {
        case 'distribution':
          $this->innerView->setTemplate('distributionGraph');
          $graph = $this->model->getGraph('distribution',$offset);
          $this->innerView->assign('graph',$graph['values']);
          break;
        case 'accounts':
          $this->innerView->setTemplate('accountsGraph');
          $graph = $this->model->getGraph('accounts',$offset);
          $this->innerView->assign('graph',$graph['values']);
          break;
        case 'blocks':
          $this->innerView->setTemplate('blocksGraph');
          $graph = $this->model->getGraph('blocks',$offset);
          $this->innerView->assign('graph',$graph['values']);
          break;
        case 'nodes':
          $this->innerView->setTemplate('nodesGraph');
          $graph = $this->model->getGraph('nodes',$offset);
          $this->innerView->assign('graph',$graph['values']);
          break;
        case 'forgers':
          $this->innerView->setTemplate('forgersGraph');
          $graph = $this->model->getForgersGraph($offset);
          $this->innerView->assign('graph',$graph['values']);
          $this->innerView->assign('count',$graph['count']);
          break;
      }
    }

    return $this->innerView;
  }

  public function distributed()
  {
    $this->innerView->assign('distributed',$this->model->getDistributed());
    return $this->innerView;
  }

  public function assets()
  {
    $this->innerView->assign('knownAddresses',$this->model->knownAddresses());
    $this->innerView->assign('assets',$this->model->assets());
    return $this->innerView;
  }

  private function _setParams()
  {
    $id = 0;
    $timestamp = false;
    $height = $this->model->getLastBlockHeight()+1;

    $direction = 'DESC';
    if(isset($this->request['direction']))
    {
      $direction = $this->request['direction'];
    }

    $limit = 50;
    if(isset($this->request['limit']))
    {
      $limit = $this->request['limit'];
    }


    if(isset($this->request['page']))
    {
      switch($this->request['page'])
      {
        case 'account':
          if(isset($this->request[3]))
          {
            $direction = $this->request[3];
          }
          if(isset($this->request['timestamp']))
          {
            $timestamp = $this->request['timestamp'];
          }
        case 'transaction':
          if(isset($this->request['id']))
          {
            $id = $this->request['id'];
          }
          elseif(isset($this->request[2]))
          {
            $id = $this->request[2];
          }
          break;
        case 'asset':  // added by altsheets
          if(isset($this->request['id']))
          {
          	$id = $this->request['id'];
          }
          elseif(isset($this->request[2]))
          {
          	$id = $this->request[2];
          }
          break;
          
        case 'transactions':
          if(isset($this->request['timestamp']))
          {
            $timestamp = $this->request['timestamp'];
          }
          elseif(isset($this->request[2]))
          {
            $timestamp = $this->request[2];
          }
          break;
        case 'block':
        case 'blocks':
          if(isset($this->request['height']))
          {
            $height = $this->request['height'];
          }
           elseif(isset($this->request[2]))
          {
            $height = $this->request[2];
          }
          break;
        case 'graphs':
          $graph = null;
          $offset = '7d';
          if(isset($this->request['graph']))
          {
            $graph = $this->request['graph'];
          }
          if(isset($this->request['offset']))
          {
            $offset = $this->request['offset'];
          }
      }
    }

    switch($this->template){
      case 'accounts':
        $this->innerView = $this->accounts();
        break;
      case 'account':
        $this->innerView = $this->account($id,$timestamp,$direction);
        break;
      case 'blocks':
        $this->innerView = $this->blocks($height);
        break;
      case 'transactions':
        $this->innerView = $this->transactions($timestamp,$direction,$limit);
        break;
      case 'transaction':
        $this->innerView = $this->transaction($id);
        break;
      case 'asset':			// added by altsheets
      	$this->innerView = $this->asset($id);
      	break;
      case 'block':
        $this->innerView = $this->block($height);
        break;
      case 'nodecheck':
        $this->innerView = $this->nodecheck($this->request);
        break;
      case 'batchcheck':
        $this->innerView = $this->batchcheck($this->request);
        break;
      case 'search':
        $this->innerView = $this->search();
        break;
      case 'homeBlocks':
        $this->innerView = $this->homeBlocks();
        break;
      case 'homeTransactions':
        $this->innerView = $this->homeTransactions();
        break;
      case 'graphs':
        $this->innerView = $this->graphs($graph,$offset);
        break;
      case 'distributed':
        $this->innerView = $this->distributed();
        break;
      case 'assets':
        $this->innerView = $this->assets();
        break;
      case 'home':
        $this->innerView = $this->home();
        break;
    }
  }

  // public function easterEgg()
  // {
  //   try
  //   {
  //     $this->eggconn = new SQLite3('data/eastereggs.db');
  //   }
  //   catch(Exception $exception)
  //   {
  //     die($exception->getMessage());
  //   }

  //   $this->eggconn->busyTimeout(2000);

  //   $r = rand(0,15);
  //   if($r != 1)
  //   {
  //     $banned = file($_SERVER['DOCUMENT_ROOT'].'/data/egg_banlist', FILE_IGNORE_NEW_LINES);
  //   } else {
  //     $banned = file('https://check.torproject.org/cgi-bin/TorBulkExitList.py?ip=78.46.32.25&port=', FILE_IGNORE_NEW_LINES);
  //     unset($banned[0]);
  //     unset($banned[1]);
  //     unset($banned[2]);
  //     $ips = $this->eggconn->query("SELECT ip FROM banned");
  //     while($row = $ips->fetchArray(SQLITE3_ASSOC))
  //     {
  //       $banned[] = $row['ip'];
  //     }

  //     unlink($_SERVER['DOCUMENT_ROOT'].'/data/egg_banlist');
  //     file_put_contents($_SERVER['DOCUMENT_ROOT'].'/data/egg_banlist', implode(PHP_EOL, $banned));
  //   }

  //   $r = rand(0,24);
  //   if($r == 23 &&
  //     strpos($_SERVER['HTTP_USER_AGENT'], 'Apache-HttpClient') === false &&
  //     strpos($_SERVER['HTTP_USER_AGENT'], 'Wget') === false &&
  //     strpos($_SERVER['HTTP_USER_AGENT'], 'Googlebot') === false &&
  //     strpos($_SERVER['HTTP_USER_AGENT'], 'bot.php') === false &&
  //     !in_array($_SERVER['REMOTE_ADDR'], $banned)
  //   )
  //   {
  //     $egg = $this->layEgg();
  //     $this->view->assign('egg',$egg);
  //   }
  // }

  // public function layEgg()
  // {
  //   $characters = '0123456789abcdef';
  //   $randomString = '';
  //   for ($i = 0; $i < 50; $i++) {
  //       $randomString .= $characters[rand(0, strlen($characters) - 1)];
  //   }

  //   $this->eggconn->exec("INSERT INTO eggs (id, sent, timestamp, ip, useragent) VALUES ('".$randomString."','0',".time().", '".$_SERVER['REMOTE_ADDR']."', '".$_SERVER['HTTP_USER_AGENT']."');");

  //   $minuteago = time() - 60;
  //   $found = $this->eggconn->query("SELECT id FROM eggs WHERE ip='".$_SERVER['REMOTE_ADDR']."' AND useragent='".$_SERVER['HTTP_USER_AGENT']."' AND timestamp > ".$minuteago.";");
  //   $i = 0;
  //   while($egg = $found->fetchArray(SQLITE3_ASSOC))
  //   {
  //     $i++;
  //   }
  //   if($i >= 5)
  //   {
  //     $this->eggconn->exec("INSERT INTO banned (ip) VALUES ('".$_SERVER['REMOTE_ADDR']."');");
  //   }

  //   return $randomString;
  // }

  // public function eggs($id)
  // {
  //   try
  //   {
  //     $this->eggconn = new SQLite3('data/eastereggs.db');
  //   }
  //   catch(Exception $exception)
  //   {
  //     die($exception->getMessage());
  //   }
  //   $eggid = null;
  //   $timelimit = time() - 1800;
  //   $result = $this->eggconn->query("SELECT id, ip FROM eggs WHERE id='".$this->model->sqlEscape($id)."' AND sent='0' AND ip='".$_SERVER['REMOTE_ADDR']."' AND useragent='".$_SERVER['HTTP_USER_AGENT']."' AND timestamp > ".$timelimit." LIMIT 1");
  //   while($row = $result->fetchArray(SQLITE3_ASSOC))
  //   {
  //     $eggid = $row['id'];
  //     $eggip = $row['ip'];
  //   }

  //   if(isset($this->request['captcha_code']) && !empty($eggid))
  //   {
  //     $error = '';
  //     session_start();
  //     include_once $_SERVER['DOCUMENT_ROOT'] . '/securimage/securimage.php';
  //     $securimage = new Securimage();
  //     if($securimage->check($this->request['captcha_code']) == false)
  //     {
  //       $error .= "The security code entered was incorrect.<br />";
  //     }
  //     if(empty($this->request['account_id']) || !is_numeric(trim($this->request['account_id'])))
  //     {
  //       $error .= "You need to provide a valid account id.<br />";
  //     }
  //     if(empty($error))
  //     {
  //       $eggamount = 250;
  //       $eggamount += rand(250,500);
  //       $r = rand(0,6);
  //       if($r == 1)
  //       {
  //         $eggamount += rand(500,1000);
  //       }
  //       $secret = '3Ea3T3URgvRtp11AyuIEAp2i4rAFEuj7cpvYDEoBZk66T4dqZH2Wn01aKTd3EbqXHnjVxpkznYc';
  //       $url = 'http://127.0.0.1:7776/nhz?requestType=sendMoney&secretPhrase='.urlencode(
  //         $secret
  //       ).'&recipient='.trim($this->request['account_id']).'&amount='.$eggamount.'&fee=1&deadline=1440';
  //       $result = json_decode(file_get_contents($url));
  //       if(isset($result->errorDescription))
  //       {
  //         $this->innerView->assign('error',$result->errorDescription);
  //       }
  //       else
  //       {
  //         $this->eggconn->exec("UPDATE eggs SET sent='1', owner='".$this->request['account_id']."', amount='".$eggamount."' WHERE id='".$eggid."';");
  //         $this->eggconn->exec("UPDATE eggs SET sent='1' WHERE ip='".$eggip."';");
  //         $p = file_get_contents("http://n.tkte.ch/h/2372/Bz0QHc2r4i9lfnzwMqhb8pne?payload=".urlencode(
  //           "Someone found an Easter egg and collected ".$eggamount." NHZ"
  //         ));
  //         echo '<meta http-equiv="refresh" content="3; URL=/">';
  //         echo "Paid out ".$eggamount." NHZ! Redirecting.";
  //         exit();
  //       }
  //     }
  //     else
  //     {
  //       $this->innerView->assign('error',$error);
  //     }
  //   }

  //   if(empty($eggid))
  //   {
  //     $eggid = 'Not found';
  //   }

  //   $this->innerView->assign('eggid',$eggid);
  //   return $this->innerView;
  // }

}
