<?php
declare(strict_types=1);

final class Store {
    private PDO $db;
    public function __construct(string $path) {
        $this->db = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->db->exec('PRAGMA busy_timeout = 3000');
        $this->db->exec('CREATE TABLE IF NOT EXISTS stock (sku TEXT PRIMARY KEY, quantity INTEGER NOT NULL CHECK(quantity >= 0))');
        $this->db->exec('CREATE TABLE IF NOT EXISTS reservations (request_id TEXT PRIMARY KEY, sku TEXT NOT NULL, quantity INTEGER NOT NULL CHECK(quantity > 0))');
        $this->db->exec('CREATE TABLE IF NOT EXISTS counters (name TEXT PRIMARY KEY, value INTEGER NOT NULL)');
    }
    public function seed(): void {
        $this->db->exec("INSERT OR IGNORE INTO stock VALUES ('DEMO-SHOE', 10), ('DEMO-BAG', 5)");
    }
    public function stock(): array {
        return $this->db->query('SELECT sku, quantity FROM stock ORDER BY sku')->fetchAll(PDO::FETCH_ASSOC);
    }
    public function count(string $name): void {
        $s = $this->db->prepare('INSERT INTO counters VALUES (?,1) ON CONFLICT(name) DO UPDATE SET value=value+1');
        $s->execute([$name]);
    }
    public function reserve(string $key, string $sku, int $quantity): array {
        if (trim($key) === '' || trim($sku) === '' || strlen($key) > 100 || strlen($sku) > 100 || $quantity <= 0)
            throw new InvalidArgumentException('Nonblank IDs (max 100 chars) and positive integer quantity required');
        // A write lock covers idempotency lookup, conditional decrement and recording.
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $q = $this->db->prepare('SELECT sku, quantity FROM reservations WHERE request_id=?');
            $q->execute([$key]);
            $previous = $q->fetch(PDO::FETCH_ASSOC);
            if ($previous !== false) {
                if ($previous['sku'] !== $sku || (int)$previous['quantity'] !== $quantity)
                    throw new DomainException('Request ID already used for a different payload');
                $this->db->exec('COMMIT');
                return ['request_id'=>$key, 'sku'=>$sku, 'quantity'=>$quantity, 'replayed'=>true];
            }
            $q = $this->db->prepare('UPDATE stock SET quantity=quantity-? WHERE sku=? AND quantity>=?');
            $q->execute([$quantity, $sku, $quantity]);
            if ($q->rowCount() !== 1) throw new DomainException('Unknown SKU or insufficient stock');
            $q = $this->db->prepare('INSERT INTO reservations VALUES (?,?,?)');
            $q->execute([$key, $sku, $quantity]);
            $this->db->exec('COMMIT');
            return ['request_id'=>$key, 'sku'=>$sku, 'quantity'=>$quantity, 'replayed'=>false];
        } catch (Throwable $e) {
            $this->db->exec('ROLLBACK');
            throw $e;
        }
    }
    public function metrics(): string {
        $values = ['requests'=>0, 'conflicts'=>0];
        foreach ($this->db->query('SELECT name,value FROM counters') as $row) $values[$row['name']] = (int)$row['value'];
        $out = '';
        foreach (['requests','conflicts'] as $name) $out .= "inventory_{$name}_total {$values[$name]}\n";
        foreach ($this->stock() as $row) {
            // Seed SKUs are controlled. Escape labels for any later imported SKU.
            $sku = str_replace(["\\", "\n", '"'], ["\\\\", "\\n", '\\"'], $row['sku']);
            $out .= 'inventory_available{sku="'.$sku.'"} '.$row['quantity']."\n";
        }
        return $out;
    }
}
