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

-- 지점 (프렌차이즈 내 개별 매장)
CREATE TABLE IF NOT EXISTS branches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL UNIQUE,
  brand ENUM('RIZZ','EN') NOT NULL DEFAULT 'RIZZ',
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
  email VARCHAR(100) NULL UNIQUE,
  password_hash VARCHAR(255) NULL,
  status ENUM('active','expired','pending','hold','unregistered') NOT NULL DEFAULT 'unregistered',
  memo TEXT NULL,
  branch_id INT NULL COMMENT '登録した店舗（RIZZ/EN等）',
  stripe_customer_id VARCHAR(100) NULL,
  google_sub VARCHAR(64) NULL UNIQUE COMMENT 'Google Sign-In の一意ID(sub)',
  mileage_points INT NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (branch_id) REFERENCES branches(id),
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
  stripe_price_id VARCHAR(100) NULL COMMENT 'Stripe recurring Price ID (月額課金用)',
  brand ENUM('RIZZ','EN') NULL COMMENT 'ブランド別月額プラン用（NULLは共通商品）',
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

-- Stripe 정기결제(구독) 상태
CREATE TABLE IF NOT EXISTS stripe_subscriptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  product_id INT NOT NULL,
  stripe_checkout_session_id VARCHAR(120) NULL,
  stripe_subscription_id VARCHAR(120) NULL UNIQUE,
  status ENUM('pending','active','past_due','canceled','incomplete') NOT NULL DEFAULT 'pending',
  current_period_end DATETIME NULL,
  customer_membership_id INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES membership_products(id),
  FOREIGN KEY (customer_membership_id) REFERENCES customer_memberships(id),
  INDEX idx_stripe_sub_customer (customer_id)
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

-- 지점별 휴일
CREATE TABLE IF NOT EXISTS branch_holidays (
  id INT AUTO_INCREMENT PRIMARY KEY,
  branch_id INT NOT NULL,
  holiday_date DATE NOT NULL,
  memo VARCHAR(200) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_branch_holiday (branch_id, holiday_date),
  INDEX idx_branch_holiday_date (holiday_date)
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

-- 顧客からのお問い合わせ
CREATE TABLE IF NOT EXISTS inquiries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  subject VARCHAR(150) NOT NULL,
  message TEXT NOT NULL,
  status ENUM('new','replied','closed') NOT NULL DEFAULT 'new',
  email_sent TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  INDEX idx_inquiries_status (status)
) ENGINE=InnoDB;

-- 顧客ダッシュボード：決済明細（売上・返金）
CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  customer_membership_id INT NULL,
  product_id INT NULL,
  type ENUM('sale','refund') NOT NULL DEFAULT 'sale',
  amount DECIMAL(10,0) NOT NULL DEFAULT 0,
  method ENUM('manual','stripe') NOT NULL DEFAULT 'manual',
  memo VARCHAR(200) NULL,
  created_by INT NULL COMMENT 'staff.id、NULLはシステム/Stripe自動登録',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  FOREIGN KEY (customer_membership_id) REFERENCES customer_memberships(id),
  FOREIGN KEY (product_id) REFERENCES membership_products(id),
  FOREIGN KEY (created_by) REFERENCES staff(id),
  INDEX idx_payments_customer (customer_id)
) ENGINE=InnoDB;

-- 顧客ダッシュボード：ホールド・延長・譲渡の履歴
CREATE TABLE IF NOT EXISTS membership_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_membership_id INT NOT NULL,
  type ENUM('hold','extend','transfer') NOT NULL,
  detail VARCHAR(255) NULL COMMENT '期間・日数・譲渡先などの要約',
  from_customer_id INT NULL,
  to_customer_id INT NULL,
  created_by INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_membership_id) REFERENCES customer_memberships(id) ON DELETE CASCADE,
  FOREIGN KEY (from_customer_id) REFERENCES customers(id),
  FOREIGN KEY (to_customer_id) REFERENCES customers(id),
  FOREIGN KEY (created_by) REFERENCES staff(id),
  INDEX idx_membership_events_membership (customer_membership_id)
) ENGINE=InnoDB;

-- 顧客ダッシュボード：契約書
CREATE TABLE IF NOT EXISTS contracts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  customer_membership_id INT NULL,
  title VARCHAR(150) NOT NULL,
  contract_date DATE NOT NULL,
  file_path VARCHAR(255) NULL,
  memo TEXT NULL,
  created_by INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  FOREIGN KEY (customer_membership_id) REFERENCES customer_memberships(id),
  FOREIGN KEY (created_by) REFERENCES staff(id),
  INDEX idx_contracts_customer (customer_id)
) ENGINE=InnoDB;

-- 顧客ダッシュボード：クーポン
CREATE TABLE IF NOT EXISTS coupons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  discount_amount DECIMAL(10,0) NOT NULL DEFAULT 0,
  valid_until DATE NULL,
  used_at DATETIME NULL,
  created_by INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES staff(id),
  INDEX idx_coupons_customer (customer_id)
) ENGINE=InnoDB;

-- 顧客ダッシュボード：マイレージ増減履歴
CREATE TABLE IF NOT EXISTS mileage_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  points INT NOT NULL COMMENT '+積立 / -使用',
  reason VARCHAR(150) NULL,
  created_by INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES staff(id),
  INDEX idx_mileage_logs_customer (customer_id)
) ENGINE=InnoDB;

-- 顧客ダッシュボード：監査ログ（スタッフ専用閲覧）
CREATE TABLE IF NOT EXISTS activity_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  service_type VARCHAR(30) NOT NULL COMMENT 'CRM/カレンダー/決済 等',
  action_type VARCHAR(50) NOT NULL,
  description VARCHAR(255) NOT NULL,
  created_by INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES staff(id),
  INDEX idx_activity_logs_customer (customer_id)
) ENGINE=InnoDB;

-- 顧客ダッシュボード：特記事項・担当者メモ（複数件、スタッフ専用閲覧）
CREATE TABLE IF NOT EXISTS customer_notes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  body TEXT NOT NULL,
  created_by INT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES staff(id),
  INDEX idx_customer_notes_customer (customer_id)
) ENGINE=InnoDB;
