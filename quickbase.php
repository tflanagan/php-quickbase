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

		'maxErrorRetryAttempts' => 3
	);

	public function __construct($options = array()){
		$this->settings = array_replace_recursive($this->defaults, $options);
	}

	public function api($action, $options = array()){
		$query = new QuickBaseQuery($this, $action, $options);

		return $query->response;
	}

}

class QuickBaseError extends Exception {

	public function __construct($code = 0, $message = 'No error', $detail = ''){
		parent::__construct($message.($detail === '' ? '' : '. '.$detail), $code);
	}

	public function __toString(){
		return __CLASS__.': ['.$this->code.'] '.$this->message;
	}

}

class QuickBaseQuery {
	
	public function __construct(&$parent, $action, $options){
		$this->parent = $parent;
		$this->action = $action;
		$this->options = $options;

		$this
			->addFlags()
			->processOptions()
			->actionRequest()
			->constructPayload()
			->transmit()
			->processResponse()
			->actionResponse();

		return $this;
	}

	protected function actionRequest(){
		if(method_exists('QuickBaseRequest', $this->action)){
			QuickBaseRequest::{$this->action}($this);
		}

		return $this;
	}

	protected function actionResponse(){
		if(method_exists('QuickBaseResponse', $this->action)){
			QuickBaseResponse::{$this->action}($this, $this->response);
		}

		return $this;
	}

	protected function addFlags(){
		if(!isset($this->options['msInUTC']) && $this->parent->settings['flags']['msInUTC']){
			$this->options['msInUTC'] = 1;
		}

		if(!isset($this->options['appToken']) && $this->parent->settings['appToken']){
			$this->options['appToken'] = $this->parent->settings['appToken'];
		}

		if(!isset($this->options['ticket']) && $this->parent->settings['ticket']){
			$this->options['ticket'] = $this->parent->settings['ticket'];
		}

		if(!isset($this->options['encoding']) && $this->parent->settings['flags']['encoding']){
			$this->options['encoding'] = $this->parent->settings['flags']['encoding'];
		}

		return $this;
	}

	protected function constructPayload(){
		$this->payload = '';

		if($this->parent->settings['flags']['useXML']){
			$xmlDoc = new SimpleXMLElement(implode('', array(
				'<?xml version="1.0" encoding="',
				$this->options['encoding'],
				'"?>',
				'<qdbapi></qdbapi>'
			)));

			$this->arr2Xml($this->options, $xmlDoc);

			$this->payload = $xmlDoc->asXML();
		}else{
			foreach($this->options as $key => $value){
				$this->payload .= '&'.$key.'='.urlencode($value);
			}
		}

		return $this;
	}

	protected function processOptions(){
		if(isset($this->options['fields'])){
			$this->options['field'] = $this->options['fields'];

			unset($this->options['fields']);
		}

		foreach($this->options as $key => $value){
			if(method_exists('QuickBaseOption', $key)){
				$this->options[$key] = QuickBaseOption::{$key}($value);
			}
		}

		return $this;
	}

	protected function processResponse(){
		$this->response = array();

		$this->xml2Arr($this->xmlResponse, $this->response);

		$this->cleanXml2Arr($this->response);

		if($this->response['errcode'] != $this->parent->settings['status']['errcode']){
			throw new QuickBaseError($this->response['errcode'], $this->response['errtext'], $this->response['errdetail']);
		}

		return $this;
	}

	protected function transmit(){
		$ch = curl_init(implode('', array(
			$this->parent->settings['useSSL'] ? 'https://' : 'http://',
			$this->parent->settings['realm'],
			'.',
			$this->parent->settings['domain'],
			'/db/',
			isset($this->options['dbid']) ? $this->options['dbid'] : 'main',
			'?act=',
			$this->action,
			$this->parent->settings['flags']['useXML'] ? '' : $this->payload
		)));

		curl_setopt($ch, CURLOPT_PORT, $this->parent->settings['useSSL'] ? 443 : 80);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

		if($this->parent->settings['flags']['useXML']){
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
		}

		$response = curl_exec($ch);

		if($response === false){
			throw new QuickBaseError(curl_errno($ch), curl_error($ch));
		}

		$this->xmlResponse = new SimpleXmlIterator($response);

		return $this;
	}

