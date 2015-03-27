<?php 
defined('C5_EXECUTE') or die("Access Denied.");

class EsitefulMultilingualPageSync extends QueueableJob {
	public $jQueueBatchSize = 20;
	public $jSupportsQueue = true;

	public $forceUpdate = true;
	public $echoLogs = false;

	/** The end-of-line terminator.
	* @var string
	*/
	const EOL = "\n";

	/** Returns the job name.
	* @return string
	*/
	public function getJobName() {
		return t('eSiteful Multilingual Page Sync');
	}

	/** Returns the job description.
	* @return string
	*/
	public function getJobDescription() {
		return t('Syncs the necessary data between the main Multinlingual and eSiteful Multilingual packages.');
	}

	public function getJobTmpDataPath(){
		$fh = Loader::helper('file');
		return $fh->getTemporaryDirectory().'/'.basename(__FILE__).'.tmp.encrypted';
	}

	public function getJobTmpData(){
		if(is_object($this->tmpData)){
			return $this->tmpData;
		}
		$fh = Loader::helper('file');
		$eh = Loader::helper('encryption');
		return $this->tmpData = unserialize($eh->decrypt($fh->getContents($this->getJobTmpDataPath())));
	}

	public function setJobTmpData(&$data=NULL){
		if(is_null($data)){
			$data = $this->getJobTmpData();
		}else{
			$this->tmpData = $data;
		}		
		$fh = Loader::helper('file');
		$eh = Loader::helper('encryption');
		file_put_contents($this->getJobTmpDataPath(), $eh->encrypt(serialize($data)));
	}

	public function deleteJobTmpData(){
		$fh = Loader::helper('file');
		$fh->removeAll($this->getJobTmpDataPath());
	}

	/** Executes the job.
	* @return string Returns a string describing the job result in case of success.
	* @throws Exception Throws an exception in case of errors.
	*/
	public function start(Zend_Queue $q) {
		Cache::disableCache();
		Cache::disableLocalCache();
		
		$txt = Loader::helper('text');
		$tmpData = array(
			'startTime'=>time(),
			'logs'=>array()
		);

		try {
			$db = Loader::db();
			
			$message = array();

			$tmpData['echoLogs'] = isset($_REQUEST['echoLogs']) ? $_REQUEST['echoLogs'] == 1 : $this->echoLogs;
			$tmpData['forcePush'] = isset($_REQUEST['forcePush']) ? $_REQUEST['forcePush'] == 1 : 0;
			$tmpData['forcePull'] = isset($_REQUEST['forcePull']) ? $_REQUEST['forcePull'] == 1 : 0;

			$tmpData['updated'] = 0;

			$langPagesHelper = Loader::helper('esiteful_multilingual/pages', 'esiteful_multilingual');

			$q->send(serialize(array(
				'task'=>'preclean'
			)));

			Loader::model('page_list');
			$pageList = new PageList();
			$pages = $pageList->get(999999);

			foreach($pages as $page){
				if($page->getCollectionID() == HOME_CID) continue;
				
				$q->send(serialize(array(
					'task'=>'page',
					'cID'=>$page->getCollectionID()
				)));	
			}


		}
		catch(Exception $x) {
			if(is_array($message)){
				$messsage = implode("\n", $message)	;
			}else{
				$message = '';	
			}
			$this->log($x);
			$this->markCompleted(self::JOB_ERROR_EXCEPTION_GENERAL, $message);;
		}
		
		$this->setJobTmpData($tmpData);
	}

