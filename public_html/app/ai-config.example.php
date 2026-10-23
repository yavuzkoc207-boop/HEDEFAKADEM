<?php
declare(strict_types=1);

// HEDEF AI konuşma ve gramer koçu için Groq API ayarları.
// Bu dosyayı app/ai-config.php olarak kopyalayın veya anahtarı GROQ_API_KEY ortam değişkeniyle verin.
// app/ai-config.php asla Git'e eklenmemelidir (.gitignore içinde).
return [
    'groq_api_key' => '',
    'groq_model' => 'openai/gpt-oss-20b',
];
