<?php
declare(strict_types=1);
require __DIR__ . '/../src/Store.php';
$n = 0;
function check(bool $ok): void { global $n; $n++; if (!$ok) throw new RuntimeException("Failed check $n"); }
function fails(string $type, callable $action): void {
    try { $action(); } catch (Throwable $e) { check($e instanceof $type); return; }
    throw new RuntimeException("Expected $type");
}
$path = tempnam(sys_get_temp_dir(), 'inventory-test-');
try {
    $s = new Store($path); $s->seed();
    check(count($s->stock()) === 2);
    $r = $s->reserve('r1','DEMO-SHOE',2); check($r['replayed'] === false);
    check($s->reserve('r1','DEMO-SHOE',2)['replayed'] === true);
    check((int)$s->stock()[1]['quantity'] === 8);
    fails(DomainException::class, fn() => $s->reserve('r1','DEMO-SHOE',3));
    fails(DomainException::class, fn() => $s->reserve('large','DEMO-SHOE',9));
    check((int)$s->stock()[1]['quantity'] === 8);
    fails(InvalidArgumentException::class, fn() => $s->reserve('','DEMO-SHOE',1));
    fails(InvalidArgumentException::class, fn() => $s->reserve('zero','DEMO-SHOE',0));
    fails(DomainException::class, fn() => $s->reserve('unknown',"x' OR 1=1 --",1));
    // A rolled-back key is reusable; prepared statements keep hostile SKUs inert.
    $s->reserve('large','DEMO-SHOE',8); check((int)$s->stock()[1]['quantity'] === 0);
    $s->seed(); check((int)$s->stock()[1]['quantity'] === 0);
    unset($s); $s = new Store($path);
    check($s->reserve('r1','DEMO-SHOE',2)['replayed'] === true);
    $s->count('requests'); $s->count('requests');
    check(str_contains($s->metrics(), 'inventory_requests_total 2'));
    check(str_contains($s->metrics(), 'inventory_available{sku="DEMO-SHOE"} 0'));
    echo "PASS: $n store checks\n";
} finally { unset($s); @unlink($path); }
