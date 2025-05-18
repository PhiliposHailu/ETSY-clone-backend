<?php
    require_once 'auth.php';
    
    $stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = :user_id AND product_id = :product_id");
    $stmt->execute(['user_id' => $user_id, 'product_id' => $product_id]);
    $favorite = $stmt->fetch();

    if($favorite){
        echo json_encode(["messege" => "product already in favorite"]);
    }else{
        $stmt = $pdo->prepare("INSERT INTO favorites (user_id, product_id) VALUES (:user_id, :product_id)");
        $stmt->execute(['user_id' => $user_id, 'product_id' => $product_id]);
        echo json_encode(["message" => "Added to favorites"]);
    }
?>