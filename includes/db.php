<?php

declare(strict_types=1);

$db_host = "localhost";
$db_name = "pinakes_dioristewn";
$db_user = "root";
$db_pass = "";
$db_port = 3306;

final class PdoResultAdapter
{
    /** @var array<int, array<string, mixed>> */
    private array $rows;
    private int $position = 0;
    public int $num_rows = 0;

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function __construct(array $rows)
    {
        $this->rows = array_values($rows);
        $this->num_rows = count($this->rows);
    }

    public function fetch_assoc(): ?array
    {
        if ($this->position >= $this->num_rows) {
            return null;
        }

        return $this->rows[$this->position++];
    }
}

final class PdoStatementAdapter
{
    private PdoConnectionAdapter $connection;
    private PDOStatement $statement;
    /** @var array<int, mixed> */
    private array $boundValues = [];
    private ?PdoResultAdapter $result = null;
    public int $num_rows = 0;
    public string $error = "";

    public function __construct(PdoConnectionAdapter $connection, PDOStatement $statement)
    {
        $this->connection = $connection;
        $this->statement = $statement;
    }

    public function bind_param(string $types, &...$vars): bool
    {
        $this->boundValues = [];

        foreach ($vars as &$value) {
            $this->boundValues[] = $value;
        }

        return true;
    }

    public function execute(): bool
    {
        try {
            $this->statement->execute($this->boundValues);
            $this->connection->syncLastInsertId();

            if ($this->statement->columnCount() > 0) {
                $rows = $this->statement->fetchAll(PDO::FETCH_ASSOC);
                $this->result = new PdoResultAdapter($rows);
                $this->num_rows = $this->result->num_rows;
            } else {
                $this->result = null;
                $this->num_rows = $this->statement->rowCount();
            }

            $this->error = "";
            $this->connection->error = "";
            return true;
        } catch (PDOException $exception) {
            $this->error = $exception->getMessage();
            $this->connection->error = $this->error;
            $this->result = null;
            $this->num_rows = 0;
            return false;
        }
    }

    public function get_result(): ?PdoResultAdapter
    {
        return $this->result;
    }

    public function store_result(): bool
    {
        if ($this->result === null && $this->statement->columnCount() > 0) {
            $rows = $this->statement->fetchAll(PDO::FETCH_ASSOC);
            $this->result = new PdoResultAdapter($rows);
        }

        $this->num_rows = $this->result?->num_rows ?? 0;
        return true;
    }

    public function close(): void
    {
        $this->result = null;
        $this->statement->closeCursor();
    }
}

final class PdoConnectionAdapter
{
    private PDO $pdo;
    public string $error = "";
    public int $insert_id = 0;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function query(string $sql): PdoResultAdapter|false
    {
        try {
            $statement = $this->pdo->query($sql);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
            $this->error = "";
            return new PdoResultAdapter($rows);
        } catch (PDOException $exception) {
            $this->error = $exception->getMessage();
            return false;
        }
    }

    public function prepare(string $sql): PdoStatementAdapter|false
    {
        try {
            $statement = $this->pdo->prepare($sql);

            if ($statement === false) {
                $this->error = "Failed to prepare SQL statement.";
                return false;
            }

            $this->error = "";
            return new PdoStatementAdapter($this, $statement);
        } catch (PDOException $exception) {
            $this->error = $exception->getMessage();
            return false;
        }
    }

    public function begin_transaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }

    public function syncLastInsertId(): void
    {
        $this->insert_id = (int) $this->pdo->lastInsertId();
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }
}

try {
    $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $exception) {
    http_response_code(500);
    die(
        "<!DOCTYPE html>
        <html lang='el'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Σφάλμα σύνδεσης βάσης</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    background: #f4f6f8;
                    margin: 0;
                    padding: 40px 20px;
                    color: #1f2937;
                }
                .error-box {
                    max-width: 720px;
                    margin: 0 auto;
                    background: #ffffff;
                    border: 1px solid #e5e7eb;
                    border-left: 6px solid #dc2626;
                    border-radius: 10px;
                    padding: 24px;
                    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
                }
                h1 {
                    margin-top: 0;
                    color: #b91c1c;
                }
                p {
                    line-height: 1.6;
                }
                code {
                    background: #f3f4f6;
                    padding: 2px 6px;
                    border-radius: 4px;
                }
            </style>
        </head>
        <body>
            <div class='error-box'>
                <h1>Δεν υπάρχει σύνδεση με τη βάση δεδομένων</h1>
                <p>Η εφαρμογή δεν μπόρεσε να συνδεθεί στη MySQL. Έλεγξε αν τρέχει η βάση από το XAMPP.</p>
                <p>Άνοιξε το <code>XAMPP Control Panel</code> και πάτησε <code>Start</code> στο <code>MySQL</code>.</p>
            </div>
        </body>
        </html>"
    );
}

$conn = new PdoConnectionAdapter($pdo);
