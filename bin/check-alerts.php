<?php
declare(strict_types=1);
// Read an exported metrics file, rather than contacting external services.
if ($argc !== 2) { fwrite(STDERR, "Usage: php bin/check-alerts.php metrics.txt\n"); exit(2); }
$text = @file_get_contents($argv[1]);
if ($text === false) { fwrite(STDERR, "Cannot read metrics file\n"); exit(2); }
preg_match_all('/^inventory_available\{sku="([^"\n]+)"\} (\d+)$/m', $text, $matches, PREG_SET_ORDER);
if (!$matches) { fwrite(STDERR, "No stock metrics found\n"); exit(2); }
$alerts = [];
foreach ($matches as $row) if ((int)$row[2] < 3) $alerts[] = "LOW_STOCK sku={$row[1]} available={$row[2]}";
echo $alerts ? implode("\n", $alerts)."\n" : "OK: all stock >= 3\n";
exit($alerts ? 1 : 0);
