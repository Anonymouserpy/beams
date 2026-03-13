<?php
session_start();
require "../sidebar/student_sidebar.php";
require "../../Connection/connection.php";

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: ../../Login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

// Fetch logged-in student data
$stmt = $conn->prepare("SELECT student_id, full_name, year_level, section, created_at FROM students WHERE student_id = ?");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();

$student = $result->fetch_assoc() ?? null;

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Student Dashboard</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />

    <style>
    body {
        background-color: #f4f6f9;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .main-content {
        margin-left: 250px;
        padding: 30px;
    }

    .profile-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        padding: 25px;
        transition: transform 0.2s;
    }

    .profile-card:hover {
        transform: translateY(-3px);
    }

    .profile-card h4 {
        font-weight: bold;
        margin-bottom: 20px;
        color: #0d6efd;
    }

    .profile-table th {
        width: 40%;
        text-align: left;
        color: #495057;
    }

    .profile-table td {
        text-align: left;
    }

    .profile-table tr+tr td {
        padding-top: 12px;
    }

    @media (max-width: 768px) {
        .main-content {
            margin-left: 0;
            padding: 15px;
        }
    }
    </style>
</head>

<body>

    <div class="main-content">

        <?php if ($student): ?>
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6 col-md-8 col-sm-10">
                    <div class="profile-card">
                        <h4>Welcome, <?= htmlspecialchars($student['full_name']) ?>!</h4>
                        <table class="table profile-table">
                            <tbody>
                                <tr>
                                    <th>Student ID:</th>
                                    <td><?= htmlspecialchars($student['student_id']) ?></td>
                                </tr>
                                <tr>
                                    <th>Full Name:</th>
                                    <td><?= htmlspecialchars($student['full_name']) ?></td>
                                </tr>
                                <tr>
                                    <th>Year Level:</th>
                                    <td><?= htmlspecialchars($student['year_level']) ?></td>
                                </tr>
                                <tr>
                                    <th>Section:</th>
                                    <td><?= htmlspecialchars($student['section']) ?></td>
                                </tr>
                                <tr>
                                    <th>Account Created:</th>
                                    <td><?= date('F j, Y', strtotime($student['created_at'])) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-warning text-center">
            No information available for your account.
        </div>
        <?php endif; ?>

    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>