<?php

/* Copyright 2015 Tristian Flanagan
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
*/

/* Error Handling */
class QuickBaseError extends Exception {

	public function __construct($code = 0, $message = 'No error', $detail = ''){
		parent::__construct($message.$detail, $code);
	}

	public function __toString(){
		return __CLASS__.': ['.$this->code.'] '.$this->message;
	}

}

class QuickBase {

	private $defaults = array(
		'realm' => 'www',
		'domain' => 'quickbase.com',
		'useSSL' => true,

		'username' => '',
		'password' => '',
		'appToken' => '',
		'ticket' => '',

		'flags' => array(
			'useXML' => true,
			'msInUTC' => true,
			'includeRids' => true,
			'returnPercentage' => false,
			'fmt' => 'structured',
			'encoding' => 'UTF-8'
		),

		'status' => array(
			'errcode' => 0,
			'errtext' => 'No error',
			'errdetail' => ''
		),

		'maxErrorRetryAttempts': 3,
		// 'connectionLimit' => 10,
		// 'errorOnConnectionLimit' => false
	);

	public function __construct($options){
		$this->settings = array_merge_recursive($this->defaults, $options);
	}

	public function api($action, $options){

	}

	/* Helpers */
	protected function xml2js($xml){
		$root = func_num_args() > 1 ? false : true;
		$js = array();

		if($root){
			$nodeName = $xml->getName();
			$js[$nodeName] = array();

			array_push($js[$nodeName], $this->xml2js($xml, true));

			return $json_encode($js);
		}

		if(count($xml->attributes()) > 0){
			$js['$'] = array();

			foreach($xml->attributes() as $key => $value){
				$js['$'][$key] = (string) $value;
			}
		}

		$textValue = trim((string) $xml);

		if(count($textValue) > 0){
			$js['_'] = $textValue;
		}

		foreach($xml->children() as $child){
			$childName = $child->getName();

			if(!array_key_exists($childName, $js)){
				$js[$childName] = array();
			}

			array_push($js[$childName], $this->xml2js($child, true));
		}

		return $js;
	}

}

?>