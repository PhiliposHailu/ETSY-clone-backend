<?php
require_once '../config/db.php';

try {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL UNIQUE,
            email VARCHAR(150) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL, -- Store hashed passwords, never plain text
            is_seller BOOLEAN DEFAULT FALSE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP -- Using DATETIME as in your original script
        );

        CREATE TABLE IF NOT EXISTS categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE, -- Category names should be unique
            image VARCHAR(255), -- path or URL to image
            subtitle TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS sellers (
            seller_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL UNIQUE,
            bio TEXT,
            join_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            rating DECIMAL(3, 2), -- Rating between 0.00 and 9.99

            FOREIGN KEY (user_id) REFERENCES users(id)
            ON DELETE CASCADE -- If a user is deleted, their seller profile is deleted
            ON UPDATE CASCADE
        );

        CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            seller_id INT NOT NULL, -- Products must have a seller
            title VARCHAR(255) NOT NULL,
            description TEXT,
            price DECIMAL(10, 2) NOT NULL CHECK (price >= 0), -- Price should be non-negative
            original_price DECIMAL(10, 2) DEFAULT NULL,
            stock_quantity INT NOT NULL CHECK (stock_quantity >= 0), -- Stock should be non-negative
            category_id INT NOT NULL, -- Products must have a category
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            -- Consider adding an updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP here

            -- Foreign key constraint linking product to the seller
            FOREIGN KEY (seller_id) REFERENCES sellers(seller_id)
            ON DELETE CASCADE, -- If a seller is deleted, their products are deleted (adjust if needed)
            -- Foreign key constraint linking product to its category
            FOREIGN KEY (category_id) REFERENCES categories(id)
            ON DELETE RESTRICT -- Prevent deleting a category if products are linked (adjust if needed)
        );

        CREATE TABLE IF NOT EXISTS product_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            image_url TEXT NOT NULL,

            FOREIGN KEY (product_id) REFERENCES products(id)
            ON DELETE CASCADE -- If a product is deleted, its images are deleted
        );

        CREATE TABLE IF NOT EXISTS orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            buyer_id INT NOT NULL, -- The user who placed the order
            total_price DECIMAL(10, 2) NOT NULL CHECK (total_price >= 0), -- Total price should be non-negative
            status VARCHAR(50) DEFAULT 'Pending', -- Consider using ENUM for predefined statuses
            payment_method VARCHAR(50) NOT NULL,
            shipping_address TEXT NOT NULL,
            billing_address TEXT, -- Optional, can be same as shipping
            contact_phone VARCHAR(20) NOT NULL,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP, -- Automatically updates on record modification

            -- Foreign key constraint linking order to the buyer (user)
            FOREIGN KEY (buyer_id) REFERENCES users(id)
            ON DELETE CASCADE -- If a user is deleted, their orders are deleted
        );

        CREATE TABLE IF NOT EXISTS order_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            product_id INT NOT NULL,
            quantity INT NOT NULL CHECK (quantity > 0), -- Quantity must be at least 1
            price DECIMAL(10, 2) NOT NULL CHECK (price >= 0), -- Price at the time of order should be non-negative

            FOREIGN KEY (order_id) REFERENCES orders(id)
            ON DELETE CASCADE, -- If an order is deleted, its items are deleted
            FOREIGN KEY (product_id) REFERENCES products(id)
            ON DELETE RESTRICT -- Prevent deleting a product if it's part of an order item
        );

        CREATE TABLE IF NOT EXISTS cart_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            product_id INT NOT NULL,
            quantity INT NOT NULL CHECK (quantity > 0), -- Quantity must be at least 1

            FOREIGN KEY (user_id) REFERENCES users(id)
            ON DELETE CASCADE, -- If a user is deleted, their cart items are deleted
            FOREIGN KEY (product_id) REFERENCES products(id)
            ON DELETE CASCADE, -- If a product is deleted, it's removed from carts

            UNIQUE KEY user_product_unique (user_id, product_id) -- Ensure a user can only have one entry per product
        );

        CREATE TABLE IF NOT EXISTS favorites (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            product_id INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

            FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE,

            FOREIGN KEY (product_id) REFERENCES products(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE,

            UNIQUE(user_id, product_id) -- To prevent duplicate favorites
        );

        CREATE TABLE IF NOT EXISTS reviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            user_id INT NOT NULL,
            rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5), -- Rating must be between 1 and 5
            comment TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            -- Consider adding an updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP here

            FOREIGN KEY (product_id) REFERENCES products(id)
            ON DELETE CASCADE, -- If a product is deleted, its reviews are deleted
            FOREIGN KEY (user_id) REFERENCES users(id)
            ON DELETE CASCADE -- If a user is deleted, their reviews are deleted
        );

        CREATE TABLE IF NOT EXISTS user_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(64) UNIQUE NOT NULL, -- for a 32-byte hex token
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP, -- Automatically sets the creation time

            -- Define the foreign key constraint
            FOREIGN KEY (user_id) REFERENCES users(id)
            ON DELETE CASCADE -- Deletes all tokens if the associated user is deleted
            ON UPDATE CASCADE -- Updates user_id in all user tokens if user.id changes
        );

    ");

    echo "All tables created successfully.";
} catch (PDOException $e) {
    // Log the error for debugging
    error_log("Database error during table creation: " . $e->getMessage());
    // Display a user-friendly message
    die("An error occurred during database setup. Please check logs: " . $e->getMessage());
}
