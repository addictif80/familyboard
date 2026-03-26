-- Add UNIQUE constraint on endpoint so ON DUPLICATE KEY UPDATE works
ALTER TABLE push_subscriptions
  ADD CONSTRAINT uq_push_endpoint UNIQUE (user_id, endpoint(500));
