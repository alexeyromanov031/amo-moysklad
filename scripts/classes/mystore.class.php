<?php
class mystore {
	var $db_config = null;
	var $db_config_table = null;
	var $mystore_config = array();
	var $crm_domain = null;

	function __construct() {
		global $database;
		global $mystore_config;

		$this->db_config = new SQLite3($database);
		$this->mystore_config = array (
			"domain" => $mystore_config["domain"],
			"login" => $mystore_config["login"],
			"pass" => $mystore_config["pass"],
			"meta" => $mystore_config["meta"],
			"delivery_name" => $mystore_config["delivery_name"],
		);
		$this->db_config_table = $mystore_config["db_config_table"];
	}
	
	function First_Auth() {
		$this->db_config->exec("CREATE TABLE IF NOT EXISTS ".$this->db_config_table." (name TEXT PRIMARY KEY NOT NULL , value TEXT NOT NULL, expired INTEGER );");
		// print_r(base64_encode($this->mystore_config["login"].":".$this->mystore_config["pass"]));
		$result = request("https://online.moysklad.ru/api/remap/1.2/security/token", [], 'POST', ["Authorization: Basic ".base64_encode($this->mystore_config["login"].":".$this->mystore_config["pass"])]);
		if (isset($result["access_token"]))
		{
			log_func($result, "First Authorize success", true);
			$this->db_config->exec("INSERT OR REPLACE INTO ".$this->db_config_table." (name,value,expired) VALUES ('access_token','".$result["access_token"]."', false);");
			return $result["access_token"];
		}
		else
		{
			log_func($result, "First Authorize failed");
			die("First Authorize failed");
		}
	}

	function callFunc($link, $data = null, $http_method = 'GET',$header = [], $options = []) 
	{
		// print_r($data);
		$access_token = !empty($this->db_select('WHERE name = "access_token";'))?$this->db_select('WHERE name = "access_token";')[0]:null;
		// Если ключа нет в бд, то создаем новый
		if (!isset($access_token))
			$this->First_Auth();
		// Если истек период работы ключа
		if ($access_token["expired"])
			$this->First_Auth();

		if (empty($header))
			$header =  ['Authorization: Bearer ' .$access_token["value"],'Content-Type: application/json'];

		$result = request($this->mystore_config["domain"].$link, $data, $http_method, $header, $options);
		if (isset($result["errors"]))
		{
			log_func($result, "MyStore request Error!");
			if ($result["errors"][0]["code"] == 1056)
				$this->db_config->exec("INSERT OR REPLACE INTO ".$this->db_config_table." (name,value,expired) VALUES ('access_token','".$access_token["access_token"]."',true);");
		}
		return $result;
	}

	function db_select($request)
	{
		$result = $this->db_config->query('SELECT * FROM '.$this->db_config_table.' '.$request);

		$row = array();
		if (!is_bool($result))
		{
			while($res = $result->fetchArray(SQLITE3_ASSOC))
			{
				array_push($row,$res);
			}
		}
		return $row;
	}

	function __destruct (){
		$this->db_config->close();
	}
}

?>