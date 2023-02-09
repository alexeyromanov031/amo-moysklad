<?php

// $server_name = $_SERVER["SERVER_NAME"];
// $link = 'https://'.$server_name; 

$dirname = "/scripts";
$path = substr( dirname(  __FILE__ ), 0, -strlen($dirname));
$database = $path."/db_store.db";

$crm_config = array (
	"domain" => '',
	'client_id' => "",
	"client_secret" => "",
	"redirect_uri" => "",
	'db_config_table' => "db_config",
	'crm_code' => "",
);

$mystore_config = array(
	"domain" => 'https://online.moysklad.ru/api/remap/1.2/entity',
	"login" => "",
	"pass" => "",
	"delivery_name" => "Доставка",
	"meta" => array(
		"href"=>"https://online.moysklad.ru/api/remap/1.2/entity/organization/",
		"type" => "organization",
		"mediaType" => "application/json",
	),
	'db_config_table' => "db_mystore_config",
);
