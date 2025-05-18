<?php
    require_once 'auth.php';
    
    $stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = :user_id AND product_id = :product_id");
    $stmt->execute(['user_id' => $user_id, 'product_id' => $product_id]);
    $favorite = $stmt->fetch();

    if($favorite){
        $stmt = $pdo->prepare("DELETE FROM favorites WHERE user_id = :user_id AND product_id = :product_id");
        $stmt->execute(['user_id' => $user_id, 'product_id' => $product_id]);
        echo json_encode(["message" => "Removed from favorites"]);
    }else{
        echo json_encode(["message" => "Product not in favorites"]);
    }
?>