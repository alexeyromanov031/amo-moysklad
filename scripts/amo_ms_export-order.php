<?php
//конфигурационные настройки
require_once ('config.php');
//инициация классов и методов
require_once ($path.$dirname.'/classes/mystore.class.php');
require_once ($path.$dirname.'/classes/amocrmapi3.class.php');
require_once ($path.$dirname.'/modules/functions.php');

//получаем вебхук из AMO при переходе сделки в статус "Заказано"
$data = empty($_POST)?json_decode(file_get_contents('php://input'), true):$_POST;
log_func($data, "input data from webhook");

//webhook живет небольшое количество времени, поэтому повторяем запрос
if (isset($data["leads"]["status"]))
{
    $post_data = array(
        "id"=>$data["leads"]["status"][0]["id"],
        "status_id"=>$data["leads"]["status"][0]["status_id"],
    );
    $url = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
    log_func($post_data, "request to me ".$url);
    request($url,$post_data,'POST');
    die();
}
//если в запросе есть id и status id, то отправляем в работу
$id = (!empty($data["id"]) && !empty($data["status_id"]))?$data["id"]:null;
if (isset($id))
{
    // определяем нужные запросы и параметры из амо
    //парсим fields.csv
    $config_params = fileParse($path.'/fields.csv');
    // print_r($config_params);
    $request_info = ["lead"];
    foreach ($config_params as $config_param) {
        if (!in_array($config_param["Сущность в Амо"],$request_info)) $request_info[] = $config_param["Сущность в Амо"];
    }
    // print_r($request_info);
}
else
{
    log_func($data, "crm id not detected");
    die();
}
//Если статус Заказано по fields.csv, то отправляем в работу
$successStatusArray = explode(',', str_replace(array("[", "]"), '', findField("Наименование поля в Амо","Заказано","Имя или id поля в Амо",$config_params)));
if (in_array($data["status_id"],$successStatusArray))
{
    //парсим pricelist
    $pricelist = file($path.'/pricelist')[0];
    // print_r($pricelist);

    //конфигурируем запросы МойСклад
    $mystore = new mystore();

    //получаем информацию о заказе из Амо
    $crm = new amocrmapi3();
    $order = [];
    $order["lead"] = $crm->Call_func('/api/v4/leads/'.$id.'?with=catalog_elements,contacts,products');
    //Если заказ найден, формируем необходимые поля
    if (isset($order["lead"]["_embedded"]))
    {
        // получаем дополнительные требуемые сущности из Амо согласно fields.
        foreach ($request_info as $type) {
            foreach ($config_params as $config_param) {
                $type_id = [];
                if ($config_param["Тип запроса"] === $type && strpos($config_param["Имя или id поля в Амо"],"id") > 0)
                {
                    $type_id = fillFromAmoToMyStorebyCSV(array(array_merge($config_param,array("Имя или id поля в МойСклад"=>$type))));
                }
                if (!empty($type_id))
                {
                    $order[$type] = $crm->Call_func('/api/v4/'.key($type_id).'/'.$type_id[key($type_id)]);
                }
            }
        }
        log_func($order, "order data");

        //парсим инофрмацию по тавару
        $products = productsInfo();
        // log_func($products, "products");

        //Работа с контрагентом
        $customer = customer();
        // log_func($customer, "customer");

        //Создаем заказ в параметрах указывается предоплатный или нет
        $get = newCustomerOrder();
        // log_func($get, "newCustomerOrder");
    }
    else
    {
        log_func($order, "order not found");
    }
}
else
{
    log_func($data, "status id not in array");
}

