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

echo "Date template validation passed.\n";
