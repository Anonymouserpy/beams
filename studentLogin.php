<?php
include('Includes/Header.php');

?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
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

                        <button type="submit" class="btn btn-primary btn-block">Login</button>
                    </form>

                    <p class="text-center mt-3 mb-0">
                        Don't have an account?
                        <a href="Registration.php">Register</a>
                    </p>
                </div>
                <a href="index.php" class="text-center mt-1 mb-3">Go Back</a>

            </div>
        </div>
    </div>
</div>

<?php include('Includes/Footer.php'); ?>