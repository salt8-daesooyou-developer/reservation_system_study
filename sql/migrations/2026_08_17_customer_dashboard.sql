USE reservation_system_study;

-- 결제내역
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

-- 홀딩・연장・양도 이력
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

-- 계약서
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

-- 쿠폰
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

-- 마일리지
ALTER TABLE customers ADD COLUMN mileage_points INT NOT NULL DEFAULT 0;

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

-- 로그기록（監査ログ、スタッフ専用閲覧）
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

-- 특이사항・메모（複数件の時系列担当者メモ、スタッフ専用閲覧）
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
