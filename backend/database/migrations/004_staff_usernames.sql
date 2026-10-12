-- Named staff accounts use first.last usernames. Existing accounts remain
-- intact and can be assigned usernames before enabling username login.
ALTER TABLE users ADD COLUMN username VARCHAR(80) NULL AFTER customer_id;
ALTER TABLE users ADD COLUMN full_name VARCHAR(160) NULL AFTER username;
ALTER TABLE users ADD UNIQUE KEY uq_users_username (username);
