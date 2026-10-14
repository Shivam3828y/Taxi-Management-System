<<<<<<< HEAD
-- Taxi Management System schema. Safe to run repeatedly; it never deletes rows.
CREATE DATABASE IF NOT EXISTS taxi_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE taxi_management;

CREATE TABLE IF NOT EXISTS taxis (
=======
CREATE DATABASE IF NOT EXISTS taxi_management;

USE taxi_management;

CREATE TABLE taxis (
>>>>>>> 21d202221995cc0ce9792faca7d37b1ccb23b3b4
    id INT AUTO_INCREMENT PRIMARY KEY,
    brand VARCHAR(50) NOT NULL,
    model VARCHAR(50) NOT NULL,
    registration_number VARCHAR(20) NOT NULL UNIQUE,
    rent DECIMAL(10,2) NOT NULL DEFAULT 0,
<<<<<<< HEAD
    status ENUM('Available', 'Assigned', 'Maintenance', 'Inactive') NOT NULL DEFAULT 'Available',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admins (
=======
    status ENUM('Available', 'Assigned', 'Maintenance', 'Inactive')
           NOT NULL DEFAULT 'Available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE admins (
>>>>>>> 21d202221995cc0ce9792faca7d37b1ccb23b3b4
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
<<<<<<< HEAD
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS drivers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL UNIQUE,
    email VARCHAR(100) NULL,
    address VARCHAR(255) NOT NULL,
    driving_license VARCHAR(50) NOT NULL UNIQUE,
    document_type VARCHAR(50) NULL,
    document_number VARCHAR(50) NULL,
    password VARCHAR(255) NULL,
    status ENUM('Pending', 'Verified', 'Inactive') NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assignments (
=======
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE drivers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL UNIQUE,
    email VARCHAR(100),
    address VARCHAR(255) NOT NULL,
    driving_license VARCHAR(50) NOT NULL UNIQUE,
    document_type VARCHAR(50),
    document_number VARCHAR(50),
    password VARCHAR(255),
    status ENUM('Pending', 'Verified', 'Inactive')
           NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE assignments (
>>>>>>> 21d202221995cc0ce9792faca7d37b1ccb23b3b4
    id INT AUTO_INCREMENT PRIMARY KEY,
    driver_id INT NOT NULL,
    taxi_id INT NOT NULL,
    status ENUM('Active', 'Ended') NOT NULL DEFAULT 'Active',
<<<<<<< HEAD
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_assignments_driver FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE,
    CONSTRAINT fk_assignments_taxi FOREIGN KEY (taxi_id) REFERENCES taxis(id) ON DELETE CASCADE,
    INDEX idx_assignments_driver_status (driver_id, status),
    INDEX idx_assignments_taxi_status (taxi_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agreements (
=======
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE,
    FOREIGN KEY (taxi_id) REFERENCES taxis(id) ON DELETE CASCADE
);


CREATE TABLE agreements (
>>>>>>> 21d202221995cc0ce9792faca7d37b1ccb23b3b4
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    rent DECIMAL(10,2) NOT NULL,
<<<<<<< HEAD
    terms TEXT NULL,
    status ENUM('Active', 'Ended') NOT NULL DEFAULT 'Active',
    accepted TINYINT(1) NOT NULL DEFAULT 0,
    accepted_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_agreements_assignment FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    INDEX idx_agreements_assignment_status (assignment_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
=======
    terms TEXT,
    status ENUM('Active', 'Ended') NOT NULL DEFAULT 'Active',
    accepted TINYINT(1) NOT NULL DEFAULT 0,
    accepted_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE
);


CREATE TABLE payments (
>>>>>>> 21d202221995cc0ce9792faca7d37b1ccb23b3b4
    id INT AUTO_INCREMENT PRIMARY KEY,
    driver_id INT NOT NULL,
    assignment_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
<<<<<<< HEAD
    payment_method VARCHAR(50) NULL,
    status ENUM('Paid', 'Pending', 'Failed') NOT NULL DEFAULT 'Pending',
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payments_driver FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE,
    CONSTRAINT fk_payments_assignment FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    INDEX idx_payments_driver_date (driver_id, payment_date),
    INDEX idx_payments_assignment_status (assignment_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Required by driver/taxi_request.php, driver/payments.php, and admin/assignments.php.
CREATE TABLE IF NOT EXISTS taxi_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    driver_id INT NOT NULL,
    taxi_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    payment_status ENUM('Paid', 'Pending', 'Failed') NOT NULL DEFAULT 'Pending',
    request_status ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
    rejection_reason VARCHAR(255) NULL,
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL DEFAULT NULL,
    assigned_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_taxi_requests_driver FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE,
    CONSTRAINT fk_taxi_requests_taxi FOREIGN KEY (taxi_id) REFERENCES taxis(id) ON DELETE CASCADE,
    INDEX idx_taxi_requests_driver_status (driver_id, request_status),
    INDEX idx_taxi_requests_taxi_status (taxi_id, request_status),
    INDEX idx_taxi_requests_status_date (request_status, requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
=======
    payment_method VARCHAR(50),
    status ENUM('Paid', 'Pending', 'Failed') NOT NULL DEFAULT 'Pending',
    notes VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE,
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE
);
>>>>>>> 21d202221995cc0ce9792faca7d37b1ccb23b3b4
