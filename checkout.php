<?php
require_once 'lib/init.php'; // Inclure Stripe PHP
\Stripe\Stripe::setApiKey('sk_live_qFFqmqh3jYq4iczMGXnf9qZk'); // Remplacez par votre clé secrète

header('Content-Type: application/json');

try {
    // Langue par défaut et langues supportées
    $defaultLang = 'fr';
    $supportedLangs = [
        'fr', 'en', 'es', 'de', 'it', 'pt', 'zh', 'ja', 'ko', 'ru', 'ar', 'hi',
        'bn', 'ms', 'id', 'th', 'vi', 'tr', 'nl', 'pl', 'sv', 'no', 'da', 'fi',
        'el', 'he', 'cs', 'sk', 'hu', 'ro', 'bg', 'uk', 'sr', 'hr', 'lt', 'lv',
        'et', 'sl', 'mt', 'ga', 'cy', 'is', 'sq'
    ];

    $clientLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? $defaultLang, 0, 2); // Détection de la langue
    $lang = in_array($clientLang, $supportedLangs) ? $clientLang : $defaultLang;

    // Traductions complètes
    $translations = [
        'fr' => [
            'product_name' => "Voyance - {nbQuestions} question(s)",
            'error_invalid_questions' => "Nombre de questions invalide.",
        ],
        'en' => [
            'product_name' => "Fortune Telling - {nbQuestions} question(s)",
            'error_invalid_questions' => "Invalid number of questions.",
        ],
        'es' => [
            'product_name' => "Lectura de fortuna - {nbQuestions} pregunta(s)",
            'error_invalid_questions' => "Número de preguntas no válido.",
        ],
        'de' => [
            'product_name' => "Wahrsagerei - {nbQuestions} Frage(n)",
            'error_invalid_questions' => "Ungültige Anzahl von Fragen.",
        ],
        'it' => [
            'product_name' => "Cartomanzia - {nbQuestions} domanda(e)",
            'error_invalid_questions' => "Numero di domande non valido.",
        ],
        'pt' => [
            'product_name' => "Adivinhação - {nbQuestions} pergunta(s)",
            'error_invalid_questions' => "Número de perguntas inválido.",
        ],
        'zh' => [
            'product_name' => "占卜 - {nbQuestions} 问题",
            'error_invalid_questions' => "问题数量无效。",
        ],
        'ja' => [
            'product_name' => "占い - {nbQuestions} 質問",
            'error_invalid_questions' => "無効な質問数。",
        ],
        'ko' => [
            'product_name' => "점술 - {nbQuestions} 질문",
            'error_invalid_questions' => "잘못된 질문 수.",
        ],
        'ru' => [
            'product_name' => "Гадание - {nbQuestions} вопрос(ов)",
            'error_invalid_questions' => "Недопустимое количество вопросов.",
        ],
        'ar' => [
            'product_name' => "التنجيم - {nbQuestions} سؤال(أسئلة)",
            'error_invalid_questions' => "عدد الأسئلة غير صالح.",
        ],
        'hi' => [
            'product_name' => "ज्योतिष - {nbQuestions} प्रश्न",
            'error_invalid_questions' => "अवैध प्रश्न संख्या।",
        ],
        'bn' => [
            'product_name' => "ভবিষ্যৎবাণী - {nbQuestions} প্রশ্ন",
            'error_invalid_questions' => "অবৈধ প্রশ্ন সংখ্যা।",
        ],
        'ms' => [
            'product_name' => "Meramal Nasib - {nbQuestions} soalan",
            'error_invalid_questions' => "Bilangan soalan tidak sah.",
        ],
        'id' => [
            'product_name' => "Meramal Nasib - {nbQuestions} pertanyaan",
            'error_invalid_questions' => "Jumlah pertanyaan tidak valid.",
        ],
        'th' => [
            'product_name' => "การพยากรณ์ - {nbQuestions} คำถาม",
            'error_invalid_questions' => "จำนวนคำถามไม่ถูกต้อง",
        ],
        'vi' => [
            'product_name' => "Bói toán - {nbQuestions} câu hỏi",
            'error_invalid_questions' => "Số câu hỏi không hợp lệ.",
        ],
        'tr' => [
            'product_name' => "Fal - {nbQuestions} soru",
            'error_invalid_questions' => "Geçersiz soru sayısı.",
        ],
        'nl' => [
            'product_name' => "Waarzeggen - {nbQuestions} vraag(en)",
            'error_invalid_questions' => "Ongeldig aantal vragen.",
        ],
        'pl' => [
            'product_name' => "Wróżbiarstwo - {nbQuestions} pytanie(a)",
            'error_invalid_questions' => "Nieprawidłowa liczba pytań.",
        ],
        'sv' => [
            'product_name' => "Spådom - {nbQuestions} fråga(or)",
            'error_invalid_questions' => "Ogiltigt antal frågor.",
        ],
        'no' => [
            'product_name' => "Spådom - {nbQuestions} spørsmål",
            'error_invalid_questions' => "Ugyldig antall spørsmål.",
        ],
        'da' => [
            'product_name' => "Spådom - {nbQuestions} spørgsmål",
            'error_invalid_questions' => "Ugyldigt antal spørgsmål.",
        ],
        'fi' => [
            'product_name' => "Ennustaminen - {nbQuestions} kysymys(tä)",
            'error_invalid_questions' => "Virheellinen kysymysten määrä.",
        ],
        'el' => [
            'product_name' => "Μαντεία - {nbQuestions} ερώτηση(εις)",
            'error_invalid_questions' => "Μη έγκυρος αριθμός ερωτήσεων.",
        ],
        // Continuer pour toutes les 42 langues
    ];

    $text = $translations[$lang] ?? $translations[$defaultLang]; // Utiliser la langue détectée ou par défaut

    // Lire les données envoyées par le front-end
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    $nbQuestions = $data['nbQuestions'];

    // Définir les prix en centimes (Stripe utilise des centimes)
    $prices = [
        1 => 600,  // 6 € pour 1 question
        3 => 1500, // 15 € pour 3 questions
        5 => 2800, // 28 € pour 5 questions
    ];

    // Vérification du nombre de questions
    if (!array_key_exists($nbQuestions, $prices)) {
        throw new Exception($text['error_invalid_questions']);
    }

    // Créer une session Stripe
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency' => 'eur',
                'product_data' => ['name' => str_replace('{nbQuestions}', $nbQuestions, $text['product_name'])],
                'unit_amount' => $prices[$nbQuestions],
            ],
            'quantity' => 1,
        ]],
        'mode' => 'payment',
        'success_url' => 'https://votre-site.com/success', // Remplacez par l'URL réelle
        'cancel_url' => 'https://votre-site.com/cancel',   // Remplacez par l'URL réelle
    ]);

    // Retourner l'ID de la session au frontend
    echo json_encode(['id' => $session->id]);
} catch (Exception $e) {
    // Gérer les erreurs
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
