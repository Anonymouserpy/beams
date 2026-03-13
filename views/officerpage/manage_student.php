<?php
// MUST BE FIRST - NO SPACES BEFORE <?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require "../../Connection/connection.php";

// Auth check
if (!isset($_SESSION['officer_id'])) {
    header("Location: ../../Login.php");
    exit();
}

// Handle Delete Action - BEFORE ANY OUTPUT
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $student_id = $conn->real_escape_string($_GET['delete']);
    
    // Delete related records first
    $tables = ['student_fines', 'attendance'];
    
    foreach ($tables as $table) {
        $check = $conn->query("SHOW TABLES LIKE '$table'");
        if ($check->num_rows > 0) {
            $conn->query("DELETE FROM $table WHERE student_id = '$student_id'");
        }
    }
    
    // Delete student
    $conn->query("DELETE FROM students WHERE student_id = '$student_id'");
    
    header("Location: manage_student.php");
    exit();
}

// Handle Create/Edit Action - BEFORE ANY OUTPUT
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = isset($_POST['student_id']) ? $conn->real_escape_string($_POST['student_id']) : '';
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $year_level = intval($_POST['year_level']);
    $section = $conn->real_escape_string($_POST['section']);
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Validate year level
    if ($year_level < 1 || $year_level > 12) {
        $year_level = 1;
    }
    
    if (!empty($student_id) && isset($_POST['existing_id'])) {
        // Update existing student
        $existing_id = $conn->real_escape_string($_POST['existing_id']);
        
        if (!empty($password)) {
            // Update with new password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $conn->query("UPDATE students SET 
                student_id = '$student_id',
                full_name = '$full_name',
                year_level = $year_level,
                section = '$section',
                password = '$hashed_password'
                WHERE student_id = '$existing_id'");
        } else {
            // Update without changing password
            $conn->query("UPDATE students SET 
                student_id = '$student_id',
                full_name = '$full_name',
                year_level = $year_level,
                section = '$section'
                WHERE student_id = '$existing_id'");
        }
    } else {
        // Create new student
        if (empty($password)) {
            $password = 'student123'; // Default password
        }
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $conn->query("INSERT INTO students (student_id, full_name, year_level, section, password, created_at) 
            VALUES ('$student_id', '$full_name', $year_level, '$section', '$hashed_password', NOW())");
    }
    
    header("Location: manage_student.php");
    exit();
}

// Fetch all students with aggregated data
$students = $conn->query("
    SELECT s.*, 
           COUNT(DISTINCT a.attendance_id) as attendance_count,
           COUNT(DISTINCT f.fine_id) as total_fines,
           SUM(CASE WHEN f.status = 'unpaid' THEN f.amount ELSE 0 END) as unpaid_amount
    FROM students s
    LEFT JOIN attendance a ON s.student_id = a.student_id
    LEFT JOIN student_fines f ON s.student_id = f.student_id
    GROUP BY s.student_id
    ORDER BY s.year_level ASC, s.section ASC, s.full_name ASC
");

// Get statistics
$total_students = $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'];
$total_sections = $conn->query("SELECT COUNT(DISTINCT section) as count FROM students")->fetch_assoc()['count'];
$total_unpaid = $conn->query("SELECT SUM(amount) as total FROM student_fines WHERE status = 'unpaid'")->fetch_assoc()['total'] ?: 0;
$total_attendance = $conn->query("SELECT COUNT(*) as count FROM attendance")->fetch_assoc()['count'];

// Get edit data
$edit_student = null;
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $edit_id = $conn->real_escape_string($_GET['edit']);
    $result = $conn->query("SELECT * FROM students WHERE student_id = '$edit_id'");
    $edit_student = $result->fetch_assoc();
}

// NOW START OUTPUT
require "../sidebar/officer_sidebar.php";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Manager | BEAMS Officer Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <style>
    :root {
        --primary: #6366f1;
        --primary-dark: #4f46e5;
        --secondary: #ec4899;
        --success: #10b981;
        --warning: #f59e0b;
        --danger: #ef4444;
        --info: #0ea5e9;
        --dark: #1e293b;
        --light: #f8fafc;
        --sidebar-width: 280px;
        --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --card-hover: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', sans-serif;
        background: #f1f5f9;
        color: #334155;
        margin-left: var(--sidebar-width);
        min-height: 100vh;
    }

    /* Page Header */
    .page-header {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        padding: 2rem;
        color: white;
        position: relative;
        overflow: hidden;
    }

    .page-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 500px;
        height: 500px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
    }

    .header-content {
        position: relative;
        z-index: 1;
    }

    .breadcrumb {
        background: transparent;
        padding: 0;
        margin-bottom: 0.5rem;
    }

    .breadcrumb-item a {
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
    }

    .breadcrumb-item.active {
        color: white;
    }

    .page-title {
        font-size: 2rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
    }

    .page-subtitle {
        opacity: 0.9;
        font-size: 1rem;
    }

    /* Stats Row */
    .stats-row {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1.5rem;
        padding: 2rem;
        margin-top: -3rem;
        position: relative;
        z-index: 10;
    }

    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: var(--card-shadow);
        transition: all 0.3s ease;
        border: 1px solid #e2e8f0;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--card-hover);
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 1rem;
    }

    .stat-card.primary .stat-icon {
        background: #eef2ff;
        color: var(--primary);
    }

    .stat-card.success .stat-icon {
        background: #d1fae5;
        color: var(--success);
    }

    .stat-card.warning .stat-icon {
        background: #fef3c7;
        color: var(--warning);
    }

    .stat-card.danger .stat-icon {
        background: #fee2e2;
        color: var(--danger);
    }

    .stat-value {
        font-size: 2rem;
        font-weight: 800;
        color: var(--dark);
        margin-bottom: 0.25rem;
    }

    .stat-label {
        color: #64748b;
        font-size: 0.875rem;
        font-weight: 500;
    }

    /* Main Content */
    .main-content {
        padding: 0 2rem 2rem;
    }

    .content-card {
        background: white;
        border-radius: 16px;
        box-shadow: var(--card-shadow);
        border: 1px solid #e2e8f0;
        overflow: hidden;
    }

    .card-header {
        padding: 1.5rem;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #fafafa;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .card-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--dark);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .search-box {
        position: relative;
        min-width: 300px;
    }

    .search-box input {
        width: 100%;
        padding: 0.75rem 1rem 0.75rem 2.5rem;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-size: 0.875rem;
        transition: all 0.2s;
    }

    .search-box input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    }

    .search-box i {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
    }

    /* Student Grid */
    .students-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 1.5rem;
        padding: 1.5rem;
    }

    .student-card {
        background: white;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        transition: all 0.3s ease;
        position: relative;
    }

    .student-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--card-hover);
        border-color: var(--primary);
    }

    .student-header {
        padding: 1.5rem;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .student-avatar {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
        font-weight: 700;
        flex-shrink: 0;
    }

    .student-info h4 {
        font-size: 1.125rem;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 0.25rem;
    }

    .student-id {
        font-family: 'Courier New', monospace;
        font-size: 0.875rem;
        color: var(--primary);
        font-weight: 600;
        background: #eef2ff;
        padding: 0.25rem 0.5rem;
        border-radius: 6px;
        display: inline-block;
    }

    .student-body {
        padding: 1.5rem;
    }

    .student-meta {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .meta-item {
        text-align: center;
        padding: 0.75rem;
        background: #f8fafc;
        border-radius: 8px;
    }

    .meta-value {
        font-size: 1.125rem;
        font-weight: 700;
        color: var(--dark);
    }

    .meta-label {
        font-size: 0.75rem;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .student-stats {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        margin-bottom: 1.5rem;
    }

    .stat-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.875rem;
    }

    .stat-row span:first-child {
        color: #64748b;
    }

    .stat-row span:last-child {
        font-weight: 600;
        color: var(--dark);
    }

    .fines-warning {
        color: var(--danger) !important;
    }

    .progress {
        height: 6px;
        background: #e2e8f0;
        border-radius: 3px;
        margin-bottom: 1.5rem;
    }

    .progress-bar {
        background: linear-gradient(90deg, var(--primary) 0%, var(--secondary) 100%);
        border-radius: 3px;
    }

    .student-actions {
        display: flex;
        gap: 0.5rem;
    }

    .btn-student {
        flex: 1;
        padding: 0.625rem;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.875rem;
        transition: all 0.2s;
        border: none;
        cursor: pointer;
        text-decoration: none;
        text-align: center;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .btn-view {
        background: #eef2ff;
        color: var(--primary);
    }

    .btn-view:hover {
        background: var(--primary);
        color: white;
    }

    .btn-edit {
        background: #fef3c7;
        color: #d97706;
    }

    .btn-edit:hover {
        background: #f59e0b;
        color: white;
    }

    .btn-delete {
        background: #fee2e2;
        color: #dc2626;
    }

    .btn-delete:hover {
        background: #ef4444;
        color: white;
    }

    /* Modal Styles */
    .modal-content {
        border-radius: 16px;
        border: none;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    }

    .modal-header {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        color: white;
        border-radius: 16px 16px 0 0;
        padding: 1.5rem;
        border: none;
    }

    .modal-title {
        font-weight: 700;
        font-size: 1.25rem;
    }

    .btn-close {
        filter: brightness(0) invert(1);
    }

    .modal-body {
        padding: 1.5rem;
    }

    .form-label {
        font-weight: 600;
        color: var(--dark);
        font-size: 0.875rem;
        margin-bottom: 0.5rem;
    }

    .form-control,
    .form-select {
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        padding: 0.75rem 1rem;
        font-size: 0.875rem;
        transition: all 0.2s;
    }

    .form-control:focus,
    .form-select:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: 10px;
        font-weight: 600;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px -5px rgba(99, 102, 241, 0.4);
    }

    /* Year Level Selector */
    .year-selector {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .year-option {
        position: relative;
        cursor: pointer;
    }

    .year-option input[type="radio"] {
        position: absolute;
        opacity: 0;
    }

    .year-card {
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        padding: 0.75rem;
        text-align: center;
        transition: all 0.3s ease;
        background: white;
    }

    .year-option input[type="radio"]:checked+.year-card {
        border-color: var(--primary);
        background: #eef2ff;
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.15);
    }

    .year-number {
        font-weight: 700;
        font-size: 1.125rem;
        color: var(--dark);
    }

    .year-label {
        font-size: 0.625rem;
        color: #64748b;
        text-transform: uppercase;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
    }

    .empty-icon {
        width: 120px;
        height: 120px;
        background: #f1f5f9;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        font-size: 3rem;
        color: #cbd5e1;
    }

    .empty-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 0.5rem;
    }

    .empty-text {
        color: #64748b;
        margin-bottom: 1.5rem;
    }

    /* Floating Action Button */
    .fab {
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
        box-shadow: 0 10px 25px -5px rgba(99, 102, 241, 0.5);
        cursor: pointer;
        transition: all 0.3s ease;
        border: none;
        z-index: 1000;
    }

    .fab:hover {
        transform: scale(1.1) rotate(90deg);
        box-shadow: 0 20px 35px -5px rgba(99, 102, 241, 0.6);
    }

    /* Filter Tabs */
    .filter-tabs {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1.5rem;
        padding: 0 1.5rem;
        border-bottom: 1px solid #e2e8f0;
        overflow-x: auto;
    }

    .filter-tab {
        padding: 0.75rem 1.25rem;
        border: none;
        background: transparent;
        color: #64748b;
        font-weight: 600;
        font-size: 0.875rem;
        cursor: pointer;
        border-bottom: 2px solid transparent;
        transition: all 0.2s;
        white-space: nowrap;
    }

    .filter-tab:hover {
        color: var(--primary);
    }

    .filter-tab.active {
        color: var(--primary);
        border-bottom-color: var(--primary);
    }

    /* Responsive */
    @media (max-width: 1200px) {
        .stats-row {
            grid-template-columns: repeat(2, 1fr);
        }

        .year-selector {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (max-width: 991px) {
        body {
            margin-left: 0;
        }

        .stats-row {
            grid-template-columns: 1fr;
            margin-top: 0;
        }

        .students-grid {
            grid-template-columns: 1fr;
        }

        .year-selector {
            grid-template-columns: repeat(2, 1fr);
        }

        .search-box {
            width: 100%;
            min-width: unset;
        }

        .card-header {
            flex-direction: column;
            align-items: stretch;
        }
    }

    /* Animations */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .animate-in {
        animation: fadeInUp 0.5s ease forwards;
    }

    .delay-1 {
        animation-delay: 0.1s;
    }

    .delay-2 {
        animation-delay: 0.2s;
    }

    .delay-3 {
        animation-delay: 0.3s;
    }

    .delay-4 {
        animation-delay: 0.4s;
    }

    /* Password toggle */
    .password-wrapper {
        position: relative;
    }

    .password-toggle {
        position: absolute;
        right: 1rem;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: #94a3b8;
        transition: color 0.2s;
    }

    .password-toggle:hover {
        color: var(--primary);
    }
    </style>
</head>

<body>

    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="officer_dashboard.php"><i class="fas fa-home"></i>
                            Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Student Manager</li>
                </ol>
            </nav>
            <h1 class="page-title"><i class="fas fa-user-graduate me-3"></i>Student Manager</h1>
            <p class="page-subtitle">Manage student records, track attendance, and monitor fines</p>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="stats-row">
        <div class="stat-card primary animate-in">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-value"><?php echo $total_students; ?></div>
            <div class="stat-label">Total Students</div>
        </div>
        <div class="stat-card success animate-in delay-1">
            <div class="stat-icon">
                <i class="fas fa-layer-group"></i>
            </div>
            <div class="stat-value"><?php echo $total_sections; ?></div>
            <div class="stat-label">Sections</div>
        </div>
        <div class="stat-card warning animate-in delay-2">
            <div class="stat-icon">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-value">₱<?php echo number_format($total_unpaid, 2); ?></div>
            <div class="stat-label">Unpaid Fines</div>
        </div>
        <div class="stat-card danger animate-in delay-3">
            <div class="stat-icon">
                <i class="fas fa-clipboard-check"></i>
            </div>
            <div class="stat-value"><?php echo $total_attendance; ?></div>
            <div class="stat-label">Total Records</div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-card animate-in delay-2">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-list-ul text-primary"></i>
                    All Students
                </h3>
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Search students..." onkeyup="filterStudents()">
                    </div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#studentModal"
                        onclick="resetForm()">
                        <i class="fas fa-plus me-2"></i>Add Student
                    </button>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <button class="filter-tab active" onclick="filterByYear('all')">All Students</button>
                <?php for($i = 1; $i <= 12; $i++): ?>
                <button class="filter-tab" onclick="filterByYear(<?php echo $i; ?>)">Grade <?php echo $i; ?></button>
                <?php endfor; ?>
            </div>

            <div class="students-grid" id="studentsGrid">
                <?php if ($students->num_rows > 0): ?>
                <?php while($student = $students->fetch_assoc()): 
                        $initials = implode('', array_map(function($word) { 
                            return strtoupper(substr($word, 0, 1)); 
                        }, explode(' ', $student['full_name'])));
                        $initials = substr($initials, 0, 2);
                        
                        $attendance_rate = $total_attendance > 0 ? 
                            round(($student['attendance_count'] / $total_attendance) * 100) : 0;
                    ?>
                <div class="student-card" data-year="<?php echo $student['year_level']; ?>"
                    data-name="<?php echo strtolower($student['full_name']); ?>"
                    data-id="<?php echo strtolower($student['student_id']); ?>">
                    <div class="student-header">
                        <div class="student-avatar">
                            <?php echo $initials; ?>
                        </div>
                        <div class="student-info">
                            <h4><?php echo htmlspecialchars($student['full_name']); ?></h4>
                            <span class="student-id"><?php echo htmlspecialchars($student['student_id']); ?></span>
                        </div>
                    </div>
                    <div class="student-body">
                        <div class="student-meta">
                            <div class="meta-item">
                                <div class="meta-value">Grade <?php echo $student['year_level']; ?></div>
                                <div class="meta-label">Year Level</div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-value"><?php echo htmlspecialchars($student['section']); ?></div>
                                <div class="meta-label">Section</div>
                            </div>
                        </div>

                        <div class="student-stats">
                            <div class="stat-row">
                                <span><i class="fas fa-clipboard-check me-2 text-primary"></i>Attendance Records</span>
                                <span><?php echo $student['attendance_count']; ?></span>
                            </div>
                            <div class="stat-row">
                                <span><i class="fas fa-file-invoice-dollar me-2 text-warning"></i>Total Fines</span>
                                <span><?php echo $student['total_fines']; ?></span>
                            </div>
                            <div class="stat-row">
                                <span><i class="fas fa-exclamation-circle me-2 text-danger"></i>Unpaid Amount</span>
                                <span class="<?php echo $student['unpaid_amount'] > 0 ? 'fines-warning' : ''; ?>">
                                    ₱<?php echo number_format($student['unpaid_amount'] ?: 0, 2); ?>
                                </span>
                            </div>
                        </div>

                        <div class="progress">
                            <div class="progress-bar" style="width: <?php echo min($attendance_rate, 100); ?>%"></div>
                        </div>

                        <div class="student-actions">
                            <a href="student_details.php?id=<?php echo urlencode($student['student_id']); ?>"
                                class="btn-student btn-view">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <button class="btn-student btn-edit"
                                onclick="editStudent(<?php echo htmlspecialchars(json_encode($student)); ?>)">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <a href="?delete=<?php echo urlencode($student['student_id']); ?>"
                                class="btn-student btn-delete"
                                onclick="return confirm('Are you sure you want to delete this student? This will also remove all their attendance and fine records.')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <h3 class="empty-title">No Students Yet</h3>
                    <p class="empty-text">Start by adding your first student to the system.</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#studentModal"
                        onclick="resetForm()">
                        <i class="fas fa-plus me-2"></i>Add First Student
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Floating Action Button (Mobile) -->
    <button class="fab d-lg-none" data-bs-toggle="modal" data-bs-target="#studentModal" onclick="resetForm()">
        <i class="fas fa-plus"></i>
    </button>

    <!-- Student Modal -->
    <div class="modal fade" id="studentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">
                        <i class="fas fa-user-plus me-2"></i>Add New Student
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="" id="studentForm">
                    <div class="modal-body">
                        <input type="hidden" name="existing_id" id="existing_id" value="">

                        <div class="mb-3">
                            <label class="form-label">Student ID</label>
                            <input type="text" class="form-control" name="student_id" id="student_id" required
                                placeholder="e.g., 2024-0001">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="full_name" id="full_name" required
                                placeholder="Enter full name">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Year Level</label>
                            <div class="year-selector">
                                <?php for($i = 1; $i <= 12; $i++): ?>
                                <label class="year-option">
                                    <input type="radio" name="year_level" value="<?php echo $i; ?>"
                                        <?php echo $i == 7 ? 'checked' : ''; ?>>
                                    <div class="year-card">
                                        <div class="year-number"><?php echo $i; ?></div>
                                        <div class="year-label">Grade</div>
                                    </div>
                                </label>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Section</label>
                            <input type="text" class="form-control" name="section" id="section" required
                                placeholder="e.g., A, B, C or Diamond, Ruby">
                        </div>

                        <div class="mb-3" id="passwordField">
                            <label class="form-label">Password <small class="text-muted">(Leave blank to keep unchanged
                                    when editing)</small></label>
                            <div class="password-wrapper">
                                <input type="password" class="form-control" name="password" id="password"
                                    placeholder="Enter password">
                                <i class="fas fa-eye password-toggle" onclick="togglePassword()"></i>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Student
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Edit Student Function
    function editStudent(student) {
        document.getElementById('existing_id').value = student.student_id;
        document.getElementById('student_id').value = student.student_id;
        document.getElementById('full_name').value = student.full_name;
        document.getElementById('section').value = student.section;

        // Set year level radio button
        const yearLevel = student.year_level || 7;
        const radio = document.querySelector(`input[name="year_level"][value="${yearLevel}"]`);
        if (radio) {
            radio.checked = true;
        }

        // Clear password field and make it optional
        document.getElementById('password').value = '';
        document.getElementById('password').placeholder = 'Leave blank to keep current password';

        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Student';

        const modal = new bootstrap.Modal(document.getElementById('studentModal'));
        modal.show();
    }

    // Reset form when creating new student
    function resetForm() {
        document.getElementById('studentForm').reset();
        document.getElementById('existing_id').value = '';
        document.getElementById('password').placeholder = 'Enter password';
        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-plus me-2"></i>Add New Student';
        // Set default year level
        document.querySelector('input[name="year_level"][value="7"]').checked = true;
    }

    // Reset form when modal is closed
    document.getElementById('studentModal').addEventListener('hidden.bs.modal', function() {
        resetForm();
    });

    // Toggle password visibility
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.querySelector('.password-toggle');

        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.classList.remove('fa-eye');
            toggleIcon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            toggleIcon.classList.remove('fa-eye-slash');
            toggleIcon.classList.add('fa-eye');
        }
    }

    // Filter students by search
    function filterStudents() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const cards = document.querySelectorAll('.student-card');

        cards.forEach(card => {
            const name = card.getAttribute('data-name');
            const id = card.getAttribute('data-id');

            if (name.includes(searchTerm) || id.includes(searchTerm)) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    }

    // Filter students by year level
    function filterByYear(year) {
        // Update active tab
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        event.target.classList.add('active');

        // Filter cards
        const cards = document.querySelectorAll('.student-card');
        cards.forEach(card => {
            if (year === 'all' || card.getAttribute('data-year') == year) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    }

    // Form validation
    document.getElementById('studentForm').addEventListener('submit', function(e) {
        const studentId = document.getElementById('student_id').value.trim();
        const fullName = document.getElementById('full_name').value.trim();
        const section = document.getElementById('section').value.trim();

        if (!studentId) {
            e.preventDefault();
            alert('Please enter a student ID');
            return false;
        }

        if (!fullName) {
            e.preventDefault();
            alert('Please enter the full name');
            return false;
        }

        if (!section) {
            e.preventDefault();
            alert('Please enter a section');
            return false;
        }
    });
    </script>
</body>

</html>