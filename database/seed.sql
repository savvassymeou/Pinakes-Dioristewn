USE pinakes_dioristewn;

INSERT INTO specialties (title, description) VALUES
('ΠΕ70 Δάσκαλοι', 'Ενδεικτική ειδικότητα για την πρωτοβάθμια εκπαίδευση.'),
('ΠΕ60 Νηπιαγωγοί', 'Ενδεικτική ειδικότητα για την προσχολική εκπαίδευση.'),
('ΠΕ02 Φιλόλογοι', 'Ενδεικτική ειδικότητα για τη δευτεροβάθμια εκπαίδευση.'),
('ΠΕ03 Μαθηματικοί', 'Ενδεικτική ειδικότητα για υποψηφίους μαθηματικών.'),
('ΠΕ86 Πληροφορικής', 'Ενδεικτική ειδικότητα για υποψηφίους πληροφορικής.');

INSERT INTO users (username, email, password_hash, role) VALUES
('admin', 'admin@example.com', '$2y$10$QCkjO19wCUVT28txduk22u6M50rY4VgOFjDIkqag2lpLvYp5JQ28.', 'admin'),
('maria', 'maria@example.com', '$2y$10$QCkjO19wCUVT28txduk22u6M50rY4VgOFjDIkqag2lpLvYp5JQ28.', 'candidate'),
('giorgos', 'giorgos@example.com', '$2y$10$QCkjO19wCUVT28txduk22u6M50rY4VgOFjDIkqag2lpLvYp5JQ28.', 'candidate'),
('eleni', 'eleni@example.com', '$2y$10$QCkjO19wCUVT28txduk22u6M50rY4VgOFjDIkqag2lpLvYp5JQ28.', 'candidate'),
('petros', 'petros@example.com', '$2y$10$QCkjO19wCUVT28txduk22u6M50rY4VgOFjDIkqag2lpLvYp5JQ28.', 'candidate'),
('sofia', 'sofia@example.com', '$2y$10$QCkjO19wCUVT28txduk22u6M50rY4VgOFjDIkqag2lpLvYp5JQ28.', 'candidate');

INSERT INTO user_profiles (user_id, first_name, last_name, identity_number, phone)
SELECT id, 'Admin', 'User', 'AD123456', '99111111' FROM users WHERE email = 'admin@example.com';

INSERT INTO user_profiles (user_id, first_name, last_name, identity_number, phone)
SELECT id, 'Maria', 'Papadopoulou', 'AA123456', '99222222' FROM users WHERE email = 'maria@example.com';

INSERT INTO user_profiles (user_id, first_name, last_name, identity_number, phone)
SELECT id, 'Giorgos', 'Ioannou', 'BB234567', '99333333' FROM users WHERE email = 'giorgos@example.com';

INSERT INTO user_profiles (user_id, first_name, last_name, identity_number, phone)
SELECT id, 'Eleni', 'Nikolaou', 'CC345678', '99444444' FROM users WHERE email = 'eleni@example.com';

INSERT INTO user_profiles (user_id, first_name, last_name, identity_number, phone)
SELECT id, 'Petros', 'Stavrou', 'DD456789', '99555555' FROM users WHERE email = 'petros@example.com';

INSERT INTO user_profiles (user_id, first_name, last_name, identity_number, phone)
SELECT id, 'Sofia', 'Maniati', 'EE567890', '99666666' FROM users WHERE email = 'sofia@example.com';

INSERT INTO candidate_profiles (user_id, father_name, mother_name, birth_date, specialty_id, application_status, ranking_position, points, created_at)
SELECT u.id, 'Nikos', 'Eleni', '1992-05-14', 1, 'Ενεργή αίτηση', 12, 84.50, '2024-02-10 10:00:00'
FROM users u
WHERE u.email = 'maria@example.com';

INSERT INTO candidate_profiles (user_id, father_name, mother_name, birth_date, specialty_id, application_status, ranking_position, points, created_at)
SELECT u.id, 'Andreas', 'Sophia', '1990-11-02', 2, 'Σε προσωρινό πίνακα', 7, 88.00, '2024-05-18 09:30:00'
FROM users u
WHERE u.email = 'giorgos@example.com';

INSERT INTO candidate_profiles (user_id, father_name, mother_name, birth_date, specialty_id, application_status, ranking_position, points, created_at)
SELECT u.id, 'Dimitrios', 'Anna', '1994-03-21', 3, 'Υπό έλεγχο δικαιολογητικών', 19, 79.25, '2025-01-08 11:45:00'
FROM users u
WHERE u.email = 'eleni@example.com';

INSERT INTO candidate_profiles (user_id, father_name, mother_name, birth_date, specialty_id, application_status, ranking_position, points, created_at)
SELECT u.id, 'Spyros', 'Katerina', '1989-09-09', 4, 'Οριστικός πίνακας', 4, 91.10, '2025-03-20 08:15:00'
FROM users u
WHERE u.email = 'petros@example.com';

INSERT INTO candidate_profiles (user_id, father_name, mother_name, birth_date, specialty_id, application_status, ranking_position, points, created_at)
SELECT u.id, 'Michalis', 'Georgia', '1996-07-30', 5, 'Νέα εγγραφή υποψηφίου', NULL, 72.40, '2025-09-12 14:20:00'
FROM users u
WHERE u.email = 'sofia@example.com';

INSERT INTO candidate_notification_settings (user_id, notify_new_list, notify_rank_change, notify_specialty_stats)
SELECT u.id, 1, 1, 0
FROM users u
WHERE u.role = 'candidate';

INSERT INTO tracked_candidates (user_id, candidate_profile_id)
SELECT u.id, cp.id
FROM users u
JOIN candidate_profiles cp ON cp.user_id = (SELECT id FROM users WHERE email = 'giorgos@example.com')
WHERE u.email = 'maria@example.com';

INSERT INTO tracked_candidates (user_id, candidate_profile_id)
SELECT u.id, cp.id
FROM users u
JOIN candidate_profiles cp ON cp.user_id = (SELECT id FROM users WHERE email = 'petros@example.com')
WHERE u.email = 'maria@example.com';

INSERT INTO tracked_candidates (user_id, candidate_profile_id)
SELECT u.id, cp.id
FROM users u
JOIN candidate_profiles cp ON cp.user_id = (SELECT id FROM users WHERE email = 'maria@example.com')
WHERE u.email = 'eleni@example.com';
