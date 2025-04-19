<?php if (!defined('INCLUDED')) { exit; } ?>
<style>
    /* Teacher management styles */
    .teacher-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        border-radius: 8px;
        overflow: hidden;
        table-layout: fixed;
        position: static;
        z-index: auto;
    }
    
    .teacher-table th {
        background-color: #1976D2;
        color: white;
        padding: 15px;
        text-align: left;
        font-weight: 600;
    }
    
    .teacher-table th:nth-child(1) { width: 20%; } /* Name */
    .teacher-table th:nth-child(2) { width: 25%; } /* Email */
    .teacher-table th:nth-child(3) { width: 30%; } /* Courses */
    .teacher-table th:nth-child(4) { width: 10%; } /* Status */
    .teacher-table th:nth-child(5) { width: 15%; } /* Actions */
    
    .teacher-table td {
        padding: 12px 15px;
        border-bottom: 1px solid #eee;
        vertical-align: top;
        position: static;
    }
    
    .teacher-table tr:last-child td {
        border-bottom: none;
    }
    
    .teacher-table tr:hover {
        background-color: #f5f9ff;
    }
    
    .teacher-dropdown {
        position: relative;
        display: inline-block;
    }
    
    .teacher-dropdown-btn {
        position: static;
        z-index: 1002;
        background-color: #1976D2;
        color: white;
        padding: 8px 16px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 5px;
        white-space: nowrap;
    }
    
    .teacher-dropdown-content {
        display: none;
        position: fixed;
        background-color: white;
        min-width: 200px;
        box-shadow: 0 8px 16px rgba(0,0,0,0.2);
        border-radius: 4px;
        z-index: 1001;
        overflow-y: auto;
    }
    
    .teacher-dropdown-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.1);
        z-index: 1000;
        display: none;
    }
    
    /* For dropdowns near the bottom of the page */
    .teacher-dropdown.bottom-aligned .teacher-dropdown-content {
        bottom: auto;
        top: 100%;
        margin-bottom: 0;
        margin-top: 5px;
    }
    
    .teacher-dropdown-content a {
        color: #333;
        padding: 12px 16px;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: background-color 0.2s;
        white-space: nowrap;
    }
    
    .teacher-dropdown-content a:hover {
        background-color: #f1f1f1;
    }
    
    .teacher-courses {
        max-width: 300px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .expiration-warning {
        color: #f44336;
        font-size: 0.85rem;
        margin-top: 5px;
    }
    
    .status-active {
        background-color: #e8f5e9;
        color: #2e7d32;
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        display: inline-block;
        white-space: nowrap;
        max-width: fit-content;
    }
    
    .status-inactive {
        background-color: #ffebee;
        color: #c62828;
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        display: inline-block;
        white-space: nowrap;
        max-width: fit-content;
    }
    
    /* Responsive styles for smaller screens */
    @media (max-width: 768px) {
        .teacher-table {
            display: block;
            overflow-x: auto;
        }
        
        .teacher-table th:nth-child(1),
        .teacher-table th:nth-child(2),
        .teacher-table th:nth-child(3),
        .teacher-table th:nth-child(4),
        .teacher-table th:nth-child(5) {
            width: auto;
        }
        
        .teacher-dropdown-content {
            position: fixed;
            top: 50% !important;
            left: 50% !important;
            transform: translate(-50%, -50%);
            width: 90%;
            max-width: 300px;
            max-height: 80vh;
            overflow-y: auto;
            right: auto !important;
            bottom: auto !important;
            margin-top: 0;
        }
    }
</style>

<div class="section">
    <div class="section-header">
        <h2>Teacher Management</h2>
    </div>

    <div class="search-bar">
        <form action="" method="GET" style="display: flex; gap: 10px; width: 100%;">
            <input type="hidden" name="section" value="teachers">
            <input type="text" name="teacher_search" placeholder="Search by name or email" class="search-input">
            <select name="course_filter">
                <option value="all" <?= $courseFilter == 'all' ? 'selected' : '' ?>>All Teachers</option>
                <option value="assigned" <?= $courseFilter == 'assigned' ? 'selected' : '' ?>>With Courses</option>
    <h2>Teacher Management</h2>
    <div class="teacher-filters">
        <form method="GET" class="filter-form">
            <input type="hidden" name="section" value="teachers">
            <input type="text" name="teacher_filter" 
                   placeholder="Search by name or email" 
                   value="<?php echo htmlspecialchars($_GET['teacher_filter'] ?? ''); ?>" 
                   class="search-input">
            <select name="course_filter" class="filter-select">
                <option value="all" <?php echo ($courseFilter === 'all') ? 'selected' : ''; ?>>All Teachers</option>
                <option value="assigned" <?php echo ($courseFilter === 'assigned') ? 'selected' : ''; ?>>With Courses</option>
                <option value="unassigned" <?php echo ($courseFilter === 'unassigned') ? 'selected' : ''; ?>>Without Courses</option>
            </select>
            <select name="validity_filter" class="filter-select">
                <option value="all" <?php echo ($validityFilter === 'all') ? 'selected' : ''; ?>>All Status</option>
                <option value="expiring" <?php echo ($validityFilter === 'expiring') ? 'selected' : ''; ?>>Expiring Courses</option>
                <option value="active" <?php echo ($validityFilter === 'active') ? 'selected' : ''; ?>>Active Courses</option>
            </select>
            <button type="submit" class="btn">Apply Filters</button>
        </form>
    </div>

    <?php if (empty($teacherCourses) && empty($teachersWithoutCourses)): ?>
        <div class="no-data-message">
            <p>No teachers found matching your criteria.</p>
        </div>
    <?php else: ?>
        <table class="teacher-table">
            <tr>
                <th style="width: 60px;">S.No</th>
                <th>Teacher Name</th>
                <th>Email</th>
                <th>Courses</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
            <?php 
            // Display teachers with courses first
            $serialNumber = 1;
            foreach ($teacherCourses as $teacher): 
            ?>
                <tr>
                    <td><?php echo $serialNumber++; ?></td>
                    <td><?php echo htmlspecialchars(ucfirst($teacher['teacher_name'])); ?></td>
                    <td><?php echo htmlspecialchars($teacher['teacher_email']); ?></td>
                    <td>
                        <div class="teacher-courses">
                            <?php echo htmlspecialchars($teacher['course_titles'] ?? 'No courses assigned'); ?>
                        </div>
                        <?php if (isset($teacher['nearest_expiration']) && strtotime($teacher['nearest_expiration']) < strtotime('+30 days')): ?>
                            <div class="expiration-warning">
                                Course expiring on: <?php echo date('Y-m-d', strtotime($teacher['nearest_expiration'])); ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status-active">
                            Active (<?php echo $teacher['course_count']; ?> courses)
                        </span>
                    </td>
                    <td>
                        <div class="teacher-dropdown">
                            <button class="teacher-dropdown-btn" onclick="toggleTeacherDropdown(<?php echo $teacher['teacher_id']; ?>)">
                                <i class="fas fa-ellipsis-v"></i> Actions
                            </button>
                            <div id="teacher-dropdown-<?php echo $teacher['teacher_id']; ?>" class="teacher-dropdown-content">
                                <a href="admin_dashboard.php?section=courses&teacher_id=<?php echo $teacher['teacher_id']; ?>">
                                    <i class="fas fa-book"></i> View Courses
                                </a>
                                <a href="admin_dashboard.php?section=courses&assign_to=<?php echo $teacher['teacher_id']; ?>">
                                    <i class="fas fa-plus-circle"></i> Assign Course
                                </a>
                                <a href="#" onclick="openChangePasswordModal(<?php echo $teacher['teacher_id']; ?>, '<?php echo htmlspecialchars($teacher['teacher_name']); ?>')">
                                    <i class="fas fa-key"></i> Change Password
                                </a>
                                <a href="#" onclick="if(confirm('Are you sure you want to delete this teacher?')) { deleteTeacher(<?php echo $teacher['teacher_id']; ?>); }">
                                    <i class="fas fa-trash"></i> Delete Teacher
                                </a>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            
            <?php 
            // Display teachers without courses
            foreach ($teachersWithoutCourses as $teacher): 
            ?>
                <tr>
                    <td><?php echo $serialNumber++; ?></td>
                    <td><?php echo htmlspecialchars(ucfirst($teacher['teacher_name'])); ?></td>
                    <td><?php echo htmlspecialchars($teacher['teacher_email']); ?></td>
                    <td>
                        <div class="teacher-courses">
                            No courses assigned
                        </div>
                    </td>
                    <td>
                        <span class="status-inactive">
                            Inactive
                        </span>
                    </td>
                    <td>
                        <div class="teacher-dropdown">
                            <button class="teacher-dropdown-btn" onclick="toggleTeacherDropdown(<?php echo $teacher['teacher_id']; ?>)">
                                <i class="fas fa-ellipsis-v"></i> Actions
                            </button>
                            <div id="teacher-dropdown-<?php echo $teacher['teacher_id']; ?>" class="teacher-dropdown-content">
                                <a href="admin_dashboard.php?section=courses&assign_to=<?php echo $teacher['teacher_id']; ?>">
                                    <i class="fas fa-plus-circle"></i> Assign Course
                                </a>
                                <a href="#" onclick="openChangePasswordModal(<?php echo $teacher['teacher_id']; ?>, '<?php echo htmlspecialchars($teacher['teacher_name']); ?>')">
                                    <i class="fas fa-key"></i> Change Password
                                </a>
                                <a href="#" onclick="if(confirm('Are you sure you want to delete this teacher?')) { deleteTeacher(<?php echo $teacher['teacher_id']; ?>); }">
                                    <i class="fas fa-trash"></i> Delete Teacher
                                </a>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<!-- Password Change Modal -->
<div id="passwordChangeModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closePasswordModal()">&times;</span>
        <h2>Change Password</h2>
        <p id="password-user-info">Change password for: <span id="teacher-name"></span></p>
        
        <form id="change-password-form" method="POST" action="admin_dashboard.php?section=teachers">
            <input type="hidden" id="user_id" name="user_id">
            <input type="hidden" name="change_password" value="1">
            
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" required minlength="6">
                <div class="help-text">Password must be at least 6 characters long</div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn" onclick="closePasswordModal()">Cancel</button>
                <button type="submit" class="btn btn-blue">Change Password</button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden form for delete action -->
<form id="delete-teacher-form" method="POST" action="admin_dashboard.php?section=teachers" style="display: none;">
    <input type="hidden" id="delete_teacher_id" name="teacher_id">
    <input type="hidden" name="delete_teacher" value="1">
</form>

<script>
// Add window resize event listener
window.addEventListener('resize', function() {
    // Close all dropdowns when window is resized
    document.querySelectorAll('.teacher-dropdown-content').forEach(menu => {
        menu.style.display = 'none';
    });
});

function toggleTeacherDropdown(teacherId) {
    event.preventDefault();
    
    const dropdown = document.getElementById(`teacher-dropdown-${teacherId}`);
    const button = event.currentTarget;
    const buttonRect = button.getBoundingClientRect();
    
    // Close all other dropdowns first
    document.querySelectorAll('.teacher-dropdown-content').forEach(menu => {
        if (menu.id !== `teacher-dropdown-${teacherId}`) {
            menu.style.display = 'none';
        }
    });
    
    // Toggle overlay
    let overlay = document.querySelector('.teacher-dropdown-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'teacher-dropdown-overlay';
        document.body.appendChild(overlay);
    }
    
    if (dropdown.style.display === 'block') {
        dropdown.style.display = 'none';
        overlay.style.display = 'none';
    } else {
        // Position the dropdown
        if (window.innerWidth <= 768) {
            // Mobile centered positioning
            dropdown.style.position = 'fixed';
            dropdown.style.top = '50%';
            dropdown.style.left = '50%';
            dropdown.style.transform = 'translate(-50%, -50%)';
            dropdown.style.width = '90%';
            dropdown.style.maxWidth = '300px';
        } else {
            // Desktop positioning
            dropdown.style.position = 'fixed';
            const viewportHeight = window.innerHeight;
            const dropdownHeight = 200; // Approximate height of dropdown
            
            // Calculate available space below and above
            const spaceBelow = viewportHeight - buttonRect.bottom;
            const spaceAbove = buttonRect.top;
            
            if (spaceBelow < dropdownHeight && spaceAbove > spaceBelow) {
                // Position above button if there's more space there
                dropdown.style.top = 'auto';
                dropdown.style.bottom = (viewportHeight - buttonRect.top) + 'px';
            } else {
                // Position below button
                dropdown.style.top = buttonRect.bottom + 'px';
                dropdown.style.bottom = 'auto';
            }
            
            // Horizontal positioning
            if (window.innerWidth - buttonRect.right < 200) {
                // Align to right if too close to right edge
                dropdown.style.right = '10px';
                dropdown.style.left = 'auto';
            } else {
                dropdown.style.left = buttonRect.left + 'px';
                dropdown.style.right = 'auto';
            }
            
            dropdown.style.transform = 'none';
            dropdown.style.width = 'auto';
            dropdown.style.maxHeight = `${Math.min(300, spaceBelow - 10)}px`;
        }
        
        dropdown.style.display = 'block';
        overlay.style.display = 'block';
    }
    
    event.stopPropagation();
}

// Add click event listener to close dropdowns when clicking outside
document.addEventListener('click', function(event) {
    if (!event.target.closest('.teacher-dropdown')) {
        document.querySelectorAll('.teacher-dropdown-content').forEach(menu => {
            menu.style.display = 'none';
        });
        const overlay = document.querySelector('.teacher-dropdown-overlay');
        if (overlay) {
            overlay.style.display = 'none';
        }
    }
});

// Update scroll event listener
document.addEventListener('scroll', function() {
    const dropdowns = document.querySelectorAll('.teacher-dropdown-content');
    const overlay = document.querySelector('.teacher-dropdown-overlay');
    
    dropdowns.forEach(dropdown => {
        if (dropdown.style.display === 'block') {
            const button = dropdown.previousElementSibling;
            const buttonRect = button.getBoundingClientRect();
            
            if (window.innerWidth > 768) {
                // Update position on scroll for desktop view
                if (dropdown.style.top !== 'auto') {
                    dropdown.style.top = `${buttonRect.bottom}px`;
                } else {
                    dropdown.style.bottom = `${window.innerHeight - buttonRect.top}px`;
                }
            }
        }
    });
}, { passive: true });

function openChangePasswordModal(userId, userName) {
    document.getElementById('user_id').value = userId;
    document.getElementById('teacher-name').textContent = userName;
    document.getElementById('passwordChangeModal').style.display = 'block';
    return false;
}

function closePasswordModal() {
    document.getElementById('passwordChangeModal').style.display = 'none';
    document.getElementById('change-password-form').reset();
}

function deleteTeacher(teacherId) {
    document.getElementById('delete_teacher_id').value = teacherId;
    document.getElementById('delete-teacher-form').submit();
}
</script> 














