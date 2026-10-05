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

$overflowRows = array_fill(0, 4, ['id' => 'row-overflow', 'date' => '2026-10-01']);
$overflowTemplate = [
    'templateType' => 'worship',
    'headers' => ['', '', ''],
    'rows' => $overflowRows,
    'design' => ['footerHeight' => 310],
];
if (count(validate_template($overflowTemplate)['rows']) !== 4) {
    throw new RuntimeException('A worship page within the 10 mm A4 tolerance was rejected.');
}

$overflowTemplate['rows'][] = ['id' => 'row-overflow-5', 'date' => '2026-10-01'];
try {
    validate_template($overflowTemplate);
    throw new RuntimeException('A worship page exceeding the 10 mm A4 tolerance was accepted.');
} catch (InvalidArgumentException) {
    // Expected: larger overflow remains blocked to prevent clipping.
}

$ushersTemplate = [
    'templateType' => 'ushers',
    'headers' => ['', '', ''],
    'rows' => array_fill(0, 21, ['id' => 'usher-row', 'date' => '2026-10-01']),
    'design' => ['logoSize' => 124],
];
try {
    validate_template($ushersTemplate);
    throw new RuntimeException('The worship-only tolerance increased usher row capacity.');
} catch (InvalidArgumentException) {
    // Expected: usher sheets retain their original row capacity.
}

echo "Date template validation passed.\n";
