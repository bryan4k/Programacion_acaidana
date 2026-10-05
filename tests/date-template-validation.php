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

$overflowRows = array_fill(0, 24, ['id' => 'row-overflow', 'date' => '2026-10-01']);
$overflowTemplate = [
    'templateType' => 'worship',
    'headers' => ['', '', ''],
    'rows' => $overflowRows,
    'design' => ['headerHeight' => 300, 'footerHeight' => 320],
];
if (count(validate_template($overflowTemplate)['rows']) !== 24) {
    throw new RuntimeException('A worship page within the full-height A4 capacity was rejected due to decorative image sizes.');
}

$overflowTemplate['rows'][] = ['id' => 'row-overflow-25', 'date' => '2026-10-01'];
try {
    validate_template($overflowTemplate);
    throw new RuntimeException('A worship page exceeding the full-height A4 row estimate was accepted.');
} catch (InvalidArgumentException) {
    // Expected: the full-height row estimate still rejects rows above capacity.
}

$ushersTemplate = [
    'templateType' => 'ushers',
    'headers' => ['', '', ''],
    'rows' => array_fill(0, 27, ['id' => 'usher-row', 'date' => '2026-10-01']),
    'design' => ['logoSize' => 180, 'headerHeight' => 300, 'footerHeight' => 320],
];
if (count(validate_template($ushersTemplate)['rows']) !== 27) {
    throw new RuntimeException('Large decorative images reduced usher rows within the full-height A4 estimate.');
}
$ushersTemplate['rows'][] = ['id' => 'usher-row-28', 'date' => '2026-10-01'];
try {
    validate_template($ushersTemplate);
    throw new RuntimeException('An usher page exceeding the full-height A4 row estimate was accepted.');
} catch (InvalidArgumentException) {
    // Expected: the full-height row estimate still rejects rows above capacity.
}

echo "Date template validation passed.\n";


$longVerseTemplate = [
    'templateType' => 'worship',
    'headers' => ['', '', ''],
    'rows' => [['id' => 'long-verse', 'date' => '2026-10-01']],
    'design' => ['verseHeight' => 900],
];
if (validate_template($longVerseTemplate)['design']['verseHeight'] !== 900) {
    throw new RuntimeException('A verse height larger than the former 360px cap was not accepted.');
}
