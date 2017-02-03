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

namespace QuickBase {

class QuickBase {

	const VERSION_MAJOR = 2;
	const VERSION_MINOR = 0;
	const VERSION_PATCH = 4;

	private $defaults = array(
		'realm' => 'www',
		'domain' => 'quickbase.com',
		'useSSL' => true,

		'username' => '',
		'password' => '',
		'appToken' => '',
		'userToken' => '',
		'ticket' => '',

		'flags' => array(
			'useXML' => true,
			'msInUTC' => true,
			'includeRids' => true,
			'returnPercentage' => false,
			'fmt' => 'structured',
			'encoding' => 'ISO-8859-1',
			'dbidAsParam' => false
		),

		'status' => array(
			'errcode' => 0,
			'errtext' => 'No error',
			'errdetail' => ''
		),

		'maxErrorRetryAttempts' => 3,
		'responseAsObject' => false
	);

	public $debug = false;
	public $mch;
	public $chs;

	public function __construct($options = array()){
		$this->settings = array_replace_recursive($this->defaults, $options);

		$this->mch = curl_multi_init();
		$this->chs = array();

		return $this;
	}

	public function __destruct(){
		for($i = 0, $l = count($this->chs); $i < $l; ++$i){
			curl_close($this->chs[$i]);
		}

		curl_multi_close($this->mch);
	}

	// final public function api($actions = array()){
	final public function api($action, $options = array()){
		if(!is_array($action)){
			$actions = array(
				array(
					'action' => $action,
					'options' => $options
				)
			);
		}else{
			$actions = $action;
		}

		$nActions = count($actions);
		$queries = array();
		$results = array();

		for($i = 0; $i < $nActions; ++$i){
			if(!isset($this->chs[$i])){
				$this->chs[] = self::genCH();
			}

			$query = new QuickBaseQuery($this, $actions[$i]['action'], $actions[$i]['options']);

			$query
				->addFlags()
				->processOptions()
				->actionRequest()
				->constructPayload()
				->prepareCH($this->chs[$i]);

			curl_multi_add_handle($this->mch, $this->chs[$i]);

			if($this->debug){
				var_dump('Executing QB Query', $query->getPayload());
			}

			$queries[] = $query;
		}

		do {
			curl_multi_exec($this->mch, $running);
			curl_multi_select($this->mch);
		}while($running > 0);

		for($i = 0; $i < $nActions; ++$i){
			try {
				$queries[$i]
					->processCH()
					->checkForAndHandleError()
					->actionResponse()
					->finalize();

				$results[] = $queries[$i]->response;

				curl_multi_remove_handle($this->mch, $this->chs[$i]);
			}catch(\Exception $err){
				curl_multi_remove_handle($this->mch, $this->chs[$i]);

				throw $err;
			}
		}

		if($nActions === 1){
			return $results[0];
		}

		return $results;
	}

	final public static function genCH(){
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

		return $ch;
	}

}

class QuickBaseError extends \Exception {

	protected $details;

	public function __construct($code = 0, $message = 'No error', $details = ''){
		parent::__construct($message, $code);

		$this->details = $details;

		return $this;
	}

	public function __toString(){
		return implode('', array(
			__CLASS__,
			': [',
			$this->code,
			'] ',
			$this->message,
			$this->details === '' ? '' : '. '.$this->details
		));
	}

	final public function getDetails(){
		return $this->details;
	}

}

class QuickBaseQuery {

	public $parent;
	public $action = '';
	public $settings = array();
	public $options = array();
	public $response = array();

	private $nErrors = 0;

	protected $payload = '';

	public function __construct(&$parent, $action = '', $options = array()){
		$this->parent = $parent;
		$this->settings = array_replace_recursive(array(), $this->parent->settings);
		$this->action = $action;
		$this->options = $options;

		return $this;
	}

	final public function actionRequest(){
		if(method_exists('\QuickBase\QuickBaseRequest', $this->action)){
			QuickBaseRequest::{$this->action}($this);
		}

		return $this;
	}

	final public function actionResponse(){
		if(method_exists('\QuickBase\QuickBaseResponse', $this->action)){
			QuickBaseResponse::{$this->action}($this, $this->response);
		}

		return $this;
	}

	final public function addFlags(){
		if(!isset($this->options['msInUTC']) && $this->settings['flags']['msInUTC']){
			$this->options['msInUTC'] = 1;
		}

		if(!isset($this->options['appToken']) && $this->settings['appToken']){
			$this->options['appToken'] = $this->settings['appToken'];
		}

		if(!isset($this->options['userToken']) && $this->settings['userToken']){
			$this->options['usertoken'] = $this->settings['userToken'];
		}

		if(!isset($this->options['ticket']) && $this->settings['ticket']){
			$this->options['ticket'] = $this->settings['ticket'];
		}

		if(!isset($this->options['encoding']) && $this->settings['flags']['encoding']){
			$this->options['encoding'] = $this->settings['flags']['encoding'];
		}

		if(!isset($this->options['responseAsObject']) && $this->settings['responseAsObject']){
			$this->options['responseAsObject'] = $this->settings['responseAsObject'];
		}

		return $this;
	}

