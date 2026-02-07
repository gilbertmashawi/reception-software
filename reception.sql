-- Create database
CREATE DATABASE IF NOT EXISTS linkspot_db;
USE linkspot_db;

-- Users table (for login)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    role VARCHAR(20) DEFAULT 'reception',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default users
INSERT INTO users (username, password, full_name, role) VALUES
('gilbert', 'reception', 'Gilbert', 'reception'),
('teddy', 'reception', 'Teddy', 'reception'),
('walter', 'reception', 'Walter', 'reception'),
('tafadzwa', 'reception', 'Tafadzwa', 'reception'),
('admin', 'admin123', 'Administrator', 'admin');

-- Meeting rooms table
CREATE TABLE IF NOT EXISTS meeting_rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_name VARCHAR(100) NOT NULL,
    booked_by VARCHAR(100) NOT NULL,
    start_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_date DATE NOT NULL,
    end_time TIME NOT NULL,
    hours DECIMAL(5,2) NOT NULL,
    cost DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (start_date),
    INDEX (booked_by)
);

-- Voucher types table
CREATE TABLE IF NOT EXISTS voucher_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE
);

-- Insert voucher types
INSERT INTO voucher_types (name, price) VALUES
('1 Hour', 1.00),
('2 Hours', 2.00),
('3 Hours', 3.00),
('4 Hours', 4.00),
('1 Day', 5.00),
('Laptop', 1.00),
('Day Laptop', 2.00);

-- Voucher sales table
CREATE TABLE IF NOT EXISTS voucher_sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_date DATE NOT NULL,
    sale_time TIME NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (sale_date)
);

-- Voucher sale items table
CREATE TABLE IF NOT EXISTS voucher_sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    voucher_type_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES voucher_sales(id) ON DELETE CASCADE,
    FOREIGN KEY (voucher_type_id) REFERENCES voucher_types(id),
    INDEX (sale_id)
);

-- Mall payments table
CREATE TABLE IF NOT EXISTS mall_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_date DATE NOT NULL,
    month_paid VARCHAR(50) NOT NULL,
    payer_name VARCHAR(100) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (payment_date)
);

-- LinkSpot payments table
CREATE TABLE IF NOT EXISTS linkspot_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_date DATE NOT NULL,
    month_paid VARCHAR(50) NOT NULL,
    payer_name VARCHAR(100) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (payment_date)
);

-- Tasks table
CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_date DATE NOT NULL,
    task_time TIME NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    status ENUM('pending', 'complete', 'halt') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (task_date),
    INDEX (status)
);

-- History/Activity log table
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    activity_date DATE NOT NULL,
    activity_time TIME NOT NULL,
    activity_type VARCHAR(50) NOT NULL,
    description VARCHAR(500) NOT NULL,
    details TEXT,
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (activity_date),
    INDEX (activity_type),
    FOREIGN KEY (user_id) REFERENCES users(id)
);