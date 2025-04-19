<?php
require_once 'config/database.php';

/**
 * Check if a course should be activated based on enrollment count
 * and activate it if conditions are met
 * 
 * @param int $courseId The ID of the course to check
 * @return bool True if the course was activated, false otherwise
 */
function checkAndActivateCourse($courseId) {
    $db = new Database();
    $conn = $db->getConnection();
    
    try {
        // Start transaction
        $conn->exec('BEGIN');
        
        // Get course details
        $stmt = $conn->prepare('
            SELECT 
                c.*,
                (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as enrolled_students
            FROM courses c
            WHERE c.id = :id
        ');
        $stmt->bindValue(':id', $courseId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $course = $result->fetchArray(SQLITE3_ASSOC);
        
        if (!$course) {
            throw new Exception('Course not found');
        }
        
        // If course is already active or doesn't have a teacher, don't activate
        if ($course['status'] === 'active' || empty($course['teacher_id'])) {
            $conn->exec('COMMIT');
            return false;
        }
        
        // Check if minimum students requirement is met
        if ($course['enrolled_students'] >= $course['min_students']) {
            // Activate the course
            $startDate = date('Y-m-d'); // Today
            $endDate = date('Y-m-d', strtotime("+{$course['validity_days']} days"));
            
            $stmt = $conn->prepare('
                UPDATE courses
                SET status = "active",
                    start_date = :start_date,
                    end_date = :end_date
                WHERE id = :id
            ');
            $stmt->bindValue(':id', $courseId, SQLITE3_INTEGER);
            $stmt->bindValue(':start_date', $startDate, SQLITE3_TEXT);
            $stmt->bindValue(':end_date', $endDate, SQLITE3_TEXT);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to activate course');
            }
            
            $conn->exec('COMMIT');
            return true;
        }
        
        $conn->exec('COMMIT');
        return false;
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->exec('ROLLBACK');
        }
        error_log('Error activating course: ' . $e->getMessage());
        return false;
    }
}

/**
 * Check if a student is eligible to enroll in a course
 * 
 * @param int $studentId The ID of the student
 * @param int $courseId The ID of the course
 * @return array ['eligible' => bool, 'message' => string]
 */
function checkCourseEligibility($studentId, $courseId) {
    $db = new Database();
    $conn = $db->getConnection();
    
    try {
        // Get course details
        $stmt = $conn->prepare('
            SELECT 
                c.*,
                (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as enrolled_students
            FROM courses c
            WHERE c.id = :id
        ');
        $stmt->bindValue(':id', $courseId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $course = $result->fetchArray(SQLITE3_ASSOC);
        
        if (!$course) {
            return ['eligible' => false, 'message' => 'Course not found'];
        }
        
        // Check if course is cancelled
        if ($course['status'] === 'cancelled') {
            return ['eligible' => false, 'message' => 'This course has been cancelled'];
        }
        
        // Check if course is expired
        if ($course['status'] === 'expired') {
            return ['eligible' => false, 'message' => 'This course has expired'];
        }
        
        // Check if course is inactive
        if ($course['status'] === 'inactive') {
            return ['eligible' => false, 'message' => 'This course is currently inactive'];
        }
        
        // Check if student is already enrolled
        $stmt = $conn->prepare('
            SELECT COUNT(*) as count
            FROM enrollments
            WHERE student_id = :student_id AND course_id = :course_id
        ');
        $stmt->bindValue(':student_id', $studentId, SQLITE3_INTEGER);
        $stmt->bindValue(':course_id', $courseId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $enrollment = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($enrollment['count'] > 0) {
            return ['eligible' => false, 'message' => 'You are already enrolled in this course'];
        }
        
        // Check if course is full
        if ($course['enrolled_students'] >= $course['max_students']) {
            return ['eligible' => false, 'message' => 'This course is full'];
        }
        
        // All checks passed
        return ['eligible' => true, 'message' => 'Eligible for enrollment'];
    } catch (Exception $e) {
        error_log('Error checking eligibility: ' . $e->getMessage());
        return ['eligible' => false, 'message' => 'An error occurred while checking eligibility'];
    }
}

/**
 * Enroll a student in a course
 * 
 * @param int $studentId The ID of the student
 * @param int $courseId The ID of the course
 * @return array ['success' => bool, 'message' => string, 'activated' => bool]
 */
function enrollStudentInCourse($studentId, $courseId) {
    $db = new Database();
    $conn = $db->getConnection();
    
    try {
        // First check eligibility
        $eligibility = checkCourseEligibility($studentId, $courseId);
        
        if (!$eligibility['eligible']) {
            return ['success' => false, 'message' => $eligibility['message'], 'activated' => false];
        }
        
        // Start transaction
        $conn->exec('BEGIN');
        
        // Enroll the student
        $stmt = $conn->prepare('
            INSERT INTO enrollments (student_id, course_id)
            VALUES (:student_id, :course_id)
        ');
        $stmt->bindValue(':student_id', $studentId, SQLITE3_INTEGER);
        $stmt->bindValue(':course_id', $courseId, SQLITE3_INTEGER);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to enroll in course');
        }
        
        $conn->exec('COMMIT');
        
        // Check if the course should be activated
        $activated = checkAndActivateCourse($courseId);
        
        return [
            'success' => true, 
            'message' => 'Successfully enrolled in course', 
            'activated' => $activated
        ];
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->exec('ROLLBACK');
        }
        error_log('Error enrolling student: ' . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred during enrollment', 'activated' => false];
    }
} 