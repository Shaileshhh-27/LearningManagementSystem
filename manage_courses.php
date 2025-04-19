<?php
require_once 'auth/Auth.php';
require_once 'config/database.php';

session_start();
$auth = new Auth();

// If not logged in or not an admin, redirect to login page
if (!$auth->isLoggedIn() || $auth->getUserRole() !== 'admin') {
    header('Location: index.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// Handle course actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $title = $_POST['title'];
                $description = $_POST['description'];
                $teacher_id = $_POST['teacher_id'];
                $start_date = $_POST['start_date'] ?? date('Y-m-d');
                $end_date = $_POST['end_date'] ?? date('Y-m-d', strtotime('+3 months'));
                
                $stmt = $conn->prepare('INSERT INTO courses (title, description, teacher_id, start_date, end_date) VALUES (:title, :description, :teacher_id, :start_date, :end_date)');
                $stmt->bindValue(':title', $title, SQLITE3_TEXT);
                $stmt->bindValue(':description', $description, SQLITE3_TEXT);
                $stmt->bindValue(':teacher_id', $teacher_id, SQLITE3_INTEGER);
                $stmt->bindValue(':start_date', $start_date, SQLITE3_TEXT);
                $stmt->bindValue(':end_date', $end_date, SQLITE3_TEXT);
                $stmt->execute();
                break;
                
            case 'update':
                $id = $_POST['course_id'];
                $title = $_POST['title'];
                $description = $_POST['description'];
                $teacher_id = $_POST['teacher_id'];
                $start_date = $_POST['start_date'] ?? date('Y-m-d');
                $end_date = $_POST['end_date'] ?? date('Y-m-d', strtotime('+3 months'));
                
                $stmt = $conn->prepare('UPDATE courses SET title = :title, description = :description, teacher_id = :teacher_id, start_date = :start_date, end_date = :end_date WHERE id = :id');
                $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
                $stmt->bindValue(':title', $title, SQLITE3_TEXT);
                $stmt->bindValue(':description', $description, SQLITE3_TEXT);
                $stmt->bindValue(':teacher_id', $teacher_id, SQLITE3_INTEGER);
                $stmt->bindValue(':start_date', $start_date, SQLITE3_TEXT);
                $stmt->bindValue(':end_date', $end_date, SQLITE3_TEXT);
                $stmt->execute();
                break;
                
            case 'delete':
                $id = $_POST['course_id'];
                $stmt = $conn->prepare('DELETE FROM courses WHERE id = :id');
                $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
                $stmt->execute();
                break;
        }
        header('Location: manage_courses.php');
        exit();
    }
}

// Check if date columns exist
$hasDateColumns = false;
$tableInfo = $conn->query("PRAGMA table_info(courses)");
while ($column = $tableInfo->fetchArray(SQLITE3_ASSOC)) {
    if ($column['name'] === 'start_date' || $column['name'] === 'end_date') {
        $hasDateColumns = true;
        break;
    }
}

// Fetch all courses with teacher names
$query = '
    SELECT c.*, u.username as teacher_name 
    FROM courses c 
    LEFT JOIN users u ON c.teacher_id = u.id 
    ORDER BY ' . ($hasDateColumns ? 'c.start_date DESC' : 'c.id DESC');

$stmt = $conn->prepare($query);
$result = $stmt->execute();
$courses = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    // Add default dates if columns don't exist
    if (!$hasDateColumns) {
        $row['start_date'] = date('Y-m-d');
        $row['end_date'] = date('Y-m-d', strtotime('+3 months'));
    }
    $courses[] = $row;
}

// Fetch all teachers for the dropdown
$stmt = $conn->prepare('SELECT id, username FROM users WHERE role = :role');
$stmt->bindValue(':role', 'teacher', SQLITE3_TEXT);
$result = $stmt->execute();
$teachers = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $teachers[] = $row;
}

