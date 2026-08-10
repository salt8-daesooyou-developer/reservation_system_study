USE reservation_system_study;

-- 로그인 검증용 테스트 계정 10개 추가 (비밀번호: test1234!, 동일 해시 재사용)
UPDATE customers SET email = 'test02@salteight.com', branch_id = 1  WHERE phone = '090-1111-2002';
UPDATE customers SET email = 'test03@salteight.com', branch_id = 2  WHERE phone = '090-1111-2003';
UPDATE customers SET email = 'test04@salteight.com', branch_id = 3  WHERE phone = '090-1111-2004';
UPDATE customers SET email = 'test05@salteight.com', branch_id = 4  WHERE phone = '090-1111-2005';
UPDATE customers SET email = 'test06@salteight.com', branch_id = 10 WHERE phone = '090-1111-2006';
UPDATE customers SET email = 'test07@salteight.com', branch_id = 1  WHERE phone = '090-1111-2007';
UPDATE customers SET email = 'test08@salteight.com', branch_id = 2  WHERE phone = '090-1111-2008';
UPDATE customers SET email = 'test09@salteight.com', branch_id = 10 WHERE phone = '090-1111-2009';
UPDATE customers SET email = 'test10@salteight.com', branch_id = 3  WHERE phone = '090-1111-2010';
UPDATE customers SET email = 'test11@salteight.com', branch_id = 4  WHERE phone = '090-1111-2011';

UPDATE customers SET password_hash = '$2y$10$Fu8EdGSw5BWe9g/luOwM0OyUTepULOqHQDTTMA9sxKU0C0oG9C7gC'
  WHERE phone IN (
    '090-1111-2002','090-1111-2003','090-1111-2004','090-1111-2005','090-1111-2006',
    '090-1111-2007','090-1111-2008','090-1111-2009','090-1111-2010','090-1111-2011'
  );
