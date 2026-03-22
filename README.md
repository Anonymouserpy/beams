# BITS Event Attendance Management System 

BEAMS (BITS Event Attendance Management System) is a web-based system built using PHP that helps manage student attendance during events. It allows officers to track attendance, monitor absences, and automatically apply fines for missed log-in or log-out times.

## System Overview
The system simulates multiple terminals:
- Index.php: You choose where you login either student or officer
- officer login: Where the officer login their account
- officer Dashboard: Can see the total students enroll, Events organized, attendance record, total unpaid fines, and the upcomming events also fine collection status
- Sidebar: A Menu where you can see the Dashboard, Students, Events, Create Events, Register Officer and Manage Fines
- Events: Total Events, upcomming events, Total Attendance, and Past Attendance also you can see all Events and edit and view it
- Create Events: Where you create events
- Register Officer: Where you register the new officer
- Manage Fines: See all the students with fines and you can pay htere and see the total fines, total amount, Unpaid fines and unpaid amounts
- Student Login: Where a Student login their account
- Student Sidebar: You can see the Dashboard, Events, Attendance, Fines, and My Profile 
- Student Dashboard: You can see your information, unpaid amount, total fines event attended and upcomming events and recent attendance and also attendance history
- Events: Can see the upcomming events, and past events
- Attendance: Total events, Attended and Missed and your attendance history
- Fines: See the total fines, unpaid fines, total unpaid amount and Paid
- My Profile: Where you can see all your information and also edit you password
- Logout: To end you session

## Technology Stack
- Front-End: HTML5, CSS, Bootstrap, JavaScript, AJAX
- Back-End: PHP
- Database: MySQL
- Real-Time Updates: Ratchet WebSocket Server
- Version Control: GitHub

## Setup Instructions
1. Fork or clone this repository
2. Import the SQL file (beams.sql) to MySQL database
3. Change Directory to Connection
4. Run WebSocket server: `php websocket_server.php`
5. Access system via browser using `http://localhost/beams/`

## 📂 Project Structure
```
/beams
│── /Auth
│   │── login_officer.php
│   │── login_student.php
│   │── logout.php
│   │── officer_register.php
│   │── student_register.php
│
│── /Connection
│   │── connection.php
│
│── /Includes
│   │── websocket_server.php
│   │── generate_fines_cron.php
│   │── Footer.php
│   │── Header.php
│
│── /Views
│   │── /officerpage
│   │   │── create_event.php
│   │   │── get_event_details.php
│   │   │── manage_event.php
│   │   │── manage_fines.php
│   │   │── manage_student.php
│   │   │── officer_dashboard.php
│   │   │── officer_register.php
│   │
│   │── /sidebar
│   │   │── officer_sidebar.php
│   │   │── student_sidebar.php
│   │
│   │── /studentpage
│   │   │── student_attendance.php
│   │   │── student_dashboard.php
│   │   │── student_event.php
│   │   │── student_fines.php
│   │   │── student_profile.php
│
│── last_fine_run.txt
│── composer.json
│── composer.lock
│── index.php
│── officer_login.php
│── registration.php
│── student_login.php
```

## 🤝 Contributing
Contributions are welcome! You can fork this repository and submit a pull request.

## License
Educational use only. Not for commercial deployment.

## 👨‍💻 Author
Jomarie M. Alcaria
Dave Nier M. Alaya-ay
James J. Diongzon
Fritchie Anne V. Ermina
Velee Ensoy
TG Grahambelle Gomez
Kearlstinne Annrae L. Rufin 
BSIT Student