	/* Helpers */
	protected static function arr2Xml($arr, &$xml){
		if(is_array($arr)){
			foreach($arr as $key => $value){
				if($key === '$'){
					foreach($value as $attr => $attrValue){
						$xml->addAttribute($attr, htmlspecialchars($attrValue));
					}

					continue;
				}

				if($key === '_'){
					$xml[0] = $value;

					continue;
				}

				if(is_int($key)){
					if($key === 0){
						QuickBaseQuery::arr2Xml($arr[$key], $xml);
					}else{
						$name = $xml->getName();

						$child = $xml->xpath('..')[0]->addChild($name);

						QuickBaseQuery::arr2Xml($arr[$key], $child);
					}

					continue;
				}

				$child = $xml->addChild($key);

				QuickBaseQuery::arr2Xml($arr[$key], $child);
			}
		}else{
			$xml[0] = htmlspecialchars($arr);
		}
	}

	protected static function cleanXml2Arr(&$arr){
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
					QuickBaseQuery::cleanXml2Arr($arr[$key]);
				}

				if(is_numeric($arr[$key])){
					$arr[$key] = (double) $arr[$key];
				}else
				if(is_string($arr[$key])){
					if(strtolower($arr[$key]) === 'true'){
						$arr[$key] = true;
					}else
					if(strtolower($arr[$key]) === 'false'){
						$arr[$key] = false;
					}
				}
			}
		}
	}

	protected static function xml2Arr($xml, &$arr){
		for($xml->rewind(); $xml->valid(); $xml->next()){
			$key = $xml->key();

			if(!array_key_exists($key, $arr)){
				$arr[$key] = array();
			}

			if($xml->hasChildren()){
				$node = array();

				QuickBaseQuery::xml2Arr($xml->current(), $node);
			}else{
				$node = strval($xml->current());
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
	 * When an option is a simple return of the value given, comment the function out.
	 * This will increase performance, cutting out an unnecessary function execution.
	*/

	// public static function API_AddField(&$query){ }
	// public static function API_AddGroupToRole(&$query){ }
	// public static function API_AddRecord(&$query){ }
	// public static function API_AddReplaceDBPage(&$query){ }
	// public static function API_AddSubGroup(&$query){ }
	// public static function API_AddUserToGroup(&$query){ }
	// public static function API_AddUserToRole(&$query){ }

	public static function API_Authenticate(&$query){
		// API_Authenticate can only happen over SSL
		$query->settings['useSSL'] = true;
	}

	// public static function API_ChangeGroupInfo(&$query){ }
	// public static function API_ChangeManager(&$query){ }
	// public static function API_ChangeRecordOwner(&$query){ }
	// public static function API_ChangeUserRole(&$query){ }
	// public static function API_CloneDatabase(&$query){ }
	// public static function API_CopyGroup(&$query){ }
	// public static function API_CopyMasterDetail(&$query){ }
	// public static function API_CreateDatabase(&$query){ }
	// public static function API_CreateGroup(&$query){ }
	// public static function API_CreateTable(&$query){ }
	// public static function API_DeleteDatabase(&$query){ }
	// public static function API_DeleteField(&$query){ }
	// public static function API_DeleteGroup(&$query){ }
	// public static function API_DeleteRecord(&$query){ }

	public static function API_DoQuery(&$query){
		if(!isset($query->options['returnPercentage']) && isset($query->parent->settings['flags']['returnPercentage'])){
			$query->options['returnPercentage'] = $query->parent->settings['flags']['returnPercentage'];
		}

		if(!isset($query->options['fmt']) && isset($query->parent->settings['flags']['fmt'])){
			$query->options['fmt'] = $query->parent->settings['flags']['fmt'];
		}

		if(!isset($query->options['includeRids']) && isset($query->parent->settings['flags']['includeRids'])){
			$query->options['includeRids'] = $query->parent->settings['flags']['includeRids'];
		}
	}

	// public static function API_DoQueryCount(&$query){ }
	// public static function API_EditRecord(&$query){ }
	// public static function API_FieldAddChoices(&$query){ }
	// public static function API_FieldRemoveChoices(&$query){ }
	// public static function API_FindDBByName(&$query){ }
	// public static function API_GenAddRecordForm(&$query){ }
	// public static function API_GenResultsTable(&$query){ }
	// public static function API_GetAncestorInfo(&$query){ }
	// public static function API_GetAppDTMInfo(&$query){ }
	// public static function API_GetDBPage(&$query){ }
	// public static function API_GetDBInfo(&$query){ }
	// public static function API_GetDBVar(&$query){ }
	// public static function API_GetGroupRole(&$query){ }
	// public static function API_GetNumRecords(&$query){ }
	// public static function API_GetSchema(&$query){ }
	// public static function API_GetRecordAsHTML(&$query){ }
	// public static function API_GetRecordInfo(&$query){ }
	// public static function API_GetRoleInfo(&$query){ }
	// public static function API_GetUserInfo(&$query){ }
	// public static function API_GetUserRole(&$query){ }
	// public static function API_GetUsersInGroup(&$query){ }
	// public static function API_GrantedDBs(&$query){ }
	// public static function API_GrantedDBsForGroup(&$query){ }
	// public static function API_GrantedGroups(&$query){ }
	// public static function API_ImportFromCSV(&$query){ }
	// public static function API_ProvisionUser(&$query){ }
	// public static function API_PurgeRecords(&$query){ }
	// public static function API_RemoveGroupFromRole(&$query){ }
	// public static function API_RemoveSubgroup(&$query){ }
	// public static function API_RemoveUserFromGroup(&$query){ }
	// public static function API_RemoveUserFromRole(&$query){ }
	// public static function API_RenameApp(&$query){ }
	// public static function API_RunImport(&$query){ }
	// public static function API_SendInvitation(&$query){ }
	// public static function API_SetDBVar(&$query){ }
	// public static function API_SetFieldProperties(&$query){ }
	// public static function API_SetKeyField(&$query){ }
	// public static function API_SignOut(&$query){ }
	// public static function API_UploadFile(&$query){ }
	// public static function API_UserRoles(&$query){ }

}

class QuickBaseResponse {

	/* NOTICE:
	 * When an option is a simple return of the value given, comment the function out.
	 * This will increase performance, cutting out an unnecessary function execution.
	*/

	// public static function API_AddField(&$query, &$results){ }
	// public static function API_AddGroupToRole(&$query, &$results){ }
	// public static function API_AddRecord(&$query, &$results){ }
	// public static function API_AddReplaceDBPage(&$query, &$results){ }
	// public static function API_AddSubGroup(&$query, &$results){ }
	// public static function API_AddUserToGroup(&$query, &$results){ }
	// public static function API_AddUserToRole(&$query, &$results){ }

	public static function API_Authenticate(&$query, &$results){
		$query->parent->settings['ticket'] = $results['ticket'];
		$query->parent->settings['username'] = $query->options['username'];
		$query->parent->settings['password'] = $query->options['password'];
	}

	// public static function API_ChangeGroupInfo(&$query, &$results){ }
	// public static function API_ChangeManager(&$query, &$results){ }
	// public static function API_ChangeRecordOwner(&$query, &$results){ }
	// public static function API_ChangeUserRole(&$query, &$results){ }
	// public static function API_CloneDatabase(&$query, &$results){ }
	// public static function API_CopyGroup(&$query, &$results){ }
	// public static function API_CopyMasterDetail(&$query, &$results){ }
	// public static function API_CreateDatabase(&$query, &$results){ }
	// public static function API_CreateGroup(&$query, &$results){ }
	// public static function API_CreateTable(&$query, &$results){ }
	// public static function API_DeleteDatabase(&$query, &$results){ }
	// public static function API_DeleteField(&$query, &$results){ }
	// public static function API_DeleteGroup(&$query, &$results){ }
	// public static function API_DeleteRecord(&$query, &$results){ }
	// public static function API_DoQuery(&$query, &$results){ }
	// public static function API_DoQueryCount(&$query, &$results){ }
	// public static function API_EditRecord(&$query, &$results){ }
	// public static function API_FieldAddChoices(&$query, &$results){ }
	// public static function API_FieldRemoveChoices(&$query, &$results){ }
	// public static function API_FindDBByName(&$query, &$results){ }
	// public static function API_GenAddRecordForm(&$query, &$results){ }
	// public static function API_GenResultsTable(&$query, &$results){ }
	// public static function API_GetAncestorInfo(&$query, &$results){ }
	// public static function API_GetAppDTMInfo(&$query, &$results){ }
	// public static function API_GetDBPage(&$query, &$results){ }
	// public static function API_GetDBInfo(&$query, &$results){ }
	// public static function API_GetDBVar(&$query, &$results){ }
	// public static function API_GetGroupRole(&$query, &$results){ }
	// public static function API_GetNumRecords(&$query, &$results){ }
	// public static function API_GetSchema(&$query, &$results){ }
	// public static function API_GetRecordAsHTML(&$query, &$results){ }
	// public static function API_GetRecordInfo(&$query, &$results){ }
	// public static function API_GetRoleInfo(&$query, &$results){ }
	// public static function API_GetUserInfo(&$query, &$results){ }
	// public static function API_GetUserRole(&$query, &$results){ }
	// public static function API_GetUsersInGroup(&$query, &$results){ }
	// public static function API_GrantedDBs(&$query, &$results){ }
	// public static function API_GrantedDBsForGroup(&$query, &$results){ }
	// public static function API_GrantedGroups(&$query, &$results){ }
	// public static function API_ImportFromCSV(&$query, &$results){ }
	// public static function API_ProvisionUser(&$query, &$results){ }
	// public static function API_PurgeRecords(&$query, &$results){ }
	// public static function API_RemoveGroupFromRole(&$query, &$results){ }
	// public static function API_RemoveSubgroup(&$query, &$results){ }
	// public static function API_RemoveUserFromGroup(&$query, &$results){ }
	// public static function API_RemoveUserFromRole(&$query, &$results){ }
	// public static function API_RenameApp(&$query, &$results){ }
	// public static function API_RunImport(&$query, &$results){ }
	// public static function API_SendInvitation(&$query, &$results){ }
	// public static function API_SetDBVar(&$query, &$results){ }
	// public static function API_SetFieldProperties(&$query, &$results){ }
	// public static function API_SetKeyField(&$query, &$results){ }
	// public static function API_SignOut(&$query, &$results){ }
	// public static function API_UploadFile(&$query, &$results){ }
	// public static function API_UserRoles(&$query, &$results){ }

}

class QuickBaseOption {

	/* NOTICE:
	 * When an option is a simple return of the value given, comment the function out.
	 * This will increase performance, cutting out an unnecessary function execution.
	*/

	/* Common to All */
	// public static function apptoken($val){
	// 	return $val;
	// }

	// public static function dbid($val){
	// 	return $val;
	// }

	// public static function ticket($val){
	// 	return $val;
	// }

	// public static function udata($val){
	// 	return $val;
	// }

	/* API Specific Options */

	/* API_ChangeGroupInfo, API_CreateGroup */
	// public static function accountId($val){
	// 	return $val;
	// }

	/* API_AddField */
	// public static function add_to_forms($val){
	// 	return $val;
	// }

	/* API_GrantedDBs */
	// public static function adminOnly($val){
	// 	return $val;
	// }

	/* API_GrantedGroups */
	// public static function adminonly($val){
	// 	return $val;
	// }

	/* API_SetFieldProperties */
	// public static function allow_new_choices($val){
	// 	return $val;
	// }

	/* API_AddUserToGroup */
	// public static function allowAdminAccess($val){
	// 	return $val;
	// }

	/* API_SetFieldProperties */
	// public static function allowHTML($val){
	// 	return $val;
	// }

	/* API_RemoveGroupFromRole */
	// public static function allRoles($val){
	// 	return $val;
	// }

	/* API_SetFieldProperties */
	// public static function appears_by_default($val){
	// 	return $val;
	// }

	/* API_SetFieldProperties */
	// 'append-public static function only'($val){
	// 	return $val;
	// }

	/* API_SetFieldProperties */
	// public static function blank_is_zero($val){
	// 	return $val;
	// }

	/* API_SetFieldProperties */
	// public static function bold($val){
	// 	return $val;
	// }

	/* API_FieldAddChoices, API_FieldRemoveChoices */
	// public static function choice($val){
	// 	return $val;
	// }

	/* API_SetFieldProperties */
	// public static function choices($val){
	// 	return $val;
	// }

	/* API_DoQuery, API_GenResultsTable, API_ImportFromCSV */
	public static function clist($val){
		if(is_array($val)){
			return implode('.', $val);
		}

		return $val;
	}

	/* API_ImportFromCSV */
	public static function clist_output($val){
		if(is_array($val)){
			return implode('.', $val);
		}

		return $val;
	}

	/* API_SetFieldProperties */
	// public static function comma_start($val){
	// 	return $val;
	// }

	/* API_CopyMasterDetail */
	// public static function copyfid($val){
	// 	return $val;
	// }

	/* API_CreateDatabase */
	// public static function createapptoken($val){
	// 	return $val;
	// }

	/* API_SetFieldProperties */
	// public static function currency_format($val){
	// 	return $val;
	// }

	/* API_SetFieldProperties */
	// public static function currency_symbol($val){
	// 	return $val;
	// }

	/* API_CreateDatabase */
	// public static function dbdesc($val){
	// 	return $val;
	// }

	/* API_CreateDatabase, API_FindDBByName */
	// public static function dbname($val){
	// 	return $val;
	// }

	/* API_SetFieldProperties */
	// public static function decimal_places($val){
	// 	return $val;
	// }

	/* API_SetFieldProperties */
	// public static function default_today($val){
	// 	return $val;
	// }

	/* API_SetFieldProperties */
	// public static function default_value($val){
	// 	return $val;
	// }

	/* API_ChangeGroupInfo, API_CopyGroup, API_CreateGroup */
	// public static function description($val){
	// 	return $val;
	// }

	/* API_CopyMasterDetail */
	// public static function destrid($val){
	// 	return $val;
	// }

	/* API_GetRecordAsHTML */
	// public static function dfid($val){
	// 	return $val;
	// }

	/* API_SetFieldProperties */
	// public static function display_as_button($val){
	// 	return $val;
	// }

	/* API_SetFieldProperties */
	// public static function display_dow($val){
	// 	return $val;
	// }

	/* API_SetFieldProperties */
	// public static function display_month($val){
	// 	return $val;
	// }

	/* API_SetFieldProperties */
	// public static function display_relative($val){
	// 	return $val;
	// }

	/* API_SetFieldProperties */
	// public static function display_time($val){
	// 	return $val;
	// }

	/* API_SetFieldProperties */
	// public static function display_zone($val){
	// 	return $val;
	// }

	/* API_AddRecord, API_EditRecord */
	// public static function disprec($val){
	// 	return $val;
	// }

	/* API_SetFieldProperties */
	// public static function does_average($val){
	// 	return $val;
	// }

	/* API_SetFieldProperties */
	// public static function does_total($val){
	// 	return $val;
	// }

	/* API_SetFieldProperties */
	// public static function doesdatacopy($val){
	// 	return $val;
	// }

	/* API_GetUserInfo, API_ProvisionUser */
	// public static function email($val){
	// 	return $val;
	// }

	/* API_CloneDatabase */
	// public static function excludefiles($val){
	// 	return $val;
	// }

	/* API_GrantedDBs */
	// public static function excludeparents($val){
	// 	return $val;
	// }

	/* API_AddRecord, API_EditRecord */
	// public static function fform($val){
	// 	return $val;
	// }

	/* API_DeleteField, API_FieldAddChoices, API_FieldRemoveChoices, API_SetFieldProperties, API_SetKeyField */
	// public static function fid($val){
	// 	return $val;
	// }

	/* API_AddRecord, API_EditRecord, API_GenAddRecordForm, API_UploadFile */
	public static function field($val){
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
	// public static function fieldhelp($val){
	// 	return $val;
	// }

	/* API_SetFieldProperties */
	// public static function find_enabled($val){
	// 	return $val;
	// }

	/* API_DoQuery */
	// public static function fmt($val){
	// 	return $val;
	// }

	/* API_ProvisionUser */
	// public static function fname($val){
	// 	return $val;
	// }

	/* API_SetFieldProperties */
	// public static function formula($val){
	// 	return $val;
	// }

	/* API_CopyGroup */
	// public static function gacct($val){
	// 	return $val;
	// }

	/* API_AddGroupToRole, API_AddSubGroup, API_AddUserToGroup, API_ChangeGroupInfo, API_CopyGroup, API_DeleteGroup, API_GetGroupRole, API_GetUsersInGroup, API_GrantedDBsForGroup, API_RemoveGroupFromRole, API_RemoveSubgroup, API_RemoveUserFromGroup */
	// public static function gid($val){
	// 	return $val;
	// }

	/* API_SetFieldProperties */
	// public static function has_extension($val){
	// 	return $val;
	// }

	/* API_Authenticate */
	// public static function hours($val){
	// 	return $val;
	// }

	/* API_RunImport */
	// public static function id($val){
	// 	return $val;
	// }

	/* API_AddRecord, API_EditRecord */
	// public static function ignoreError($val){
	// 	return $val;
	// }

	/* API_GetUserRole */
	// public static function inclgrps($val){
	// 	return $val;
	// }

	/* API_GetUsersInGroup */
	// public static function includeAllMgrs($val){
	// 	return $val;
	// }

	/* API_GrantedDBs */
	// public static function includeancestors($val){
	// 	return $val;
	// }

	/* API_DoQuery */
	// public static function includeRids($val){
	// 	return $val;
	// }

	/* API_GenResultsTable */
	// public static function jht($val){
	// 	return $val;
	// }

	/* API_GenResultsTable */
	// public static function jsa($val){
	// 	return $val;
	// }

	/* API_CloneDatabase */
	// public static function keepData($val){
	// 	return $val;
	// }

	/* API_ChangeRecordOwner, API_DeleteRecord, API_EditRecord, API_GetRecordInfo */
	// public static function key($val){
	// 	return $val;
	// }

	/* API_AddField, API_SetFieldProperties */
	// public static function label($val){
	// 	return $val;
	// }

	/* API_ProvisionUser */
	// public static function lname($val){
	// 	return $val;
	// }

	/* API_SetFieldProperties */
	// public static function maxlength($val){
	// 	return $val;
	// }

	/* API_AddField */
	// public static function mode($val){
	// 	return $val;
	// }

	/* API_AddRecord, API_EditRecord, API_ImportFromCSV */
	// public static function msInUTC($val){
	// 	return $val;
	// }

	/* API_ChangeGroupInfo, API_CopyGroup, API_CreateGroup */
	// public static function name($val){
	// 	return $val;
	// }

	/* API_RenameApp */
	// public static function newappname($val){
	// 	return $val;
	// }

	/* API_CloneDatabase */
	// public static function newdbdesc($val){
	// 	return $val;
	// }

	/* API_CloneDatabase */
	// public static function newdbname($val){
	// 	return $val;
	// }

	/* API_ChangeManager */
	// public static function newmgr($val){
	// 	return $val;
	// }

	/* API_ChangeRecordOwner */
	// public static function newowner($val){
	// 	return $val;
	// }

	/* API_ChangeUserRole */
	// public static function newroleid($val){
	// 	return $val;
	// }

	/* API_SetFieldProperties */
	// public static function no_wrap($val){
	// 	return $val;
	// }

	/* API_SetFieldProperties */
	// public static function numberfmt($val){
	// 	return $val;
	// }

	/* API_DoQuery, API_GenResultsTable */
	public static function options($val){
		if(is_array($val)){
			return implode('.', $val);
		}

		return $val;
	}

	/* API_AddReplaceDBPage */
	// public static function pagebody($val){
	// 	return $val;
	// }

	/* API_AddReplaceDBPage */
	// public static function pageid($val){
	// 	return $val;
	// }

	/* API_GetDBPage */
	// public static function pageID($val){
	// 	return $val;
	// }

	/* API_AddReplaceDBPage */
	// public static function pagename($val){
	// 	return $val;
	// }

	/* API_AddReplaceDBPage */
	// public static function pagetype($val){
	// 	return $val;
	// }

	/* API_FindDBByName */
	// public static function ParentsOnly($val){
	// 	return $val;
	// }

	/* API_Authenticate */
	// public static function password($val){
	// 	return $val;
	// }

	/* API_CreateTable */
	// public static function pnoun($val){
	// 	return $val;
	// }

	/* API_DoQuery, API_GenResultsTable, API_PurgeRecords */
	// public static function qid($val){
	// 	return $val;
	// }

	/* API_DoQuery, API_GenResultsTable, API_PurgeRecords */
	// public static function qname($val){
	// 	return $val;
	// }

	/* API_DoQuery, API_DoQueryCount, API_GenResultsTable, API_PurgeRecords */
	// public static function query($val){
	// 	return $val;
	// }

	/* API_ImportFromCSV */
	public static function records_csv($val){
		if(is_array($val)){
			return implode("\n", $val);
		}

		return $val;
	}

	/* API_CopyMasterDetail */
	// public static function recurse($val){
	// 	return $val;
	// }

	/* API_CopyMasterDetail */
	// public static function relfids($val){
	// 	return $val;
	// }

	/* API_SetFieldProperties */
	// public static function required($val){
	// 	return $val;
	// }

	/* API_DoQuery */
	// public static function returnPercentage($val){
	// 	return $val;
	// }

	/* API_ChangeRecordOwner, API_DeleteRecord, API_EditRecord, API_GetRecordAsHTML, API_GetRecordInfo, API_UploadFile */
	// public static function rid($val){
	// 	return $val;
	// }

	/* API_AddGroupToRole, API_AddUserToRole, API_ChangeUserRole, API_ProvisionUser, API_RemoveGroupFromRole, API_RemoveUserFromRole */
	// public static function roleid($val){
	// 	return $val;
	// }

	/* API_ImportFromCSV */
	// public static function skipfirst($val){
	// 	return $val;
	// }

	/* API_DoQuery, API_GenResultsTable */
	public static function slist($val){
		if(is_array($val)){
			return implode('.', $val);
		}

		return $val;
	}

	/* API_SetFieldProperties */
	// public static function sort_as_given($val){
	// 	return $val;
	// }

	/* API_CopyMasterDetail */
	// public static function sourcerid($val){
	// 	return $val;
	// }

	/* API_AddSubGroup, API_RemoveSubgroup */
	// public static function subgroupid($val){
	// 	return $val;
	// }

	/* API_CreateTable */
	// public static function tname($val){
	// 	return $val;
	// }

	/* API_AddField */
	// public static function type($val){
	// 	return $val;
	// }

	/* API_SetFieldProperties */
	// public static function unique($val){
	// 	return $val;
	// }

	/* API_EditRecord */
	// public static function update_id($val){
	// 	return $val;
	// }

	/* API_AddUserToGroup, API_AddUserToRole, API_ChangeUserRole, API_GetUserRole, API_GrantedGroups, API_RemoveUserFromGroup, API_RemoveUserFromRole, API_SendInvitation */
	// public static function userid($val){
	// 	return $val;
	// }

	/* API_Authenticate */
	// public static function username($val){
	// 	return $val;
	// }

	/* API_CloneDatabase */
	// public static function usersandroles($val){
	// 	return $val;
	// }

	/* API_SendInvitation */
	// public static function usertext($val){
	// 	return $val;
	// }

	/* API_SetDBVar */
	// public static function value($val){
	// 	return $val;
	// }

	/* API_GetDBVar, API_SetDBVar */
	// public static function varname($val){
	// 	return $val;
	// }

	/* API_SetFieldProperties */
	// public static function width($val){
	// 	return $val;
	// }

	/* API_GrantedDBs */
	// public static function withembeddedtables($val){
	// 	return $val;
	// }

}

?>