	final public function constructPayload(){
		$this->payload = '';

		if($this->settings['flags']['useXML']){
			$xmlDoc = new \SimpleXMLElement(implode('', array(
				'<?xml version="1.0" encoding="',
				$this->options['encoding'],
				'"?>',
				'<qdbapi></qdbapi>'
			)));

			$this->arr2Xml($this->options, $xmlDoc);

			$this->payload = $xmlDoc->asXML();
		}else{
			foreach($this->options as $key => $value){
				if($key === 'field'){
					$this->payload .= '&_fid_'.$value['fid'].'='.urlencode($value['value']);
				}else{
					$this->payload .= '&'.$key.'='.urlencode($value);
				}
			}
		}

		return $this;
	}

	final public function checkForAndHandleError(){
		if(isset($this->response['errcode']) && $this->response['errcode'] != $this->settings['status']['errcode']){
			++$this->nErrors;

			if($this->nErrors <= $this->parent->settings['maxErrorRetryAttempts'] && $this->response['errcode'] == 4 && isset($this->parent->settings['username']) && isset($this->parent->settings['password'])){
				try {
					$newTicket = $this->parent->api('API_Authenticate', array(
						'username' => $this->parent->settings['username'],
						'password' => $this->parent->settings['password']
					));

					$this->parent->settings['ticket'] = $newTicket['ticket'];
					$this->settings['ticket'] = $newTicket['ticket'];
					$this->options['ticket'] = $newTicket['ticket'];

					return $this
						->constructPayload()
						->prepareCH()
						->processCH()
						->checkForAndHandleError();
				}catch(Exception $newTicketErr){
					++$this->nErrors;

					if($this->nErrors <= $this->parent->settings['maxErrorRetryAttempts']){
						return $this->checkForAndHandleError();
					}

					throw $newTicketErr;
				}
			}

			throw new QuickBaseError($this->response['errcode'], $this->response['errtext'], isset($this->response['errdetail']) ? $this->response['errdetail'] : '');
		}

		return $this;
	}

	final public function finalize(){
		if(isset($this->options['responseAsObject']) && $this->options['responseAsObject']){
			QuickBaseQuery::arr2Obj($this->response);
		}

		return $this;
	}

	final public function getPayload(){
		return $this->payload;
	}

	final public function prepareCH(&$ch){
		if(isset($this->ch) && !$ch){
			$ch = $this->ch;
		}

		curl_setopt($ch, CURLOPT_URL, implode('', array(
			$this->settings['useSSL'] ? 'https://' : 'http://',
			$this->settings['realm'],
			'.',
			$this->settings['domain'],
			'/db/',
			isset($this->options['dbid']) && !$this->settings['flags']['dbidAsParam'] ? $this->options['dbid'] : 'main',
			'?act=',
			$this->action,
			$this->settings['flags']['useXML'] ? '' : $this->payload
		)));

		curl_setopt($ch, CURLOPT_PORT, $this->settings['useSSL'] ? 443 : 80);

		if($this->settings['flags']['useXML']){
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				'POST /db/'.(isset($this->options['dbid']) ? $this->options['dbid'] : 'main').' HTTP/1.0',
				'Content-Type: text/xml;',
				'Accept: text/xml',
				'Cache-Control: no-cache',
				'Pragma: no-cache',
				'Content-Length: '.strlen($this->payload),
				'QUICKBASE-ACTION: '.$this->action
			));
			curl_setopt($ch, CURLOPT_POSTFIELDS, $this->payload);
		}else{
			curl_setopt($ch, CURLOPT_POST, false);
			curl_setopt($ch, CURLOPT_HTTPHEADER, false);
			curl_setopt($ch, CURLOPT_POSTFIELDS, false);
		}

		$this->ch = $ch;

