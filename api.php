<?php
// api.php using LangChain agent
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['message'])) {
    echo json_encode(['error' => 'No message provided.']);
    exit;
}

$message = trim($data['message']);

// Call the Python LangChain agent via HTTP
$python_url = 'http://localhost:5000/query';
$payload = json_encode(['message' => $message]);

$options = array(
    'http' => array(
        'header'  => "Content-Type: application/json\r\n",
        'method'  => 'POST',
        'content' => $payload,
        'timeout' => 120
    )
);
$context  = stream_context_create($options);
$result = file_get_contents($python_url, false, $context);

if ($result === false) {
    echo json_encode(['response' => 'Error contacting the LangChain service.']);
    exit;
}

echo $result;
?>
