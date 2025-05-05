<?php
    header("Content_Type: Applicatioin/json");

    $method = $_SERVER["REQUEST_METHOD"];
    $url = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

    $path = str_replace("/api", "", $url);

    // for api/cart/add/<p_id>
    if($method == "POST" && preg_match("#^/cart/add/(\d+)$#", $path, $matches)){
        $product_id = $matches[1];
        require "handlers/cart_add.php";
    }
    
    // for api/cart
    elseif($method == "GET" && $path == "/cart"){
        require "handlers/cart_list.php";
    }
    
    // for api/cart/remove/<p_id>
    elseif($method == "POST" && preg_match("#^/cart/remove/(\d+)$#", $path, $matches)){
        $product_id = $matches[1];
        require "handlers/cart_list.php";
    }
    
    // for api/category/<p_id>
    elseif($method == "GET" &&  preg_match("#^/category/([\w-]+)$#", $path, $matches)) {
            $category_id = $matches[1];
            require "handlers/category_get.php";
        
    }
    
    // for api/category
    elseif ($method == "GET" && $path == "/category") {
            require "handlers/category_list.php";
    }
    
    else{
        http_response_code(404);
    }
?>