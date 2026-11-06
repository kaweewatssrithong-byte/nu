USE workflow_system;

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
