-- Remove face_image column from users table
ALTER TABLE users DROP COLUMN face_image;

-- Remove face validation settings from settings table
DELETE FROM settings WHERE `key` = 'face_validation_enabled';
