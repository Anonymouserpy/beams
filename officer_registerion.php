<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Create an Account</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />

    <style>
    .alert {
        display: none;
    }

    .alert.show {
        display: block;
        animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .spinner-border {
        width: 1rem;
        height: 1rem;
        display: none;
    }

    .btn-loading .spinner-border {
        display: inline-block;
    }

    .btn-loading .btn-text {
        display: none;
    }

    .is-invalid {
        border-color: #dc3545 !important;
    }
    </style>
</head>

<body class="bg-light">

    <div class="container d-flex justify-content-center align-items-center" style="min-height: 100vh;">
        <div class="col-md-6 col-lg-5 p-4 bg-white shadow rounded-3">

            <h3 class="text-center mb-4">Create an Account</h3>

            <!-- Alert Messages -->
            <div class="alert alert-danger" id="errorAlert" role="alert"></div>
            <div class="alert alert-success" id="successAlert" role="alert"></div>

            <form id="registerForm" action="Auth/officer_register.php" method="POST">

                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" class="form-control" id="full_name" placeholder="Enter your full name"
                        name="full_name" required />
                    <div class="invalid-feedback">Please enter your full name</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Officer ID</label>
                    <input type="text" class="form-control" id="officer_id" placeholder="Enter your Officer ID"
                        name="officer_id" required />
                    <div class="invalid-feedback">Officer ID is required</div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Position</label>
                        <select name="position" id="position" class="form-select" required>
                            <option value="">Select Position</option>
                            <option value="Admin">Admin</option>
                            <option value="Officer">Officer</option>
                        </select>
                        <div class="invalid-feedback">Select position</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" id="password"
                            placeholder="Enter password (min 6 characters)" name="password" required minlength="6" />
                        <div class="invalid-feedback">Password must be at least 6 characters</div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" class="form-control" id="confirm_password" placeholder="Re-enter password"
                        name="confirm_password" required />
                    <div class="invalid-feedback">Passwords do not match</div>
                </div>

                <button type="submit" class="btn btn-primary w-100 mt-2" id="submitBtn">
                    <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                    <span class="btn-text">Register</span>
                </button>

                <div class="text-center mt-3">
                    <small class="text-muted">Already have an account? <a href="officer_login.php"
                            class="text-decoration-none">Login here</a></small>
                </div>

            </form>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    document.getElementById('registerForm').addEventListener('submit', function(e) {
        e.preventDefault();

        // Reset alerts
        const errorAlert = document.getElementById('errorAlert');
        const successAlert = document.getElementById('successAlert');
        errorAlert.classList.remove('show');
        successAlert.classList.remove('show');

        // Get form values
        const fullName = document.getElementById('full_name').value.trim();
        const officerId = document.getElementById('officer_id').value.trim();
        const position = document.getElementById('position').value;
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;

        // Validation
        let isValid = true;

        // Reset invalid states
        document.querySelectorAll('.form-control, .form-select').forEach(el => {
            el.classList.remove('is-invalid');
        });

        if (fullName === '') {
            document.getElementById('full_name').classList.add('is-invalid');
            isValid = false;
        }

        if (officerId === '') {
            document.getElementById('officer_id').classList.add('is-invalid');
            isValid = false;
        }

        if (position === '') {
            document.getElementById('position').classList.add('is-invalid');
            isValid = false;
        }

        if (password.length < 6) {
            document.getElementById('password').classList.add('is-invalid');
            isValid = false;
        }

        if (password !== confirmPassword) {
            document.getElementById('confirm_password').classList.add('is-invalid');
            showError('Passwords do not match!');
            isValid = false;
        }

        if (!isValid) return;

        // Show loading
        const btn = document.getElementById('submitBtn');
        btn.classList.add('btn-loading');
        btn.disabled = true;

        // Send AJAX request
        const formData = new FormData(this);

        fetch('Auth/officer_register.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json()) // Expect JSON from PHP
            .then(data => {
                btn.classList.remove('btn-loading');
                btn.disabled = false;

                if (data.status === 'success') {
                    showSuccess(data.message);
                    this.reset(); // Clear form
                    setTimeout(() => {
                        window.location.href = 'officerLogin.php';
                    }, 2000);
                } else if (data.status === 'error') {
                    showError(data.message);
                } else {
                    showError('Unexpected response from server.');
                }
            })
            .catch(error => {
                btn.classList.remove('btn-loading');
                btn.disabled = false;
                showError('Network error. Please try again.');
            });
    });

    function showError(message) {
        const alert = document.getElementById('errorAlert');
        alert.textContent = message;
        alert.classList.add('show');
    }

    function showSuccess(message) {
        const alert = document.getElementById('successAlert');
        alert.textContent = message;
        alert.classList.add('show');
    }

    // Real-time password match check
    document.getElementById('confirm_password').addEventListener('input', function() {
        const password = document.getElementById('password').value;
        if (this.value !== password) {
            this.classList.add('is-invalid');
        } else {
            this.classList.remove('is-invalid');
        }
    });
    </script>

</body>

</html>