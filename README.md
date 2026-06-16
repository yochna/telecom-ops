📡 TeleOps — Telecom Operations Dashboard
A full-stack PHP-based internal operations dashboard for telecom companies, designed to track SIM lifecycle, customer complaints, tower outages, and auto-identify high-churn risk zones in real-time.
Live Demo: telecomops.free.nf
🚀 Features
Role-Based Access Control
Admin — Full access: manage SIMs, log/resolve outages, view all analytics
Support Agent — Restricted access: log complaints, view data, cannot modify outages or SIM status
SIM Lifecycle Management
Activate, deactivate, suspend, or port SIM cards
Real-time status tracking with zone-based filtering
Full inventory with customer details and activation dates
Intelligent Complaint System
Auto-priority scoring based on complaint type:
SIM swap → Critical (fraud risk)
Network → High (service impact)
Billing → Medium
Data → Low
Sort by priority with resolution tracking
SLA monitoring with average resolution time
Tower Outage Logger
Log active outages with affected SIM count
Real-time status updates (active/resolved)
Zone-based impact analysis
Live Dashboard with KPIs
Active SIMs count
Open complaints (with critical alerts)
Active tower outages
Average resolution time vs SLA target
Churn Risk Zone Algorithm — cross-references outages + complaints + inactive SIMs to flag high-risk areas
SIM health visualization by zone
Complaint distribution analytics
🛠 Tech Stack
Table
Layer	Technology
Backend	PHP 8.x, PDO
Database	MySQL
Frontend	HTML5, CSS3 (Grid/Flexbox)
Security	Prepared statements, session-based auth
Hosting	InfinityFree
📁 Project Structure
plain
telecom-ops/
├── index.php          # Login page with role-based redirect
├── dashboard.php      # Main dashboard with KPIs & analytics
├── sims.php           # SIM inventory management
├── complaints.php     # Complaint ticketing with auto-priority
├── outages.php        # Tower outage logger
├── logout.php         # Session destroy
├── db.php             # Database connection (PDO)
├── auth.php           # Session guard middleware
└── telecom.sql        # Database schema & seed data
🔐 Security Highlights
100% prepared statements — zero SQL injection risk
Password hashing with password_hash() / password_verify()
Session-based authentication with role validation
Input sanitization with htmlspecialchars()
🎯 Demo Credentials
Table
Role	Email	Password
Admin	admin@teleops.in	password
Agent	agent@teleops.in	password
🚀 Local Setup (XAMPP)
Install XAMPP
Clone repo to C:\xampp\htdocs\telecom-ops\
Import telecom.sql via phpMyAdmin
Start Apache + MySQL
Open http://localhost/telecom-ops/
📊 Database Schema
sql
users         — id, name, email, password, role
sims          — id, sim_number, customer_name, phone, zone, status, activated_at
complaints    — id, sim_id, customer_name, zone, type, priority, status, assigned_to
tower_outages — id, tower_id, zone, location, sims_affected, status, started_at
💡 Why This Project?
This mirrors real-world telecom backend systems (Airtel, Jio, Vi) that operations teams use daily. It demonstrates:
Production-grade PHP architecture
Database normalization & foreign key relationships
Business logic automation (priority scoring, churn risk)
Security-first development practices
Clean UI/UX for data-heavy applications
📜 License
Open source — built for educational and portfolio purposes.
Built with 💙 for telecom operations simulation
