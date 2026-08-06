USE reservation_system_study;

-- 지점
INSERT INTO branches (name) VALUES
  ('RIZZ PILATES 本町'),
  ('RIZZ PILATES 南森町'),
  ('RIZZ PILATES 谷町四丁目'),
  ('RIZZ PILATES 鶴橋')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- 관리자 계정 (id: admin / pw: admin123)
INSERT INTO staff (username, password_hash, name, role) VALUES
  ('admin', '$2y$10$2yns6XMseisctD.9ueXPI.vhhtJsvCV7GkTyDvRCYtXXakARhIsym', '管理者', 'admin')
ON DUPLICATE KEY UPDATE name = VALUES(name), role = VALUES(role);

-- 예약권 상품
INSERT INTO membership_products (name, type, valid_days, session_count, price) VALUES
  ('1ヶ月 フリーパス', 'period', 30, NULL, 15000),
  ('10回 回数券', 'count', NULL, 10, 80000),
  ('20回 回数券', 'count', NULL, 20, 150000)
ON DUPLICATE KEY UPDATE price = VALUES(price);

-- 수업 종류
INSERT INTO classes (name, category, capacity) VALUES
  ('BASIC', 'GROUP', 10),
  ('LOWER BODY', 'GROUP', 10),
  ('UPPER BODY', 'GROUP', 10)
ON DUPLICATE KEY UPDATE capacity = VALUES(capacity);

-- 샘플 고객 (동작 확인용)
INSERT INTO customers (name, name_kana, gender, birth_date, phone, status) VALUES
  ('林 佳慧', 'はやし よしえ', 'female', '1997-07-26', '090-1289-6385', 'active'),
  ('岡田 和恵', 'おかだ かずえ', 'female', '1988-08-11', '090-6969-1881', 'active'),
  ('中村 百那', 'なかむら もな', 'female', '2005-10-02', '080-2898-7525', 'expired'),
  ('山中 望', 'やまなか のぞみ', 'female', '1994-08-16', '090-6282-0261', 'hold'),
  ('渡邊 奈々', NULL, 'male', NULL, '080-4983-8553', 'unregistered')
ON DUPLICATE KEY UPDATE status = VALUES(status);