		return $this;
	}

	final public function processCH(){
		$response = curl_multi_getcontent($this->ch);

		$errno = curl_errno($this->ch);
		$error = curl_error($this->ch);

		$headerSize = curl_getinfo($this->ch, CURLINFO_HEADER_SIZE);

		if($response === false || $response === ''){
			++$this->nErrors;

			if($this->nErrors <= $this->settings['maxErrorRetryAttempts']){
				return $this->prepareCH()->processCH();
			}

			throw new QuickBaseError($errno, $error);
		}

		$headers = substr($response, 0, $headerSize);
		$body = substr($response, $headerSize);

		self::parseCURLHeaders($headers);

		if($headers['Content-Type'] === 'application/xml'){
			$this->response = array();

			$xml = new \SimpleXmlIterator($body);

			$this->xml2Arr($xml, $this->response);

			$this->cleanXml2Arr($this->response);
		}else{
			$this->response = $body;
		}

		return $this;
	}

	final public function processOptions(){
		if(isset($this->options['fields'])){
			$this->options['field'] = $this->options['fields'];

			unset($this->options['fields']);
		}

		foreach($this->options as $key => $value){
			if(method_exists('\QuickBase\QuickBaseOption', $key)){
				$this->options[$key] = QuickBaseOption::{$key}($value);
			}
		}

		return $this;
	}

	/* Helpers */
	final public static function arr2Obj(&$arr, $return = false){
		$obj = new \stdClass;

		foreach($arr as $key => $val){
			if(!empty($key)){
				if(is_array($val)){
					$obj->{$key} = self::arr2Obj($val, true);
				}else{
					$obj->{$key} = $val;
				}
			}
		}

		if($return){
			return $obj;
		}

		$arr = $obj;
	}

	final public static function arr2Xml($arr, &$xml){
		if(is_array($arr)){
			foreach($arr as $key => $value){
				if($key === '$'){
					foreach($value as $attr => $attrValue){
						$xml->addAttribute($attr, $attrValue);
					}

					continue;
				}

				if($key === '_'){
					$xml[0] = $value;

					continue;
				}

				if(is_int($key)){
					if($key === 0){
						self::arr2Xml($arr[$key], $xml);
					}else{
						$name = $xml->getName();

						$child = $xml->xpath('..')[0]->addChild($name);

						self::arr2Xml($arr[$key], $child);
					}

					continue;
				}

				$child = $xml->addChild($key);

				self::arr2Xml($arr[$key], $child);
			}
		}else{
			$xml[0] = $arr;
		}
	}

	final public static function cleanXml2Arr(&$arr){
		if(is_array($arr)){
			foreach($arr as $key => $value){
				if(is_array($value) && count($value) === 1){
					$arr[$key] = $value[0];
				}

				if(is_array($arr[$key]) && count($arr[$key]) === 1){
					$singulars = array(
						substr($key, 0, -1),
						substr($key, 0, -3).'y'
					);

					$i = array_search(array_keys($arr[$key])[0], $singulars);

					if($i !== false){
						$arr[$key] = $arr[$key][$singulars[$i]];
					}
				}

				if(is_array($arr[$key])){
					if(isset($arr[$key]['_']) && isset($arr[$key]['BR']) && count($arr[$key])){
						$arr[$key] = $arr[$key]['_'];
					}else{
						self::cleanXml2Arr($arr[$key]);
					}
				}

				if(is_numeric($arr[$key]) && (substr($arr[$key], 0, 1) !== '0' || $arr[$key] === '0')){
					$arr[$key] = (double) $arr[$key];
				}else
				if(is_string($arr[$key])){
					if(strtolower($arr[$key]) === 'true'){
						$arr[$key] = true;
					}else
					if(strtolower($arr[$key]) === 'false'){
						$arr[$key] = false;
					}else{
						$arr[$key] = trim($arr[$key]);
					}
				}
			}
		}
	}

	final public static function parseCURLHeaders(&$headers){
		$newHeaders = array();
		$headers = explode("\r\n", $headers);

		foreach($headers as $header){
			$i = strpos($header, ':');

			$newHeaders[substr($header, 0, $i)] = substr($header, $i + 2);
		}

		$headers = $newHeaders;
	}

	final public static function xml2Arr($xml, &$arr){
		for($xml->rewind(); $xml->valid(); $xml->next()){
			$key = $xml->key();

			if(!array_key_exists($key, $arr)){
				$arr[$key] = array();
			}

			$node = trim(strval($xml->current()));

			if($xml->hasChildren()){
				if($node !== ''){
					$node = array(
						'_' => $node
					);
				}else{
					$node = array();
				}

				self::xml2Arr($xml->current(), $node);
			}

			$attrs = $xml->current()->attributes();

			if($attrs){
				if(!is_array($node)){
					$node = array(
						'_' => $node
					);
				}

				foreach($attrs as $attrKey => $attrValue){
					$node[$attrKey] = strval($attrValue);
				}
			}

			$arr[$key][] = $node;
		}
	}

}

class QuickBaseRequest {

	/* NOTICE:
	 * When an action is a simple return of the value given, comment the function out.
	 * This will increase performance, cutting out an unnecessary function execution.
	*/

	// final public static function API_AddField(&$query){ }
	// final public static function API_AddGroupToRole(&$query){ }
	// final public static function API_AddRecord(&$query){ }
	// final public static function API_AddReplaceDBPage(&$query){ }
	// final public static function API_AddSubGroup(&$query){ }
	// final public static function API_AddUserToGroup(&$query){ }
	// final public static function API_AddUserToRole(&$query){ }

	final public static function API_Authenticate(&$query){
		// API_Authenticate can only happen over SSL
		$query->settings['useSSL'] = true;
	}

	// final public static function API_ChangeGroupInfo(&$query){ }
	// final public static function API_ChangeManager(&$query){ }
	// final public static function API_ChangeRecordOwner(&$query){ }
	// final public static function API_ChangeUserRole(&$query){ }
	// final public static function API_CloneDatabase(&$query){ }
	// final public static function API_CopyGroup(&$query){ }
	// final public static function API_CopyMasterDetail(&$query){ }
	// final public static function API_CreateDatabase(&$query){ }
	// final public static function API_CreateGroup(&$query){ }
	// final public static function API_CreateTable(&$query){ }
	// final public static function API_DeleteDatabase(&$query){ }
	// final public static function API_DeleteField(&$query){ }
	// final public static function API_DeleteGroup(&$query){ }
	// final public static function API_DeleteRecord(&$query){ }

