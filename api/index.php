<?php
    header("Content-Type: application/json");

    $method = $_SERVER["REQUEST_METHOD"];
    $url = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

    $path = str_replace("/etsy-clone-backend/api", "", $url);

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
    elseif($method == "DELETE" && preg_match("#^/cart/remove/(\d+)$#", $path, $matches)){
        $product_id = $matches[1];
        require "handlers/cart_list.php";
    }
    
    // for api/category/<p_id>
    elseif($method == "GET" &&  preg_match("#^/category/([\w-]+)$#", $path, $matches)) {
            $category_name = $matches[1];
            require "handlers/category_get.php";
    }
    
    // for api/category
    elseif ($method == "GET" && $path == "/category") {
            require "handlers/category_list.php";
    }

    // Registration: POST /register
    elseif ($method === "POST" && $path === "/register") {
        require "handlers/register.php";
    }

    // Sign in: POST /signin
    elseif ($method === "POST" && $path === "/signin") {
        require "handlers/signin.php";
    }

    // GET /listing – all listings
    elseif ($method === "GET" && $path === "/product_list") {
        require "handlers/products_list.php";
    }

    // GET /listing/<id> – single listing
    elseif ($method === "GET" && preg_match("#^/product_get/(\d+)$#", $path, $matches)) {
        $product_id = $matches[1]; // Router extracts the ID and stores it
        require "handlers/product_get.php";
    }

    // GET /seller/<id> – get seller details
    elseif ($method === "GET" && preg_match("#^/seller/(\d+)$#", $path, $matches)) {
        $seller_id = $matches[1];
        require "handlers/seller_get.php";
    }
    
    else{
        http_response_code(404);
    }
?>