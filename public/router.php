<?php
declare(strict_types=1);
require __DIR__ . '/../src/Store.php';
header('Content-Type: application/json');
$store = null;
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
try {
    $dbPath = getenv('INVENTORY_DB') ?: __DIR__ . '/../var/inventory.sqlite';
    $store = new Store($dbPath);
    if ($path === '/health' && $method === 'GET') { echo json_encode(['status'=>'ok']); return; }
    if ($path === '/metrics' && $method === 'GET') {
        header('Content-Type: text/plain; version=0.0.4'); echo $store->metrics(); return;
    }
    $store->count('requests');
    if ($path === '/stock' && $method === 'GET') { echo json_encode($store->stock()); return; }
    if ($path === '/reservations' && $method === 'POST') {
        $body = file_get_contents('php://input', false, null, 0, 8193);
        if (strlen($body) > 8192) throw new InvalidArgumentException('Body too large');
        try { $data = json_decode($body, true, 16, JSON_THROW_ON_ERROR); }
        catch (JsonException $e) { throw new InvalidArgumentException('Malformed JSON'); }
        if (!is_array($data) || !isset($data['request_id'], $data['sku'], $data['quantity'])
            || !is_string($data['request_id']) || !is_string($data['sku']) || !is_int($data['quantity']))
            throw new InvalidArgumentException('Expected request_id, sku and integer quantity');
        $result = $store->reserve($data['request_id'], $data['sku'], $data['quantity']);
        http_response_code($result['replayed'] ? 200 : 201);
        echo json_encode($result); return;
    }
    http_response_code(404); echo json_encode(['error'=>'Route not found']);
} catch (InvalidArgumentException $e) {
    http_response_code(400); echo json_encode(['error'=>$e->getMessage()]);
} catch (DomainException $e) {
    if ($store) $store->count('conflicts');
    http_response_code(409); echo json_encode(['error'=>$e->getMessage()]);
} catch (Throwable $e) {
    // Avoid returning database paths or exception details to clients.
    http_response_code(503); echo json_encode(['error'=>'Service unavailable']);
    error_log('inventory_service_error type=' . get_class($e));
}
