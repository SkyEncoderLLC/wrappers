<?php

/**
 * SkyEncoder
 * 
 * The Sky Encoder API wrapper for PHP 5.3+.
 * 
 * @author Sky Encoder
 * @copyright 2014
 * @access public
 */
class SkyEncoder {
	
	const API_ENDPOINT = 'http://skyencoder.com/api/1.0/';
	const STATUS_ENDPOINT = 'http://status.skyencoder.com';
	
	protected $apiKey = false;
	protected $apiToken = false;
	protected $curl = false;
	
	public $lastResponse = false;
	public $lastRawResponse = false;
	public $lastHttpCode = false;
	
	/**
	 * SkyEncoder::__construct()
	 * 
	 * The class constructor. Provided a valid API Key and Secret Token. You can
	 * find your API tokens in the API section of the Your Account page on our
	 * website at http://skyencoder.com/your-account
	 * 
	 * @param string $apiKey
	 * @param string $apiToken
	 * @return void
	 */
	public function __construct($apiKey, $apiToken) {
		$this->apiKey = $apiKey;
		$this->apiToken = $apiToken;
	}
	
	/**
	 * SkyEncoder::getJobs()
	 * 
	 * Get a list of your account jobs. The list is sorted by most recent by default.
	 * 
	 * @link http://docs.skyencoder.com/v1.0/docs/list
	 * @param mixed $config
	 * @return mixed standard response
	 */
	public function getJobs($config=array()) {
		/* set defaults */
		$config = (empty($config) || !is_array($config)) ? array() : $config;
		$config['filters'] = (!isset($config['filters']) || !is_array($config['filters'])) ? array() : $config['filters'];
		$config['sort'] = (!isset($config['sort']) || !is_array($config['sort'])) ? array('events.created'=>-1) : $config['sort'];
		
		/* make the request */
		return $this->_call('jobs/list', $config);
	}
	
	/**
	 * SkyEncoder::createJob()
	 * 
	 * Create a new encoding job.
	 * 
	 * @link http://docs.skyencoder.com/v1.0/docs/create
	 * @param mixed $config
	 * @return mixed standard response
	 */
	public function createJob($config=array()) {
		/* set defaults */
		$config = (empty($config) || !is_array($config)) ? array() : $config;
		
		/* error check */
		switch (true) {
			case (!$this->valid(array('file', 'outputs'), $config)):
				return $this->error('Invalid file or output paramter. Please ensure that you have included both.');
				break;
			
			case (!is_string($config['file'])):
				return $this->error('Invalid file paramter. Please ensure that you have provided a valid string URL for the source file.');
				break;
			
			case (!is_array($config['outputs'])):
				return $this->error('Invalid output parameter. Please ensure that you have provided an array of output tasks.');
				break;
		}
		
		/* make the request */
		return $this->_call('jobs/create', $config);
	}
	
	/**
	 * SkyEncoder::getJob()
	 * 
	 * Get the full job details with associated output tasks.
	 * 
	 * @link http://docs.skyencoder.com/v1.0/docs/jobsdetails
	 * @param mixed $jobId
	 * @return mixed standard response
	 */
	public function getJob($jobId) {
		/* check the id */
		if (empty($jobId) || !is_string($jobId)) {
			return $this->error('Invalid job ID provided. The ID must be a string and not empty.');
		}
		
		/* make the request */
		return $this->_call('jobs/details',  array('id'=>$jobId));
	}
	
	/**
	 * SkyEncoder::getStatus()
	 * 
	 * Use the status service to get the realtime output task
	 * progress and status.
	 * 
	 * @link http://docs.skyencoder.com/v1.0/docs/status
	 * @param mixed $taskId
	 * @return mixed standard response
	 */
	public function getStatus($taskId) {
		/* check the id */
		if (empty($taskId) || (!is_string($taskId) && !is_array($taskId))) {
			return $this->error('Invalid task ID provided. The ID must be a string or an array of strings.');
		}
		
		/* convert strings into an array for standardization */
		if (is_string($taskId)) {
			$taskId = array($taskId);
		}
		
		/* make the request */
		return $this->_call('', array(), self::STATUS_ENDPOINT.'?ids='.implode(',', $taskId));
	}
	
