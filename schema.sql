-- Clean up target environments if active
DROP TABLE IF EXISTS user_transactions CASCADE;

-- Create production ledger architecture
CREATE TABLE user_transactions (
    transaction_id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    amount NUMERIC(10, 2) NOT NULL,
    purchase_date TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Compound index specifically structured for fast user scoping + date bounds matching
CREATE INDEX idx_user_transactions_user_date 
ON user_transactions(user_id, purchase_date);

-- Inject Mock records targeting specific application variables (User: 42)
INSERT INTO user_transactions (user_id, item_name, amount, purchase_date) VALUES
(42, 'Premium Subscription Plan', 49.99, '2026-11-01 10:30:00+00'),
(42, 'Enterprise Cloud Storage Add-on', 15.00, '2026-11-03 14:15:00+00'),
(42, 'Premium Subscription Plan', 49.99, '2026-11-05 09:00:00+00'),
(42, 'Advanced API Access Token', 120.00, '2026-11-08 16:45:00+00'),
(42, 'Developer Hardware Toolkit Upgrade', 350.00, '2026-11-09 11:20:00+00'),
(99, 'Basic Tier Subscription', 19.99, '2026-10-15 08:00:00+00'),
(99, 'Extra User Seat License Allocation', 5.00, '2026-10-20 11:22:00+00');
