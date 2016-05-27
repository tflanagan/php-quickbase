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

$expected = array(
	'action' => 'API_AddRecord',
	'errcode' => 0,
	'errtext' => 'No error',
	'rid' => 0,
	'update_id' => 0
);

$actual = $qb->api('API_AddRecord', array(
	'dbid' => getenv('dbid')
));

if(!objStrctMatch($actual, $expected)){
	throw new Exception('Mismatched API_AddRecord Data Structure');
}

$rid = $actual['rid'];

$expected = array(
	'action' => 'API_GetRecordInfo',
	'errcode' => 0,
	'errtext' => 'No error',
	'rid' => 0,
	'num_fields' => 0,
	'update_id' => 0,
	'field' => array(
		array(
			'fid' => 0,
			'name' => '',
			'type' => '',
			'value' => 0,
			'printable' => ''
		),
		array(
			'fid' => 0,
			'name' => '',
			'type' => '',
			'value' => 0,
			'printable' => ''
		),
		array(
			'fid' => 0,
			'name' => '',
			'type' => '',
			'value' => 0
		),
		array(
			'fid' => 0,
			'name' => '',
			'type' => '',
			'value' => '',
			'printable' => ''
		),
		array(
			'fid' => 0,
			'name' => '',
			'type' => '',
			'value' => '',
			'printable' => ''
		)
	)
);

$actual = $qb->api('API_GetRecordInfo', array(
	'dbid' => getenv('dbid'),
	'rid' => $rid
));

if(!objStrctMatch($actual, $expected)){
	throw new Exception('Mismatched API_GetRecordInfo Data Structure');
}

$expected = array(
	'action' => 'API_DeleteRecord',
	'errcode' => 0,
	'errtext' => 'No error',
	'rid' => 0
);

$actual = $qb->api('API_DeleteRecord', array(
	'dbid' => getenv('dbid'),
	'rid' => $rid
));

if(!objStrctMatch($actual, $expected)){
	throw new Exception('Mismatched API_DeleteRecord Data Structure');
}

?>
