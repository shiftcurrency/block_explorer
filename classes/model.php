<?php
class Model
{

  private $config = null;
  private $conn = null;

  public function __construct()
  {
    global $config;
    $this->config = $config;
    try
    {
      $this->conn = new SQLite3($this->config['database']);
    }
    catch(Exception $exception)
    {
      die($exception->getMessage());
    }

    $this->conn->busyTimeout(5000);
    $this->conn->exec(
      'PRAGMA cache_size = '.$config['dbcachesize'].';'.
      'PRAGMA synchronous=OFF;'.
      'PRAGMA temp_store=2;'
    );
  }

  public function __destruct()
  {
    $this->conn->close();
  }

  public function fromBlockchain($data)
  {
	$data_string = '';
	foreach($data as $key=>$value) { $data_string .= $key.'='.$value.'&'; }
	rtrim($data_string, '&');
	$ch = curl_init($this->config['nhz']);
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; Linux i686; rv:20.0) Gecko/20121230 Firefox/20.0');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch, CURLOPT_SSLVERSION, 3);
	curl_setopt($ch, CURLOPT_POST, count($data));
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
	$result = curl_exec($ch);
	$error = curl_error($ch);
	curl_close($ch);

	if(!empty($error))
	{
	  return $error;
	}

	return json_decode($result);
  }

  public function fullSearch($string)
  {
    $string = trim($this->sqlEscape($string));
    $result = array();
    $account = $this->find(
      'transactions',
      array(
        'conditions'=>"recipient = '".$string."' OR recipientrs = '".$string."'",
        'limit'=>1,
        'fields'=>array('recipient')
      )
    );
    if(isset($account[0]['recipient']))
    {
      $result['account'] = $account[0]['recipient'];
    }

    $block = $this->find(
      'blockdata',
      array(
        'conditions'=>"previousblock = '".$string."'",
        'limit'=>1,
        'fields'=>array('height')
      )
    );
    if(isset($block[0]['height']))
    {
      $result['block'] = $block[0]['height']-1;
    }

    $transaction = $this->find(
      'transactions',
      array(
        'conditions'=>"id = '".$string."'",
        'limit'=>1,
        'fields'=>array('id')
      )
    );
    if(isset($transaction[0]['id']))
    {
      $result['transaction'] = $transaction[0]['id'];
    }

    return $result;
  }

  public function homeBlockData() {
    $state = $this->fromBlockchain(
        array(
            'requestType'=>'getBlockchainStatus'
        )
    );
    $lastBlock = $state->numberOfBlocks - 1;
    $results = array();
    for($i = $lastBlock; $i > $lastBlock - 10; $i--) {
        $block = $this->fromBlockchain(
            array(
                'requestType'=>'getBlock',
                'height'=>$i
            )
        );
        $block->time = $this->calculateTimestamp($block->timestamp);

        $results[] = $block;
    }

    return $results;
  }

  public function moreBlockData($height = false)
  {
    if(!$height) {
      $state = $this->fromBlockchain(
          array(
              'requestType'=>'getBlockchainStatus'
          )
      );
      $lastBlock = $state->numberOfBlocks - 1;
    } else {
      $lastBlock = $height;
    }

    $results = array();
    for($i = $lastBlock; $i > $lastBlock - 51; $i--) {
        $block = $this->fromBlockchain(
            array(
                'requestType'=>'getBlock',
                'height'=>$i
            )
        );
        if(isset($block->height)) {
          $block->time = $this->calculateTimestamp($block->timestamp);

          $results[] = $block;
        }
    }

    if(isset($results[50])) {
      unset($results[50]);
    }

    return $results;
  }

  public function assets()
  {
    $results = $this->find(
      'assets',
      array(
        'fields'=>array('asset','account','accountrs','name','description','quantity','decimals','trades'),
        'order'=>'name'
      )
    );

    return $results;
  }

  public function getBlockData($height)
  {
    $block = $this->fromBlockchain(array(
        'requestType'=>'getBlock',
        'height'=>$height
    ));
    $block->time = $this->calculateTimestamp($block->timestamp);
    $block->prevheight = $height-1;
    $block->nextheight = $height+1;

    return $block;
  }

  private function _getBlockFields()
  {
    return array(
      'generator',
      'generatorrs',
      'timestamp',
      'numberoftransactions',
      'previousblock',
      'nextblock',
      'totalamount',
      'height',
      'totalfee'
    );
  }

  public function homeTransactionData($blocks = array()) {
    $transactions = array();
    if(!empty($blocks)) {
        foreach($blocks as $block) {
            foreach($block->transactions as $transaction) {
                $transactions[] = $transaction;
            }
            $lastBlock = $block->height;
        }
    }

    while(count($transactions) < 10) {
        if(!isset($lastBlock)) {
            $state = $this->fromBlockchain(
                array(
                    'requestType'=>'getBlockchainStatus'
                )
            );
            $lastBlock = $state->numberOfBlocks;
        }

        $block = $this->fromBlockchain(array(
            'requestType'=>'getBlock',
            'height'=>$lastBlock-1
        ));
        $lastBlock = $block->height;
        foreach($block->transactions as $transaction) {
            $transactions[] = $transaction;
        }
    }

    $results = array();
    foreach($transactions as $transaction) {
        $transaction = $this->fromBlockchain(array(
            'requestType'=>'getTransaction',
            'transaction'=>$transaction
        ));

        if(!isset($transaction->recipient)) {
          $transaction->recipient = '13675701959091502344';
        }

        $transaction->time = $this->calculateTimestamp($transaction->timestamp);
        $results[] = $transaction;
    }

    return $results;
  }

  public function moreTransactionData($lastBlock = false) {
    $transactions = array();

    while(count($transactions) < 50) {
        if(!$lastBlock) {
            $state = $this->fromBlockchain(
                array(
                    'requestType'=>'getBlockchainStatus'
                )
            );
            $lastBlock = $state->numberOfBlocks;
        }

        $block = $this->fromBlockchain(array(
            'requestType'=>'getBlock',
            'height'=>$lastBlock-1
        ));
        $lastBlock = $block->height;
        foreach($block->transactions as $transaction) {
            $transactions[] = $transaction;
        }
    }

    $results = array();
    foreach($transactions as $transaction) {
        $transaction = $this->fromBlockchain(array(
            'requestType'=>'getTransaction',
            'transaction'=>$transaction
        ));

        if(!isset($transaction->recipient)) {
          $transaction->recipient = '13675701959091502344';
        }

        $transaction->time = $this->calculateTimestamp($transaction->timestamp);
        $results[] = $transaction;
    }

    return $results;

  }

  public function getTransactionData($id)
  {

    $transaction = $this->fromBlockchain(array(
        'requestType'=>'getTransaction',
        'transaction'=>$id
    ));
    $deadline = $transaction->timestamp+$transaction->deadline;
    $transaction->deadline = $this->calculateTimestamp($deadline);
    $transaction->time = $this->calculateTimestamp($transaction->timestamp);
    $transaction->blockTime = $this->calculateTimestamp($transaction->blockTimestamp);
    if(!isset($transaction->recipient)) {
      $transaction->recipient = '13675701959091502344'; 
    }

    return $transaction;
  }

  public function checkAssetWarnings($id)  // (added by altsheets)
  // loads XML file with (possibly) problematic assets
  // returns null if asset id not in XML file
  // returns XML node if asset id is in XML file
  //
  // 
  {
  	// N.B.: It might make sense to load the file only once,
  	// then keep in memory - instead of loading for each call:
  	$xml=simplexml_load_file("data/assetwarnings.xml");
  	
  	$result = null; 
  	foreach($xml->children() as $problematicAsset) {
  		if ($problematicAsset->id == $id)  // will return the last match!
  			$result = $problematicAsset;  
  	};
  	return $result;
  }
  
