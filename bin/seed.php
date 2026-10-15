<?php
require __DIR__ . '/../src/Store.php';
$path = getenv('INVENTORY_DB') ?: __DIR__ . '/../var/inventory.sqlite';
(new Store($path))->seed();
echo "Fictional stock seeded (existing stock preserved).\n";
