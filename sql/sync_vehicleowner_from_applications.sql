-- Migration: Sync vehicleowner fields from latest non-null applications
-- BACKUP AND VERIFY BEFORE RUNNING ON PRODUCTION
-- This script will:
-- 1) Create a backup table of affected vehicleowner rows
-- 2) For each field, copy the value from the most recent application (by applicationDate) when vehicleowner field is NULL/empty
-- 3) Avoid overwriting existing vehicleowner non-empty values

START TRANSACTION;

-- 1) Backup affected vehicleowner rows for auditing
CREATE TABLE IF NOT EXISTS backup_vehicleowner_before_sync AS
SELECT DISTINCT vo.*
FROM vehicleowner vo
JOIN applications a ON a.OwnerID = vo.OwnerID
WHERE (
    (a.schoolID IS NOT NULL AND a.schoolID <> '' AND (vo.schoolID IS NULL OR vo.schoolID = ''))
 OR (a.employment_type IS NOT NULL AND a.employment_type <> '' AND (vo.employment_type IS NULL OR vo.employment_type = ''))
 OR (a.additional_driver_name IS NOT NULL AND a.additional_driver_name <> '' AND (vo.additional_driver_name IS NULL OR vo.additional_driver_name = ''))
 OR (a.additional_driver_relationship IS NOT NULL AND a.additional_driver_relationship <> '' AND (vo.additional_driver_relationship IS NULL OR vo.additional_driver_relationship = ''))
 OR (a.drivers_license IS NOT NULL AND a.drivers_license <> '' AND (vo.drivers_license IS NULL OR vo.drivers_license = ''))
);

-- 2) Update schoolID from latest application where missing
UPDATE vehicleowner vo
JOIN (
  SELECT a1.OwnerID, a1.schoolID
  FROM applications a1
  JOIN (
    SELECT OwnerID, MAX(applicationDate) AS latestDate
    FROM applications
    WHERE schoolID IS NOT NULL AND schoolID <> ''
    GROUP BY OwnerID
  ) a2 ON a1.OwnerID = a2.OwnerID AND a1.applicationDate = a2.latestDate
) src ON vo.OwnerID = src.OwnerID
SET vo.schoolID = src.schoolID
WHERE vo.schoolID IS NULL OR vo.schoolID = '';

-- 3) Update employment_type
UPDATE vehicleowner vo
JOIN (
  SELECT a1.OwnerID, a1.employment_type
  FROM applications a1
  JOIN (
    SELECT OwnerID, MAX(applicationDate) AS latestDate
    FROM applications
    WHERE employment_type IS NOT NULL AND employment_type <> ''
    GROUP BY OwnerID
  ) a2 ON a1.OwnerID = a2.OwnerID AND a1.applicationDate = a2.latestDate
) src ON vo.OwnerID = src.OwnerID
SET vo.employment_type = src.employment_type
WHERE vo.employment_type IS NULL OR vo.employment_type = '';

-- 4) Update drivers_license
UPDATE vehicleowner vo
JOIN (
  SELECT a1.OwnerID, a1.drivers_license
  FROM applications a1
  JOIN (
    SELECT OwnerID, MAX(applicationDate) AS latestDate
    FROM applications
    WHERE drivers_license IS NOT NULL AND drivers_license <> ''
    GROUP BY OwnerID
  ) a2 ON a1.OwnerID = a2.OwnerID AND a1.applicationDate = a2.latestDate
) src ON vo.OwnerID = src.OwnerID
SET vo.drivers_license = src.drivers_license
WHERE vo.drivers_license IS NULL OR vo.drivers_license = '';

-- 5) Update additional driver name
UPDATE vehicleowner vo
JOIN (
  SELECT a1.OwnerID, a1.additional_driver_name
  FROM applications a1
  JOIN (
    SELECT OwnerID, MAX(applicationDate) AS latestDate
    FROM applications
    WHERE additional_driver_name IS NOT NULL AND additional_driver_name <> ''
    GROUP BY OwnerID
  ) a2 ON a1.OwnerID = a2.OwnerID AND a1.applicationDate = a2.latestDate
) src ON vo.OwnerID = src.OwnerID
SET vo.additional_driver_name = src.additional_driver_name
WHERE vo.additional_driver_name IS NULL OR vo.additional_driver_name = '';

-- 6) Update additional driver relationship
UPDATE vehicleowner vo
JOIN (
  SELECT a1.OwnerID, a1.additional_driver_relationship
  FROM applications a1
  JOIN (
    SELECT OwnerID, MAX(applicationDate) AS latestDate
    FROM applications
    WHERE additional_driver_relationship IS NOT NULL AND additional_driver_relationship <> ''
    GROUP BY OwnerID
  ) a2 ON a1.OwnerID = a2.OwnerID AND a1.applicationDate = a2.latestDate
) src ON vo.OwnerID = src.OwnerID
SET vo.additional_driver_relationship = src.additional_driver_relationship
WHERE vo.additional_driver_relationship IS NULL OR vo.additional_driver_relationship = '';

COMMIT;

-- After running, verify with:
-- SELECT COUNT(*) FROM vehicleowner vo JOIN applications a ON vo.OwnerID = a.OwnerID WHERE (a.schoolID IS NOT NULL AND a.schoolID <> '' AND (vo.schoolID IS NULL OR vo.schoolID='')) OR ... (same conditions) ;

-- NOTE: This script only fills missing fields in `vehicleowner` using the most recent non-null application values. It does NOT overwrite existing owner data.
