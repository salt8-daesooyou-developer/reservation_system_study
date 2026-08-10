USE reservation_system_study;

ALTER TABLE customers ADD COLUMN stripe_customer_id VARCHAR(100) NULL;
ALTER TABLE membership_products ADD COLUMN stripe_price_id VARCHAR(100) NULL COMMENT 'Stripe recurring Price ID (月額課金用)';

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
