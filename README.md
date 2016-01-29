php-quickbase
=============

[![License](https://poser.pugx.org/tflanagan/quickbase/license)](https://packagist.org/packages/tflanagan/quickbase) [![Latest Stable Version](https://poser.pugx.org/tflanagan/quickbase/version)](https://packagist.org/packages/tflanagan/quickbase) [![Total Downloads](https://poser.pugx.org/tflanagan/quickbase/downloads)](https://packagist.org/packages/tflanagan/quickbase) [![Build Status](https://travis-ci.org/tflanagan/php-quickbase.svg?branch=master)](https://travis-ci.org/tflanagan/php-quickbase)

A lightweight, very flexible QuickBase API

Install
-------
```
$ composer require tflanagan/quickbase
```

Example
-------
```php
try {
	$qb = new \QuickBase\QuickBase(array(
		'realm' => 'www',
		'appToken' => '****'
	));

	$qb->api('API_Authenticate', array(
		'username' => '****',
		'password' => '****'
	));

	$response = $qb->api('API_DoQuery', array(
		'dbid' => '*****',
		'clist' => '3.12',
		'options' => 'num-5'
	));

	foreach($response['table']['records'] as $record){
		$qb->api('API_EditRecord', array(
			'dbid' => '*****',
			'rid' => $record[3],
			'fields' => array(
				array( 'fid' => 12, 'value' => $record[12])
			)
		));
	}

	$response = $qb->api('API_DoQuery', array(
		'dbid' => '*****',
		'clist' => '3.12',
		'options' => 'num-5'
	));

	var_dump($response['table']['records']);
}catch(\QuickBase\QuickBaseError $err){
	echo '('.$err->getCode().') '.$err->getMessage().'. '.$err->getDetails();
}

```

Class
-----
```php
class \QuickBase\QuickBase {

	private $defaults;

	final public api($action[, $options = array()]);

}

class \QuickBase\QuickBaseError extends \Exception {

	protected int $code;
	protected string $message;
	protected string $details;

	protected int $line;
	protected string $file;

	final public getCode(void);
	final public getMessage(void);
	final public getDetails(void);

	final public getLine(void);
	final public getFile(void);

}

class \QuickBase\QuickBaseQuery {

	public QuickBase $parent;
	public string $action;
	public array $settings;
	public array $options;
	public array $response;

	private int $nErrors;

	protected string $payload;

	final public actionRequest();
	final public actionResponse();
	final public addFlags();
	final public constructPayload();
	final public checkForAndHandleError();
	final public processOptions();
	final public transmit();

	final public static arr2Obj(&$arr[, $return = false]);
	final public static arr2Xml($arr, &$xml);
	final public static cleanXml2Arr(&$arr);
	final public static parseCURLHeaders(&$headers);
	final public static xml2Arr($xml, &$arr);

}

class \QuickBase\QuickBaseRequest {

	final public static API_[Authenticate, DoQuery, etc](&$query);

}

class \QuickBase\QuickBaseResponse {

	final public static API_[Authenticate, DoQuery, etc](&$query, &$results);

}

class \QuickBase\QuickBaseOption {

	final public static [clist, fields, etc]($val);

}
```

Error Handling
--------------

php-quickbase throws exceptions whenever an error is detected. You do not have to manually check for QuickBase errors, just wrap your code in `try/catch`'s and you're good to go!

```php
try {
	// QuickBase API Calls Here
}catch(\QuickBase\QuickBaseError $err){
	echo '('.$err->getCode().') '.$err->getMessage().'. '.$err->getDetails();

	/*
	 * class \QuickBase\QuickBaseError extends \Exception {
	 *
	 * 	protected int $code;
	 * 	protected string $message;
	 * 	protected string $details;
	 *
	 * 	protected int $line;
	 * 	protected string $file;
	 *
	 * 	final public getCode(void);
	 * 	final public getMessage(void);
	 * 	final public getDetails(void);
	 *
	 * 	final public getLine(void);
	 * 	final public getFile(void);
	 *
	 * }
	*/
}
```

License
-------

Copyright 2015 Tristian Flanagan

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
