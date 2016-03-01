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
	if(count($argv) !== 8){
		echo implode("\n", array(
			'ERROR: Incorrect CL Benchmark Usage.',
			'',
			"\t$ php tests\\benchmark.php <n> <realm> <username> <password> <appToken> <dbid> <appid>",
			'',
			"\tn:        1000",
			"\trealm:    www",
			"\tusername: foo@bar.com",
			"\tpassword: foobar",
			"\tappToken: dn23iuct88jvbcx7v9vttp2an6",
			"\tdbid:     bkcamms4m",
			"\tappid:    bkcamms4c",
			''
		));

		exit(1);
	}

	putenv('n='.$argv[1]);
	putenv('realm='.$argv[2]);
	putenv('username='.$argv[3]);
	putenv('password='.$argv[4]);
	putenv('appToken='.$argv[5]);
	putenv('dbid='.$argv[6]);
	putenv('appid='.$argv[7]);
}

$l = getenv('n');

echo "\nRunning Benchmark. N = ".$l;

$times = array();

for($i = 1; $i - 1 < $l; ++$i){
	echo "\nRunning cycle ".$i.'...';
	$cmd = 'php runAll.php '.getenv('realm').' '.getenv('username').' '.getenv('password').' '.getenv('appToken').' '.getenv('dbid').' '.getenv('appid');

	exec($cmd, $output);

	$output = implode(' ', $output);

	preg_match("/(Total Elasped Time: (\d+) seconds)/", $output, $totalTime);

	$totalTime = (int) $totalTime[2];

	echo " Completed. Time: ".$totalTime;

	$times[] = $totalTime;
}

echo "\n\nAverage Execution Time: ".(array_sum($times) / $l).' seconds.';

fclose($stderr);

if($error){
	echo "\n\nBenchmarking Failed! Please see above for details.";
}else{
	echo "\n\nBenchmarking Complete!";
}

?>