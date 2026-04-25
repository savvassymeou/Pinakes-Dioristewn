USE pinakes_dioristewn;

SET FOREIGN_KEY_CHECKS = 0;
DELETE FROM tracked_candidates;
DELETE FROM candidate_notification_settings;
DELETE FROM password_reset_tokens;
DELETE FROM candidate_profiles;
DELETE FROM user_profiles;
DELETE FROM users;
DELETE FROM specialties;
ALTER TABLE tracked_candidates AUTO_INCREMENT = 1;
ALTER TABLE candidate_notification_settings AUTO_INCREMENT = 1;
ALTER TABLE password_reset_tokens AUTO_INCREMENT = 1;
ALTER TABLE candidate_profiles AUTO_INCREMENT = 1;
ALTER TABLE user_profiles AUTO_INCREMENT = 1;
ALTER TABLE users AUTO_INCREMENT = 1;
ALTER TABLE specialties AUTO_INCREMENT = 1;
SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO specialties (id, title, description) VALUES
(1, 'ΠΕ70 Δάσκαλοι', 'Ειδικότητα πρωτοβάθμιας εκπαίδευσης για δασκάλους δημοτικού.'),
(2, 'ΠΕ60 Νηπιαγωγοί', 'Ειδικότητα προσχολικής εκπαίδευσης για νηπιαγωγούς.'),
(3, 'ΠΕ02 Φιλόλογοι', 'Ειδικότητα δευτεροβάθμιας εκπαίδευσης για φιλολόγους.'),
(4, 'ΠΕ03 Μαθηματικοί', 'Ειδικότητα δευτεροβάθμιας εκπαίδευσης για μαθηματικούς.'),
(5, 'ΠΕ86 Πληροφορικής', 'Ειδικότητα πληροφορικής για πρωτοβάθμια και δευτεροβάθμια εκπαίδευση.'),
(6, 'ΠΕ04.01 Φυσικοί', 'Ειδικότητα φυσικών επιστημών για τη δευτεροβάθμια εκπαίδευση.');

INSERT INTO users (id, username, email, password_hash, role, created_at) VALUES
(1, 'admin', 'admin@example.com', '$2y$10$hNoyHARaMY0DwwZ8f2NQ.elY9x8b3NUPm0GWd7Hiz58HIwfjUDBQi', 'admin', '2024-01-05 09:00:00'),
(2, 'maria', 'maria@example.com', '$2y$10$hNoyHARaMY0DwwZ8f2NQ.elY9x8b3NUPm0GWd7Hiz58HIwfjUDBQi', 'candidate', '2024-02-10 10:00:00'),
(3, 'giorgos', 'giorgos@example.com', '$2y$10$hNoyHARaMY0DwwZ8f2NQ.elY9x8b3NUPm0GWd7Hiz58HIwfjUDBQi', 'candidate', '2024-05-18 09:30:00'),
(4, 'eleni', 'eleni@example.com', '$2y$10$hNoyHARaMY0DwwZ8f2NQ.elY9x8b3NUPm0GWd7Hiz58HIwfjUDBQi', 'candidate', '2025-01-08 11:45:00'),
(5, 'petros', 'petros@example.com', '$2y$10$hNoyHARaMY0DwwZ8f2NQ.elY9x8b3NUPm0GWd7Hiz58HIwfjUDBQi', 'candidate', '2025-03-20 08:15:00'),
(6, 'sofia', 'sofia@example.com', '$2y$10$hNoyHARaMY0DwwZ8f2NQ.elY9x8b3NUPm0GWd7Hiz58HIwfjUDBQi', 'candidate', '2025-09-12 14:20:00'),
(7, 'nikos', 'nikos@example.com', '$2y$10$hNoyHARaMY0DwwZ8f2NQ.elY9x8b3NUPm0GWd7Hiz58HIwfjUDBQi', 'candidate', '2026-01-22 12:10:00'),
(8, 'anna', 'anna@example.com', '$2y$10$hNoyHARaMY0DwwZ8f2NQ.elY9x8b3NUPm0GWd7Hiz58HIwfjUDBQi', 'candidate', '2026-02-14 16:35:00');

INSERT INTO user_profiles (user_id, first_name, last_name, identity_number, phone) VALUES
(1, 'Admin', 'User', 'AD123456', '2100000000'),
(2, 'Μαρία', 'Παπαδοπούλου', 'ΑΑ123456', '6912345678'),
(3, 'Γιώργος', 'Ιωάννου', 'ΒΒ234567', '6923456789'),
(4, 'Ελένη', 'Νικολάου', 'ΓΓ345678', '6934567890'),
(5, 'Πέτρος', 'Σταύρου', 'ΔΔ456789', '6945678901'),
(6, 'Σοφία', 'Μανιάτη', 'ΕΕ567890', '6956789012'),
(7, 'Νίκος', 'Αθανασίου', 'ΖΖ678901', '6967890123'),
(8, 'Άννα', 'Κωνσταντίνου', 'ΗΗ789012', '6978901234');

INSERT INTO candidate_profiles
    (user_id, father_name, mother_name, birth_date, specialty_id, application_status, ranking_position, points, created_at)
VALUES
(2, 'Νικόλαος', 'Ελένη', '1992-05-14', 1, 'Σε προσωρινό πίνακα', 12, 84.50, '2024-02-10 10:00:00'),
(3, 'Ανδρέας', 'Σοφία', '1990-11-02', 2, 'Σε προσωρινό πίνακα', 7, 88.00, '2024-05-18 09:30:00'),
(4, 'Δημήτριος', 'Άννα', '1994-03-21', 3, 'Υπό έλεγχο δικαιολογητικών', 19, 79.25, '2025-01-08 11:45:00'),
(5, 'Σπύρος', 'Κατερίνα', '1989-09-09', 4, 'Σε οριστικό πίνακα', 4, 91.10, '2025-03-20 08:15:00'),
(6, 'Μιχάλης', 'Γεωργία', '1996-07-30', 5, 'Νέα εγγραφή υποψηφίου', NULL, 72.40, '2025-09-12 14:20:00'),
(7, 'Χρήστος', 'Μαρίνα', '1991-12-04', 1, 'Σε οριστικό πίνακα', 3, 93.20, '2026-01-22 12:10:00'),
(8, 'Παναγιώτης', 'Δήμητρα', '1995-04-17', 6, 'Σε προσωρινό πίνακα', 9, 86.75, '2026-02-14 16:35:00');

INSERT INTO candidate_notification_settings (user_id, notify_new_list, notify_rank_change, notify_specialty_stats)
SELECT id, 1, 1, 0
FROM users
WHERE role = 'candidate';

INSERT INTO tracked_candidates (user_id, candidate_profile_id) VALUES
(2, 2),
(2, 4),
(4, 1),
(6, 7);
