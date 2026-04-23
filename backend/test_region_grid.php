<?php
// Test new neighborRegions logic
$region = "16.05_108.20";
$parts = explode('_', $region);
$lat = round((float)$parts[0], 2);
$lng = round((float)$parts[1], 2);
$step = 0.01;
$rows = [];

// Expand từ 3x3 thành 5x5 grid (±0.02)
foreach ([-2*$step, -$step, 0.0, $step, 2*$step] as $dLat) {
    foreach ([-2*$step, -$step, 0.0, $step, 2*$step] as $dLng) {
        if ($dLat == 0.0 && $dLng == 0.0) {
            continue;
        }
        $rows[] = number_format($lat + $dLat, 2, '.', '') . '_' . number_format($lng + $dLng, 2, '.', '');
    }
}

$rows = array_values(array_unique($rows));

echo "Base region: $region\n";
echo "Total neighbors: " . count($rows) . "\n";
echo "Neighbor regions:\n";
foreach ($rows as $r) {
    echo "  $r\n";
}

echo "\n=== CHECK ===\n";
$testRegion = "16.06_108.22";
if (in_array($testRegion, $rows)) {
    echo "✓ Region $testRegion INCLUDED\n";
} else {
    echo "❌ Region $testRegion NOT included\n";
}
