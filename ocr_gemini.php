<?php
require_once __DIR__ . '/config.php';

/**
 * Sends an image to Gemini Vision API to extract structured data.
 */
function runGeminiOCR(string $imagePath, string $pageType = 'form', string $model = 'gemini-flash-latest'): string {
    if (!defined('GEMINI_API_KEY') || GEMINI_API_KEY === 'YOUR_GEMINI_API_KEY_HERE' || empty(GEMINI_API_KEY)) {
        return json_encode(['error' => 'Gemini API Key not configured.']);
    }

    $imageData = base64_encode(file_get_contents($imagePath));
    $mimeType = mime_content_type($imagePath);

    // Using the user-selected AI model
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . GEMINI_API_KEY;

    // Tailor prompt based on page type
    if ($pageType === 'id_picture' || $pageType === 'documentary') {
        $prompt = "TASK: Scan this entire image. Identify what type of document or image this is, and extract all readable text/data.
        FIELDS TO EXTRACT:
        - document_type: A short description of what this is (e.g. 'UMID Card', 'Passport', 'Utility Bill', 'Selfie Photo', 'Receipt', 'Landscape').
        - description: If the image is a photo (e.g. selfie, location, object, face), provide a 1-2 sentence description of what you see.
        - Extract any other key-value pairs you find (like names, ID numbers, addresses, dates) as snake_case keys.
        - raw_text: A full transcription of all readable text on this page.
        Return ONLY a flat JSON object.";
    } else {
        // Form page (1-4)
        $prompt = "TASK: From this application form, extract all readable field-value pairs into a JSON object.
        MANDATORY FIELDS (if present): given_name, last_name, middle_name, dob, age.
        Keys should be snake_case labels. Also include 'raw_text' with full transcription.
        EXAMPLE OUTPUT: {\"given_name\": \"MARIA\", \"last_name\": \"SANTOS\", \"raw_text\": \"...\"}";
    }

    $payload = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt],
                    [
                        "inline_data" => [
                            "mime_type" => $mimeType,
                            "data" => $imageData
                        ]
                    ]
                ]
            ]
        ],
        "generationConfig" => [
            "response_mime_type" => "application/json"
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    // Bypass SSL cert issue if on local dev (CAUTION)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode !== 200) {
        return json_encode(['error' => 'Gemini API Request failed with code ' . $httpCode, 'details' => $response]);
    }

    $data = json_decode($response, true);
    $resultText = $data['candidates'][0]['content']['parts'][0]['text'] ?? '{}';

    // Gemini might return extra text or JSON wrapped in markdown, sanitize
    $cleanText = trim($resultText);
    
    // Remove markdown code blocks if present (e.g. ```json ... ```)
    if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $cleanText, $matches)) {
        $cleanText = $matches[1];
    }
    
    return $cleanText;
}