	final public static function API_DoQuery(&$query){
		if(!isset($query->options['returnPercentage']) && isset($query->settings['flags']['returnPercentage'])){
			$query->options['returnPercentage'] = $query->settings['flags']['returnPercentage'];
		}

		if(!isset($query->options['fmt']) && isset($query->settings['flags']['fmt'])){
			$query->options['fmt'] = $query->settings['flags']['fmt'];
		}

		if(!isset($query->options['includeRids']) && isset($query->settings['flags']['includeRids'])){
			$query->options['includeRids'] = $query->settings['flags']['includeRids'];
		}
	}

	// final public static function API_DoQueryCount(&$query){ }
	// final public static function API_EditRecord(&$query){ }
	// final public static function API_FieldAddChoices(&$query){ }
	// final public static function API_FieldRemoveChoices(&$query){ }
	// final public static function API_FindDBByName(&$query){ }
	// final public static function API_GenAddRecordForm(&$query){ }
	// final public static function API_GenResultsTable(&$query){ }
	// final public static function API_GetAncestorInfo(&$query){ }

	final public static function API_GetAppDTMInfo(&$query){
		$query->settings['flags']['dbidAsParam'] = true;
	}

	// final public static function API_GetDBPage(&$query){ }
	// final public static function API_GetDBInfo(&$query){ }
	// final public static function API_GetDBVar(&$query){ }
	// final public static function API_GetGroupRole(&$query){ }
	// final public static function API_GetNumRecords(&$query){ }
	// final public static function API_GetSchema(&$query){ }
	// final public static function API_GetRecordAsHTML(&$query){ }
	// final public static function API_GetRecordInfo(&$query){ }
	// final public static function API_GetRoleInfo(&$query){ }
	// final public static function API_GetUserInfo(&$query){ }
	// final public static function API_GetUserRole(&$query){ }
	// final public static function API_GetUsersInGroup(&$query){ }
	// final public static function API_GrantedDBs(&$query){ }
	// final public static function API_GrantedDBsForGroup(&$query){ }
	// final public static function API_GrantedGroups(&$query){ }
	// final public static function API_ImportFromCSV(&$query){ }
	// final public static function API_ProvisionUser(&$query){ }
	// final public static function API_PurgeRecords(&$query){ }
	// final public static function API_RemoveGroupFromRole(&$query){ }
	// final public static function API_RemoveSubgroup(&$query){ }
	// final public static function API_RemoveUserFromGroup(&$query){ }
	// final public static function API_RemoveUserFromRole(&$query){ }
	// final public static function API_RenameApp(&$query){ }
	// final public static function API_RunImport(&$query){ }
	// final public static function API_SendInvitation(&$query){ }
	// final public static function API_SetDBVar(&$query){ }
	// final public static function API_SetFieldProperties(&$query){ }
	// final public static function API_SetKeyField(&$query){ }
	// final public static function API_SignOut(&$query){ }
	// final public static function API_UploadFile(&$query){ }
	// final public static function API_UserRoles(&$query){ }

}

class QuickBaseResponse {

	/* NOTICE:
	 * When an action is a simple return of the value given, comment the function out.
	 * This will increase performance, cutting out an unnecessary function execution.
	*/

	// final public static function API_AddField(&$query, &$results){ }
	// final public static function API_AddGroupToRole(&$query, &$results){ }
	// final public static function API_AddRecord(&$query, &$results){ }
	// final public static function API_AddReplaceDBPage(&$query, &$results){ }
	// final public static function API_AddSubGroup(&$query, &$results){ }
	// final public static function API_AddUserToGroup(&$query, &$results){ }
	// final public static function API_AddUserToRole(&$query, &$results){ }

	final public static function API_Authenticate(&$query, &$results){
		$query->parent->settings['ticket'] = $results['ticket'];
		$query->settings['ticket'] = $results['ticket'];

		$query->parent->settings['username'] = $query->options['username'];
		$query->settings['username'] = $query->options['username'];

		$query->parent->settings['password'] = $query->options['password'];
		$query->settings['password'] = $query->options['password'];
	}

	// final public static function API_ChangeGroupInfo(&$query, &$results){ }
	// final public static function API_ChangeManager(&$query, &$results){ }
	// final public static function API_ChangeRecordOwner(&$query, &$results){ }
	// final public static function API_ChangeUserRole(&$query, &$results){ }
	// final public static function API_CloneDatabase(&$query, &$results){ }
	// final public static function API_CopyGroup(&$query, &$results){ }
	// final public static function API_CopyMasterDetail(&$query, &$results){ }
	// final public static function API_CreateDatabase(&$query, &$results){ }
	// final public static function API_CreateGroup(&$query, &$results){ }
	// final public static function API_CreateTable(&$query, &$results){ }
	// final public static function API_DeleteDatabase(&$query, &$results){ }
	// final public static function API_DeleteField(&$query, &$results){ }
	// final public static function API_DeleteGroup(&$query, &$results){ }
	// final public static function API_DeleteRecord(&$query, &$results){ }

