	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
	<style>
body {
    margin: 0;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #f8f9fa;
}

.sidebar {
    background: linear-gradient(135deg, #33A1E0, #212529);
    box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    overflow-y: auto;
}

.sidebar h4 {
    font-weight: bold;
    margin-bottom: 2rem;
}

.nav-link {
    color: rgba(255, 255, 255, 0.8) !important;
    transition: all 0.3s ease;
    padding: 0.75rem 1rem;
    border-radius: 5px;
    margin-bottom: 0.5rem;
}

.nav-link:hover {
    background-color: rgba(255, 255, 255, 0.1);
    color: white !important;
    transform: translateX(5px);
}

.nav-link i {
    margin-right: 10px;
}

.logout {
    margin-top: auto;
    border-top: 1px solid rgba(255, 255, 255, 0.2);
    padding-top: 1rem;
}

.main-content {
    margin-left: 250px;
    padding: 20px;
}

.welcome-banner {
    background: linear-gradient(135deg, #33A1E0, #0056b3);
    color: white;
    padding: 3rem 2rem;
    text-align: center;
    font-size: 2.5rem;
    font-weight: bold;
    border-radius: 10px;
    margin-bottom: 2rem;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

@media (max-width: 768px) {
    .sidebar {
        width: 100%;
        height: auto;
        position: relative;
    }

    .main-content {
        margin-left: 0;
    }
}
	</style>
	<!-- SIDEBAR -->
	<div class="sidebar bg-dark text-white p-3" style="width: 250px;">
	    <h4 class="mb-4 text-center"><i class="fas fa-user-graduate"></i> Student</h4>
	    <ul class="nav flex-column">
	        <li class="nav-item mb-2">
	            <a href="/Beams/Views/Students/StudentDashboard.php" class="nav-link text-white"><i
	                    class="fas fa-calendar-alt"></i> Dashboard</a>
	        </li>
	        <li class="nav-item mb-2">
	            <a href="/Beams/Views/Students/StudentEvents.php" class="nav-link text-white"><i
	                    class="fas fa-calendar-alt"></i> Events</a>
	        </li>
	        <li class="nav-item mb-2">
	            <a href="/Beams/Views/Students/StudentAttendance.php" class="nav-link text-white"><i
	                    class="fas fa-history"></i> Attendance History</a>
	        </li>
	        <li class="nav-item logout">
	            <a href="../../Auth/logout.php" class="nav-link text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
	        </li>
	    </ul>
	</div>