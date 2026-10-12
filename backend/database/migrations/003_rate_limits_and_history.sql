-- Supporting tables for rate-limited OTP and account/subscription operations.
CREATE TABLE IF NOT EXISTS rate_limits (
    scope VARCHAR(60) NOT NULL,
    identity_hash CHAR(64) NOT NULL,
    hit_count INT UNSIGNED NOT NULL DEFAULT 0,
    window_started_at DATETIME NOT NULL,
    PRIMARY KEY (scope, identity_hash),
    KEY ix_rate_limits_window (window_started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE service_plans ADD COLUMN data_cap_bytes BIGINT UNSIGNED NULL AFTER validity_days;

CREATE TABLE IF NOT EXISTS subscription_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    subscription_id BIGINT UNSIGNED NOT NULL,
    changed_by_user_id BIGINT UNSIGNED NULL,
    field_name VARCHAR(60) NOT NULL,
    old_value VARCHAR(255) NULL,
    new_value VARCHAR(255) NULL,
    reason VARCHAR(500) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY ix_subscription_history (subscription_id, created_at),
    CONSTRAINT fk_subscription_history_subscription FOREIGN KEY (subscription_id) REFERENCES subscriptions(id),
    CONSTRAINT fk_subscription_history_user FOREIGN KEY (changed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS account_sequences (
    sequence_name VARCHAR(60) NOT NULL PRIMARY KEY,
    next_value BIGINT UNSIGNED NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS refund_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    payment_id BIGINT UNSIGNED NOT NULL,
    requested_by_user_id BIGINT UNSIGNED NOT NULL,
    approved_by_user_id BIGINT UNSIGNED NULL,
    amount_minor BIGINT UNSIGNED NOT NULL,
    currency CHAR(3) NOT NULL,
    provider_refund_id VARCHAR(160) NULL,
    status ENUM('requested', 'approved', 'submitted', 'succeeded', 'failed', 'rejected') NOT NULL DEFAULT 'requested',
    reason VARCHAR(500) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY ix_refund_payment_status (payment_id, status),
    CONSTRAINT fk_refund_payment FOREIGN KEY (payment_id) REFERENCES payments(id),
    CONSTRAINT fk_refund_requester FOREIGN KEY (requested_by_user_id) REFERENCES users(id),
    CONSTRAINT fk_refund_approver FOREIGN KEY (approved_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cash_reconciliations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    cashier_session_id BIGINT UNSIGNED NOT NULL,
    reconciled_by_user_id BIGINT UNSIGNED NOT NULL,
    expected_amount_minor BIGINT UNSIGNED NOT NULL,
    counted_amount_minor BIGINT UNSIGNED NOT NULL,
    currency CHAR(3) NOT NULL,
    discrepancy_reason VARCHAR(500) NULL,
    status ENUM('matched', 'discrepancy', 'reviewed') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY ix_cash_reconciliation_session (cashier_session_id, created_at),
    CONSTRAINT fk_cash_reconciliation_session FOREIGN KEY (cashier_session_id) REFERENCES cashier_sessions(id),
    CONSTRAINT fk_cash_reconciliation_user FOREIGN KEY (reconciled_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO account_sequences (sequence_name, next_value) VALUES ('pppoe', 252230001)
ON DUPLICATE KEY UPDATE sequence_name = VALUES(sequence_name);

INSERT INTO roles (name) VALUES
    ('Super Admin'), ('Admin'), ('Technician'), ('Accountant'),
    ('Customer Service'), ('Agent'), ('Customer')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO permissions (name) VALUES
    ('system.health.read'), ('users.manage'), ('customers.read'), ('customers.write'),
    ('plans.manage'), ('accounts.read'), ('accounts.write'), ('accounts.rebind'),
    ('payments.read'), ('payments.collect_cash'), ('payments.reconcile'), ('payments.refund'),
    ('tickets.read'), ('tickets.manage'), ('work_orders.manage'),
    ('network.diagnostics.read'), ('network.provision'), ('audit.read'), ('settings.manage')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Initial role grants for staff identity/display. Operational endpoints must
-- still enforce each permission and site scope server-side when implemented.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.name = 'Super Admin';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name = 'Admin' AND p.name NOT IN ('users.manage', 'settings.manage');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name = 'Technician' AND p.name IN ('system.health.read', 'customers.read', 'accounts.read', 'tickets.read', 'work_orders.manage', 'network.diagnostics.read', 'network.provision');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name = 'Accountant' AND p.name IN ('system.health.read', 'customers.read', 'payments.read', 'payments.collect_cash', 'payments.reconcile', 'payments.refund', 'audit.read');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name = 'Customer Service' AND p.name IN ('customers.read', 'customers.write', 'accounts.read', 'accounts.rebind', 'tickets.read', 'tickets.manage');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name = 'Agent' AND p.name IN ('customers.read', 'accounts.read', 'payments.read', 'payments.collect_cash');
