-- Add tagCode column to existing rfidtag table
-- Run this if you already have the rfidtag table and don't want to recreate it

ALTER TABLE rfidtag
ADD COLUMN IF NOT EXISTS tagCode VARCHAR(100) DEFAULT NULL COMMENT 'Scanned RFID tag code from converter' AFTER stickerID,
ADD INDEX IF NOT EXISTS idx_tagCode (tagCode);

-- Verify column was added
DESCRIBE rfidtag;
