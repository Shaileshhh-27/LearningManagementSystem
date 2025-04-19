<?php if (!defined('INCLUDED')) { exit; } ?>

<div class="section-header">
    <h2>Reports</h2>
</div>

<style>
    .report-cards {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .report-card {
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        padding: 20px;
        transition: transform 0.2s;
    }
    
    .report-card:hover {
        transform: translateY(-5px);
    }
    
    .report-card h3 {
        margin-top: 0;
        color: #333;
        font-size: 1.2rem;
        margin-bottom: 10px;
    }
    
    .report-card p {
        color: #666;
        margin-bottom: 15px;
        font-size: 0.9rem;
    }
    
    .report-icon {
        font-size: 2rem;
        margin-bottom: 15px;
        color: #1976D2;
    }
    
    .report-form {
        margin-top: 15px;
    }
    
    .report-form .form-group {
        margin-bottom: 10px;
    }
    
    .report-form label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
        font-size: 0.9rem;
    }
    
    .report-form select, 
    .report-form input[type="date"] {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    .report-actions {
        margin-top: 15px;
        display: flex;
        justify-content: flex-end;
    }
    
    .report-preview {
        margin-top: 30px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        padding: 20px;
        display: none;
    }
    
    .report-preview h3 {
        margin-top: 0;
        margin-bottom: 15px;
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
    }
    
    .report-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .report-table th, 
    .report-table td {
        padding: 10px;
        text-align: left;
        border-bottom: 1px solid #eee;
    }
    
    .report-table th {
        background-color: #f5f5f5;
        font-weight: bold;
    }
    
    .download-options {
        margin-top: 15px;
        display: flex;
        gap: 10px;
        justify-content: flex-end;
    }
    
    .btn-download {
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
</style>

<div class="report-cards">
    <!-- Student Enrollment Report -->
    <div class="report-card">
        <div class="report-icon">
            <i class="fas fa-user-graduate"></i>
        </div>
        <h3>Student Enrollment Report</h3>
        <p>Generate a report of student enrollments across all courses or filter by specific course.</p>
        <form class="report-form" id="enrollment-report-form">
            <div class="form-group">
                <label for="enrollment-course">Course</label>
                <select id="enrollment-course" name="course_id">
                    <option value="all">All Courses</option>
                    <?php foreach ($allCourses as $course): ?>
                        <option value="<?php echo $course['id']; ?>">
                            <?php echo htmlspecialchars($course['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="enrollment-date-from">From Date</label>
                <input type="date" id="enrollment-date-from" name="date_from">
            </div>
            <div class="form-group">
                <label for="enrollment-date-to">To Date</label>
                <input type="date" id="enrollment-date-to" name="date_to">
            </div>
            <div class="report-actions">
                <button type="button" class="btn btn-blue" onclick="generateReport('enrollment')">
                    Generate Report
                </button>
            </div>
        </form>
    </div>
    
    <!-- Course Performance Report -->
    <div class="report-card">
        <div class="report-icon">
            <i class="fas fa-chart-line"></i>
        </div>
        <h3>Course Performance Report</h3>
        <p>Analyze course performance including enrollment rates, completion rates, and student feedback.</p>
        <form class="report-form" id="performance-report-form">
            <div class="form-group">
                <label for="performance-course">Course</label>
                <select id="performance-course" name="course_id">
                    <option value="all">All Courses</option>
                    <?php foreach ($allCourses as $course): ?>
                        <option value="<?php echo $course['id']; ?>">
                            <?php echo htmlspecialchars($course['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="performance-period">Time Period</label>
                <select id="performance-period" name="period">
                    <option value="last_month">Last Month</option>
                    <option value="last_quarter">Last Quarter</option>
                    <option value="last_year">Last Year</option>
                    <option value="all_time">All Time</option>
                </select>
            </div>
            <div class="report-actions">
                <button type="button" class="btn btn-blue" onclick="generateReport('performance')">
                    Generate Report
                </button>
            </div>
        </form>
    </div>
    
    <!-- Teacher Activity Report -->
    <div class="report-card">
        <div class="report-icon">
            <i class="fas fa-chalkboard-teacher"></i>
        </div>
        <h3>Teacher Activity Report</h3>
        <p>Review teacher activities including course creation, assignment grading, and student interactions.</p>
        <form class="report-form" id="teacher-report-form">
            <div class="form-group">
                <label for="teacher-select">Teacher</label>
                <select id="teacher-select" name="teacher_id">
                    <option value="all">All Teachers</option>
                    <?php
                    $teacherQuery = "SELECT id, username FROM users WHERE role = 'teacher'";
                    $teacherResult = $conn->query($teacherQuery);
                    while ($teacher = $teacherResult->fetchArray(SQLITE3_ASSOC)) {
                        echo '<option value="' . $teacher['id'] . '">' . htmlspecialchars($teacher['username']) . '</option>';
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label for="teacher-date-from">From Date</label>
                <input type="date" id="teacher-date-from" name="date_from">
            </div>
            <div class="form-group">
                <label for="teacher-date-to">To Date</label>
                <input type="date" id="teacher-date-to" name="date_to">
            </div>
            <div class="report-actions">
                <button type="button" class="btn btn-blue" onclick="generateReport('teacher')">
                    Generate Report
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Report Preview Section -->
<div id="report-preview" class="report-preview">
    <h3 id="report-title">Report Preview</h3>
    <div id="report-content"></div>
    <div class="download-options">
        <button class="btn btn-download" onclick="downloadReport('csv')">
            <i class="fas fa-file-csv"></i> Download CSV
        </button>
        <button class="btn btn-download" onclick="downloadReport('excel')">
            <i class="fas fa-file-excel"></i> Download Excel
        </button>
    </div>
</div>

<script>
// Function to generate reports
function generateReport(reportType) {
    // Get the form data
    const form = document.getElementById(`${reportType}-report-form`);
    const formData = new FormData(form);
    
    // Set the report title
    let reportTitle = '';
    switch(reportType) {
        case 'enrollment':
            reportTitle = 'Student Enrollment Report';
            break;
        case 'performance':
            reportTitle = 'Course Performance Report';
            break;
        case 'teacher':
            reportTitle = 'Teacher Activity Report';
            break;
    }
    
    document.getElementById('report-title').textContent = reportTitle;
    document.getElementById('report-content').innerHTML = '<p style="text-align:center;">Loading report data...</p>';
    document.getElementById('report-preview').style.display = 'block';
    
    // Convert FormData to URL parameters
    const params = new URLSearchParams();
    for (const [key, value] of formData.entries()) {
        params.append(key, value);
    }
    params.append('type', reportType);
    
    // Fetch the report data from the server
    fetch(`generate_report.php?${params.toString()}`)
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    throw new Error(`Server returned ${response.status}: ${text.substring(0, 100)}`);
                });
            }
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Failed to parse response as JSON:', text.substring(0, 200));
                    throw new Error('Invalid JSON response from server. Please check the console for details.');
                }
            });
        })
        .then(data => {
            if (data.error) {
                document.getElementById('report-content').innerHTML = `<p style="color: red; text-align: center;">${data.error}</p>`;
                return;
            }
            
            if (!data.rows || data.rows.length === 0) {
                document.getElementById('report-content').innerHTML = '<p style="text-align:center;">No data available for the selected criteria.</p>';
                return;
            }
            
            // Generate the report table
            let tableHTML = '<table class="report-table">';
            
            // Add table headers
            tableHTML += '<thead><tr>';
            for (const key in data.headers) {
                tableHTML += `<th>${data.headers[key]}</th>`;
            }
            tableHTML += '</tr></thead>';
            
            // Add table body
            tableHTML += '<tbody>';
            data.rows.forEach(row => {
                tableHTML += '<tr>';
                for (const key in data.headers) {
                    tableHTML += `<td>${row[key] !== null ? row[key] : '-'}</td>`;
                }
                tableHTML += '</tr>';
            });
            tableHTML += '</tbody></table>';
            
            // Display the report
            document.getElementById('report-content').innerHTML = tableHTML;
            
            // Store report data for download
            window.currentReportData = {
                type: reportType,
                params: Object.fromEntries(params.entries()),
                title: reportTitle
            };
        })
        .catch(error => {
            console.error('Error fetching report data:', error);
            document.getElementById('report-content').innerHTML = `<p style="color: red; text-align: center;">Error generating report: ${error.message}</p>`;
        });
    
    // Scroll to the report
    document.getElementById('report-preview').scrollIntoView({ behavior: 'smooth' });
}

// Function to download reports
function downloadReport(format) {
    // Check if we have report data
    if (!window.currentReportData) {
        alert('Please generate a report first.');
        return;
    }
    
    const { type, params } = window.currentReportData;
    
    // Create URL parameters
    const urlParams = new URLSearchParams(params);
    urlParams.set('format', format);
    
    // Redirect to download endpoint
    window.location.href = `generate_report.php?${urlParams.toString()}`;
}

// Set default dates for date inputs
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date();
    const oneMonthAgo = new Date();
    oneMonthAgo.setMonth(today.getMonth() - 1);
    
    const formatDate = date => date.toISOString().split('T')[0];
    
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => {
        if (input.name.includes('date_to')) {
            input.value = formatDate(today);
        } else if (input.name.includes('date_from')) {
            input.value = formatDate(oneMonthAgo);
        }
    });
    
    // Initialize window.currentReportData
    window.currentReportData = null;
});
</script> 