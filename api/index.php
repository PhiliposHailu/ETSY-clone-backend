<?php
    header("Content-Type: application/json, multipart/form-data");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE, PUT");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Access-Control-Allow-Origin: *");

    $method = $_SERVER["REQUEST_METHOD"];
    $url = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

    $path = str_replace("/etsy/api", "", $url);

    if ($method === 'OPTIONS') {
        // Respond with 204 No Content for a successful preflight
        http_response_code(204);
        exit(); // Stop script execution after handling preflight
    }

    // for api/cart/add/<p_id>
    if (($method === "POST" || $method === "PUT") && preg_match("#^/cart/add/(\d+)$#", $path, $matches)) {
        $product_id = (int)$matches[1];
    
        if ($product_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid product ID']);
            exit;
        }
    
        require "handlers/cart_add.php";
    }

    else if ($method === "DELETE" && preg_match("#^/cart/delete/(\d+)$#", $path, $matches)) {
        $product_id = (int)$matches[1];
    
        if ($product_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
            exit;
        }
        require "handlers/cart_delete.php";
    }
    
    // for api/cart
    elseif($method == "GET" && $path == "/cart"){
        require "handlers/cart_list.php";
    }
    
    // for api/cart/remove/<p_id>
    elseif($method == "DELETE" && preg_match("#^/cart/remove/(\d+)$#", $path, $matches)){
        $product_id = (int)$matches[1];
        if ($product_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid product ID']);
            exit;
        }
        require "handlers/cart_remove.php";
    }

    // for api/review/add/<p_id>
    elseif($method == "POST" && preg_match("#^/review/add/(\d+)$#", $path, $matches)){
        $product_id = (int)$matches[1];
        if($product_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid product ID']);
            exit;
        }
        require "handlers/review_add.php";
    }

    // for api/review/<p_id>
    elseif($method == "GET" && preg_match("#^/reviews/(\d+)$#", $path, $matches)){
        $product_id = (int)$matches[1];
        if($product_id <= 0){
            http_response_code(400);
            echo json_encode(['error' => 'Invalid product ID']);
            exit;
        }
        require "handlers/review_list.php";

    }
    
    // for api/category/<p_id>
    elseif($method == "GET" &&  preg_match("#^/category/(\d+)$#", $path, $matches)) {
            $category_id = $matches[1];
            require "handlers/category_get.php";
    }

    // for api/favorite
    elseif($method === "GET" && $path === "/favorite"){
        require "handlers/favorites_list.php";
    }

    elseif($method === "POST" && preg_match("#^/favorite/(\d+)$#", $path, $matches)){
        $product_id = (int)$matches[1];
        if($product_id <= 0){
            http_response_code(400);
            echo json_encode(['error' => 'Invalid product ID']);
            exit;
        }
        require "handlers/favorites_add.php";
    }

     elseif($method === "DELETE" && preg_match("#^/favorite/(\d+)$#", $path, $matches)){
        $product_id = (int)$matches[1];
        if($product_id <= 0){
            http_response_code(400);
            echo json_encode(['error' => 'Invalid product ID']);
            exit;
        }
        require "handlers/favorites_remove.php";
    }
    
    // for api/category
    elseif ($method === "GET" && $path === "/category") {
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

    elseif ($method === "GET" && $path === "/products/discounted") {
        require "handlers/get_discounted_products.php";
    }

    elseif ($method === "GET" && $path === "/products/purchased") {
        require "handlers/purchased_products.php";
    }

    // GET /products – all products
    elseif ($method === "GET" && preg_match("#^/products(?:\?(?:seller=(\d+))?(?:&?category=(\d+))?)?$#", $path, $matches)) {
        $sellerUserId = $matches[1] ?? null;
        $category_id =  $matches[2] ?? null;
    
        require "handlers/products.php";
    }

    // GET /product/<id> – single product
    elseif ($method === "GET" && preg_match("#^/product/(\d+)$#", $path, $matches)) {
        $product_id = $matches[1]; // Router extracts the ID and stores it
        require "handlers/product.php";
    }

    // GET /seller/<id> – get seller details
    elseif ($method === "GET" && preg_match("#^/seller/(\d+)$#", $path, $matches)) {
        $seller_id = $matches[1];
        require "handlers/seller_get.php";
    }

    //-- Product Endpoints for Sellers --//

    // Add new product
    elseif ($method === "POST" && $path === "/product/add") {
        require "handlers/product_add.php";
    }

    // Update existing product
    elseif ($method === "PUT" && preg_match("#^/product/update/(\d+)$#", $path, $matches)) {
        $product_id = (int)$matches[1];
        require "handlers/product_update.php";
    }

    // Delete a product
    elseif ($method === "DELETE" && preg_match("#^/product/delete/(\d+)$#", $path, $matches)) {
        $product_id = (int)$matches[1];
        require "handlers/product_remove.php";
    }

    elseif ($method === "GET" && $path === "/get_me") {
        require "handlers/get_me.php";
    }

    // Get Orders Made to a Loged in user 
    elseif ($method === "GET" && $path === "/orders") {
        require "handlers/order.php";
    }

    elseif ($method === "GET" && isset($_GET['id']) && preg_match("#^/users\?id=(\d+)$#", $path, $matches)) {
        $user_id = $matches[1];
        require "handlers/get_user.php";
    }

    // CHECK OUT 
    elseif ($method === "POST" && $path === "/buy"){
        require "handlers/orders.php";
    }

    elseif ($method === "GET" && $path === "/recent"){
        require "handlers/recent.php";
    }

    elseif ($method === "GET" && $path === "/stats"){
        require "handlers/seller_stats.php";
    }

    elseif ($method === "GET" && $path === "/reviews"){
        require "handlers/reviews_seller.php";
    }
    
    else {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "$path API Endpoint Not Found!"]);
    }