	final public static function API_DoQuery(&$query, &$results){
		if(isset($query->options['fmt']) && $query->options['fmt'] === 'structured'){
			// QuickBase Support Case #480141
			if(isset($results['table']['queries'])){
				for($i = 0, $l = count($results['table']['queries']); $i < $l; ++$i){
					if(isset($results['table']['queries'][$i]['qydesc']) && is_array($results['table']['queries'][$i]['qydesc'])){
						$results['table']['queries'][$i]['qydesc'] = $results['table']['queries'][$i]['qydesc']['_'];
					}
				}
			}
			// End Quickbase Support Case #480141

			if(isset($results['table']['records'])){
				if(!is_array($results['table']['records']) && $results['table']['records'] === ''){
					$results['table']['records'] = array();
				}

				for($i = 0, $l = count($results['table']['records']); $i < $l; ++$i){
					$newRecord = array(
						'update_id' => $results['table']['records'][$i]['update_id']
					);

					if($query->options['includeRids']){
						$newRecord['rid'] = $results['table']['records'][$i]['rid'];
					}

					if(isset($results['table']['records'][$i]['f']['_'])){
						$results['table']['records'][$i]['f'] = array( $results['table']['records'][$i]['f'] );
					}

					foreach($results['table']['records'][$i]['f'] as $key => $field){
						if(isset($field['url'])){
							$value = array(
								'filename' => $field['_'],
								'url' => $field['url']
							);
						}else{
							$value = $field['_'];
						}

						$newRecord[$field['id']] = $value;
					}

					$results['table']['records'][$i] = $newRecord;
				}
			}

			if(isset($results['table']['variables']) && isset($results['table']['variables']['var'])){
				if(isset($results['table']['variables']['var']['_'])){
					$results['table']['variables']['var'] = array( $results['table']['variables']['var'] );
				}

				$vars = array();

				foreach($results['table']['variables']['var'] as $key => $value){
					$vars[$value['name']] = $value['_'];
				}

				$results['table']['variables'] = $vars;
			}

			if(isset($results['table']['chdbids'])){
				if(isset($results['table']['chdbids']['_'])){
					$results['table']['chdbids'] = array( $results['table']['chdbids'] );
				}

				for($i = 0, $l = count($results['table']['chdbids']); $i < $l; ++$i){
					$results['table']['chdbids'][$i] = array(
						'name' => $results['table']['chdbids'][$i]['name'],
						'dbid' => $results['table']['chdbids'][$i]['_']
					);
				}
			}

			if(isset($results['table']['lusers'])){
				if(isset($results['table']['lusers']['_'])){
					$results['table']['lusers'] = array( $results['table']['lusers'] );
				}

				for($i = 0, $l = count($results['table']['lusers']); $i < $l; ++$i){
					$results['table']['lusers'][$i] = array(
						'name' => $results['table']['lusers'][$i]['_'],
						'id' => $results['table']['lusers'][$i]['id']
					);
				}
			}
		}else{
			if(isset($results['record'])){
				$results['records'] = $results['record'];

				unset($results['record']);

				if(is_string($results['records'])){
					$results['records'] = array();
				}else
				if(isset($results['records']['update_id'])){
					$results['records'] = array( $results['records'] );
				}
			}else{
				$results['records'] = array();
			}

			if(is_string($results['variables'])){
				$results['variables'] = array();
			}

			if(is_string($results['chdbids'])){
				$results['chdbids'] = array();
			}
		}
	}

	// final public static function API_DoQueryCount(&$query, &$results){ }
	// final public static function API_EditRecord(&$query, &$results){ }
	// final public static function API_FieldAddChoices(&$query, &$results){ }
	// final public static function API_FieldRemoveChoices(&$query, &$results){ }
	// final public static function API_FindDBByName(&$query, &$results){ }
	// final public static function API_GenAddRecordForm(&$query, &$results){ }
	// final public static function API_GenResultsTable(&$query, &$results){ }
	// final public static function API_GetAncestorInfo(&$query, &$results){ }
	// final public static function API_GetAppDTMInfo(&$query, &$results){ }
	// final public static function API_GetDBPage(&$query, &$results){ }
	// final public static function API_GetDBInfo(&$query, &$results){ }
	// final public static function API_GetDBVar(&$query, &$results){ }
	// final public static function API_GetGroupRole(&$query, &$results){ }
	// final public static function API_GetNumRecords(&$query, &$results){ }

	final public static function API_GetSchema(&$query, &$results){
		// QuickBase Support Case #480141
		if(isset($results['table']['queries'])){
			for($i = 0, $l = count($results['table']['queries']); $i < $l; ++$i){
				if(isset($results['table']['queries'][$i]['qydesc']) && is_array($results['table']['queries'][$i]['qydesc'])){
					$results['table']['queries'][$i]['qydesc'] = $results['table']['queries'][$i]['qydesc']['_'];
				}
			}
		}
		// End Quickbase Support Case #480141

		if(isset($results['table']['chdbids'])){
			if(isset($results['table']['chdbids']['_'])){
				$results['table']['chdbids'] = array( $results['table']['chdbids'] );
			}

			for($i = 0, $l = count($results['table']['chdbids']); $i < $l; ++$i){
				$results['table']['chdbids'][$i] = array(
					'name' => $results['table']['chdbids'][$i]['name'],
					'dbid' => $results['table']['chdbids'][$i]['_']
				);
			}
		}

		if(isset($results['table']['variables']) && isset($results['table']['variables']['var'])){
			if(isset($results['table']['variables']['var']['_'])){
				$results['table']['variables']['var'] = array( $results['table']['variables']['var'] );
			}

			$vars = array();

			foreach($results['table']['variables']['var'] as $key => $value){
				$vars[$value['name']] = $value['_'];
			}

			$results['table']['variables'] = $vars;
		}
	}

