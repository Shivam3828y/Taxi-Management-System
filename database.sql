CREATE DATABASE IF NOT EXISTS taxi_management;

USE taxi_management;


-- =====================================================
-- ADMINS
-- =====================================================

CREATE TABLE admins (

    id INT AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(100) NOT NULL,

    username VARCHAR(50) NOT NULL UNIQUE,

    password VARCHAR(255) NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

);


-- =====================================================
-- DRIVERS
-- =====================================================

CREATE TABLE drivers (

    id INT AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(100) NOT NULL,

    phone VARCHAR(20) NOT NULL UNIQUE,

    email VARCHAR(100),

    address TEXT,

    driving_license VARCHAR(50) NOT NULL UNIQUE,

    status ENUM(
        'Pending',
        'Verified',
        'Rejected',
        'Inactive'
    ) NOT NULL DEFAULT 'Pending',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

);


-- =====================================================
-- TAXIS
-- =====================================================

CREATE TABLE taxis (

    id INT AUTO_INCREMENT PRIMARY KEY,

    brand VARCHAR(50) NOT NULL,

    model VARCHAR(50) NOT NULL,

    registration_number VARCHAR(20) NOT NULL UNIQUE,

    rent DECIMAL(10,2) NOT NULL DEFAULT 0,

    status ENUM(
        'Available',
        'Assigned',
        'Maintenance',
        'Inactive'
    ) NOT NULL DEFAULT 'Available',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

);


-- =====================================================
-- ASSIGNMENTS
-- =====================================================

CREATE TABLE assignments (

    id INT AUTO_INCREMENT PRIMARY KEY,

    driver_id INT NOT NULL,

    taxi_id INT NOT NULL,

    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    status ENUM(
        'Active',
        'Completed',
        'Cancelled'
    ) NOT NULL DEFAULT 'Active',

    FOREIGN KEY (driver_id)
        REFERENCES drivers(id)
        ON DELETE CASCADE,

    FOREIGN KEY (taxi_id)
        REFERENCES taxis(id)
        ON DELETE CASCADE

);


-- =====================================================
-- AGREEMENTS
-- =====================================================

CREATE TABLE agreements (

    id INT AUTO_INCREMENT PRIMARY KEY,

    assignment_id INT NOT NULL,

    start_date DATE NOT NULL,

    end_date DATE,

    rent DECIMAL(10,2) NOT NULL,

    status ENUM(
        'Active',
        'Expired',
        'Cancelled'
    ) NOT NULL DEFAULT 'Active',

    accepted TINYINT(1) NOT NULL DEFAULT 0,

    accepted_at DATETIME NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (assignment_id)
        REFERENCES assignments(id)
        ON DELETE CASCADE

);


-- =====================================================
-- PAYMENTS
-- =====================================================

CREATE TABLE payments (

    id INT AUTO_INCREMENT PRIMARY KEY,

    driver_id INT NOT NULL,

    agreement_id INT NULL,

    amount DECIMAL(10,2) NOT NULL,

    payment_date DATE NOT NULL,

    payment_method VARCHAR(50),

    status ENUM(
        'Pending',
        'Paid',
        'Failed'
    ) NOT NULL DEFAULT 'Pending',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (driver_id)
        REFERENCES drivers(id)
        ON DELETE CASCADE,

    FOREIGN KEY (agreement_id)
        REFERENCES agreements(id)
        ON DELETE SET NULL

);