public function getAssetData($id)     // (added by altsheets)
  // gets all asset data from API
  // if asset exists, then:
  // transforms quantityQNT with decimals to quantity
  // checks if there is an asset warning, if yes includes them.
  {
  	$asset = $this->fromBlockchain(array(
  			'requestType'=>'getAsset',
  			'asset'=>$id
  	));
  	
  	if (! isset ($asset->errorCode) ){ 

  		$quantity = $asset->quantityQNT;
  		 
  		for ($i = $asset->decimals; $i>0; $i--){
  			$quantity = $quantity / 10;
  		};
  		$asset->quantity = $quantity;
  		 
  		// a future version of HZ will have these asset properties:
  		// $asset->numberOfTransfers = "test1";
  		// $asset->numberOfAccounts  = "test2";
  		
  		// question: Can it be assumed that the description is properly escaped,
  		// or is it possible that an asset description can contain malicious code?
  		// In the latter case, rather do something about it, by escaping it in here.
  	}
  	
  	$warning = $this->checkAssetWarnings($id);
  	if ($warning != null) {
  		$asset->warning = $warning;
  	}
  	
  	return $asset;
  }
  
  
/**
 * get transactions for account
 *
 * need to query for the previous block + the real block for each transaction,
 * because the database doesn't contain block id :/
 *
 * @param  string  $id     id of account
 * @param  array   $forges options: 'include' (bool: include forges), 'nofee' (bool: forges without fee)
 * @return array
 */
  public function getAccountTransactions($id, $timestamp = false, $direction = 'DESC', $forges = array('include'=>true,'nofee'=>false))
  {
    $id = $this->sqlEscape($id);

    $data = array(
	  'requestType'=>'getAccountTransactionIds',
	  'account'=>$id,
	  'lastIndex'=>199
	);

	if(isset($timestamp))
	{
		$data['timestamp'] = $timestamp;
	}

	$result = $this->fromBlockchain($data);

	$transactions = array();
	$data = array(
		'requestType'=>'getTransaction'
	);
	foreach($result->transactionIds as $transaction)
	{
		$data['transaction'] = $transaction;
		$transactions[] = $this->fromBlockchain($data);
	}

    $tmp = array();
    foreach($transactions as $key=>$value)
    {
      if(!isset($tmp['first']))
      {
        $tmp['first'] = $value->timestamp;
      }

      $tmp['last'] = $value->timestamp;

      $transactions[$key]->ts = $value->timestamp;
      $transactions[$key]->timestamp = $this->calculateTimestamp($value->timestamp);

      $data = array('requestType'=>'getBlock','height'=>$value->height);
      $block = $this->fromBlockchain($data);
      $block->ts = $block->timestamp;
      $block->timestamp = $this->calculateTimestamp($block->timestamp);
      $transactions[$key]->block = $block;
    }

    $timestamps = array('first'=>0,'last'=>1);
    if(!empty($tmp))
    {
      sort($tmp);
      $timestamps['first'] = $tmp[0];
      $timestamps['last'] = $tmp[1];
    }

    $timestamps['request'] = $timestamp;

    // if($forges['include'])
    // {
    //   $forgedBlocks = $this->getAccountForgedBlocks($id,$forges['nofee'],false,$timestamps);
    //   $transactions = array_merge($transactions,$forgedBlocks);
    //   $timestamp = array();

    //   foreach($transactions as $key=>$row) {
    //     if(isset($row['height']))
    //     {
    //       $transactions[$key]['sender'] = $row['height'];
    //       $transactions[$key]['recipient'] = $row['generator'];
    //       $transactions[$key]['recipientrs'] = $row['generatorrs'];
    //     }
    //     $timestamp[$key]  = $row['timestamp'];
    //   }

    //   array_multisort($timestamp, SORT_DESC, $transactions);
    // }

    return $transactions;
  }

  public function getAccountForgedBlocks($id, $nofee = false, $countonly = false,$timestamps = false)
  {
    $conditions = "generator='".$this->sqlEscape($id)."'";
    if(!$nofee)
    {
      $conditions .= " AND totalfee!=0";
    }

    if(isset($timestamps['request']) && $timestamps['request'] === false)
    {
      $conditions .= " AND timestamp > ".$timestamps['first'];
    }
    elseif($timestamps)
    {
      $conditions .= " AND timestamp BETWEEN ".$timestamps['first']." AND ".$timestamps['last'];
    }

    $order = 'timestamp DESC';

    $fields = array('height','generator','generatorrs','totalfee','timestamp');
    if($countonly)
    {
      $fields = 'count(*)';
      $order = '';
    }

    $data = $this->find(
      'blockdata',
      array('fields'=>$fields,'conditions'=>$conditions,'order'=>$order)
    );

    if(!$countonly)
    {
      foreach($data as $key=>$block)
      {
        $data[$key]['timestamp'] = $this->calculateTimestamp($block['timestamp']);
      }
    }
    else
    {
      $data = $data[0]['count(*)'];
    }

    return $data;
  }

  public function calculateBalance($id, $transactions = null)
  {
    // added by altsheets
    // not sure if this is the correct fix
    // but it repairs the problem
    // explained in https://bitcointalk.org/index.php?topic=823785.msg11751822#msg11751822
    //
    if(!is_numeric($id))
    {
  	$id = $this->fromBlockchain(array('requestType'=>'rsConvert','account'=>$id))->account;
    }
    // end of altsheets change
    //

    $account = $this->sqlEscape($id);
    $received = $this->find(
      'transactions',
      array(
        'fields'=>array(
          'sum(amount) as received',
          'count(id) as receivedtx'
        ),
        'conditions'=>"recipient = '".$account."'"
      )
    )[0];
    $sent = $this->find(
      'transactions',
      array(
        'fields'=>array(
          'sum(amount) as sent',
          'count(id) as senttx',
          'sum(fee) as fees'
        ),
        'conditions'=>"sender = '".$account."'"
      )
    )[0];
    $forged = $this->find(
      'blockdata',
      array(
        'fields'=>array(
          'sum(totalfee) as forged',
          'count(height) as totalforgedblocks'
        ),
        'conditions'=>"generator = '".$account."'"
      )
    )[0];
    $withfee = $this->find(
      'blockdata',
      array(
        'fields'=>array(
          'count(height) as forgedblocks'
        ),
        'conditions'=>"generator = '".$account."' and totalfee != 0"
      )
    )[0];
    $fromTable = $this->find(
      'balances',
      array(
        'fields'=>array('balance'),
        'conditions'=>"id = '".$account."'"
      )
    );
    $balance = array(
      'balance'=>$fromTable[0]['balance'],
      'received'=>$received['received'],
      'receivedtx'=>$received['receivedtx'],
      'forged'=>$forged['forged'],
      'fees'=>$sent['fees'],
      'sent'=>$sent['sent'],
      'senttx'=>$sent['senttx'],
      'forgedblocks'=>$withfee['forgedblocks'],
      'totalforgedblocks'=>$forged['totalforgedblocks']
    );

    return $balance;
  }

  public function getOffset($offset)
  {
    switch($offset)
    {
      case '7d':
        $offset = 604800;
        break;
      case '30d':
        $offset = 2592000;
        break;
      case '60d':
        $offset = 5184000;
        break;
      case '90d':
        $offset = 7776000;
        break;
      default:
        $offset = $this->calculateNow();
        break;
    }

    return $offset;
  }

  public function getDistributed()
  {
    $fields = array(
      '(account_bounty+dev_bounty+node_bounty+giveaways+sold+dividends+nfd) as total',
      'account_bounty',
      'dev_bounty',
      'node_bounty',
      'giveaways',
      'sold',
      'dividends',
      'nfd'
    );
    $data = $this->find(
      'fundstats',
      array(
        'fields'=>$fields,
        'order'=>'timestamp DESC',
        'limit'=>1
      )
    );

    return $data[0];
  }

  public function getGraph($type,$offset = '7d')
  {
    $offset = $this->getOffset($offset);

    $time = $this->calculateNow()-$offset;

    $fields = array('timestamp','accounts');
    $table = 'fundstats';
    $useDB = null;
    if($type == 'distribution')
    {
      $fields = array(
        'timestamp',
        'account_bounty',
        'dev_bounty',
        'node_bounty',
        'giveaways',
        'sold',
        'dividends',
	'nfd',
	'foundation'
      );
    }
    elseif($type == 'blocks')
    {
      $fields = array('timestamp','blocks');
    }
    elseif($type == 'nodes')
    {
      $useDB = '../nodegraph/nodegraph.db';
      $table = 'nodegraph';
      $fields = array('timestamp','nodes');
      $time = time()-$offset;
    }

    $results = $this->find(
      $table,
      array(
        'fields'=>'count(timestamp)',
        'conditions'=>'timestamp > '.$time,
        'order'=>'timestamp ASC',
        'useDB'=>$useDB
      )
    )[0]['count(*)'];

    if($results < 168)
    {
      $data = $this->find(
        $table,
        array(
          'fields'=>$fields,
          'conditions'=>'timestamp > '.$time,
          'order'=>'timestamp ASC',
          'useDB'=>$useDB
        )
      );
    }
    else
    {
      $factor = $results / 168;
      $from = '(select ';
      foreach($fields as $field)
      {
        $from .= 'a.'.$field.', ';
      }
      $from .= 'count (*) rownumber from '.$table.' a, '.$table.' b where a.timestamp >= b.timestamp group by ';
      foreach($fields as $field)
      {
        $from .= 'a.'.$field.', ';
      }
      $from = substr($from, 0, -2).')';

      $data = $this->find(
        $from,
        array(
          'fields'=>$fields,
          'conditions'=>'rownumber % '.$factor.' = 0 AND timestamp > '.$time,
          'order'=>'timestamp ASC',
          'useDB'=>$useDB
        )
      );
    }

    $result = array(
      array('name'=>'accounts','data'=>array(),'showInLegend'=>false,'color'=>'#009540')
    );
    if($type == 'distribution')
    {
      $result = array(
        array(
          'name'=>'total',
          'data'=>array(),
          'color'=>'#009540'
        ),
        array(
          'name'=>'founders',
          'data'=>array()
        ),
        array(
          'name'=>'sales',
          'data'=>array()
        ),
        array(
          'name'=>'bounties',
          'data'=>array()
        ),
        array(
          'name'=>'nodes',
          'data'=>array()
        ),
        array(
          'name'=>'giveaways',
          'data'=>array()
        ),
        array(
          'name'=>'dividends',
          'data'=>array()
        ),
	array(
	  'name'=>'nfd',
	  'data'=>array()
	),
	array(
	  'name'=>'foundation',
	  'data'=>array()
	)
      );
    }
    elseif($type == 'blocks')
    {
      $result = array(
        array('name'=>'blocks','data'=>array(),'showInLegend'=>false,'color'=>'#009540')
      );
    }
    elseif($type == 'nodes')
    {
      $result = array(
        array('name'=>'nodes','data'=>array(),'showInLegend'=>false,'color'=>'#009540')
      );
    }
    $categories = array();
    $i = 0;
    foreach($data as $value)
    {
      $value['time'] = intval(strtotime($value['time']).'000');
      if($value['time'] == 4998814)
      {
        $value['time'] = '2014-03-22 23:22:22 UTC';
      }

      if($type == 'accounts')
      {
        $result[0]['data'][$i] = array($value['time'],$value['accounts']);
      }
      elseif($type == 'distribution')
      {
        $result[1]['data'][$i] = array($value['time'],$value['dev_bounty']);
        $result[3]['data'][$i] = array($value['time'],$value['account_bounty']);
        $result[4]['data'][$i] = array($value['time'],$value['node_bounty']);
        $result[5]['data'][$i] = array($value['time'],$value['giveaways']);
        $result[2]['data'][$i] = array($value['time'],$value['sold']);
        $result[6]['data'][$i] = array($value['time'],$value['dividends']);
        $result[7]['data'][$i] = array($value['time'],$value['nfd']);
        $result[8]['data'][$i] = array($value['time'],$value['foundation']);
        $result[0]['data'][$i] = array($value['time'],$value['account_bounty']+$value['dev_bounty']+$value['node_bounty']+$value['giveaways']+$value['sold']+$value['dividends']+$value['nfd']);
      }
      elseif($type == 'blocks')
      {
        $next = $value['blocks']+1;
        $result[0]['data'][$i] = array('x'=>$value['time'],'y'=>$value['blocks'],'url'=>'?page=blocks&height='.$next);
      }
      elseif($type == 'nodes')
      {
        $result[0]['data'][$i] = array('x'=>$value['timestamp'].'000','y'=>$value['nodes']);
      }
      $i++;
    }

    $graph['values'] = json_encode($result);
    return $graph;
  }

  public function getForgersGraph($offset)
  {
    $offset = $this->getOffset($offset);

    $time = $this->calculateNow()-$offset;

    $table = 'blockdata';
    $fields = array('generator','generatorrs','count(height) as y', 'sum(totalfee) as fees');
    $conditions = 'timestamp > '.$time;

    $data = $this->find(
      $table,
      array(
        'fields'=>$fields,
        'conditions'=>$conditions,
        'group'=>'generator',
        'order'=>'y'
      )
    );

    $total = $this->find(
      $table,
      array(
        'fields'=>array('count(height) as blocks', 'sum(totalfee) as fees'),
        'conditions'=>$conditions
      )
    )[0];

    $knownAddresses = $this->knownAddresses();

    foreach($data as $key=>$value)
    {
      if(isset($knownAddresses[$value['generator']])):
        $data[$key]['name'] = $knownAddresses[$value['generator']];
      else:
        $data[$key]['name'] = $value['generatorrs'];
      endif;
      $percentage = ($value['y']*100)/$total['blocks'];
      $data[$key]['percent'] = round($percentage,2);
      $percentage = ($value['fees']*100)/$total['fees'];
      $data[$key]['feepercent'] = round($percentage,2);
    }

    $graph['count']  = count($data);
    $graph['values'] = json_encode($data);
    return $graph;
  }

  public function calculateNow()
  {
    $genesis = strtotime('2014-03-22 22:22:22 Europe/London');
    $now = time() - $genesis;
    return $now;
  }

  public function calculateTimestamp($timestamp = null)
  {
    $genesis = strtotime('2014-03-22 22:22:22 Europe/London');
    if(!$timestamp)
    {
      $now = time();
      return $now - $genesis;
    }

    $time = $genesis + $timestamp;
    return date('Y-m-d H:i:s',$time).' '.date_default_timezone_get();
  }

  public function getCurrentTimestamp()
  {
    $genesis = strtotime('2014-03-22 22:22:22 Europe/London');
    return time() - $genesis;
  }

  public function find($table,$options = array())
  {
    $fields = '*';
    if(isset($options['fields']))
    {
      if(is_array($options['fields']))
      {
        $tmp = '';
        foreach($options['fields'] as $field)
        {
          $tmp .= $this->sqlEscape($field).', ';
        }
        $fields = substr($tmp, 0, -2);
      }
      else
      {
        $fields = $this->sqlEscape($options['fields']);
      }
    }

    $table  = $this->sqlEscape($table);

    $query = 'SELECT '.$fields.' FROM '.$table;

    if(isset($options['conditions']) && !empty($options['conditions']))
    {
      $query .= ' WHERE '.$options['conditions'];
    }

    if(isset($options['group']) && !empty($options['group']))
    {
      $query .= ' GROUP BY '.$this->sqlEscape($options['group']);
    }

    if(isset($options['order']) && !empty($options['order']))
    {
      $query .= ' ORDER BY '.$this->sqlEscape($options['order']);
    }

    if(isset($options['limit']) && !empty($options['limit']))
    {
      $query .= ' LIMIT '.$this->sqlEscape($options['limit']);
    }

    $useDB = null;
    if(isset($options['useDB']))
    {
      $useDB = $options['useDB'];
    }

    $results = $this->_getRows($query,$useDB);

    if(isset($results[0]) && array_key_exists('timestamp', $results[0]))
    {
      foreach($results as $key=>$value)
      {
        $value['time'] = $this->calculateTimestamp($value['timestamp']);
        $results[$key] = $value;
      }
    }

    return $results;
  }

  public function sqlEscape($string)
  {
    return $this->conn->escapeString($string);
  }

  private function _getRows($query,$useDB = null)
  {
    if($useDB)
    {
      try
      {
        $conn = new SQLite3($useDB);
      }
      catch(Exception $exception)
      {
        die($exception->getMessage());
      }

      $conn->busyTimeout(5000);
      $conn->exec(
        'PRAGMA cache_size = 131072;'.
        'PRAGMA synchronous=OFF;'.
        'PRAGMA temp_store=2;'
      );
    } else {
      $conn = $this->conn;
    }

    $result = $conn->query($query);
    $rows = [];
    while($row = $result->fetchArray(SQLITE3_ASSOC))
    {
      $rows[] = $row;
    }

    return $rows;
  }

  public function knownAddresses()
  {
    $addresses = $this->readAddresses();

    $aliases = $this->find(
      'aliases',
      array(
        'fields'=>array('alias','uri'),
        'conditions'=>'uri LIKE "acct:%@nhz"'
      )
    );

    $addrAliases = array();
    foreach($aliases as $alias)
    {
      $acct = str_replace(array('acct:','@nhz'),'',$alias['uri']);
      if(!is_numeric($acct))
      {
        $acct = $this->fromBlockchain(array('requestType'=>'rsConvert','account'=>$acct))->account;
      }

      $addrAliases[$acct] = htmlspecialchars($alias['alias']);
    }

    foreach($addrAliases as $key=>$value)
    {
      if(!isset($addresses[$key]))
      {
        $addresses[$key] = $value;
      }
    }

    return $addresses;
  }

  public function readAddresses()
  {
    $lines = file('data'.DS.'addresses');
    $addresses = array();
    foreach($lines as $line)
    {
      $tmp = explode(':',$line);
      $addresses[$tmp[0]] = $tmp[1];
    }

    return $addresses;
  }

  public function getBalances()
  {
    $data = $this->find(
      'balances',
      array(
        'order'=>'balance DESC'
      )
    );
    /*$query = "SELECT
      t.recipientrs as rs, t.recipient as id,
      (
        (SELECT SUM(t1.amount) FROM transactions t1 WHERE t1.recipient=t.recipient)-IFNULL(
          (SELECT (SUM(t2.amount)+SUM(t2.fee)) FROM transactions t2 WHERE t2.sender=t.recipient),
          0
        )
      )+IFNULL(
        (SELECT SUM(b.totalfee) FROM blockdata b WHERE b.generator=t.recipient AND b.totalfee<>'0'),
        0
      ) balance FROM transactions t GROUP BY t.recipient ORDER BY balance DESC;";
    $data = $this->_getRows($query);*/

    return $data;
  }

  public function getServerApiCache($address)
  {
    $limit = time() - 300;
    $data = $this->find(
      'apicache',
      array(
        'useDB'=>'./data/apicache.db',
        'conditions'=>'ip_address = \''.$this->sqlEscape($address).'\' AND timestamp >= '.$limit
      )
    );

    if(!empty($data[0]))
    {
      $obj = new stdClass();
      foreach($data[0] as $key => $value)
      {
        $obj->$key = $value;
      }

      return $obj;
    }

    return false;
  }

  public function saveServerApiCache($data)
  {
    try
    {
      $this->apiconn = new SQLite3('./data/apicache.db');
    }
    catch(Exception $exception)
    {
      die($exception->getMessage());
    }

    $this->apiconn->busyTimeout(5000);
    global $config;
    $this->apiconn->exec(
      'PRAGMA cache_size = '.$config['dbcachesize'].';'.
      'PRAGMA synchronous=OFF;'.
      'PRAGMA temp_store=2;'
    );

    $this->apiconn->exec("INSERT OR REPLACE INTO apicache (ip_address, timestamp, last_hallmark, updated_at, last_offline, last_online, full_uptime, last_payout, last_payout_uptime) VALUES ('".$data->ip_address."', '".time()."', '".$data->last_hallmark."', '".$data->updated_at."', '".$data->last_offline."', '".$data->last_online."', '".$data->full_uptime."', '".$data->last_payout."', '".$data->last_payout_uptime."');");
    return true;
  }

  public function getLastBlockHeight()
  {
    $state = $this->fromBlockchain(array(
      'requestType'=>'getBlockchainStatus'
    ));

    $last = $state->numberOfBlocks - 1;
    return $last;
  }
}
?>
