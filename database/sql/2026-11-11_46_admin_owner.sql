-- =====================================================================
-- DerZ — STEP 46: THE OWNER ACCOUNT OF DERZ ADMIN (run once, after step 45)
--
-- Amir, phone 0972795880 (signs in with the phone), role Owner, password 123456.
-- Change the password right after the first sign-in: My account > Change password.
-- Re-runnable: nothing happens when an account with this phone or email exists.
-- =====================================================================
SET NAMES utf8mb4;

INSERT INTO admin_staff (name, email, phone, password, role_id, active, must_change_password, created_at)
SELECT 'Amir', 'itdtechnologieset@gmail.com', '0972795880',
       '$2y$10$78TizmwTirsI.62RVWb7OeXr.p2v2pq.9GNtl.BJnT/swhbny3G8m',
       (SELECT id FROM admin_roles WHERE is_owner = 1 ORDER BY id LIMIT 1), 1, 0, NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM admin_staff WHERE phone = '0972795880' OR email = 'itdtechnologieset@gmail.com');