//Заполняет необходимые поля из Амо в требуемые поля для МойСклад согласно fields.csv
function fillFromAmoToMyStorebyCSV($config_params = [])
{
    global $mystore, $crm, $order;

    $data = [];
    foreach ($config_params as $param) {
        if (!empty($param['Имя или id поля в МойСклад']))
        {
            // получаем значение по Амо
            if (intval($param['Имя или id поля в Амо']) > 1000)
            {
                $amo_data = $crm->get_custom_field_value($order[$param["Сущность в Амо"]]["custom_fields_values"], $param["Имя или id поля в Амо"]);
            }
            else
            {
                $params = explode('.',$param['Имя или id поля в Амо']);
                $variable = $order[$param["Сущность в Амо"]];
                for ($i=0; $i < sizeof($params); $i++) {
                    $variable = isset($variable[$params[$i]])?$variable[$params[$i]]:"";
                }
                $amo_data =  $variable;
            }
            //если есть префикс, топ подставляем его к полученным данным
            if (isset($param["Префикс"]))
                $amo_data = $param["Префикс"].$amo_data;

            //Формируем поля для заказа МойСклад
            //Если поле имеет тип метаданных, то делаем запрос в МойСклад
            $result = null;
            if ($param["Тип Данных в МойСклад"] === "meta" OR strlen($param["Тип Данных в МойСклад"]) > 20)
            {
                $type = (strlen($param["Имя или id поля в МойСклад"]) > 20)?"customentity/":$param["Имя или id поля в МойСклад"];
                $type .= (strlen($param["Тип Данных в МойСклад"]) > 20)?$param["Тип Данных в МойСклад"]:"";
                log_func($type." - ".$amo_data, "find metadata in myStore by amo request");
                $getMetaData = $mystore->callFunc('/'.$type.'?filter='.http_build_query(array("name"=>$amo_data)),array(),'GET');
                log_func($getMetaData, "find metadata in myStore");
                if (isset($getMetaData["rows"][0]))
                {
                    $result = array("meta" => $getMetaData["rows"][0]["meta"]);
                }
            }
            else
            {
                $result = $amo_data."";
            }
            // log_func($result, "connect amo and myStore data");

            //Формируем поле
            if (isset($result))
            {
                if (strlen($param["Имя или id поля в МойСклад"]) > 20)
                {
                    if (!isset ($data["attributes"]))
                        $data["attributes"] = [];

                    $data["attributes"][] = array(
                        "meta" => array(
                            "href" => "https://online.moysklad.ru/api/remap/1.2/entity/".$param["Сущность в МойСклад"]."/metadata/attributes/".$param["Имя или id поля в МойСклад"],
                            "type" => "attributemetadata",
                            "mediaType"=>"application/json",
                        ),
                        "value" => $result,
                    );
                }
                else $data[$param["Имя или id поля в МойСклад"]] = $result;
            }
            $result = null;
        }
    } 
    return $data;
}
    
function productsInfo() //получаем инофрмацию по тавару из заказа Амо и из МойСклад
{
    global $mystore, $crm, $order, $pricelist, $config_params;
    $total_price = 0;
    $delivery_price = 0;
    $products = [];

    foreach ($order["lead"]["_embedded"]["catalog_elements"] as $prod) 
    {
        //получаем информацию о товаре из заказа Амо
        $get = $crm->Call_func('/api/v4/catalogs/'.$prod["metadata"]["catalog_id"].'/elements/'.$prod["id"]);
        log_func($get, "product info from amo");
        if (isset($get["id"]))
        {
            $sku = $crm->get_custom_field_value(
                $get["custom_fields_values"],
                findField("Наименование поля в Амо","Ссылка на сделку Амо","Имя или id поля в Амо",$config_params)
            );
            $product = array(
                "name" => $get["name"],
                "quantity" => $prod["metadata"]["quantity"],
                "sku" => $sku,
                "lastPrice" => 0,
            );

            //получаем информацию по товару из МойСклад и его цену
            $request = $mystore->callFunc(
                    '/product?filter=article='.$product["sku"],
                    array(),
                    'GET'
                );
            log_func($request, "get product info from myStore");

            if(isset($request["rows"][0]))
            {
                $request = $request["rows"][0];
                $product["ms_id"] = $request["id"];
                $product["ms_name"] = $request["name"];
                $product["meta"] = $request["meta"];
                $product["ms_price"] = 0;
                foreach ($request["salePrices"] as $price) {
                    if ($price["priceType"]["id"] === $pricelist)
                        $product["ms_price"] = isset($price["value"])?$price["value"]/100.0:$product["ms_price"];
                }
                if ($product["name"] === $mystore->mystore_config["delivery_name"])
                    $delivery_price = $product["ms_price"];
            }
            // log_func($product, "parse product info from myStore in buffer");
            $total_price += $product["name"] === $mystore->mystore_config["delivery_name"]?0:$product["ms_price"]*$product["quantity"];
            $products[] = $product;
        }
    }

    log_func($total_price, "Total price");
    if ($total_price == 0)
    {
        log_func([],"TOTAL PRICE IS NULL ERRRORRRR!!!!");
        // die("TOTAL PRICE IS NULL ERRRORRRR!!!!");
        $products = [];
    }

    // //Расчет конечной цены товара
    $productsResult = []; 
    foreach ($products as $prod) {
        $last_price = $prod["ms_price"]*($order["lead"]["price"] - $delivery_price)/$total_price;
        $productsResult[] = array_merge($prod,array("lastPrice"=>$last_price));
    }
    return $productsResult;
}

