-- Create database schema for AUPWU Management System

CREATE DATABASE IF NOT EXISTS aupwu_db;
USE aupwu_db;

-- Users table for authentication
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    role ENUM('admin', 'member', 'officer') NOT NULL DEFAULT 'member',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default admin account
INSERT INTO users (username, password, email, role) 
VALUES ('admin', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'admin@aupwu.org', 'admin');
-- Default password is 'secret'

-- Member profiles table with detailed information
CREATE TABLE members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    address TEXT NOT NULL,
    unit_college VARCHAR(100) NOT NULL,
    designation VARCHAR(100) NOT NULL,
    chapter VARCHAR(100) NOT NULL,
    date_of_appointment DATE NOT NULL,
    date_of_birth DATE NOT NULL,
    contact_number VARCHAR(20) NOT NULL,
    email VARCHAR(100) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    up_status ENUM('in', 'out') NOT NULL DEFAULT 'in',
    photo_path VARCHAR(255),
    signature_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Committees table
CREATE TABLE committees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default committees
INSERT INTO committees (name, description) VALUES
('Human Resource Merit Promotion & Selection Board', 'Handles promotions and merit-based selections'),
('Human Resource Committee', 'Manages HR policies and practices'),
('Janitorial Inspection Monitoring Team Committee', 'Oversees janitorial services and quality'),
('Security Committee', 'Handles security matters and policies'),
('Sports and Development Committee', 'Organizes sports events and related activities'),
('Search Committee', 'Responsible for searching qualified candidates for positions'),
('Bids and Awards Committee', 'Handles procurement and bidding processes'),
('Gender and Development Committee', 'Promotes gender equality and inclusivity'),
('Cultural Committee', 'Organizes cultural events and activities'),
('Finance Committee', 'Manages financial affairs and budgeting'),
('Education and Training Committee', 'Handles educational programs and training'),
('Health and Wellness Committee', 'Promotes health and wellness initiatives'),
('Environmental Committee', 'Handles environmental concerns and programs'),
('Grievance Committee', 'Addresses complaints and grievances'),
('Other', 'For committees not specified in the list');

-- Member Committee assignments
CREATE TABLE member_committees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    committee_id INT NOT NULL,
    position VARCHAR(100),
    start_date DATE NOT NULL,
    end_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    FOREIGN KEY (committee_id) REFERENCES committees(id) ON DELETE CASCADE
);

-- Elections table
CREATE TABLE elections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    is_active BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Positions to be voted for
CREATE TABLE positions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    election_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    max_winners INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE
);

-- Candidates running for positions
CREATE TABLE candidates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    election_id INT NOT NULL,
    position_id INT NOT NULL,
    member_id INT NOT NULL,
    platform TEXT,
    is_approved BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
    FOREIGN KEY (position_id) REFERENCES positions(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
);

-- Votes cast by members
CREATE TABLE votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    election_id INT NOT NULL,
    position_id INT NOT NULL,
    candidate_id INT NOT NULL,
    voter_id INT NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
    FOREIGN KEY (position_id) REFERENCES positions(id) ON DELETE CASCADE,
    FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
    FOREIGN KEY (voter_id) REFERENCES members(id) ON DELETE CASCADE,
    UNIQUE KEY unique_vote (election_id, position_id, voter_id)
);

-- Activity logs for audit trail
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
