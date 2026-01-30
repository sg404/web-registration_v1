-- Migration: Remove registration_code column from applications
-- 1) Backup any existing non-null registration codes (if any)
-- 2) Drop the column
-- Run in a safe environment and ensure backups are taken before applying to production.

START TRANSACTION;

-- Backup any application rows that have registration_code (likely none)
CREATE TABLE IF NOT EXISTS backup_app_registration_code (
  applicationID INT PRIMARY KEY,
  registration_code VARCHAR(50),
  code_expiry DATETIME,
  registration_code_sent_at DATETIME,
  backup_taken_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO backup_app_registration_code (applicationID, registration_code, code_expiry, registration_code_sent_at)
SELECT applicationID, registration_code, code_expiry, registration_code_sent_at FROM applications WHERE registration_code IS NOT NULL;

-- Drop the registration_code column (this will also remove any index on that column)
ALTER TABLE applications DROP COLUMN IF EXISTS registration_code;

COMMIT;

-- NOTE: This migration intentionally only removes the `registration_code` column. 
-- If you also want to remove `code_expiry` or `registration_code_sent_at`, add similar ALTER TABLE DROP COLUMN statements after reviewing data/usage.