	/**
	 * SkyEncoder::cancelTask()
	 * 
	 * Cancel an individual task.
	 * 
	 * @link http://docs.skyencoder.com/v1.0/docs/cancel-1
	 * @param string $taskId
	 * @return mixed standard response
	 */
	public function cancelTask($taskId) {
		/* check the id */
		if (empty($taskId) || !is_string($taskId)) {
			return $this->error('Invalid job ID provided. The ID must be a string and not empty.');
		}
		
		/* make the request */
		return $this->_call('tasks/cancel', array('id'=>$taskId));
	}
	
	/**
	 * SkyEncoder::cancelJob()
	 * 
	 * Cancel all pending or processing jobs.
	 * 
	 * @link http://docs.skyencoder.com/v1.0/docs/cancel
	 * @param string $jobId
	 * @return mixed standard response
	 */
	public function cancelJob($jobId) {
		/* check the id */
		if (empty($jobId) || !is_string($jobId)) {
			return $this->error('Invalid job ID provided. The ID must be a string and not empty.');
		}
		
		/* make the request */
		return $this->_call('jobs/cancel', array('id'=>$jobId));
	}
	
	/**
	 * SkyEncoder::error()
	 * 
	 * @param string $msg
	 * @return mixed standard response
	 */
	public function error($msg='') {
		return array('error'=>1, 'msg'=>$msg, 'data'=>array());
	}
	
	/**
	 * SkyEncoder::success()
	 * 
	 * @param mixed $data
	 * @return mixed standard response
	 */
	public function success($data=array()) {
		return array('error'=>0, 'msg'=>'', 'data'=>$data);
	}
	
	/**
	 * SkyEncoder::valid()
	 * 
	 * @param mixed $check
	 * @param mixed $set
	 * @return mixed standard response
	 */
	public function valid($check, $set) {
		/* make sure set is an array and check isn't empty */
		if (empty($set) || !is_array($set) || empty($check)) {
			return false;
		}
		
		/* convert a single string to an array to standardize */
		if (is_string($check)) {
			$check = array($check);
		}
		
		/* check the list */
		foreach ($check as $i=>$key) {
			$key = (string) $key;
			
			if (!isset($set[$key]) || (is_string($set[$key]) && strlen($set[$key]) == 0) || (is_array($set[$key]) && empty($set[$key]))) {
				return false;
			}
		}
		
		return true;
	}
	
	/**
	 * SkyEncoder::_call()
	 * 
	 * @param mixed $method
	 * @param mixed $vars
	 * @param string $endpoint
	 * @return
	 */
	protected function _call($method, $vars=array(), $endpoint='') {
		/* set the endpoint url */
		$url = (!empty($endpoint)) ? rtrim($endpoint, '/').ltrim($method, '/') : self::API_ENDPOINT.ltrim($method, '/');
		
		/* create the curl request */
		$this->curl = curl_init($url);
		curl_setopt($this->curl, CURLOPT_POST, true);
		curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($this->curl, CURLOPT_USERAGENT, 'Sky Encoder PHP API Wrapper');
		curl_setopt($this->curl, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
		curl_setopt($this->curl, CURLOPT_HTTPHEADER, array('SE-Secret-Key: '.$this->apiKey, 'SE-API-Token: '.$this->apiToken));
		
		/* add in the post body if we have any variables */
		if (!empty($vars)) {
			curl_setopt($this->curl, CURLOPT_POSTFIELDS, json_encode($vars));
		}
		
		/* set the header */
		$this->lastHttpCode = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
		
		/* process it */
		$this->lastRawResponse = curl_exec($this->curl);
		curl_close($this->curl);
		
		/* parse it */
		$this->lastResponse = json_decode($this->lastRawResponse, true);
		
		/* error check */
		if (empty($this->lastResponse)) {
			return array('error'=>1, 'msg'=>'Invalid JSON response.', 'url'=>$url, 'response'=>$this->lastRawResponse, 'data'=>array());
		}
		
		/* done */
		return $this->lastResponse;
	}
	
}

?>