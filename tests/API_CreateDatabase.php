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

echo 'test skipped... ';

return;

$expected = array(
	'action' => 'API_CreateDatabase',
	'errcode' => 0,
	'errtext' => 'No error',
	'dbid' => '',
	'appdbid' => '',
	'apptoken' => ''
);

$actual = $qb->api('API_CreateDatabase', array(
	'dbname' => 'Test DB',
	'dbdesc' => 'Testing DB from Node-QuickBase Tests',
	'createapptoken' => true
));

if(!objStrctMatch($actual, $expected)){
	throw new Exception('Mismatched API_CreateDatabase Data Structure');
}

$expected = array(
	'action' => 'API_DeleteDatabase',
	'errcode' => 0,
	'errtext' => 'No error'
);

$actual = $qb->api('API_DeleteDatabase', array(
	'dbid' => $actual['appdbid']
));

if(!objStrctMatch($actual, $expected)){
	throw new Exception('Mismatched API_DeleteDatabase Data Structure');
}

?>
