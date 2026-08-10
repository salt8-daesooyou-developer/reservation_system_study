USE reservation_system_study;

ALTER TABLE customers ADD COLUMN branch_id INT NULL COMMENT '登録した店舗（RIZZ/EN等）';
ALTER TABLE customers ADD FOREIGN KEY (branch_id) REFERENCES branches(id);
