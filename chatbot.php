<?php
function detectEmotion($message) {
    $url = 'http://localhost:5001/detect_emotion';
    $data = json_encode(['text' => $message]);

    $options = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n",
            'content' => $data
        ]
    ];

    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    if ($result === FALSE) {
        return "Error connecting to emotion detection service.";
    }

    $response = json_decode($result, true);
    if (isset($response['emotion'])) {
        return $response['emotion'][0]['label'];
    } else {
        return "No emotion detected.";
    }
}
?>
