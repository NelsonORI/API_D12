<?php
// C:\Apache24\htdocs\API_D12\Database\migration.php

// 1. Load configuration and establish PDO connection
// Corrected path to navigate up one level to API_D12 root for conf.php
require_once __DIR__ . "/../conf.php"; // load $conf settings

try {
    // Attempt to establish connection
    $dsn = "mysql:host={$conf['db_host']};dbname={$conf['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $conf['db_user'], $conf['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); // For better security

    echo "✅ DB connection established.\n";

} catch (PDOException $e) {
    die("❌ Connection failed: " . $e->getMessage() . "\n");
}

// 2. Define the SQL commands as a single string
$sql = "
-- Temporarily disable foreign key checks to safely drop and re-create tables
SET FOREIGN_KEY_CHECKS = 0;

-- **FORCE RESET:** Drop all tables to ensure the expected structure is created
DROP TABLE IF EXISTS transactions;
DROP TABLE IF EXISTS `order`;
DROP TABLE IF EXISTS inventories;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS event;
DROP TABLE IF EXISTS project;

-- 1. CREATE TABLE: project (User/Account table)
CREATE TABLE project (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NULL DEFAULT NULL,
    role ENUM('admin', 'user') NULL DEFAULT 'user',
    is_active TINYINT(1) NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. CREATE TABLE: password_resets
CREATE TABLE password_resets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(255) NOT NULL,
    reset_token VARCHAR(64) NOT NULL UNIQUE,
    token_expires DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    used TINYINT(1) NULL DEFAULT 0,
    PRIMARY KEY (id),
    INDEX user_id (user_id),
    CONSTRAINT password_resets_ibfk_1 FOREIGN KEY (user_id) REFERENCES project (id) ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. CREATE TABLE: event
CREATE TABLE event (
    id INT NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL DEFAULT NULL,
    venue VARCHAR(255) NULL DEFAULT NULL,
    event_date DATETIME NULL DEFAULT NULL,
    ticket_price DECIMAL(10,2) NULL DEFAULT NULL,
    available_tickets INT NULL DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. CREATE TABLE: inventories
CREATE TABLE inventories (
    id INT NOT NULL AUTO_INCREMENT,
    event_id INT NULL DEFAULT NULL,
    tickets_available INT NULL DEFAULT NULL,
    tickets_sold INT NULL DEFAULT 0,
    last_updated TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX event_id (event_id),
    CONSTRAINT inventories_ibfk_1 FOREIGN KEY (event_id) REFERENCES event (id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. CREATE TABLE: 'order' (Using backticks is crucial for the reserved word)
CREATE TABLE `order` (
    id INT NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL DEFAULT NULL,
    event_id INT NULL DEFAULT NULL,
    quantity INT NULL DEFAULT NULL,
    total_amount DECIMAL(10,2) NULL DEFAULT NULL,
    status ENUM('pending', 'confirmed', 'cancelled') NULL DEFAULT 'pending',
    order_date TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    checkout_request_id VARCHAR(255) NULL DEFAULT NULL,
    payment_reference VARCHAR(255) NULL DEFAULT NULL,
    paid_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    INDEX user_id (user_id),
    INDEX event_id (event_id),
    CONSTRAINT order_ibfk_1 FOREIGN KEY (user_id) REFERENCES project (id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT order_ibfk_2 FOREIGN KEY (event_id) REFERENCES event (id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. CREATE TABLE: transactions
CREATE TABLE transactions (
    id INT NOT NULL AUTO_INCREMENT,
    order_id INT NULL DEFAULT NULL,
    checkout_request_id VARCHAR(255) NULL DEFAULT NULL UNIQUE,
    phone_number VARCHAR(20) NULL DEFAULT NULL,
    amount DECIMAL(10,2) NULL DEFAULT NULL,
    account_reference VARCHAR(255) NULL DEFAULT NULL,
    status ENUM('pending', 'completed', 'failed') NULL DEFAULT 'pending',
    mpesa_response TEXT NULL DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX order_id (order_id),
    CONSTRAINT transactions_ibfk_1 FOREIGN KEY (order_id) REFERENCES `order` (id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;
";

// 3. Execute the SQL statements
try {
    // The exec() method executes one or more SQL statements and returns the number of rows affected.
    $pdo->exec($sql);
    echo "✅ Database tables created/checked successfully!!!!\n";
} catch (PDOException $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
}

