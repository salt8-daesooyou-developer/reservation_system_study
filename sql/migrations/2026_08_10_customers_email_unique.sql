USE reservation_system_study;
ALTER TABLE customers ADD UNIQUE KEY uniq_customers_email (email);
