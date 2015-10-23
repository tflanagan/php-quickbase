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
	$qb = new QuickBase(array(
		'realm' => 'www',
		'appToken' => '****'
	));

	$qb->api('API_Authenticate', array(
		'username' => getenv('username'),
		'password' => getenv('password')
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
}catch(Exception $err){
	var_dump($err);
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
