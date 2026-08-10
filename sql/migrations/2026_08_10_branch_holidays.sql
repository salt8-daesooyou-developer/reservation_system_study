USE reservation_system_study;

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
