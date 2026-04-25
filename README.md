# Ichnos - Εφαρμογή Παρακολούθησης Πινάκων Διοριστέων

Η εφαρμογή υλοποιεί ένα σύστημα αναζήτησης και παρακολούθησης πινάκων διοριστέων για υποψηφίους εκπαιδευτικούς. Περιλαμβάνει ξεχωριστά modules για admin, candidate, search και API, όπως ζητά η εκφώνηση.

## Τεχνολογίες

- PHP 8.x
- MySQL / MariaDB
- HTML, CSS, JavaScript
- XAMPP για τοπική εκτέλεση

## Βάση Δεδομένων

Τα αρχεία της βάσης βρίσκονται στον φάκελο `database`.

- `schema.sql`: δημιουργεί τη βάση `pinakes_dioristewn` και τους πίνακες.
- `seed.sql`: εισάγει καθαρά demo δεδομένα με ελληνικές ειδικότητες και υποψηφίους.
- `demo_candidates.csv`: δείγμα CSV για δοκιμή της λειτουργίας φόρτωσης λίστας από τον admin.

Βασικοί πίνακες:

- `users`: λογαριασμοί χρηστών με ρόλο `admin` ή `candidate`.
- `user_profiles`: βασικά στοιχεία χρηστών.
- `specialties`: ειδικότητες.
- `candidate_profiles`: στοιχεία υποψηφίων στους πίνακες.
- `tracked_candidates`: υποψήφιοι που παρακολουθεί ένας candidate.
- `candidate_notification_settings`: επιλογές ειδοποιήσεων candidate.

## Demo Λογαριασμοί

Όλοι οι demo χρήστες στο `seed.sql` χρησιμοποιούν τον ίδιο κωδικό:

```text
password
```

Παραδείγματα:

- Admin: `admin@example.com`
- Candidate: `maria@example.com`
- Candidate: `giorgos@example.com`

## Modules

### Admin Module

Το admin dashboard βρίσκεται στο:

```text
modules/admin/admindashboard.php
```

Υποστηρίζει:

- προβολή συνολικής εικόνας dashboard
- δημιουργία, επεξεργασία και διαγραφή χρηστών
- φόρτωση λίστας υποψηφίων από CSV
- reports ανά ειδικότητα και ανά έτος
- αλλαγή βασικών στοιχείων admin
- αλλαγή κωδικού admin

Το CSV import δέχεται στήλες όπως:

```text
first_name,last_name,father_name,mother_name,birth_date,identity_number,email,phone,ranking_position,points,application_status
```

Το αρχείο `database/demo_candidates.csv` μπορεί να χρησιμοποιηθεί για δοκιμή.

### Candidate Module

Το candidate dashboard βρίσκεται στο:

```text
modules/candidate/candidatedashboard.php
```

Υποστηρίζει:

- προβολή και επεξεργασία προφίλ
- εμφάνιση email χωρίς δυνατότητα αλλαγής
- επιλογές ειδοποιήσεων
- παρακολούθηση πορείας αίτησης με progress/timeline λογική
- αναζήτηση και παρακολούθηση άλλων υποψηφίων
- αλλαγή κωδικού candidate

### Search Module

Το search module βρίσκεται στο:

```text
modules/search/searchdashboard.php
```

Υποστηρίζει:

- αναζήτηση υποψηφίων με ονοματεπώνυμο
- φίλτρο ειδικότητας
- ταξινόμηση αποτελεσμάτων
- στατιστικά ανά ειδικότητα
- στατιστικά ανά έτος και περίοδο

### API Module

Το API βρίσκεται στο:

```text
api/api.php
```

Διαθέσιμα endpoints:

```text
GET /api/api.php?endpoint=specialties
GET /api/api.php?endpoint=candidates
GET /api/api.php?endpoint=candidates&name=Μαρία&specialty_id=1&year=2024&order=points_desc
GET /api/api.php?endpoint=stats&specialty_id=1
```

Το endpoint `candidates` υποστηρίζει φίλτρα:

- `name`
- `specialty_id`
- `year`
- `order`

Τιμές για `order`:

- `rank_asc`
- `name_asc`
- `points_desc`
- `recent_desc`

Το endpoint `stats` επιστρέφει:

- σύνολο υποψηφίων
- μέσο όρο ηλικίας
- μέσο όρο μορίων
- στατιστικά ανά έτος
- στατιστικά ανά περίοδο

## Προτεινόμενα Σενάρια Ελέγχου

1. Guest μπαίνει στο Search και κάνει αναζήτηση.
2. Guest κάνει εγγραφή ως candidate.
3. Candidate κάνει login.
4. Candidate ενημερώνει το προφίλ του.
5. Candidate βλέπει ότι το email εμφανίζεται αλλά δεν αλλάζει.
6. Candidate προσθέτει άλλον υποψήφιο στη λίστα παρακολούθησης.
7. Admin κάνει login.
8. Admin δημιουργεί, επεξεργάζεται και διαγράφει χρήστη.
9. Admin φορτώνει το `database/demo_candidates.csv`.
10. Admin βλέπει reports.
11. Τα API endpoints επιστρέφουν σωστό JSON.

## Σημειώσεις Παράδοσης

Η εφαρμογή είναι οργανωμένη με κοινή βάση δεδομένων, αλλά κάθε χρήστης βλέπει μόνο το module που αντιστοιχεί στον ρόλο του. Οι σελίδες προστατεύονται με role checks και οι βάσεις δεδομένων χρησιμοποιούν prepared statements στα βασικά queries.
