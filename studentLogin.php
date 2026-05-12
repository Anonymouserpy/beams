

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<div class="container d-flex justify-content-center align-items-center" style="min-height: 100vh;">
    <div class="col-md-5 p-4 bg-white shadow rounded-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h3 class="text-center mb-4">Student Login</h3>

                    <form action="Auth/login_student.php" method="POST">
                        <div class="form-group">
                            <label for="student_id">Student ID:</label>
                            <input type="text" name="student_id" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="password">Password:</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>

                        <button type="submit" class="text-center mt-1 mb-3 btn btn-primary">Login</button>
                    </form>

                    <p class="text-center mt-3 mb-0">
                        Don't have an account?
                        <a href="registration.php">Register</a>
                    </p>
                </div>
                <a href="index.php" class="text-center mt-1 mb-3">Go Back</a>

            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</div>


