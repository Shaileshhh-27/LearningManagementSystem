# Learning Management System

A comprehensive web-based Learning Management System (LMS) built with PHP and SQLite, designed to facilitate online education through course management, student enrollment, and performance tracking.

## Features

### User Management
- Multi-role support (Admin, Teacher, Student)
- Profile management with customizable avatars
- Secure authentication system

### Course Management
- Course creation and enrollment
- Lecture management with multimedia support
- Assignment submission and grading
- Progress tracking and analytics

### Reporting System
- Student enrollment reports
- Course performance analytics
- Teacher activity monitoring
- Export capabilities (CSV, Excel)

### System Administration
- Database health monitoring
- System configuration management
- User activity tracking
- Automated avatar generation

## Technical Requirements

- PHP 7.4 or higher
- SQLite3
- Apache Web Server
- Required PHP Extensions:
  - GD Library (for image processing)
  - SQLite3
  - JSON
  - FileInfo

## Installation

1. Clone the repository to your web server directory
2. Ensure proper file permissions:
   ```bash
   chmod 755 learn/dddd
   chmod 644 learn/dddd/error_log.txt
   chmod 644 learn/dddd/debug_log.txt
   ```
3. Configure your web server to point to the project directory
4. Run `setup_admin.php` to create the initial admin account
5. Access the system through your web browser

## Configuration

### PHP Settings (Recommended)
```ini
post_max_size = 2G
upload_max_filesize = 2G
memory_limit = 2G
max_execution_time = 300
max_input_time = 300
```

### Database
- SQLite database file location: `config/database.php`
- Required tables:
  - users
  - courses
  - enrollments
  - assignments
  - submissions
  - lectures

## Security Features

- Input validation and sanitization
- Secure file upload handling
- Role-based access control
- Session management
- Error logging and monitoring

## Maintenance

- Use `check_php_config.php` to verify system settings
- Monitor `error_log.txt` for system errors
- Regular database backups recommended
- Use `validate_db_structure.php` to check database integrity

## License

© 2024 Learning Management System. All rights reserved.

## Support

For technical issues or questions, check the system health page at `system_health.php` or contact system administration.
