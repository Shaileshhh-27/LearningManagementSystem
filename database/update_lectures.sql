-- Add thumbnail_path column to lectures table if it doesn't exist
ALTER TABLE lectures ADD COLUMN thumbnail_path VARCHAR(255); 