	// final public static function API_GetRecordAsHTML(&$query, &$results){ }
	// final public static function API_GetRecordInfo(&$query, &$results){ }

	final public static function API_GetRoleInfo(&$query, &$results){
		if(isset($results['roles']['id'])){
			$results['roles'] = array( $results['roles'] );
		}

		for($i = 0, $l = count($results['roles']); $i < $l; ++$i){
			$results['roles'][$i]['access'] = array(
				'name' => $results['roles'][$i]['access']['_'],
				'id' => $results['roles'][$i]['access']['id']
			);
		}
	}

	// final public static function API_GetUserInfo(&$query, &$results){ }
	// final public static function API_GetUserRole(&$query, &$results){ }
	// final public static function API_GetUsersInGroup(&$query, &$results){ }

	final public static function API_GrantedDBs(&$query, &$results){
		if(isset($results['databases']['dbinfo'])){
			$results['databases'] = $results['databases']['dbinfo'];
		}
	}

	// final public static function API_GrantedDBsForGroup(&$query, &$results){ }
	// final public static function API_GrantedGroups(&$query, &$results){ }

	final public static function API_ImportFromCSV(&$query, &$results){
		if(isset($results['rids'])){
			if(isset($results['rids']['fields'])){
				$results['rids'] = array_map(function($record){
					foreach($record['field'] as $field){
						$record[$field['id']] = $field['_'];
					}

					unset($record['field']);

					return $record;
				}, $results['rids']['fields']);
			}else{
				$results['rids'] = array_map(function($record){
					$ret = array(
						'rid' => $record['_']
					);

					if(isset($record['update_id'])){
						$ret['update_id'] = $record['update_id'];
					}

					return $ret;
				}, $results['rids']);
			}
		}
	}

	// final public static function API_ProvisionUser(&$query, &$results){ }
	// final public static function API_PurgeRecords(&$query, &$results){ }
	// final public static function API_RemoveGroupFromRole(&$query, &$results){ }
	// final public static function API_RemoveSubgroup(&$query, &$results){ }
	// final public static function API_RemoveUserFromGroup(&$query, &$results){ }
	// final public static function API_RemoveUserFromRole(&$query, &$results){ }
	// final public static function API_RenameApp(&$query, &$results){ }
	// final public static function API_RunImport(&$query, &$results){ }
	// final public static function API_SendInvitation(&$query, &$results){ }
	// final public static function API_SetDBVar(&$query, &$results){ }
	// final public static function API_SetFieldProperties(&$query, &$results){ }
	// final public static function API_SetKeyField(&$query, &$results){ }
	// final public static function API_SignOut(&$query, &$results){ }
	// final public static function API_UploadFile(&$query, &$results){ }

	final public static function API_UserRoles(&$query, &$results){
		for($i = 0, $l = count($results['users']); $i < $l; ++$i){
			for($o = 0, $k = count($results['users'][$i]['roles']); $o < $k; ++$o){
				$results['users'][$i]['roles'][$o]['access'] = array(
					'name' => $results['users'][$i]['roles'][$o]['access']['_'],
					'id' => $results['users'][$i]['roles'][$o]['access']['id']
				);
			}
		}
	}

}

class QuickBaseOption {

	/* NOTICE:
	 * When an option is a simple return of the value given, comment the function out.
	 * This will increase performance, cutting out an unnecessary function execution.
	*/

	/* Common to All */
	// final public static function apptoken($val){ }
	// final public static function usertoken($val){ }
	// final public static function dbid($val){ }
	// final public static function ticket($val){ }
	// final public static function udata($val){ }

	/* API Specific Options */
	/* API_ChangeGroupInfo, API_CreateGroup */
	// final public static function accountId($val){ }

	/* API_AddField */
	// final public static function add_to_forms($val){ }

	/* API_GrantedDBs */
	// final public static function adminOnly($val){ }

	/* API_GrantedGroups */
	// final public static function adminonly($val){ }

	/* API_SetFieldProperties */
	// final public static function allow_new_choices($val){ }

	/* API_AddUserToGroup */
	// final public static function allowAdminAccess($val){ }

	/* API_SetFieldProperties */
	// final public static function allowHTML($val){ }

	/* API_RemoveGroupFromRole */
	// final public static function allRoles($val){ }

	/* API_SetFieldProperties */
	// final public static function appears_by_default($val){ }

	/* API_SetFieldProperties */
	// 'append-final public static function only'($val){ }

	/* API_SetFieldProperties */
	// final public static function blank_is_zero($val){ }

	/* API_SetFieldProperties */
	// final public static function bold($val){ }

