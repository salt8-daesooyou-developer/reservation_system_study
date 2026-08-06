-- 예약관리시스템 스키마
-- 참고: BRO CRM (RIZZ PILATES) 화면 구조 - 고객관리 + 예약(캘린더) 중심 MVP

CREATE DATABASE IF NOT EXISTS reservation_system_study
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE reservation_system_study;

-- 운영자(스태프) 로그인 계정
CREATE TABLE IF NOT EXISTS staff (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(50) NOT NULL,
  role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 고객
CREATE TABLE IF NOT EXISTS customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  name_kana VARCHAR(100) NULL,
  gender ENUM('male','female','unknown') NOT NULL DEFAULT 'unknown',
  birth_date DATE NULL,
  phone VARCHAR(30) NULL UNIQUE,
  email VARCHAR(100) NULL,
  password_hash VARCHAR(255) NULL,
  status ENUM('active','expired','pending','hold','unregistered') NOT NULL DEFAULT 'unregistered',
  memo TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_customers_status (status),
  INDEX idx_customers_name (name)
) ENGINE=InnoDB;

-- 예약권/멤버십 상품
CREATE TABLE IF NOT EXISTS membership_products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  type ENUM('period','count') NOT NULL DEFAULT 'period',
  valid_days INT NULL,
  session_count INT NULL,
  price DECIMAL(10,0) NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 고객이 보유한 예약권/멤버십
CREATE TABLE IF NOT EXISTS customer_memberships (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  product_id INT NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NULL,
  remaining_count INT NULL,
  status ENUM('active','expired','hold','pending') NOT NULL DEFAULT 'active',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES membership_products(id),
  INDEX idx_cm_customer (customer_id)
) ENGINE=InnoDB;

-- 지점 (프렌차이즈 내 개별 매장)
CREATE TABLE IF NOT EXISTS branches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL UNIQUE,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 수업 종류
CREATE TABLE IF NOT EXISTS classes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  category VARCHAR(50) NULL,
  capacity INT NOT NULL DEFAULT 10,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 캘린더에 배치되는 실제 수업 일정
CREATE TABLE IF NOT EXISTS schedules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  branch_id INT NOT NULL,
  class_id INT NOT NULL,
  instructor_name VARCHAR(50) NULL,
  schedule_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  capacity INT NOT NULL DEFAULT 10,
  status ENUM('scheduled','closed','cancelled') NOT NULL DEFAULT 'scheduled',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (class_id) REFERENCES classes(id),
  FOREIGN KEY (branch_id) REFERENCES branches(id),
  INDEX idx_schedules_date (schedule_date),
  INDEX idx_schedules_branch (branch_id)
) ENGINE=InnoDB;

-- 예약 / 출석 처리
CREATE TABLE IF NOT EXISTS reservations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  schedule_id INT NOT NULL,
  customer_id INT NOT NULL,
  customer_membership_id INT NULL,
  status ENUM('reserved','show','noshow','cancelled') NOT NULL DEFAULT 'reserved',
  reserved_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE CASCADE,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  FOREIGN KEY (customer_membership_id) REFERENCES customer_memberships(id),
  UNIQUE KEY uniq_schedule_customer (schedule_id, customer_id),
  INDEX idx_reservations_schedule (schedule_id)
) ENGINE=InnoDB;
