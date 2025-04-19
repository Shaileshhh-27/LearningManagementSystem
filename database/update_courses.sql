-- Add start_date and end_date columns to courses table if they don't exist
ALTER TABLE courses ADD COLUMN start_date DATE DEFAULT CURRENT_DATE;
ALTER TABLE courses ADD COLUMN end_date DATE DEFAULT (date('now', '+3 months'));

-- Add a unique constraint for course title per teacher
CREATE UNIQUE INDEX IF NOT EXISTS idx_unique_course_teacher 
ON courses(title, teacher_id);

-- Add status column to track course state
ALTER TABLE courses ADD COLUMN status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'inactive', 'expired'));

-- Add price column to courses table
ALTER TABLE courses ADD COLUMN price DECIMAL(10,2) DEFAULT 0.00;