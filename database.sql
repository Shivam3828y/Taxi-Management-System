CREATE DATABASE IF NOT EXISTS taxi_management;

USE taxi_management;

CREATE TABLE taxis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brand VARCHAR(50) NOT NULL,
    model VARCHAR(50) NOT NULL,
    registration_number VARCHAR(20) NOT NULL UNIQUE,
    rent DECIMAL(10,2) NOT NULL DEFAULT 0,
    status ENUM('Available', 'Assigned', 'Maintenance', 'Inactive')
           NOT NULL DEFAULT 'Available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);