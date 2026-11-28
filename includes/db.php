<?php

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        config('DB_HOST', '127.0.0.1'),
        config('DB_PORT', '3306'),
        config_required('DB_NAME')
    );

    try {
        $pdo = new PDO($dsn, config_required('DB_USER'), config('DB_PASS', ''), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        // Keep MySQL's NOW() and PHP's clocks talking about the same thing.
        $pdo->exec("SET time_zone = '+00:00'");
    } catch (PDOException $e) {
        // Re-throw WITHOUT the original exception: its stack trace contains
        // the constructor arguments, i.e. the database password.
        throw new RuntimeException('Database connection failed: ' . $e->getMessage());
    }

    return $pdo;
}

/** Run a prepared statement and return it. */
function db_run(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/** First row or null. */
function db_one(string $sql, array $params = []): ?array
{
    $row = db_run($sql, $params)->fetch();
    return $row === false ? null : $row;
}

/** Every matching row. Empty array when nothing matched. */
function db_all(string $sql, array $params = []): array
{
    return db_run($sql, $params)->fetchAll();
}


function db_value(string $sql, array $params = []): mixed
{
    $value = db_run($sql, $params)->fetchColumn();
    return $value === false ? null : $value;
}


function db_insert_id(): string
{
    return db()->lastInsertId();
}


function db_begin(): void
{
    if (!db()->inTransaction()) {
        db()->beginTransaction();
    }
}

function db_commit(): void
{
    if (db()->inTransaction()) {
        db()->commit();
    }
}

function db_rollback(): void
{
    if (db()->inTransaction()) {
        db()->rollBack();
    }
}