<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$template = [
    'templateType' => 'worship',
    'headers' => ['', '', ''],
    'rows' => [[
        'id' => 'row-1',
        'date' => '2026-10-01',
        'secondDate' => '2026-10-08',
    ]],
];
$validated = validate_template($template);
if (($validated['rows'][0]['secondDate'] ?? null) !== '2026-10-08') {
    throw new RuntimeException('The optional second worship date was not preserved.');
}

$template['rows'][0]['secondDate'] = '2026-02-30';
try {
    validate_template($template);
    throw new RuntimeException('An invalid second worship date was accepted.');
} catch (InvalidArgumentException) {
    // Expected: invalid optional dates are rejected.
}

$template = ['templateType' => 'worship', 'headers' => ['', '', ''], 'rows' => [['id' => 'row-legacy', 'date' => '2026-10-01']]];
$validated = validate_template($template);
if (($validated['rows'][0]['date'] ?? null) !== '2026-10-01' || array_key_exists('secondDate', $validated['rows'][0])) {
    throw new RuntimeException('Legacy one-date rows did not remain compatible.');
}
if (($validated['design']['visibility']['logo'] ?? null) !== true || ($validated['design']['visibility']['verse'] ?? null) !== true) {
    throw new RuntimeException('Legacy templates must default all decorations to visible.');
}

$visibilityTemplate = [
    'templateType' => 'worship', 'headers' => ['', '', ''],
    'rows' => [['id' => 'visible-row', 'date' => '2026-10-01']],
    'design' => ['visibility' => ['logo' => false, 'footer' => false]],
];
$validated = validate_template($visibilityTemplate);
if ($validated['design']['visibility']['logo'] !== false || $validated['design']['visibility']['footer'] !== false || $validated['design']['visibility']['watermark'] !== true) {
    throw new RuntimeException('Independent decoration visibility settings were not preserved.');
}

$visibilityTemplate['design']['visibility']['footer'] = 'hidden';
try {
    validate_template($visibilityTemplate);
    throw new RuntimeException('A non-boolean decoration visibility setting was accepted.');
} catch (InvalidArgumentException) {
    // Expected: visibility values must be booleans.
}

$overflowRows = array_fill(0, 17, ['id' => 'row-overflow', 'date' => '2026-10-01']);
$overflowTemplate = [
    'templateType' => 'worship',
    'headers' => ['', '', ''],
    'rows' => $overflowRows,
    'design' => ['footerHeight' => 310],
];
if (count(validate_template($overflowTemplate)['rows']) !== 17) {
    throw new RuntimeException('A worship page within the physical A4 bounds was rejected due to internal decoration reserves.');
}

$overflowTemplate['rows'][] = ['id' => 'row-overflow-18', 'date' => '2026-10-01'];
try {
    validate_template($overflowTemplate);
    throw new RuntimeException('A worship page exceeding the estimated physical A4 row boundary was accepted.');
} catch (InvalidArgumentException) {
    // Expected: larger overflow remains blocked to prevent clipping.
}

$ushersTemplate = [
    'templateType' => 'ushers',
    'headers' => ['', '', ''],
    'rows' => array_fill(0, 21, ['id' => 'usher-row', 'date' => '2026-10-01']),
    'design' => ['logoSize' => 180],
];
if (count(validate_template($ushersTemplate)['rows']) !== 21) {
    throw new RuntimeException('A large decorative logo reduced usher rows still within the physical A4 bounds.');
}
$ushersTemplate['rows'][] = ['id' => 'usher-row-22', 'date' => '2026-10-01'];
try {
    validate_template($ushersTemplate);
    throw new RuntimeException('An usher page exceeding its estimated physical A4 row boundary was accepted.');
} catch (InvalidArgumentException) {
    // Expected: the physical A4 row capacity still blocks content beyond the sheet edge.
}

echo "Date template validation passed.\n";
