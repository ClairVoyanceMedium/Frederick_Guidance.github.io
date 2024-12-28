<?php
require_once 'lib/init.php';

// Validation stricte du format de la clé Stripe
function isValidStripeKey($key) {
    return preg_match('/^sk_[a-zA-Z0-9]{24}$/', $key) === 1;
}

$stripeSecretKey = getenv('sk_live_qFFqmqh3jYq4iczMGXnf9qZk');
if (!$stripeSecretKey || !isValidStripeKey($stripeSecretKey)) {
    http_response_code(500);
    echo json_encode(['error' => "La configuration de la clé secrète Stripe est manquante, incorrecte ou mal formatée."]);
    exit;
}

\Stripe\Stripe::setApiKey($stripeSecretKey);

$defaultCurrency = 'EUR';
$cacheFile = 'exchange_rates_cache.json';
$cacheTime = 86400; // Cache valide pendant 24 heures

// Charger ou mettre à jour le cache des taux de change
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
    $exchangeRates = json_decode(file_get_contents($cacheFile), true);
} else {
    $exchangeRatesXml = simplexml_load_file('https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml');
    if ($exchangeRatesXml) {
        $exchangeRates = [];
        foreach ($exchangeRatesXml->Cube->Cube->Cube as $rateNode) {
            $exchangeRates[(string)$rateNode['currency']] = (float)$rateNode['rate'];
        }
        $exchangeRates['EUR'] = 1; // EUR est la base, donc taux de change de 1 pour lui-même
        file_put_contents($cacheFile, json_encode($exchangeRates));
    } else {
        throw new Exception("Impossible de charger les taux de change de la BCE.");
    }
}

// Détection de la devise avec rotation des APIs de fallback
$geoApis = [
    'http://ip-api.com/json/',
    'https://ipinfo.io/json',
    'https://ipapi.co/json/',
    // Ajoutez d'autres APIs ici si possible
];

$geoData = null;
$deviseLocale = $defaultCurrency;
foreach ($geoApis as $api) {
    try {
        $geoResponse = file_get_contents($api);
        $geoData = json_decode($geoResponse, true);
        if (isset($geoData['currency'])) {
            $deviseLocale = $geoData['currency'];
            break;
        }
    } catch (Exception $e) {
        // Échec de l'API, essayez la suivante avec un délai
        usleep(500000); // Pause de 0.5 seconde avant d'essayer la prochaine API
        continue;
    }
}

// Définir un cookie sécurisé
setcookie('deviseLocale', $deviseLocale, [
    'expires' => time() + 86400,
    'path' => '/',
    'domain' => 'clairvoyancemedium.github.io',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict',
]);

header('Content-Type: application/json');

// Langues supportées avec plus de traductions dynamiques
$defaultLang = 'fr';
$supportedLangs = ['fr', 'en', 'es', 'de', 'it', 'pt', 'zh', 'ja', 'ko', 'ru'];
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
    // Ajoutez plus de traductions ici selon les besoins
];

$clientLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? $defaultLang, 0, 2);
$lang = in_array($clientLang, $supportedLangs) ? $clientLang : $defaultLang;
$text = $translations[$lang] ?? $translations[$defaultLang];

// Lire et valider les données d'entrée
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!is_array($data) || !isset($data['nbQuestions']) || !is_int(filter_var($data['nbQuestions'], FILTER_VALIDATE_INT)) || $data['nbQuestions'] <= 0) {
    throw new Exception($text['error_invalid_questions']);
}
$nbQuestions = $data['nbQuestions'];

// Définir les prix en centimes pour la devise par défaut
$basePrices = [
    1 => 600,
    3 => 1500,
    5 => 2800,
];

if (!array_key_exists($nbQuestions, $basePrices)) {
    throw new Exception($text['error_invalid_questions']);
}

// Conversion du prix en fonction de la devise locale avec arrondi précis
$convertedPrice = $basePrices[$nbQuestions];
if ($deviseLocale !== $defaultCurrency) {
    if (isset($exchangeRates[$deviseLocale])) {
        $rate = $exchangeRates[$deviseLocale];
        $convertedPrice = number_format($convertedPrice * $rate, 2, '.', '');
        $convertedPrice = intval(str_replace('.', '', $convertedPrice)); // Convertir en centimes
    } else {
        throw new Exception("Impossible d'obtenir un taux de change pour la devise {$deviseLocale}.");
    }
}

// Créer une session Stripe Checkout
try {
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency' => strtolower($deviseLocale),
                'product_data' => [
                    'name' => str_replace('{nbQuestions}', $nbQuestions, $text['product_name']),
                ],
                'unit_amount' => $convertedPrice,
            ],
            'quantity' => 1,
        ]],
        'mode' => 'payment',
        'success_url' => 'https://clairvoyancemedium.github.io/Frederick_Guidance.github.io/success.html',
        'cancel_url' => 'https://clairvoyancemedium.github.io/Frederick_Guidance.github.io/echec.html',
        'locale' => $lang,
    ]);

    echo json_encode(['id' => $session->id]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
