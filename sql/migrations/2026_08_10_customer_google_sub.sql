USE reservation_system_study;
ALTER TABLE customers ADD COLUMN google_sub VARCHAR(64) NULL UNIQUE COMMENT 'Google Sign-In の一意ID(sub)';
