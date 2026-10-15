<?php
declare(strict_types=1);
require __DIR__ . '/../src/Store.php';
try {
    $s = new Store(getenv('INVENTORY_DB'));
    $s->reserve($argv[1], 'DEMO-SHOE', 1);
    echo "reserved\n";
} catch (DomainException $e) {
    echo "conflict\n";
}
