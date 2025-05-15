<?php
    require_once '../config/db.php';
    header('Content-Type: application/json');

    $cookie_name = "cart";

    if (isset($_COOKIE[$cookie_name])){
        $cart = json_decode($_COOKIE[$cookie_name], true);
        if(!is_array($cart)){
            $cart = [];
        } 
    }else{
        $cart = [];
    }

    $product_ids = array_column($cart, 'product_id');

    if(empty($product_ids)){
        echo json_encode([]);
        exit;
    }

    $placeholders = rtrim(str_repeat('?,', count($product_ids)), ',');

    $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
    $stmt->execute($product_ids);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $cartWithDetails = [];
    foreach ($products as $product) {
        foreach ($cart as $item) {
            if ($item['product_id'] == $product['id']) {
                $product['quantity'] = $item['quantity'];
                $cartWithDetails[] = $product;
                break;
            }
        }
    }

    echo json_encode($cartWithDetails);

?>