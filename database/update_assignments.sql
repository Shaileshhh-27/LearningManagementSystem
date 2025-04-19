-- Add assignment_file column to assignments table if it doesn't exist
ALTER TABLE assignments ADD COLUMN assignment_file TEXT; 