$page_title = 'Manage Courses';
require_once 'includes/admin_header.php';
?>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Course Management</h5>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                <i class="fas fa-plus"></i> Add New Course
            </button>
        </div>
        <div class="card-body">
            <div class="row">
                <?php foreach ($courses as $course): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card course-card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($course['title']); ?></h5>
                            <p class="card-text text-muted"><?php echo htmlspecialchars($course['description']); ?></p>
                            <div class="mb-2">
                                <strong>Teacher:</strong> <?php echo htmlspecialchars($course['teacher_name']); ?>
                            </div>
                            <div class="mb-2">
                                <strong>Start Date:</strong> <?php echo date('M d, Y', strtotime($course['start_date'])); ?>
                            </div>
                            <div class="mb-3">
                                <strong>End Date:</strong> <?php echo date('M d, Y', strtotime($course['end_date'])); ?>
                            </div>
                            <div class="d-flex justify-content-between">
                                <button class="btn btn-sm btn-primary edit-course"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editCourseModal"
                                        data-id="<?php echo $course['id']; ?>"
                                        data-title="<?php echo htmlspecialchars($course['title']); ?>"
                                        data-description="<?php echo htmlspecialchars($course['description']); ?>"
                                        data-teacher-id="<?php echo $course['teacher_id']; ?>"
                                        data-start-date="<?php echo $course['start_date']; ?>"
                                        data-end-date="<?php echo $course['end_date']; ?>">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn btn-sm btn-danger delete-course"
                                        data-bs-toggle="modal"
                                        data-bs-target="#deleteCourseModal"
                                        data-id="<?php echo $course['id']; ?>"
                                        data-title="<?php echo htmlspecialchars($course['title']); ?>">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Add Course Modal -->
    <div class="modal fade" id="addCourseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Course</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label">Course Title</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Teacher</label>
                            <select class="form-select" name="teacher_id" required>
                                <option value="">Select a teacher</option>
                                <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>">
                                    <?php echo htmlspecialchars($teacher['username']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Course</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Course Modal -->
    <div class="modal fade" id="editCourseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Course</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="course_id" id="edit_course_id">
                        <div class="mb-3">
                            <label class="form-label">Course Title</label>
                            <input type="text" class="form-control" name="title" id="edit_title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="edit_description" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Teacher</label>
                            <select class="form-select" name="teacher_id" id="edit_teacher_id" required>
                                <option value="">Select a teacher</option>
                                <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>">
                                    <?php echo htmlspecialchars($teacher['username']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" id="edit_start_date" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" id="edit_end_date" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Course</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Course Modal -->
    <div class="modal fade" id="deleteCourseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Course</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete course <span id="delete_course_title"></span>?</p>
                </div>
                <div class="modal-footer">
                    <form method="POST">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="course_id" id="delete_course_id">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    </div> <!-- End of container -->

    <!-- Add Back Button -->
    <a href="admin_dashboard.php" class="back-button" id="backButton">
        <i class="fas fa-arrow-left"></i>
    </a>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle edit course modal
        document.querySelectorAll('.edit-course').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.dataset.id;
                const title = this.dataset.title;
                const description = this.dataset.description;
                const teacherId = this.dataset.teacherId;
                const startDate = this.dataset.startDate;
                const endDate = this.dataset.endDate;

                document.getElementById('edit_course_id').value = id;
                document.getElementById('edit_title').value = title;
                document.getElementById('edit_description').value = description;
                document.getElementById('edit_teacher_id').value = teacherId;
                document.getElementById('edit_start_date').value = startDate;
                document.getElementById('edit_end_date').value = endDate;
            });
        });

        // Handle delete course modal
        document.querySelectorAll('.delete-course').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.dataset.id;
                const title = this.dataset.title;

                document.getElementById('delete_course_id').value = id;
                document.getElementById('delete_course_title').textContent = title;
            });
        });

        // Back button visibility
        window.addEventListener('scroll', function() {
            const backButton = document.getElementById('backButton');
            if (window.scrollY > 300) {
                backButton.classList.add('visible');
            } else {
                backButton.classList.remove('visible');
            }
        });
    </script>
</body>
</html> 