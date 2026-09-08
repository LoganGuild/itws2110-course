<?php

namespace App;

/**
 * The only class that talks to the database. SQLite, one table, one file --
 * the path comes from the PANTRY_DB environment variable so tests can point it
 * at a throwaway file.
 *
 * Everything in here is "how do we store it". Nothing in here is "what is
 * allowed" -- that is Stock and Pantry. Keeping those apart is what lets the
 * unit tests run without a database at all.
 */
final class Repository
{
    // A typed property. `private` again: nobody outside gets the raw connection.
    private \PDO $db;

    public function __construct(string $path)
    {
        if ($path !== ':memory:') {
            // `@` silences the warning if the directory already exists. Used
            // sparingly -- here it is exactly the case we do not care about.
            @mkdir(dirname($path), 0777, true);
        }

        // PDO is PHP's one database API for every engine. Swap the connection
        // string for "mysql:host=db;dbname=app" and the rest of this file works.
        $this->db = new \PDO('sqlite:' . $path);       //  .  joins strings
        // Throw exceptions on errors instead of returning false -- so a typo in
        // SQL blows up loudly where it happens, not three lines later.
        $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        // Rows come back as ['name' => 'Milk', ...] instead of also having [0], [1], ...
        $this->db->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

        // Create the table the first time. Single quotes: no variables inside, so
        // a multi-line SQL string is just a string.
        $this->db->exec('CREATE TABLE IF NOT EXISTS products (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            name        TEXT    NOT NULL UNIQUE,
            amount      REAL    NOT NULL DEFAULT 0,
            best_before TEXT    NULL
        )');
    }

    /**
     * A "named constructor": a static method that returns a new instance.
     * `self` is this class; `new self(...)` is `new Repository(...)`.
     *
     * getenv() reads an environment variable; `?:` falls back to the default
     * when it is missing or empty. __DIR__ is the folder this file is in.
     */
    public static function fromEnvironment(): self
    {
        return new self(getenv('PANTRY_DB') ?: __DIR__ . '/../data/pantry.sqlite');
    }

    /**
     * The docblock below is for humans and tools: PHP itself only knows `array`.
     * It says "a list of rows, each with these four keys and types".
     *
     * @return array<int, array{id:int,name:string,amount:float,best_before:?string}>
     */
    public function all(): array
    {
        // query() for SQL with no user input in it. fetchAll() -> list of rows.
        $rows = $this->db->query('SELECT * FROM products ORDER BY name')->fetchAll();
        // array_map runs a function over every element and returns the results.
        // [$this, 'row'] is how you point at a method as a value.
        return array_map([$this, 'row'], $rows);
    }

    /** @return array{id:int,name:string,amount:float,best_before:?string}|null */
    public function find(int $id): ?array
    {
        // THE most important habit in this file: never paste a value into SQL.
        // prepare() with a `?` placeholder, then execute() with the values.
        // The database receives the query and the data separately, so a name
        // like  Robert'); DROP TABLE products;--  is just a weird name.
        $stmt = $this->db->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();                 // one row, or false if none
        return $row ? $this->row($row) : null;
    }

    public function nameExists(string $name): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM products WHERE lower(name) = lower(?)');
        $stmt->execute([$name]);
        return (bool) $stmt->fetchColumn();    // (bool) casts: 1 -> true, false -> false
    }

    public function insert(string $name, ?string $bestBefore): int
    {
        $stmt = $this->db->prepare('INSERT INTO products (name, best_before) VALUES (?, ?)');
        $stmt->execute([$name, $bestBefore]);  // null here becomes SQL NULL
        return (int) $this->db->lastInsertId();
    }

    public function setAmount(int $id, float $amount): void
    {
        $stmt = $this->db->prepare('UPDATE products SET amount = ? WHERE id = ?');
        $stmt->execute([$amount, $id]);
    }

    /**
     * SQLite hands everything back as strings. This turns one raw row into the
     * shape the rest of the app expects, with real ints, floats and nulls.
     *
     * @param array<string,mixed> $r
     */
    private function row(array $r): array
    {
        // This is an "associative array": PHP's one data structure for both
        // lists and maps. `=>` pairs a key with a value.
        return [
            'id' => (int) $r['id'],
            'name' => (string) $r['name'],
            'amount' => (float) $r['amount'],
            'best_before' => $r['best_before'] === null || $r['best_before'] === '' ? null : (string) $r['best_before'],
        ];
    }
}
