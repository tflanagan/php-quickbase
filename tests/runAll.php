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

/* Dependancies */
require_once(__DIR__.'/../quickbase.php');

/* Error Handling */
set_error_handler(function($errno, $errstr, $errfile, $errline, $errcontext){
	if(error_reporting() === 0){
		return false;
	}

	throw new Exception($errstr, $errno);
});

/* Globals */
$stderr = fopen('php://stderr', 'w+');
$error = false;

if(!getenv('TRAVIS')){
	if(count($argv) !== 6){
		echo implode("\n", array(
			'ERROR: Incorrect CL Test Usage.',
			'',
			"\t$ php tests\\runAll.php <realm> <username> <password> <appToken> <dbid>",
			'',
			"\trealm:    www",
			"\tusername: foo@bar.com",
			"\tpassword: foobar",
			"\tappToken: dn23iuct88jvbcx7v9vttp2an6",
			"\tdbid:     bkcamms4m",
			"\t          (must be a table dbid, not an application dbid)",
			''
		));

		exit(1);
	}

	putenv('realm='.$argv[1]);
	putenv('username='.$argv[2]);
	putenv('password='.$argv[3]);
	putenv('appToken='.$argv[4]);
	putenv('dbid='.$argv[5]);
}

$qb = new QuickBase(array(
	'realm' => getenv('realm'),
	'appToken' => getenv('appToken')
));

/* Helpers */
function objStrctEquiv($a, $b){
	$aType = gettype($a);
	$bType = gettype($b);

	if(($aType !== 'array' && $aType !== 'object') || ($bType !== 'array' && $bType !== 'object')){
		return false;
	}

	$keys = array_keys($a);
	$nKeys = count($keys);

	for($i = 0; $i < $nKeys; ++$i){
		$key = $keys[$i];
		$val = $a[$key];

		if(!isset($b[$key])){
			return false;
		}

		if(!objStrctMatch($val, $b[$key])){
			return false;
		}
	}

	return true;
}

function objStrctMatch($a, $b){
	if($a === NULL || $b === NULL || !isset($a) || !isset($b)){
		return $a === $b;
	}

	$aType = gettype($a);
	$bType = gettype($b);

	if($aType === 'double'){
		$aType = 'integer';
	}

	if($bType === 'double'){
		$bType = 'integer';
	}

	if(($aType !== 'array' && $aType !== 'object') || ($bType !== 'array' && $bType !== 'object')){
		return $aType === $bType;
	}

	if($aType === 'array' && $bType === 'array' && count($a) !== count($b)){
		return false;
	}

	if(($aType === 'object' || $aType === 'array') && !objStrctEquiv($a, $b)){
		return false;
	}

	if(($bType === 'object' || $bType === 'array') && !objStrctEquiv($b, $a)){
		return false;
	}

	return true;
}

/* Main */
$files = array_diff(scandir(__DIR__), array(
	'..',
	'.',
	'runAll.php',
	'API_Authenticate.php'
));

array_unshift($files, 'API_Authenticate.php');

foreach($files as $i => $file){
	if(strpos($file, '.') === 0){
		continue;
	}

	try {
		echo "\nRunning Test ".$file.'... ';

		include(__DIR__.'/'.$file);

		echo 'Passed.';
	}catch(Exception $e){
		$details = '';

		if(method_exists($e, 'getDetails')){
			$details = $e->getDetails();
		}

		fwrite($stderr, implode('', array(
			'Failed!',
			"\n\nError: [".$e->getCode().'] ',
			$e->getMessage(),
			'. ',
			$details,
			"\n".$e->getTraceAsString()
		)));

		$error = true;

		break;
	}
}

fclose($stderr);

if($error){
	echo "\n\nTesting Failed! Please see above for details.";
}else{
	echo "\n\nTesting Complete!";
}

?>