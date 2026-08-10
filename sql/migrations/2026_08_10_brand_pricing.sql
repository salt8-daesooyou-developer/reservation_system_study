USE reservation_system_study;

ALTER TABLE branches ADD COLUMN brand ENUM('RIZZ','EN') NOT NULL DEFAULT 'RIZZ';
ALTER TABLE membership_products ADD COLUMN brand ENUM('RIZZ','EN') NULL COMMENT 'ブランド別月額プラン用（NULLは共通商品）';

-- 既存店舗はすべて RIZZ ブランド
UPDATE branches SET brand = 'RIZZ';

-- EN ブランドの店舗（仮の店舗名。実際の店舗名は管理画面の「+ 店舗を追加」から変更・追加してください）
INSERT INTO branches (name, brand) VALUES ('EN PILATES', 'EN')
  ON DUPLICATE KEY UPDATE brand = VALUES(brand);

-- ブランド別 月額プラン
INSERT INTO membership_products (name, type, valid_days, price, brand) VALUES
  ('RIZZ 月額会員', 'period', 30, 11000, 'RIZZ'),
  ('EN 月額会員', 'period', 30, 22000, 'EN')
ON DUPLICATE KEY UPDATE price = VALUES(price), brand = VALUES(brand);