	/* API_FieldAddChoices, API_FieldRemoveChoices */
	// final public static function choice($val){ }

	/* API_SetFieldProperties */
	// final public static function choices($val){ }

	/* API_DoQuery, API_GenResultsTable, API_ImportFromCSV */
	final public static function clist($val){
		if(is_array($val)){
			return implode('.', $val);
		}

		return $val;
	}

	/* API_ImportFromCSV */
	final public static function clist_output($val){
		if(is_array($val)){
			return implode('.', $val);
		}

		return $val;
	}

	/* API_SetFieldProperties */
	// final public static function comma_start($val){ }

	/* API_CopyMasterDetail */
	// final public static function copyfid($val){ }

	/* API_CreateDatabase */
	// final public static function createapptoken($val){ }

	/* API_SetFieldProperties */
	// final public static function currency_format($val){ }

	/* API_SetFieldProperties */
	// final public static function currency_symbol($val){ }

	/* API_CreateDatabase */
	// final public static function dbdesc($val){ }

	/* API_CreateDatabase, API_FindDBByName */
	// final public static function dbname($val){ }

	/* API_SetFieldProperties */
	// final public static function decimal_places($val){ }

	/* API_SetFieldProperties */
	// final public static function default_today($val){ }

	/* API_SetFieldProperties */
	// final public static function default_value($val){ }

	/* API_ChangeGroupInfo, API_CopyGroup, API_CreateGroup */
	// final public static function description($val){ }

	/* API_CopyMasterDetail */
	// final public static function destrid($val){ }

	/* API_GetRecordAsHTML */
	// final public static function dfid($val){ }

	/* API_SetFieldProperties */
	// final public static function display_as_button($val){ }

	/* API_SetFieldProperties */
	// final public static function display_dow($val){ }

	/* API_SetFieldProperties */
	// final public static function display_month($val){ }

	/* API_SetFieldProperties */
	// final public static function display_relative($val){ }

	/* API_SetFieldProperties */
	// final public static function display_time($val){ }

	/* API_SetFieldProperties */
	// final public static function display_zone($val){ }

	/* API_AddRecord, API_EditRecord */
	// final public static function disprec($val){ }

	/* API_SetFieldProperties */
	// final public static function does_average($val){ }

	/* API_SetFieldProperties */
	// final public static function does_total($val){ }

	/* API_SetFieldProperties */
	// final public static function doesdatacopy($val){ }

	/* API_GetUserInfo, API_ProvisionUser */
	// final public static function email($val){ }

	/* API_CloneDatabase */
	// final public static function excludefiles($val){ }

	/* API_GrantedDBs */
	// final public static function excludeparents($val){ }

	/* API_AddRecord, API_EditRecord */
	// final public static function fform($val){ }

	/* API_DeleteField, API_FieldAddChoices, API_FieldRemoveChoices, API_SetFieldProperties, API_SetKeyField */
	// final public static function fid($val){ }

	/* API_AddRecord, API_EditRecord, API_GenAddRecordForm, API_UploadFile */
	final public static function field($val){
		if(!is_array($val)){
			$val = array($val);
		}

		$newVal = array();

		foreach($val as $key => $value) {
			$temp = array(
				'$' => array(),
				'_' => $value['value']
			);

			if(isset($val[$key]['fid'])){
				$temp['$']['fid'] = $val[$key]['fid'];
			}

			if(isset($val[$key]['name'])){
				$temp['$']['name'] = $val[$key]['name'];
			}

			if(isset($val[$key]['filename'])){
				$temp['$']['filename'] = $val[$key]['filename'];
			}

			$newVal[] = $temp;
		}

		return $newVal;
	}

	/* API_SetFieldProperties */
	// final public static function fieldhelp($val){ }

	/* API_SetFieldProperties */
	// final public static function find_enabled($val){ }

	/* API_DoQuery */
	// final public static function fmt($val){ }

	/* API_ProvisionUser */
	// final public static function fname($val){ }

	/* API_SetFieldProperties */
	// final public static function formula($val){ }

	/* API_CopyGroup */
	// final public static function gacct($val){ }

	/* API_AddGroupToRole, API_AddSubGroup, API_AddUserToGroup, API_ChangeGroupInfo, API_CopyGroup, API_DeleteGroup, API_GetGroupRole, API_GetUsersInGroup, API_GrantedDBsForGroup, API_RemoveGroupFromRole, API_RemoveSubgroup, API_RemoveUserFromGroup */
	// final public static function gid($val){ }

	/* API_SetFieldProperties */
	// final public static function has_extension($val){ }

	/* API_Authenticate */
	// final public static function hours($val){ }

	/* API_RunImport */
	// final public static function id($val){ }

	/* API_AddRecord, API_EditRecord */
	// final public static function ignoreError($val){ }

	/* API_GetUserRole */
	// final public static function inclgrps($val){ }

	/* API_GetUsersInGroup */
	// final public static function includeAllMgrs($val){ }

	/* API_GrantedDBs */
	// final public static function includeancestors($val){ }

	/* API_DoQuery */
	// final public static function includeRids($val){ }

