-- Baby Monitor Database Schema
CREATE DATABASE IF NOT EXISTS baby_monitor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE baby_monitor;

-- Users Table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'babysitter') DEFAULT 'babysitter',
    avatar VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

-- Baby Profiles Table
CREATE TABLE baby_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parent_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    birth_date DATE NOT NULL,
    gender ENUM('male', 'female', 'other') DEFAULT 'other',
    weight_kg DECIMAL(4,2) DEFAULT NULL,
    feeding_interval_hours INT DEFAULT 3,
    last_fed TIMESTAMP NULL,
    notes TEXT,
    photo VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Babysitter Assignments
CREATE TABLE babysitter_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    babysitter_id INT NOT NULL,
    baby_id INT NOT NULL,
    assigned_by INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (babysitter_id) REFERENCES users(id),
    FOREIGN KEY (baby_id) REFERENCES baby_profiles(id),
    FOREIGN KEY (assigned_by) REFERENCES users(id)
);

-- Activity Logs (detected events)
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    baby_id INT NOT NULL,
    detected_by INT DEFAULT NULL,
    sound_type ENUM('hungry','sleepy','discomfort','happy','burp','unknown') NOT NULL,
    confidence_score DECIMAL(5,2) DEFAULT 0.00,
    duration_seconds INT DEFAULT 0,
    alert_sent TINYINT(1) DEFAULT 0,
    resolved TINYINT(1) DEFAULT 0,
    resolved_at TIMESTAMP NULL,
    resolved_by INT DEFAULT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (baby_id) REFERENCES baby_profiles(id),
    FOREIGN KEY (detected_by) REFERENCES users(id),
    FOREIGN KEY (resolved_by) REFERENCES users(id)
);

-- Notification Preferences
CREATE TABLE notification_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    sound_enabled TINYINT(1) DEFAULT 1,
    popup_enabled TINYINT(1) DEFAULT 1,
    alert_volume INT DEFAULT 80,
    hungry_alert TINYINT(1) DEFAULT 1,
    sleepy_alert TINYINT(1) DEFAULT 1,
    discomfort_alert TINYINT(1) DEFAULT 1,
    happy_alert TINYINT(1) DEFAULT 0,
    email_alerts TINYINT(1) DEFAULT 0,
    quiet_hours_enabled TINYINT(1) DEFAULT 0,
    quiet_start TIME DEFAULT '22:00:00',
    quiet_end TIME DEFAULT '06:00:00',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Sessions (for security)
CREATE TABLE user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Feeding Schedule
CREATE TABLE feeding_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    baby_id INT NOT NULL,
    logged_by INT NOT NULL,
    fed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    amount_ml INT DEFAULT NULL,
    feeding_type ENUM('breast','formula','solids','water') DEFAULT 'formula',
    notes VARCHAR(255),
    FOREIGN KEY (baby_id) REFERENCES baby_profiles(id),
    FOREIGN KEY (logged_by) REFERENCES users(id)
);

-- ⚠️ DO NOT add demo users here — passwords must be hashed by PHP on your server.
-- After importing this SQL, visit: http://localhost/baby-monitor/setup.php
-- That page will create the demo users with correct bcrypt hashes and seed all data.
