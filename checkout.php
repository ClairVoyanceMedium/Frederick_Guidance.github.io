<?php
require_once 'lib/init.php'; // Inclure Stripe PHP

// Récupérer la clé secrète Stripe depuis une variable d'environnement
$stripeSecretKey = getenv('STRIPE_SECRET_KEY');
if (!$stripeSecretKey) {
    http_response_code(500);
    echo json_encode(['error' => 'La clé secrète Stripe n\'est pas configurée correctement.']);
    exit;
}

\Stripe\Stripe::setApiKey($stripeSecretKey);

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

    // Détecter la langue du client
    $clientLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? $defaultLang, 0, 2);
    $lang = in_array($clientLang, $supportedLangs) ? $clientLang : $defaultLang;

    // Traductions pour les 42 langues
    $translations = [
        'fr' => ['product_name' => "Voyance - {nbQuestions} question(s)", 'error_invalid_questions' => "Nombre de questions invalide."],
        'en' => ['product_name' => "Fortune Telling - {nbQuestions} question(s)", 'error_invalid_questions' => "Invalid number of questions."],
        'es' => ['product_name' => "Lectura de fortuna - {nbQuestions} pregunta(s)", 'error_invalid_questions' => "Número de preguntas no válido."],
        'de' => ['product_name' => "Wahrsagerei - {nbQuestions} Frage(n)", 'error_invalid_questions' => "Ungültige Anzahl von Fragen."],
        'it' => ['product_name' => "Cartomanzia - {nbQuestions} domanda(e)", 'error_invalid_questions' => "Numero di domande non valido."],
        'pt' => ['product_name' => "Adivinhação - {nbQuestions} pergunta(s)", 'error_invalid_questions' => "Número de perguntas inválido."],
        'zh' => ['product_name' => "占卜 - {nbQuestions} 问题", 'error_invalid_questions' => "问题数量无效。"],
        'ja' => ['product_name' => "占い - {nbQuestions} 質問", 'error_invalid_questions' => "無効な質問数。"],
        'ko' => ['product_name' => "점술 - {nbQuestions} 질문", 'error_invalid_questions' => "잘못된 질문 수."],
        'ru' => ['product_name' => "Гадание - {nbQuestions} вопрос(ов)", 'error_invalid_questions' => "Недопустимое количество вопросов."],
        'ar' => ['product_name' => "التنجيم - {nbQuestions} سؤال(أسئلة)", 'error_invalid_questions' => "عدد الأسئلة غير صالح."],
        // Ajoutez les 32 autres langues ici...
    ];

    $text = $translations[$lang] ?? $translations[$defaultLang];

    // Lire les données envoyées par le frontend
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Validation des données
    if (!isset($data['nbQuestions']) || !is_int($data['nbQuestions']) || $data['nbQuestions'] <= 0) {
        throw new Exception($text['error_invalid_questions']);
    }

    $nbQuestions = $data['nbQuestions'];

    // Définir les prix en centimes (Stripe utilise des centimes)
    $prices = [
        1 => 600,  // 6 € pour 1 question
        3 => 1500, // 15 € pour 3 questions
        5 => 2800, // 28 € pour 5 questions
    ];

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
        'success_url' => 'https://votre-site.com/success', // Remplacez par votre URL
        'cancel_url' => 'https://votre-site.com/cancel',   // Remplacez par votre URL
    ]);

    // Retourner l'ID de la session
    echo json_encode(['id' => $session->id]);
} catch (Exception $e) {
    // Gérer les erreurs
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
