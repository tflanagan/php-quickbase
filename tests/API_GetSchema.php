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
	'action' => 'API_GetSchema',
	'errcode' => 0,
	'errtext' => 'No error',
	'time_zone' => '',
	'date_format' => '',
	'table' => array(
		'name' => '',
		'desc' => '',
		'original' => array(
			'table_id' => '',
			'app_id' => '',
			'cre_date' => 0,
			'mod_date' => 0,
			'next_record_id' => 0,
			'next_field_id' => 0,
			'next_query_id' => 0,
			'def_sort_fid' => 0,
			'def_sort_order' => 0,
			'key_fid' => 0,
			'single_record_name' => '',
			'plural_record_name' => ''
		),
		'variables' => array(
			'test' => ''
		),
		'queries' => array(
			array(
				'id' => 1,
				'qycalst' => '',
				'qyopts' => 'nos.',
				'qytype' => 'table',
				'qyname' => 'List All'
			), array(
				'id' => 2,
				'qycalst' => '',
				'qyopts' => 'so-D.onlynew.nos.',
				'qytype' => 'table',
				'qyname' => 'List Changes',
				'qydesc' => 'Sorted by Date Modified',
				'qyslst' => 2
			)
		),
		'fields' => array(
			array(
				'label' => 'Date Created',
				'nowrap' => 1,
				'bold' => 0,
				'required' => 0,
				'appears_by_default' => 0,
				'find_enabled' => 0,
				'allow_new_choices' => 0,
				'sort_as_given' => 1,
				'carrychoices' => 1,
				'foreignkey' => 0,
				'unique' => 0,
				'doesdatacopy' => 0,
				'fieldhelp' => '',
				'allowInRecTemplate' => 0,
				'display_time' => 1,
				'display_relative' => 0,
				'display_month' => 'number',
				'default_today' => 0,
				'display_dow' => 0,
				'display_zone' => 0,
				'id' => 1,
				'field_type' => 'timestamp',
				'base_type' => 'int64',
				'role' => 'created'
			), array(
				'label' => 'Date Modified',
				'nowrap' => 1,
				'bold' => 0,
				'required' => 0,
				'appears_by_default' => 0,
				'find_enabled' => 0,
				'allow_new_choices' => 0,
				'sort_as_given' => 1,
				'carrychoices' => 1,
				'foreignkey' => 0,
				'unique' => 0,
				'doesdatacopy' => 0,
				'fieldhelp' => '',
				'allowInRecTemplate' => 0,
				'display_time' => 1,
				'display_relative' => 0,
				'display_month' => 'number',
				'default_today' => 0,
				'display_dow' => 0,
				'display_zone' => 0,
				'id' => 2,
				'field_type' => 'timestamp',
				'base_type' => 'int64',
				'role' => 'modified'
			), array(
				'label' => 'Record ID#',
				'nowrap' => 1,
				'bold' => 0,
				'required' => 0,
				'appears_by_default' => 0,
				'find_enabled' => 1,
				'allow_new_choices' => 0,
				'sort_as_given' => 1,
				'carrychoices' => 1,
				'foreignkey' => 0,
				'unique' => 1,
				'doesdatacopy' => 0,
				'fieldhelp' => '',
				'allowInRecTemplate' => 0,
				'decimal_places' => 0,
				'comma_start' => 4,
				'numberfmt' => 0,
				'does_average' => 0,
				'does_total' => 0,
				'blank_is_zero' => 1,
				'id' => 3,
				'field_type' => 'recordid',
				'base_type' => 'int32',
				'role' => 'recordid',
				'mode' => 'virtual'
			), array(
				'label' => 'Record Owner',
				'nowrap' => 1,
				'bold' => 0,
				'required' => 0,
				'appears_by_default' => 0,
				'find_enabled' => 1,
				'allow_new_choices' => 1,
				'sort_as_given' => 1,
				'carrychoices' => 1,
				'foreignkey' => 0,
				'unique' => 0,
				'doesdatacopy' => 0,
				'fieldhelp' => '',
				'allowInRecTemplate' => 0,
				'display_user' => 'fullnamelf',
				'default_kind' => 'none',
				'id' => 4,
				'field_type' => 'userid',
				'base_type' => 'text',
				'role' => 'owner'
			), array(
				'label' => 'Last Modified By',
				'nowrap' => 1,
				'bold' => 0,
				'required' => 0,
				'appears_by_default' => 0,
				'find_enabled' => 1,
				'allow_new_choices' => 1,
				'sort_as_given' => 1,
				'carrychoices' => 1,
				'foreignkey' => 0,
				'unique' => 0,
				'doesdatacopy' => 0,
				'fieldhelp' => '',
				'allowInRecTemplate' => 0,
				'display_user' => 'fullnamelf',
				'default_kind' => 'none',
				'id' => 5,
				'field_type' => 'userid',
				'base_type' => 'text',
				'role' => 'modifier'
			)
		)
	)
);

$actual = $qb->api('API_GetSchema', array(
	'dbid' => getenv('dbid')
));

if(!objStrctMatch($actual, $expected)){
	throw new Exception('Mismatched API_GetSchema Data Structure');
}

?>
