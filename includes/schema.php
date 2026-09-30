<?php
declare(strict_types=1);

/** Krijon tabelat (MySQL ose SQLite). */
function schema_statements(string $driver): array
{
    $pk = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    $eng = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';

    return [
        "CREATE TABLE IF NOT EXISTS admins (
            id $pk,
            email VARCHAR(190) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at VARCHAR(19)
        )$eng",

        "CREATE TABLE IF NOT EXISTS categories (
            id $pk,
            parent_id INT NULL,
            name VARCHAR(120) NOT NULL,
            slug VARCHAR(140) NOT NULL,
            sort INT NOT NULL DEFAULT 0
        )$eng",

        "CREATE TABLE IF NOT EXISTS products (
            id $pk,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            category_id INT NULL,
            subcategory_id INT NULL,
            brand VARCHAR(120) NULL,
            sku VARCHAR(80) NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0,
            sale_price DECIMAL(10,2) NULL,
            stock INT NOT NULL DEFAULT 0,
            low_stock_threshold INT NOT NULL DEFAULT 5,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            badge_new TINYINT NOT NULL DEFAULT 0,
            badge_sale TINYINT NOT NULL DEFAULT 0,
            badge_best TINYINT NOT NULL DEFAULT 0,
            badge_featured TINYINT NOT NULL DEFAULT 0,
            sizes VARCHAR(255) NULL,
            colors VARCHAR(255) NULL,
            material VARCHAR(255) NULL,
            weight VARCHAR(80) NULL,
            details TEXT,
            sold_count INT NOT NULL DEFAULT 0,
            created_at VARCHAR(19),
            updated_at VARCHAR(19)
        )$eng",

        "CREATE TABLE IF NOT EXISTS product_images (
            id $pk,
            product_id INT NOT NULL,
            path VARCHAR(255) NOT NULL,
            sort INT NOT NULL DEFAULT 0
        )$eng",

        "CREATE TABLE IF NOT EXISTS customers (
            id $pk,
            name VARCHAR(190) NOT NULL,
            email VARCHAR(190) NOT NULL UNIQUE,
            phone VARCHAR(60) NULL,
            created_at VARCHAR(19)
        )$eng",

        "CREATE TABLE IF NOT EXISTS orders (
            id $pk,
            customer_id INT NULL,
            customer_name VARCHAR(190) NOT NULL,
            email VARCHAR(190) NOT NULL,
            phone VARCHAR(60) NULL,
            country VARCHAR(100) NULL,
            city VARCHAR(120) NULL,
            address VARCHAR(255) NULL,
            postal_code VARCHAR(30) NULL,
            notes TEXT,
            subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
            shipping DECIMAL(10,2) NOT NULL DEFAULT 0,
            discount DECIMAL(10,2) NOT NULL DEFAULT 0,
            total DECIMAL(10,2) NOT NULL DEFAULT 0,
            discount_code VARCHAR(60) NULL,
            payment_method VARCHAR(40) NOT NULL DEFAULT 'cod',
            payment_status VARCHAR(20) NOT NULL DEFAULT 'unpaid',
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at VARCHAR(19)
        )$eng",

        "CREATE TABLE IF NOT EXISTS order_items (
            id $pk,
            order_id INT NOT NULL,
            product_id INT NULL,
            name VARCHAR(255) NOT NULL,
            image VARCHAR(255) NULL,
            size VARCHAR(60) NULL,
            color VARCHAR(60) NULL,
            qty INT NOT NULL DEFAULT 1,
            price DECIMAL(10,2) NOT NULL DEFAULT 0
        )$eng",

        "CREATE TABLE IF NOT EXISTS discounts (
            id $pk,
            code VARCHAR(60) NOT NULL UNIQUE,
            percent DECIMAL(5,2) NOT NULL DEFAULT 0,
            start_date VARCHAR(10) NULL,
            end_date VARCHAR(10) NULL,
            min_order DECIMAL(10,2) NOT NULL DEFAULT 0,
            max_uses INT NOT NULL DEFAULT 0,
            used INT NOT NULL DEFAULT 0,
            active TINYINT NOT NULL DEFAULT 1
        )$eng",

        "CREATE TABLE IF NOT EXISTS reviews (
            id $pk,
            product_id INT NOT NULL,
            customer_name VARCHAR(190) NOT NULL,
            rating INT NOT NULL DEFAULT 5,
            comment TEXT,
            approved TINYINT NOT NULL DEFAULT 0,
            created_at VARCHAR(19)
        )$eng",

        "CREATE TABLE IF NOT EXISTS banners (
            id $pk,
            title VARCHAR(190) NOT NULL,
            subtitle VARCHAR(255) NULL,
            image VARCHAR(255) NULL,
            link VARCHAR(255) NULL,
            sort INT NOT NULL DEFAULT 0,
            active TINYINT NOT NULL DEFAULT 1
        )$eng",

        "CREATE TABLE IF NOT EXISTS settings (
            k VARCHAR(100) NOT NULL PRIMARY KEY,
            v TEXT
        )$eng",
    ];
}

function default_settings(): array
{
    return [
        'store_name' => 'UTILITY.KS',
        'contact_email' => '',
        'shipping_cost' => '3.00',
        'free_shipping_over' => '100',
        'best_seller_min' => '5',
        'hero_title' => 'BUILT FOR THE ONES WHO TRAIN',
        'hero_subtitle' => 'Premium boxing, training & everyday utility wear.',
        'hero_button_text' => 'SHOP NOW',
        'hero_button_link' => 'shop',
        'hero_image' => '',
        'featured_title' => 'FEATURED',
        'new_title' => 'NEW ARRIVALS',
        'best_title' => 'BEST SELLERS',
        'section_limit' => '8',
    ];
}