	/* API_GenResultsTable */
	// final public static function jht($val){ }

	/* API_GenResultsTable */
	// final public static function jsa($val){ }

	/* API_CloneDatabase */
	// final public static function keepData($val){ }

	/* API_ChangeRecordOwner, API_DeleteRecord, API_EditRecord, API_GetRecordInfo */
	// final public static function key($val){ }

	/* API_AddField, API_SetFieldProperties */
	// final public static function label($val){ }

	/* API_ProvisionUser */
	// final public static function lname($val){ }

	/* API_SetFieldProperties */
	// final public static function maxlength($val){ }

	/* API_AddField */
	// final public static function mode($val){ }

	/* API_AddRecord, API_EditRecord, API_ImportFromCSV */
	// final public static function msInUTC($val){ }

	/* API_ChangeGroupInfo, API_CopyGroup, API_CreateGroup */
	// final public static function name($val){ }

	/* API_RenameApp */
	// final public static function newappname($val){ }

	/* API_CloneDatabase */
	// final public static function newdbdesc($val){ }

	/* API_CloneDatabase */
	// final public static function newdbname($val){ }

	/* API_ChangeManager */
	// final public static function newmgr($val){ }

	/* API_ChangeRecordOwner */
	// final public static function newowner($val){ }

	/* API_ChangeUserRole */
	// final public static function newroleid($val){ }

	/* API_SetFieldProperties */
	// final public static function no_wrap($val){ }

	/* API_SetFieldProperties */
	// final public static function numberfmt($val){ }

	/* API_DoQuery, API_GenResultsTable */
	final public static function options($val){
		if(is_array($val)){
			return implode('.', $val);
		}

		return $val;
	}

	/* API_AddReplaceDBPage */
	// final public static function pagebody($val){ }

	/* API_AddReplaceDBPage */
	// final public static function pageid($val){ }

	/* API_GetDBPage */
	// final public static function pageID($val){ }

	/* API_AddReplaceDBPage */
	// final public static function pagename($val){ }

	/* API_AddReplaceDBPage */
	// final public static function pagetype($val){ }

	/* API_FindDBByName */
	// final public static function ParentsOnly($val){ }

	/* API_Authenticate */
	// final public static function password($val){ }

	/* API_CreateTable */
	// final public static function pnoun($val){ }

	/* API_DoQuery, API_GenResultsTable, API_PurgeRecords */
	// final public static function qid($val){ }

	/* API_DoQuery, API_GenResultsTable, API_PurgeRecords */
	// final public static function qname($val){ }

	/* API_DoQuery, API_DoQueryCount, API_GenResultsTable, API_PurgeRecords */
	// final public static function query($val){ }

	/* API_ImportFromCSV */
	final public static function records_csv($val){
		if(is_array($val)){
			return implode("\n", $val);
		}

		return $val;
	}

	/* API_CopyMasterDetail */
	// final public static function recurse($val){ }

	/* API_CopyMasterDetail */
	// final public static function relfids($val){ }

	/* API_SetFieldProperties */
	// final public static function required($val){ }

	/* API_DoQuery */
	// final public static function returnPercentage($val){ }

	/* API_ChangeRecordOwner, API_DeleteRecord, API_EditRecord, API_GetRecordAsHTML, API_GetRecordInfo, API_UploadFile */
	// final public static function rid($val){ }

	/* API_AddGroupToRole, API_AddUserToRole, API_ChangeUserRole, API_ProvisionUser, API_RemoveGroupFromRole, API_RemoveUserFromRole */
	// final public static function roleid($val){ }

	/* API_ImportFromCSV */
	// final public static function skipfirst($val){ }

	/* API_DoQuery, API_GenResultsTable */
	final public static function slist($val){
		if(is_array($val)){
			return implode('.', $val);
		}

		return $val;
	}

	/* API_SetFieldProperties */
	// final public static function sort_as_given($val){ }

	/* API_CopyMasterDetail */
	// final public static function sourcerid($val){ }

	/* API_AddSubGroup, API_RemoveSubgroup */
	// final public static function subgroupid($val){ }

	/* API_CreateTable */
	// final public static function tname($val){ }

	/* API_AddField */
	// final public static function type($val){ }

	/* API_SetFieldProperties */
	// final public static function unique($val){ }

	/* API_EditRecord */
	// final public static function update_id($val){ }

	/* API_AddUserToGroup, API_AddUserToRole, API_ChangeUserRole, API_GetUserRole, API_GrantedGroups, API_RemoveUserFromGroup, API_RemoveUserFromRole, API_SendInvitation */
	// final public static function userid($val){ }

	/* API_Authenticate */
	// final public static function username($val){ }

	/* API_CloneDatabase */
	// final public static function usersandroles($val){ }

	/* API_SendInvitation */
	// final public static function usertext($val){ }

	/* API_SetDBVar */
	// final public static function value($val){ }

	/* API_GetDBVar, API_SetDBVar */
	// final public static function varname($val){ }

	/* API_SetFieldProperties */
	// final public static function width($val){ }

	/* API_GrantedDBs */
	// final public static function withembeddedtables($val){ }

}

} // End QuickBase Namespace

?>