<?php if (!defined('INCLUDED')) { exit; } ?>

<style>
    /* Enhanced styles for course management */
    .course-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        border-radius: 8px;
        overflow: hidden;
    }
    
    .course-table th {
        background-color: #1976D2;
        color: white;
        padding: 15px;
        text-align: left;
        font-weight: 600;
    }
    
    .course-table td {
        padding: 12px 15px;
        border-bottom: 1px solid #eee;
    }
    
    .course-table tr:last-child td {
        border-bottom: none;
    }
    
    .course-table tr:hover {
        background-color: #f5f9ff;
    }
    
    .course-status {
        display: inline-block;
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        text-align: center;
    }
    
    .status-active {
        background-color: #e8f5e9;
        color: #2e7d32;
    }
    
    .status-pending {
        background-color: #fff8e1;
        color: #f57c00;
    }
    
    .status-inactive {
        background-color: #ffebee;
        color: #c62828;
    }
    
    .status-expired {
        background-color: #f5f5f5;
        color: #616161;
    }
    
    .status-cancelled {
        background-color: #fafafa;
        color: #9e9e9e;
    }
    
    .course-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .btn-action {
        padding: 6px 12px;
        border-radius: 4px;
        font-size: 0.85rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: all 0.2s;
        white-space: nowrap;
    }
    
    .btn-edit {
        background-color: #2196F3;
        color: white;
    }
    
    .btn-edit:hover {
        background-color: #1976D2;
    }
    
    .btn-assign {
        background-color: #FF9800;
        color: white;
        border: none;
        cursor: pointer;
    }
    
    .btn-assign:hover {
        background-color: #F57C00;
    }
    
    .btn-delete {
        background-color: #F44336;
        color: white;
        border: none;
        cursor: pointer;
    }
    
    .btn-delete:hover {
        background-color: #D32F2F;
    }
    
    .btn-green {
        background-color: #4CAF50;
    }
    
    .btn-green:hover {
        background-color: #388E3C;
    }
    
    .btn-start {
        background-color: #4CAF50;
        color: white;
        border: none;
        cursor: pointer;
    }
    
    .btn-start:hover {
        background-color: #388E3C;
    }
    
    .btn-stop {
        background-color: #f44336;
        color: white;
        border: none;
        cursor: pointer;
    }
    
    .btn-stop:hover {
        background-color: #d32f2f;
    }
    
    .search-bar {
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .student-count {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .student-count i {
        color: #1976D2;
    }
    
    /* Enhanced student count display */
    .student-count-container {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    
    .student-count-row {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .student-count-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.85rem;
        font-weight: 600;
    }
    
    .enrolled-count {
        background-color: #e3f2fd;
        color: #1976D2;
    }
    
    .validity-info {
        color: #616161;
        font-size: 0.85rem;
        margin-top: 5px;
    }
    
    .teacher-info {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .teacher-avatar {
        width: 30px;
        height: 30px;
        background-color: #e3f2fd;
        color: #1976D2;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
    }
    
    .no-teacher {
        color: #9e9e9e;
        font-style: italic;
    }
</style>

<?php
// Get all courses with teacher information
$statusFilter = $_GET['status_filter'] ?? 'all';
$teacherIdFilter = $_GET['teacher_id'] ?? null;
$assignToTeacher = $_GET['assign_to'] ?? null;

$courseQuery = '
    SELECT 
        c.*, 
        u.username as teacher_name,
        (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as enrolled_students
    FROM courses c 
    LEFT JOIN users u ON c.teacher_id = u.id 
    WHERE 1=1
';

// Add filter conditions
if ($statusFilter !== 'all') {
    $courseQuery .= ' AND c.status = :status';
}

// Filter by teacher if specified
if ($teacherIdFilter) {
    $courseQuery .= ' AND c.teacher_id = :teacher_id';
}

// Show only unassigned courses if assigning to a teacher
if ($assignToTeacher) {
    $courseQuery .= ' AND c.teacher_id IS NULL';
}

$courseQuery .= ' ORDER BY c.created_at DESC';

$stmt = $conn->prepare($courseQuery);

if ($statusFilter !== 'all') {
    $stmt->bindValue(':status', $statusFilter, SQLITE3_TEXT);
}

if ($teacherIdFilter) {
    $stmt->bindValue(':teacher_id', $teacherIdFilter, SQLITE3_INTEGER);
}

$result = $stmt->execute();
$allCoursesData = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    // Set default values for missing columns
    $row['validity_days'] = $row['validity_days'] ?? 90;
    $row['status'] = $row['status'] ?? 'pending';
    $row['enrolled_students'] = $row['enrolled_students'] ?? 0;
    $allCoursesData[] = $row;
}

// Get teacher name if assigning courses
$teacherName = '';
if ($assignToTeacher) {
    $stmt = $conn->prepare('SELECT username FROM users WHERE id = :id AND role = "teacher"');
    $stmt->bindValue(':id', $assignToTeacher, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $teacher = $result->fetchArray(SQLITE3_ASSOC);
    if ($teacher) {
        $teacherName = $teacher['username'];
    }
}
?>

<div class="section-header">
    <h2>
        <?php if ($assignToTeacher): ?>
            Assign Course to <?php echo htmlspecialchars($teacherName); ?>
        <?php elseif ($teacherIdFilter): ?>
            Courses for <?php echo htmlspecialchars($allCoursesData[0]['teacher_name'] ?? 'Teacher'); ?>
        <?php else: ?>
            Course Management
        <?php endif; ?>
    </h2>
</div>

<div class="search-bar">
    <form method="GET" style="width: 100%; display: flex; gap: 10px;">
        <input type="hidden" name="section" value="courses">
        <?php if ($teacherIdFilter): ?>
            <input type="hidden" name="teacher_id" value="<?php echo $teacherIdFilter; ?>">
        <?php endif; ?>
        <?php if ($assignToTeacher): ?>
            <input type="hidden" name="assign_to" value="<?php echo $assignToTeacher; ?>">
        <?php endif; ?>
        <select name="status_filter" style="padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
            <option value="all">All Status</option>
            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
            <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            <option value="expired" <?php echo $statusFilter === 'expired' ? 'selected' : ''; ?>>Expired</option>
            <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
        </select>
        <button type="submit" class="btn btn-blue">Filter</button>
        
        <?php if ($teacherIdFilter || $assignToTeacher): ?>
            <a href="admin_dashboard.php?section=courses" class="btn" style="margin-left: auto;">
                <i class="fas fa-arrow-left"></i> Back to All Courses
            </a>
        <?php else: ?>
            <a href="admin_create_course.php" class="btn btn-green" style="margin-left: auto;">
                <i class="fas fa-plus"></i> Create New Course
            </a>
        <?php endif; ?>
    </form>
</div>

<table class="course-table">
    <thead>
        <tr>
            <th>Title</th>
            <?php if (!$teacherIdFilter && !$assignToTeacher): ?>
                <th>Teacher</th>
            <?php endif; ?>
            <th>Validity</th>
            <th>Students</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($allCoursesData)): ?>
            <tr>
                <td colspan="<?php echo (!$teacherIdFilter && !$assignToTeacher) ? '6' : '5'; ?>" style="text-align: center; padding: 20px;">
                    <?php if ($assignToTeacher): ?>
                        No unassigned courses available to assign.
                    <?php elseif ($teacherIdFilter): ?>
                        This teacher has no assigned courses.
                    <?php else: ?>
                        No courses found.
                    <?php endif; ?>
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($allCoursesData as $course): ?>
                <tr>
                    <td><?php echo htmlspecialchars($course['title']); ?></td>
                    <?php if (!$teacherIdFilter && !$assignToTeacher): ?>
                        <td>
                            <?php if ($course['teacher_id']): ?>
                                <div class="teacher-info">
                                    <div class="teacher-avatar">
                                        <?php echo strtoupper(substr($course['teacher_name'], 0, 1)); ?>
                                    </div>
                                    <?php echo htmlspecialchars(ucfirst($course['teacher_name'])); ?>
                                </div>
                            <?php else: ?>
                                <span class="no-teacher"><i class="fas fa-user-slash"></i> Not assigned</span>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                    <td>
                        <?php echo htmlspecialchars($course['validity_days']); ?> days
                        <?php if (!empty($course['start_date']) && !empty($course['end_date'])): ?>
                            <div class="validity-info">
                                <i class="fas fa-calendar-alt"></i> 
                                <?php echo date('d M Y', strtotime($course['start_date'])); ?> - 
                                <?php echo date('d M Y', strtotime($course['end_date'])); ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="student-count-container">
                            <div class="student-count-row">
                                <div class="student-count-badge enrolled-count">
                                    <i class="fas fa-users"></i>
                                    <?php echo $course['enrolled_students']; ?> enrolled
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="course-status status-<?php echo htmlspecialchars($course['status']); ?>">
                            <?php echo ucfirst(htmlspecialchars($course['status'])); ?>
                        </span>
                    </td>
                    <td class="course-actions">
                        <?php if ($assignToTeacher): ?>
                            <a href="admin_assign_teacher.php?id=<?php echo $course['id']; ?>&teacher_id=<?php echo $assignToTeacher; ?>&auto_assign=1" class="btn-action btn-assign">
                                <i class="fas fa-user-plus"></i> Assign
                            </a>
                        <?php else: ?>
                            <?php if (!$course['teacher_id']): ?>
                                <a href="admin_assign_teacher.php?id=<?php echo $course['id']; ?>" class="btn-action btn-assign">
                                    <i class="fas fa-user-plus"></i> Assign Teacher
                                </a>
                            <?php else: ?>
                                <?php if ($course['status'] === 'pending'): ?>
                                    <a href="admin_start_course.php?id=<?php echo $course['id']; ?>" class="btn-action btn-start" onclick="return confirm('Start this course? This will set the current date as the start date.')">
                                        <i class="fas fa-play"></i> Start
                                    </a>
                                    <a href="admin_unassign_teacher.php?id=<?php echo $course['id']; ?>" class="btn-action btn-delete" onclick="return confirm('Unassign teacher from this course? This will set the course back to pending status.')">
                                        <i class="fas fa-user-minus"></i> Unassign Teacher
                                    </a>
                                <?php endif; ?>
                                <?php if ($course['status'] === 'active'): ?>
                                    <a href="admin_stop_course.php?id=<?php echo $course['id']; ?>" class="btn-action btn-stop" onclick="return confirm('Stop this course? Students will no longer be able to access it.')">
                                        <i class="fas fa-stop"></i> Stop
                                    </a>
                                <?php endif; ?>
                                <?php if ($course['status'] === 'inactive'): ?>
                                    <a href="admin_start_course.php?id=<?php echo $course['id']; ?>" class="btn-action btn-start" onclick="return confirm('Restart this course? This will set the current date as the new start date.')">
                                        <i class="fas fa-play"></i> Restart
                                    </a>
                                    <a href="admin_unassign_teacher.php?id=<?php echo $course['id']; ?>" class="btn-action btn-delete" onclick="return confirm('Unassign teacher from this course? This will set the course back to pending status.')">
                                        <i class="fas fa-user-minus"></i> Unassign Teacher
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                            <a href="admin_edit_course.php?id=<?php echo $course['id']; ?>" class="btn-action btn-edit">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <button class="btn-action btn-delete" onclick="deleteCourse(<?php echo $course['id']; ?>)">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<script>
function deleteCourse(courseId) {
    if (confirm('Are you sure you want to delete this course?')) {
        window.location.href = 'admin_delete_course.php?id=' + courseId;
    }
}
</script> 