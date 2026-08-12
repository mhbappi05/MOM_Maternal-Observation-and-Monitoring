<?php
/**
 * Maternal health suggestions from current vitals.
 * Uses Gemini if GEMINI_API_KEY is set; otherwise returns local rule-based tips.
 * Response shape matches OpenAI chat completions for the existing frontend.
 */
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode([
        'choices' => [[
            'message' => ['content' => 'Unable to read vitals. Please try again.']
        ]]
    ]);
    exit;
}

$heartRate = $data['heart_rate'] ?? '--';
$bp = $data['blood_pressure'] ?? '--';
$temp = $data['temperature'] ?? '--';
$fetal = $data['fetal_movement'] ?? '--';
$oxygen = $data['oxygen'] ?? '--';

function localSuggestions(array $data): string
{
    $tips = [];
    $hr = (float) ($data['heart_rate'] ?? 0);
    $ox = (float) ($data['oxygen'] ?? 0);
    $temp = (float) ($data['temperature'] ?? 0);
    $fetal = (float) ($data['fetal_movement'] ?? 0);
    $bp = (string) ($data['blood_pressure'] ?? '');
    $sys = 0;
    $dia = 0;
    if (preg_match('/(\d+)\s*\/\s*(\d+)/', $bp, $m)) {
        $sys = (int) $m[1];
        $dia = (int) $m[2];
    }

    if ($hr > 0 && $hr < 60) {
        $tips[] = 'Heart rate looks low. Rest, sit down, and tell your doctor if you feel dizzy or unusually tired.';
    } elseif ($hr > 100) {
        $tips[] = 'Heart rate is elevated. Rest, sip water, and avoid exertion. Contact your clinician if it stays high or you feel unwell.';
    }

    if ($sys >= 140 || $dia >= 90) {
        $tips[] = 'Blood pressure is high. Lie on your left side, rest, and seek medical advice promptly—especially if you have headache, vision changes, or swelling.';
    } elseif ($sys > 130 || $dia > 85) {
        $tips[] = 'Blood pressure is slightly elevated. Rest and recheck soon. Mention this reading to your doctor.';
    }

    if ($temp >= 38) {
        $tips[] = 'Temperature suggests fever. Stay hydrated and contact your care provider; do not ignore fever in pregnancy.';
    } elseif ($temp > 37.5) {
        $tips[] = 'Temperature is a little high. Drink fluids, rest in a cool place, and monitor for fever.';
    }

    if ($ox > 0 && $ox < 95) {
        $tips[] = 'Oxygen saturation is low. Sit upright, take slow deep breaths, and seek urgent care if breathlessness continues.';
    }

    if ($fetal > 0 && $fetal < 4) {
        $tips[] = 'Fetal movement count looks low for this sample. Rest, drink water, and do a kick count; call your doctor if movements stay reduced.';
    }

    if (!$tips) {
        $tips[] = 'Current vitals look within a generally reassuring range. Keep monitoring, stay hydrated, and continue your usual prenatal routine.';
    }

    $tips[] = 'This is supportive guidance only—not a diagnosis. Contact your doctor or emergency services for urgent concerns.';

    return implode("\n\n", $tips);
}

function openaiStyle(string $content): string
{
    return json_encode([
        'choices' => [[
            'message' => [
                'role' => 'assistant',
                'content' => $content
            ]
        ]]
    ]);
}

function loadEnvKey(string $name): string
{
    static $loaded = false;
    if (!$loaded) {
        $autoload = __DIR__ . '/vendor/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
            if (class_exists('Dotenv\Dotenv') && is_file(__DIR__ . '/.env')) {
                Dotenv\Dotenv::createImmutable(__DIR__)->safeLoad();
            }
        } elseif (is_file(__DIR__ . '/.env')) {
            foreach (file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                    continue;
                }
                [$k, $v] = array_map('trim', explode('=', $line, 2));
                $v = trim($v, " \t\"'");
                if ($k !== '' && getenv($k) === false) {
                    putenv("$k=$v");
                    $_ENV[$k] = $v;
                }
            }
        }
        $loaded = true;
    }
    $val = $_ENV[$name] ?? getenv($name);
    return is_string($val) ? trim($val) : '';
}

function askGemini(string $apiKey, string $prompt): ?string
{
    if ($apiKey === '' || !function_exists('curl_init')) {
        return null;
    }

    $models = ['gemini-1.5-flash', 'gemini-2.0-flash', 'gemini-1.5-flash-latest'];
    foreach ($models as $model) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
            . rawurlencode($model)
            . ':generateContent?key=' . rawurlencode($apiKey);

        $payload = [
            'contents' => [[
                'parts' => [['text' => $prompt]]
            ]],
            'generationConfig' => [
                'temperature' => 0.4,
                'maxOutputTokens' => 280,
            ]
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 20,
        ]);
        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno || !$response || $code >= 400) {
            continue;
        }

        $json = json_decode($response, true);
        $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (is_string($text) && trim($text) !== '') {
            return trim($text);
        }
    }

    return null;
}

$local = localSuggestions($data);

$prompt = "You are a cautious maternal health assistant for the MOM (Maternal Observation and Monitoring) app. "
    . "Give 3-5 short, practical suggestions for an expectant mother based on these vitals. "
    . "Be calm, clear, and non-alarmist. Always remind the user this is not a diagnosis and to contact a clinician for concerning symptoms.\n\n"
    . "Vitals:\n"
    . "- Heart rate: {$heartRate} bpm\n"
    . "- Blood pressure: {$bp} mmHg\n"
    . "- Body temperature: {$temp} °C\n"
    . "- Fetal movement: {$fetal} kicks/min\n"
    . "- Oxygen saturation: {$oxygen}%\n";

$apiKey = loadEnvKey('GEMINI_API_KEY');
$aiText = askGemini($apiKey, $prompt);

echo openaiStyle($aiText ?: $local);
