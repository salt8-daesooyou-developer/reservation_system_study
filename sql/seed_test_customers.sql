USE reservation_system_study;

INSERT INTO customers (name, name_kana, gender, birth_date, phone, status) VALUES
  ('田中 美咲', 'たなか みさき', 'female', '1990-03-14', '090-1111-2001', 'active'),
  ('佐藤 健太', 'さとう けんた', 'male', '1988-11-02', '090-1111-2002', 'active'),
  ('鈴木 あすか', 'すずき あすか', 'female', '1997-02-08', '090-1111-2003', 'unregistered'),
  ('高橋 陽子', 'たかはし ようこ', 'female', '1995-06-21', '090-1111-2004', 'active'),
  ('伊藤 大輔', 'いとう だいすけ', 'male', '1985-09-09', '090-1111-2005', 'expired'),
  ('渡辺 沙織', 'わたなべ さおり', 'female', '1992-01-30', '090-1111-2006', 'active'),
  ('山本 拓也', 'やまもと たくや', 'male', '1993-07-17', '090-1111-2007', 'hold'),
  ('中島 恵美', 'なかじま えみ', 'female', '1998-12-05', '090-1111-2008', 'active'),
  ('小林 誠', 'こばやし まこと', 'male', '1980-04-25', '090-1111-2009', 'expired'),
  ('加藤 玲子', 'かとう れいこ', 'female', '1989-03-03', '090-1111-2010', 'unregistered'),
  ('吉田 舞', 'よしだ まい', 'female', '1996-08-08', '090-1111-2011', 'pending'),
  ('松本 亮', 'まつもと りょう', 'male', '1991-02-19', '090-1111-2012', 'active'),
  ('井上 由美子', 'いのうえ ゆみこ', 'female', '1987-10-11', '090-1111-2013', 'active'),
  ('木村 翔太', 'きむら しょうた', 'male', '1994-05-03', '090-1111-2014', 'hold'),
  ('斎藤 直樹', 'さいとう なおき', 'male', '1999-01-22', '090-1111-2015', 'unregistered')
ON DUPLICATE KEY UPDATE status = VALUES(status);

-- 顧客ログイン検証用パスワード (平文: test1234!)
UPDATE customers SET password_hash = '$2y$10$Fu8EdGSw5BWe9g/luOwM0OyUTepULOqHQDTTMA9sxKU0C0oG9C7gC'
  WHERE phone IN ('090-1111-2001', '090-1111-2002');

-- メールログイン検証用テストアカウント (ログインID: daesoo.you@salteight.com / 平文: test1234!)
UPDATE customers SET email = 'daesoo.you@salteight.com'
  WHERE phone = '090-1111-2001';

-- 登録店舗（検証用に本町店を割り当て）
UPDATE customers SET branch_id = (SELECT id FROM (SELECT id FROM branches ORDER BY id LIMIT 1) AS b)
  WHERE phone IN ('090-1111-2001', '090-1111-2002');
