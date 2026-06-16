CREATE DATABASE IF NOT EXISTS telecom_ops;
USE telecom_ops;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100),
  email VARCHAR(100) UNIQUE,
  password VARCHAR(255),
  role ENUM('admin','agent') DEFAULT 'agent',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE sims (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sim_number VARCHAR(20) UNIQUE,
  customer_name VARCHAR(100),
  phone VARCHAR(15),
  zone VARCHAR(10),
  status ENUM('active','inactive','ported','suspended') DEFAULT 'active',
  activated_at DATE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE complaints (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sim_id INT,
  customer_name VARCHAR(100),
  zone VARCHAR(10),
  type ENUM('network','billing','sim_swap','data','other'),
  description TEXT,
  priority ENUM('low','medium','high','critical') DEFAULT 'medium',
  status ENUM('open','in_progress','resolved') DEFAULT 'open',
  assigned_to INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  resolved_at TIMESTAMP NULL,
  FOREIGN KEY (sim_id) REFERENCES sims(id),
  FOREIGN KEY (assigned_to) REFERENCES users(id)
);

CREATE TABLE tower_outages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tower_id VARCHAR(20),
  zone VARCHAR(10),
  location VARCHAR(150),
  sims_affected INT DEFAULT 0,
  started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  resolved_at TIMESTAMP NULL,
  status ENUM('active','resolved') DEFAULT 'active',
  notes TEXT
);

INSERT INTO users (name, email, password, role) VALUES
('Admin User', 'admin@teleops.in', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Support Agent', 'agent@teleops.in', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'agent');

INSERT INTO sims (sim_number, customer_name, phone, zone, status, activated_at) VALUES
('SIM001234', 'Rajesh Kumar', '9876543210', '4B', 'active', '2024-01-15'),
('SIM001235', 'Priya Sharma', '9876543211', '2A', 'active', '2024-02-10'),
('SIM001236', 'Aman Singh', '9876543212', '7C', 'suspended', '2024-03-01'),
('SIM001237', 'Sneha Patel', '9876543213', '1B', 'active', '2024-01-20'),
('SIM001238', 'Vikram Das', '9876543214', '3A', 'inactive', '2023-12-01');

INSERT INTO tower_outages (tower_id, zone, location, sims_affected, status, notes) VALUES
('T-004', '4B', 'Raipur North', 2341, 'active', 'Power failure at site'),
('T-011', '3A', 'Durg Central', 891, 'active', 'Fiber cut reported'),
('T-007', '1B', 'Bhilai', 1200, 'resolved', 'Resolved after equipment swap');

INSERT INTO complaints (sim_id, customer_name, zone, type, description, priority, status) VALUES
(1, 'Rajesh Kumar', '4B', 'network', 'No signal for 6 hours', 'critical', 'open'),
(2, 'Priya Sharma', '2A', 'data', 'Internet very slow', 'medium', 'open'),
(4, 'Sneha Patel', '1B', 'billing', 'Charged twice for recharge', 'high', 'resolved');