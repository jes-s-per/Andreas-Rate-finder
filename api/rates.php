<?php
// api/rates.php
// Simple REST endpoint to accept input, transform payload, call remote API, and return rates

// Enable CORS for local development
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode([ 'error' => 'Method Not Allowed. Use POST.' ]);
    exit;
}

header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if ($input === null) {
    http_response_code(400);
    echo json_encode([ 'error' => 'Invalid JSON body.' ]);
    exit;
}

// Expected input shape
// {
//   "Unit Name": "String",
//   "Arrival": "dd/mm/yyyy",
//   "Departure": "dd/mm/yyyy",
//   "Occupants": <int>,
//   "Ages": [<int array>]
// }

function fail($message, $status = 400) {
    http_response_code($status);
    echo json_encode([ 'error' => $message ]);
    exit;
}

$required = [ 'Unit Name', 'Arrival', 'Departure', 'Occupants', 'Ages' ];
foreach ($required as $key) {
    if (!array_key_exists($key, $input)) {
        fail("Missing required field: $key");
    }
}

$unitName = trim((string)$input['Unit Name']);
$arrivalStr = trim((string)$input['Arrival']);
$departureStr = trim((string)$input['Departure']);
$occupants = (int)$input['Occupants'];
$ages = $input['Ages'];

if (!is_array($ages)) {
    fail('Ages must be an array of integers.');
}

// Validate date format dd/mm/yyyy
function parse_ddmmyyyy($s) {
    $parts = explode('/', $s);
    if (count($parts) !== 3) return null;
    [$dd, $mm, $yyyy] = $parts;
    if (!ctype_digit($dd) || !ctype_digit($mm) || !ctype_digit($yyyy)) return null;
    $dd = (int)$dd; $mm = (int)$mm; $yyyy = (int)$yyyy;
    if (!checkdate($mm, $dd, $yyyy)) return null;
    // Return ISO yyyy-mm-dd
    return sprintf('%04d-%02d-%02d', $yyyy, $mm, $dd);
}

$arrivalIso = parse_ddmmyyyy($arrivalStr);
$departureIso = parse_ddmmyyyy($departureStr);
if ($arrivalIso === null || $departureIso === null) {
    fail('Arrival and Departure must be in dd/mm/yyyy format and be valid dates.');
}

if ($occupants < 1) {
    fail('Occupants must be >= 1');
}

if (count($ages) !== $occupants) {
    fail('The number of Ages must match Occupants.');
}

// Map "Unit Name" to Unit Type ID (provided IDs for testing)
$unitMap = [
    // Example mappings – adjust names as needed in UI
    'Standard Room' => -2147483637,
    'Family Room' => -2147483456,
];

if (!array_key_exists($unitName, $unitMap)) {
    // Also allow direct numeric ID provided as Unit Name for flexibility
    if (is_numeric($unitName)) {
        $unitTypeId = (int)$unitName;
    } else {
        fail('Unknown Unit Name. Use one of: ' . implode(', ', array_keys($unitMap)) . ' or provide a numeric Unit Type ID.');
    }
} else {
    $unitTypeId = $unitMap[$unitName];
}

// Build Guests array based on ages. Rule: age >= 12 => Adult, else Child
$guests = [];
foreach ($ages as $age) {
    $ageInt = (int)$age;
    $guests[] = [ 'Age Group' => ($ageInt >= 12 ? 'Adult' : 'Child') ];
}

$remotePayload = [
    'Unit Type ID' => $unitTypeId,
    'Arrival' => $arrivalIso,
    'Departure' => $departureIso,
    'Guests' => $guests,
];

// Call the remote API
$remoteUrl = 'https://dev.gondwana-collection.com/Web-Store/Rates/Rates.php';

$ch = curl_init($remoteUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [ 'Content-Type: application/json' ]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($remotePayload));

$responseBody = curl_exec($ch);
$curlErr = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($responseBody === false) {
    fail('Failed to reach remote API: ' . $curlErr, 502);
}

// Attempt to decode JSON from remote. If not JSON, return raw text.
$decoded = json_decode($responseBody, true);
if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'remote_status' => $httpCode,
        'remote_raw' => $responseBody,
        'notice' => 'Remote response was not JSON-decodable.'
    ]);
    exit;
}

http_response_code($httpCode ?: 200);
echo json_encode([
    'request' => $remotePayload,
    'response' => $decoded,
]);
