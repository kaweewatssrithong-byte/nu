CREATE DATABASE IF NOT EXISTS workflow_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE workflow_system;

CREATE TABLE IF NOT EXISTS roles (
    role_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_code VARCHAR(30) NOT NULL UNIQUE,
    role_name VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS departments (
    department_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_code VARCHAR(30) NULL UNIQUE,
    department_name VARCHAR(180) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(80) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(180) NOT NULL,
    email VARCHAR(190) NULL,
    google_sub VARCHAR(255) NULL UNIQUE,
    role_id INT UNSIGNED NOT NULL,
    department_id INT UNSIGNED NULL,
    status ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
    last_login_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(role_id),
    CONSTRAINT fk_users_department FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE SET NULL,
    INDEX idx_users_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tasks (
    task_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_code VARCHAR(40) NOT NULL UNIQUE,
    title VARCHAR(240) NOT NULL,
    description TEXT NULL,
    category ENUM('main','special') NOT NULL DEFAULT 'main',
    status ENUM('pending','assigned','in_progress','waiting_approval','approved','rejected','completed','cancelled') NOT NULL DEFAULT 'pending',
    priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
    created_by INT UNSIGNED NULL,
    assigned_to INT UNSIGNED NULL,
    assigned_department_id INT UNSIGNED NULL,
    due_date DATE NULL,
    evidence_url VARCHAR(1000) NULL,
    evidence_file VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_tasks_creator FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT fk_tasks_assignee FOREIGN KEY (assigned_to) REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT fk_tasks_department FOREIGN KEY (assigned_department_id) REFERENCES departments(department_id) ON DELETE SET NULL,
    INDEX idx_tasks_status_created (status, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
    notification_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    actor_user_id INT NULL,
    task_id BIGINT NULL,
    title VARCHAR(180) NOT NULL,
    message VARCHAR(500) NOT NULL,
    link VARCHAR(255) NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notifications_inbox (user_id, is_read, created_at),
    INDEX idx_notifications_task (task_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    log_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    username VARCHAR(100) NULL,
    event_type VARCHAR(40) NOT NULL,
    page_path VARCHAR(191) NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(512) NULL,
    details TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_created (created_at),
    INDEX idx_audit_user_created (user_id, created_at),
    INDEX idx_audit_event_created (event_type, created_at)
) ENGINE=InnoDB;

INSERT IGNORE INTO roles (role_id, role_code, role_name) VALUES
(1, 'STAFF', 'บุคลากร'),
(2, 'SUPERVISOR', 'หัวหน้างาน'),
(3, 'DIRECTOR', 'หัวหน้าสำนักงาน'),
(4, 'ADMIN', 'ผู้ดูแลระบบ');

UPDATE roles SET role_code = 'STAFF', role_name = 'บุคลากร' WHERE role_id = 1;
UPDATE roles SET role_code = 'SUPERVISOR', role_name = 'หัวหน้างาน' WHERE role_id = 2;
UPDATE roles SET role_code = 'DIRECTOR', role_name = 'หัวหน้าสำนักงาน' WHERE role_id = 3;
UPDATE roles SET role_code = 'ADMIN', role_name = 'ผู้ดูแลระบบ' WHERE role_id = 4;