	public function finish(Zend_Queue $q){

		$tmpData = $this->getJobTmpData();
		$tmpData['finishTime'] = time();
		$tmpData['totalTime'] = $tmpData['finishTime'] - $tmpData['startTime'];

		$total = count($tmpData['json']);
		$logs = count($tmpData['logs']) ? implode("\n", $tmpData['logs']) : '';
		$summary = t('Synchronized %s pages in %s second(s).', $tmpData['total'], ($tmpData['totalTime']));

		$completeLog[] = t("Started at %s", date(DATE_APP_GENERIC_MDYT_FULL_SECONDS, $tmpData['startTime']));
		$completeLog[] = t("Finished at %s", date(DATE_APP_GENERIC_MDYT_FULL_SECONDS, $tmpData['finishTime']));
		$completeLog[] = $summary;
		$completeLog[] = "\n";
		
		$logEntries = Log::getList('', $this->getLogNamespace(), 999999);
		foreach(array_reverse($logEntries) as $logEntry){
			$completeLog[] = $logEntry->getTimestamp().': '.$logEntry->getText()."\n";
		}

		Log::addEntry(implode("\n", $completeLog), $this->getJobName());
		Log::clearByType($this->getLogNamespace());

		$this->deleteJobTmpData();

		return $summary.' '.t('See log for details.');
	}

	public function getLogNamespace(){
		$tmpData = $this->getJobTmpData();
		return $this->getJobName().' '.date('c', $tmpData['startTime']);
	}
	
	public function processQueueItem(Zend_Queue_Message $msg){
		$q = $msg->getQueue();
		$msg->body = unserialize($msg->body);

		if($msg->body['task'] == 'page'){
			return $this->processQueueItem_page($msg);
		}

		$this->markCompleted(self::JOB_ERROR_EXCEPTION_GENERAL, t('Unrecognized item passed to queue.'));

	}

	protected function processQueueItem_preclean(Zend_Queue_Message $msg){
		$db = Loader::db();

		//Delete non-existent page references
		$pageAttrKey = CollectionAttributeKey::getByHandle('language');
		$db->Execute('delete from atLanguageAttributeRelations where akID = ? and oID not in (select cID from Collections)', array($pageAttrKey->getAttributeKeyID()));

		//Delete non-existent file references
		$fileAttrKey = FileAttributeKey::getByHandle('language');
		$db->Execute('delete from atLanguageAttributeRelations where akID = ? and oID not in (select cID from Files)', array($fileAttrKey->getAttributeKeyID()));
	}

	protected function processQueueItem_page(Zend_Queue_Message $msg){
		
		$q = $msg->getQueue();

		$tmpData = $this->getJobTmpData();
		$message = array();

		$tmpData['total']++;
		$page = Page::getByID($msg->body['cID']);
		$pageOrigLang = $page->getAttribute('language');
		$esfLangPagesHelper = Loader::helper('esiteful_multilingual/pages', 'esiteful_multilingual');
		
		//Set language attributes of pages according to place in sitemap
		$esfLangPagesHelper->autoLanguageAttribute($page);
		
		$attrValue = $page->getAttributeValueObject('language');	
		if(is_object($attrValue)){
			$attrValue->setCollection($page);
			$attrKey = $attrValue->getAttributeKey();
			$attrController = $attrKey->getController();
			$attrController->setAttributeValue($attrValue);

			if(MultilingualSection::isAssigned($page) || $tmpData['forcePull']){
				//Get relationships from Multilingual package
				$attrController->pullPageRelations();
			}else if(!$tmpData['forcePull'] || $tmpData['forcePush']){
				//Get relationshps from attribute
				$attrController->pushPageRelations();
			}
			
		}

		$isUpdated = $pageOrigLang != $page->getAttribute('language');
		if($isUpdated){

			$this->log('(%s) %s', $page->getAttribute('language'), $page->getCollectionName());
			$tmpData['updated']++;
		}
		
		$this->setJobTmpData($tmpData);
		$this->log(implode("\n", $message));
	}


	public function log($msg, $system = true, $echo = false){
		if(empty($msg)) return;
		elseif(!is_string($msg)) $msg = var_export($msg, true);

		$tmpData = $this->getJobTmpData();
		//Write to C5 log
		if($system){
			Log::addEntry($msg, $this->getLogNamespace());
		}
		//Echo log
		if(($echo !== false && $tmpData['echoLogs']) || $echo === true){
			echo date('c').': '. $msg."\n"; 
		}
	}

}