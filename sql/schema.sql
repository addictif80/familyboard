-- FamilyBoard Database Schema
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Families
CREATE TABLE IF NOT EXISTS families (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    invite_code VARCHAR(20) UNIQUE NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Users
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    family_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','member') DEFAULT 'member',
    avatar VARCHAR(255) DEFAULT NULL,
    color VARCHAR(7) DEFAULT '#4A90D9',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE
);

-- SMTP Settings
CREATE TABLE IF NOT EXISTS smtp_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    family_id INT NOT NULL UNIQUE,
    host VARCHAR(255) NOT NULL,
    port INT DEFAULT 587,
    username VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    from_email VARCHAR(255) NOT NULL,
    from_name VARCHAR(100) NOT NULL,
    encryption ENUM('tls','ssl','none') DEFAULT 'tls',
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE
);

-- Calendar Events
CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    family_id INT NOT NULL,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    is_all_day TINYINT(1) DEFAULT 0,
    color VARCHAR(7) DEFAULT '#4A90D9',
    recurrence VARCHAR(50) DEFAULT NULL,
    recurrence_end DATE DEFAULT NULL,
    caldav_uid VARCHAR(255) DEFAULT NULL,
    caldav_source_id INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- CalDAV Sources
CREATE TABLE IF NOT EXISTS caldav_sources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    family_id INT NOT NULL,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    url VARCHAR(500) NOT NULL,
    username VARCHAR(255) DEFAULT NULL,
    password VARCHAR(255) DEFAULT NULL,
    color VARCHAR(7) DEFAULT '#4A90D9',
    last_sync DATETIME DEFAULT NULL,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Wall Posts
CREATE TABLE IF NOT EXISTS posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    family_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT DEFAULT NULL,
    image_path VARCHAR(500) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Post Comments
CREATE TABLE IF NOT EXISTS post_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Post Reactions
CREATE TABLE IF NOT EXISTS post_reactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    emoji VARCHAR(10) DEFAULT '❤️',
    UNIQUE KEY unique_reaction (post_id, user_id),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Task Lists
CREATE TABLE IF NOT EXISTS task_lists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    family_id INT NOT NULL,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    type ENUM('tasks','shopping') DEFAULT 'tasks',
    color VARCHAR(7) DEFAULT '#4A90D9',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tasks
CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    list_id INT NOT NULL,
    user_id INT NOT NULL,
    assigned_to INT DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    notes TEXT DEFAULT NULL,
    is_completed TINYINT(1) DEFAULT 0,
    due_date DATE DEFAULT NULL,
    priority ENUM('low','medium','high') DEFAULT 'medium',
    completed_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (list_id) REFERENCES task_lists(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
);

-- Chat Messages
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    family_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Budget Categories
CREATE TABLE IF NOT EXISTS budget_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    family_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(7) DEFAULT '#4A90D9',
    icon VARCHAR(50) DEFAULT '💰',
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE
);

-- Budget Transactions
CREATE TABLE IF NOT EXISTS budget_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    family_id INT NOT NULL,
    user_id INT NOT NULL,
    category_id INT DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    type ENUM('income','expense') NOT NULL,
    date DATE NOT NULL,
    notes TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES budget_categories(id) ON DELETE SET NULL
);

-- Budget Goals
CREATE TABLE IF NOT EXISTS budget_goals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    family_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    target_amount DECIMAL(10,2) NOT NULL,
    current_amount DECIMAL(10,2) DEFAULT 0,
    deadline DATE DEFAULT NULL,
    color VARCHAR(7) DEFAULT '#4A90D9',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE
);

-- Custody Schedules
CREATE TABLE IF NOT EXISTS custody_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    family_id INT NOT NULL,
    child_name VARCHAR(100) NOT NULL,
    color VARCHAR(7) DEFAULT '#E67E22',
    notes TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE
);

-- Custody Events (who has the child on which dates)
CREATE TABLE IF NOT EXISTS custody_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT NOT NULL,
    parent_user_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    arrival_time TIME DEFAULT NULL,
    departure_time TIME DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    FOREIGN KEY (schedule_id) REFERENCES custody_schedules(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Projects
CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    family_id INT NOT NULL,
    user_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT DEFAULT NULL,
    status ENUM('active','completed','archived') DEFAULT 'active',
    budget DECIMAL(10,2) DEFAULT NULL,
    deadline DATE DEFAULT NULL,
    color VARCHAR(7) DEFAULT '#4A90D9',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Project Tasks
CREATE TABLE IF NOT EXISTS project_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    user_id INT NOT NULL,
    assigned_to INT DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    status ENUM('todo','in_progress','done') DEFAULT 'todo',
    priority ENUM('low','medium','high') DEFAULT 'medium',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
);

-- Project Expenses
CREATE TABLE IF NOT EXISTS project_expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    date DATE NOT NULL,
    notes TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(500) DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

SET FOREIGN_KEY_CHECKS = 1;