function customer()
{
    global $mystore, $order, $config_params;

    //ищем по клиенту из Амо, создан ли уже покупатель в Мой склад
    foreach ($config_params as $param) {
        if ($param["Тип запроса"] == "counterparty" && $param["Имя или id поля в МойСклад"] == "name")
            $attribute_data[] =  $param;
    }
    $data = fillFromAmoToMyStorebyCSV($attribute_data);
    $customer = $mystore->callFunc(
        '/counterparty?filter='.http_build_query($data),
        array(),
        'GET'
    );
    log_func($customer, "find customer in myStore");

    // Если контрагент (покупатель) не найден, создаем в МойСклад
    if(!isset($customer["rows"][0]))
    {
        //выбираем необходимые поля для заполнения
        $attribute_data = [];
        foreach ($config_params as $param) {
            if ($param["Тип запроса"] === "counterparty")
                $attribute_data[] =  $param;
        }
        $data = fillFromAmoToMyStorebyCSV($attribute_data);
        $data["companyType"] = "individual";
        log_func($data, "find customer in myStore");
        $customer =  $mystore->callFunc(
            '/counterparty',
            $data,
            'POST'
        );
        log_func($customer, "create customer in myStore");
    }
    else
    {
        $customer = $customer["rows"][0];
    }

    if(!isset($customer["id"]))
    {
        log_func([],"customer cannot be crearted ERRRORRRR!!!!");
        die("customer cannot be crearted ERRRORRRR!!!!");
    }

    return $customer;
}

function newCustomerOrder($paymentInStatus = false)
{
    global $mystore, $crm, $order, $config_params, $customer, $products;

    $attribute_data = [];
    foreach ($config_params as $param) {
        if ($param["Тип запроса"] === "order")
            $attribute_data[] =  $param;
    }
    //создаем заказ МойСклад и передаем поля согласно fields.csv
    $data = fillFromAmoToMyStorebyCSV($attribute_data);
    $data["organization"] = array("meta" => $mystore->mystore_config["meta"]);
    $data["agent"] = array("meta" => $customer["meta"]);
    // $data["attributes"] = [];
 
    //Добавляем продукты к заказу
    $positions = [];
    foreach ($products as $product) {
        $positions[] = array(
            "quantity" => $product["quantity"],
            "price" => (float)$product["lastPrice"]*100.0,
            "assortment" => array("meta" => $product["meta"])
        );
    }
    $data["positions"] = $positions;
    log_func($data, "data for order creation");

    // $new_order = $mystore->callFunc(
    //     '/customerorder?filter='.http_build_query(array("name"=>"тестовая")),
    //     array(),
    //     'GET',
    // );
    // log_func($new_order, "find customer in myStore");
    // $new_order = $new_order["rows"][0];

    $new_order = $mystore->callFunc('/customerorder',$data,'POST');
    log_func($new_order, "create new order");

    if (isset($new_order["id"]))
    {
        //Важно, если заказ предоплатный, то создаем еще входящий платеж
        $paymentInStatus = $crm->get_custom_field_value(
            $order["lead"]["custom_fields_values"],
            findField("Наименование поля в Амо","Получена оплата","Имя или id поля в Амо",$config_params)
        );
        log_func((float)$paymentInStatus, "paymentDraft");

        if ($paymentInStatus)
        {
            // Получаем шаблон платежа
            $paymentDraft = $mystore->callFunc('/paymentin/new',
                    array( 
                        "operations" => array("meta"=> $new_order["meta"])
                    ),
                    'PUT'
            );
            // log_func($paymentDraft, "paymentDraft");
            $paymentDraft["agent"] = array("meta"=>$customer["meta"]);
            $paymentDraft["operations"] = array("meta"=>$new_order["meta"]);
            $paymentDraft["paymentPurpose"] = "Предоплата по заказу ".$new_order["name"];
            $paymentDraft["sum"] = (float)$paymentInStatus*100.0;
            log_func($paymentDraft, "paymentDraft after sum correct");
            // создаем платеж на основе шаблона
            $newPaymentIn = $mystore->callFunc(
                '/paymentin',
                $paymentDraft,
                'POST'
            );
            log_func($newPaymentIn, "newPaymentIn");
        }
    }
    else
    {
        log_func([],"new order cannot be crearted ERRRORRRR!!!!");
        die();
    }

    return "success!";
}
?>