<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>House Management System</title>
	<!-- Bootstrap 4 CSS -->
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.4.1/dist/css/bootstrap.min.css"
		integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">
</head>

<body>

	<!-- <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
		<div class="container">
			<?php if (isset($_SESSION['userID'])): ?>
				<a class="navbar-brand fw-bold" href="Dashboard.php">🏠 House System</a>
			<?php endif; ?>
			<button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav"
				aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
				<span class="navbar-toggler-icon"></span>
			</button>

			<div class="collapse navbar-collapse" id="navbarNav">
				<?php if (isset($_SESSION['userID'])): ?>
					<ul class="navbar-nav mr-auto">
						<li class="nav-item"><a class="nav-link" href="Dashboard.php">Home</a></li>
						<li class="nav-item"><a class="nav-link" href="AddHouse.php">Add House</a></li>
					</ul>
				<?php endif; ?>

				<?php if (isset($_SESSION['userID'])): ?>
					<ul class="navbar-nav">
						<li class="nav-item dropdown">
							<a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown"
								aria-haspopup="true" aria-expanded="false">
								👤 Account
							</a>
							<div class="dropdown-menu dropdown-menu-right" aria-labelledby="userDropdown">
								<a class="dropdown-item" href="#">Profile</a>
								<div class="dropdown-divider"></div>
								<a class="dropdown-item text-danger" href="../Controller/Logout.php">Logout</a>
							</div>
						</li>
					</ul>
				<?php endif; ?>
			</div>
		</div>
	</nav> -->

	<div class="container mt-4"></div>