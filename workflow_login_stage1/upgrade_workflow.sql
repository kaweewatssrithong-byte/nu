-- Upgrade the legacy workflow_system schema used by this installation.
-- Run once. This keeps the existing due_date, department_id, users and task data.
USE workflow_system;

ALTER TABLE tasks
    ADD COLUMN category ENUM('main','special') NOT NULL DEFAULT 'main' AFTER description,
    ADD COLUMN assigned_to INT(11) NULL AFTER created_by,
    ADD COLUMN assigned_department_id INT(11) NULL AFTER assigned_to,
    ADD COLUMN evidence_url VARCHAR(1000) NULL AFTER due_date,
    ADD COLUMN evidence_file VARCHAR(255) NULL AFTER evidence_url;

-- Preserve the department already recorded on legacy tasks for department reports.
UPDATE tasks
SET assigned_department_id = department_id
WHERE assigned_department_id IS NULL AND department_id IS NOT NULL;
