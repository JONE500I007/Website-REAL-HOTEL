-- =====================================================================
-- JustHottel — migration 2026-08-17 (b): review rules
--
-- Run ONCE against the live database:
--     docker compose exec -T db2 mysql --default-character-set=utf8mb4 \
--       -u root -p"<password>" "<db_name>" < migrations/2026-08-17b_review_rules.sql
--
-- Enforces "one rating per person per hotel" in the database itself, so a
-- double-submit or a hand-crafted POST cannot slip a second scoring row in
-- behind the PHP check. Hotel owners being unable to rate their own hotel
-- is enforced in hotel_detail.php (it depends on the logged-in user, which
-- SQL alone cannot see).
--
-- The three kinds of row in `reviews`:
--   review  = parent_id IS NULL AND rating IS NOT NULL   -> limited to 1
--   comment = parent_id IS NULL AND rating IS NULL       -> unlimited
--   reply   = parent_id IS NOT NULL                      -> unlimited
-- =====================================================================


-- STEP 0 — safety check. Both queries MUST return zero rows before you run
-- STEP 1; if either returns anything, decide which row to keep and delete
-- the rest, otherwise the ALTER below will fail.

-- 0a) People who somehow already have more than one rated review:
SELECT hotel_id, user_id, COUNT(*) AS rated_reviews
FROM reviews
WHERE parent_id IS NULL AND rating IS NOT NULL
GROUP BY hotel_id, user_id
HAVING rated_reviews > 1;

-- 0b) Owners who rated their own hotel before this rule existed. These are
-- not blocked by the ALTER, but you probably want them gone. To convert one
-- into a plain comment (keeping the text, dropping the score):
--     UPDATE reviews SET rating = NULL WHERE id = <that id>;
SELECT r.id, r.hotel_id, h.hotel_name, r.user_id, r.rating
FROM reviews r
JOIN hotels h ON h.id = r.hotel_id
WHERE r.parent_id IS NULL AND r.rating IS NOT NULL AND h.owner_id = r.user_id;


-- STEP 1 — the constraint.
--
-- A UNIQUE index ignores rows whose indexed columns contain NULL, so the
-- generated column is 1 only for rated top-level reviews and NULL for
-- everything else. That makes (hotel_id, user_id) unique for reviews while
-- leaving comments and replies completely unrestricted.
--
-- Re-running this errors with "Duplicate column name" / "Duplicate key
-- name" — that just means it is already applied, and is safe to ignore.
ALTER TABLE `reviews`
  ADD COLUMN `is_rated_review` TINYINT(1)
    GENERATED ALWAYS AS (CASE WHEN `parent_id` IS NULL AND `rating` IS NOT NULL THEN 1 ELSE NULL END) STORED,
  ADD UNIQUE KEY `idx_one_review_per_user_hotel` (`hotel_id`, `user_id`, `is_rated_review`);


-- STEP 2 — verify. Expect: 'review' rows unique per (hotel_id, user_id),
-- 'comment' and 'reply' rows free to repeat.
SELECT CASE
         WHEN parent_id IS NOT NULL THEN 'reply'
         WHEN rating IS NULL        THEN 'comment'
         ELSE 'review'
       END AS kind,
       COUNT(*) AS rows_now
FROM reviews
GROUP BY kind;
