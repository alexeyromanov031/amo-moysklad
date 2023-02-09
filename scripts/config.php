<?php

// $server_name = $_SERVER["SERVER_NAME"];
// $link = 'https://'.$server_name; 

$dirname = "/scripts";
$path = substr( dirname(  __FILE__ ), 0, -strlen($dirname));
$database = $path."/db_store.db";

$crm_config = array (
	"domain" => 'https://mygepatit.amocrm.ru',
	'client_id' => "e00fd34b-a95b-45ca-b1eb-92190e6f51d8",
	"client_secret" => "ZP9awHk3fvXasNNxcHucftxTMt6xvTWQa7LmUq40lijXqm53f4X0eBcCD5xAXYKA",
	"redirect_uri" => "https://zydusheptiza.ru",
	'db_config_table' => "db_config",
	'crm_code' => "def502002737fc1237a94a4488652bf41b5564d9e82cc57e152a36ac99e360e5f7fa1bee3730aeac2d5af2582f1f9e96bace11457f47fa1487030e019c922122e4c5b2cb78dea2d390641b951535cd3d03f9a7f3af2e575e62b13727793bb5b142371ae177bc05c08a8e215d85b2fc9db500507c8a744e045f4cbeaafa1e4bed6088bb7d2cbb2955c9c5abd6a7571371c32056517ef5560605113ad24b2e055404245a0cc8c390c2100f56ff4d747270ac692dd30da78a0e1424e00c7ce275cb799e5d7368ff0cd2dc1f7bcdce4cccd35e5c0264cb75ea968ca38293c08fe2ddaffb96085096b1e46eb078e8eda6a722557dc9c206b87774fd4adccc359642b43706882d736f091158470a3f5ede7b21f4c7e0511a575e811d3828e75143df001c3a6cb12d95df0db07f8cc24254be865dfbfa83a6952809ced1bb91378009863e191834ba1c2aafb7f586e1c248bf1f3d0d7e8abf899400388fa64aaca697580a077b7a0724402a113b37fa9239641f49cb5e7e56f8a729ef9c3708947e10dad1e849639a51bfc6181a60a231d462d9aa02c88ab95472db6c356248bab466845f09fd74c881bdbec27cc98618b2b44aafccfe47108fa056e6eac45668e57cf4de7f6d004a6d6c8968c5db66826b496d8c9ebb13d0509a45",
);

$mystore_config = array(
	"domain" => 'https://online.moysklad.ru/api/remap/1.2/entity',
	"login" => "admin@mygepatit",
	"pass" => "933e75984e",
	"delivery_name" => "Доставка",
	"meta" => array(
		"href"=>"https://online.moysklad.ru/api/remap/1.2/entity/organization/e38c5e6e-ba66-11e8-9107-50480024d53d",
		"type" => "organization",
		"mediaType" => "application/json",
	),
	'db_config_table' => "db_mystore_config",
);