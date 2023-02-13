<?php

	//file parser
	function fileParse($filePath = "")
	{
	    //парсим fields.csv
	    $csvFile_fields = file($filePath);
	    array_shift($csvFile_fields);
	    $data_key = explode(";", substr($csvFile_fields[0], 0, -2));
	    array_shift($csvFile_fields);
	    $config_params = [];
	    foreach ($csvFile_fields as $line) {
	        $line_value = explode(";", substr($line, 0, -2));
	        $line_data = [];
	        foreach ($line_value as $key=>$value) {
	            if (!empty($data_key[$key])) $line_data[$data_key[$key]] = $value;
	        }
	        $config_params[] = $line_data;
	    }
	    return $config_params;
	}

	// поиск необходимого значения поля $searchKey в строке где находится параметр с ключем $searchByKey и значением $searchByValue
	function findField($searchByKey,$searchByValue,$searchKey,$data) 
	{
	    foreach ($data as $parameter) {
	        if ($parameter[$searchByKey] === $searchByValue)
	        {
	            return $parameter[$searchKey];
	        }
	    }
	}

	//Curl request
	function request($url,$data=array(),$method='GET', $header = ['Content-Type:application/json'], $options = [])
	{
	  $curl = curl_init();
	  curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($data));
	  $array = $options + array(CURLOPT_URL => $url,
	    CURLOPT_CUSTOMREQUEST => $method,
	    CURLOPT_HTTPHEADER => $header);
	  // if (!empty($user)) curl_setopt($curl, CURLOPT_USERPWD, $user);
	  curl_setopt($curl,CURLOPT_RETURNTRANSFER, true); 
	  curl_setopt_array($curl, $array);
	  $response = curl_exec($curl);
	  // $err = curl_error($curl);
	  $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	  curl_close($curl);
	  
	  $response = json_decode($response,true);
	  if ($code < 200 || $code > 204) {
  		log_func($response,"request.php - ".$url.' '.$method.' '.$code.' ',true);
  		return array("error_code"=>$code, "response"=>$response);
	  }
	  else {
	      return $response;
	  }
	}

	function log_func($data = [], $description = "", $debug = false)
	{
		if ($debug) print_r($data); 
 	   	global $path;
 	   	$log_msg = substr(date('Y-m-d H:i:s ').$description.' '.json_encode($data, JSON_UNESCAPED_UNICODE),0,800)."\n";
	    $log_filename = $path.'/log';
	    if (!file_exists($log_filename)) 
	    {
	        // create directory/folder uploads.
	        mkdir($log_filename, 0777, true);
	    }
	    $log_file_data = $log_filename.'/scripts-php_' . date('d-M-Y') . '.log';
	    // file_put_contents($log_file_data, $log_msg, FILE_APPEND);
	    file_put_contents($log_file_data, $log_msg, FILE_APPEND);
	}
?>