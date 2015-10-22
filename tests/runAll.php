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

$qb = new QuickBase();

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
		fwrite($stderr, implode('', array(
			'Failed!',
			"\n\nError: [".$e->getCode().'] ',
			$e->getMessage(),
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