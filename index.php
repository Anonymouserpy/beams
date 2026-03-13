<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login Selection</title>

    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />

    <style>
    body {
        height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
        background: #f4f4f4;
        margin: 0;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .login-card {
        width: 100%;
        max-width: 400px;
        padding: 40px;
        border-radius: 15px;
        background: #ffffff;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        text-align: center;
        transition: transform 0.2s ease;
    }

    .login-card:hover {
        transform: translateY(-5px);
    }

    .login-card h2 {
        margin-bottom: 30px;
        font-weight: 600;
        color: #333333;
    }

    .btn-lg {
        font-size: 1.1rem;
        padding: 12px;
    }
    </style>
</head>

<body>
    <div class="login-card">
        <h2>Select Login Type</h2>

        <div class="d-grid gap-3">
            <a href="StudentLogin.php" class="btn btn-primary btn-lg">
                Student Login
            </a>

            <a href="officer_Login.php" class="btn btn-success btn-lg">
                Officer Login
            </a>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>