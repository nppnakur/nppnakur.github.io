<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [];
}

// --- अपनी API Key यहाँ डालें ---
$API_KEY = "AIzaSyAdEmC7UR1KkNmuocGSJmsC-EZR6-vLssE"; 

function getSmartResponse($msg, $key) {
    if (empty($key) || $key == "YOUR_GEMINI_API_KEY_HERE") {
        return "System: Please enter a valid API Key.";
    }

    // मॉडल का नाम अब gemini-1.5-flash-latest इस्तेमाल किया गया है
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=" . $key;
    
    $data = [
        "contents" => [[
            "parts" => [["text" => "You are Dhaka AI. Answer in Hindi: " . $msg]]
        ]]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);
    $result = json_decode($response, true);
    curl_close($ch);

    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        return $result['candidates'][0]['content']['parts'][0]['text'];
    } elseif (isset($result['error'])) {
        return "API Error: " . $result['error']['message'];
    }
    return "Unknown Connection Error.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clear'])) {
        $_SESSION['chat_history'] = [];
    } elseif (!empty($_POST['message'])) {
        $u_msg = trim($_POST['message']);
        $ai_res = getSmartResponse($u_msg, $API_KEY);
        $_SESSION['chat_history'][] = ['u' => $u_msg, 'ai' => $ai_res];
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DHAKA AI - Voice & API Fixed</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #010409; color: white; font-family: sans-serif; overflow: hidden; }
        .app { max-width: 480px; margin: 10px auto; height: 95vh; background: #0d1117; display: flex; flex-direction: column; border-radius: 15px; border: 1px solid #30363d; }
        .header { padding: 12px; background: #161b22; border-bottom: 3px solid #FF9933; display: flex; justify-content: space-between; align-items: center; }
        .flag { width: 30px; height: 18px; background: linear-gradient(#FF9933 33%, #fff 33% 66%, #138808 66%); display: inline-block; animation: wave 2s infinite; border-radius: 2px; }
        @keyframes wave { 0%, 100% { transform: skewY(0deg); } 50% { transform: skewY(5deg); } }
        .chat { flex: 1; overflow-y: auto; padding: 15px; background: #010409; }
        .msg { margin-bottom: 12px; padding: 10px 14px; border-radius: 14px; max-width: 85%; line-height: 1.4; }
        .user { background: #238636; margin-left: auto; border-bottom-right-radius: 2px; }
        .ai { background: #21262d; border-left: 4px solid #FF9933; border-bottom-left-radius: 2px; }
        .footer { padding: 10px; background: #161b22; display: flex; gap: 8px; }
        .mic { width: 45px; height: 45px; border-radius: 50%; border: none; background: #30363d; color: white; }
        .in { background: #0d1117; border: 1px solid #30363d; color: white; border-radius: 20px; }
        .send { width: 45px; height: 45px; border-radius: 50%; background: #FF9933; border: none; color: black; }
    </style>
</head>
<body>

<div class="app">
    <div class="header">
        <h6 class="m-0"><i class="fas fa-robot text-warning"></i> DHAKA AI <div class="flag"></div></h6>
        <form method="POST"><button name="clear" class="btn btn-sm btn-danger p-1"><i class="fas fa-trash"></i></button></form>
    </div>
    
    <div class="chat" id="chat">
        <?php foreach($_SESSION['chat_history'] as $h): ?>
            <div class="msg user"><?= htmlspecialchars($h['u']) ?></div>
            <div class="msg ai">
                <span class="response-text"><?= nl2br(htmlspecialchars($h['ai'])) ?></span>
                <button onclick="speak(this.closest('.ai').querySelector('.response-text').innerText)" class="btn btn-sm text-info p-0 d-block mt-2">
                    <i class="fas fa-volume-up"></i> दोबारा सुनें
                </button>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="footer">
        <button type="button" class="mic" onclick="listen()"><i class="fas fa-microphone"></i></button>
        <form method="POST" class="d-flex w-100" id="form">
            <input type="text" name="message" id="input" class="form-control in me-2" placeholder="Sawal likhein..." required autocomplete="off">
            <button class="send" type="submit"><i class="fas fa-paper-plane"></i></button>
        </form>
    </div>
</div>

<script>
    const chat = document.getElementById('chat');
    chat.scrollTop = chat.scrollHeight;

    function speak(text) {
        if (!text) return;
        window.speechSynthesis.cancel();
        const speech = new SpeechSynthesisUtterance(text);
        speech.lang = 'hi-IN';
        speech.rate = 1.0;
        window.speechSynthesis.speak(speech);
    }

    function listen() {
        const recognition = new (window.SpeechRecognition || window.webkitSpeechRecognition)();
        recognition.lang = 'hi-IN';
        recognition.onresult = (e) => {
            document.getElementById('input').value = e.results[0][0].transcript;
            document.getElementById('form').submit();
        };
        recognition.start();
    }

    <?php if(!empty($_SESSION['chat_history'])): ?>
        window.onload = () => {
            const lastMsg = `<?= addslashes(end($_SESSION['chat_history'])['ai']) ?>`;
            speak(lastMsg);
        };
    <?php endif; ?>
</script>
</body>
</html>