<?php
require_once 'lib/init.php'; // Chargez vos fichiers nécessaires, comme Stripe et d'autres dépendances.

header('Content-Type: application/json'); // Toujours définir le type de contenu.

// Fonction pour valider le format de la clé Stripe
function isValidStripeKey($key) {
    return preg_match('/^sk_[a-zA-Z0-9]{24}$/', $key) === 1;
}

// Chargement de la clé Stripe
$stripeSecretKey = getenv('STRIPE_SECRET_KEY'); // Utilisez une variable d'environnement
if (!$stripeSecretKey || !isValidStripeKey($stripeSecretKey)) {
    http_response_code(500);
    echo json_encode(['error' => "Clé Stripe manquante ou invalide."]);
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
    $exchangeRatesXml = @simplexml_load_file('https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml');
    if ($exchangeRatesXml) {
        $exchangeRates = [];
        foreach ($exchangeRatesXml->Cube->Cube->Cube as $rateNode) {
            $exchangeRates[(string)$rateNode['currency']] = (float)$rateNode['rate'];
        }
        $exchangeRates['EUR'] = 1; // L'EUR est la base
        if (!file_put_contents($cacheFile, json_encode($exchangeRates))) {
            http_response_code(500);
            echo json_encode(['error' => "Impossible de sauvegarder le cache des taux de change."]);
            exit;
        }
    } else {
        http_response_code(500);
        echo json_encode(['error' => "Impossible de charger les taux de change."]);
        exit;
    }
}

// Détection de la devise locale avec rotation des APIs de fallback
$geoApis = [
    'http://ip-api.com/json/',
    'https://ipinfo.io/json',
    'https://ipapi.co/json/',
];
$deviseLocale = $defaultCurrency;
foreach ($geoApis as $api) {
    try {
        $geoResponse = @file_get_contents($api);
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

if (!isset($exchangeRates[$deviseLocale])) {
    $deviseLocale = $defaultCurrency; // Utilisez la devise par défaut si la devise locale n'est pas supportée
}

// Lire et valider les données d'entrée
$input = file_get_contents('php://input');
$data = json_decode($input, true);

error_log("Données reçues : " . json_encode($data));

if (!$data || !isset($data['nbQuestions']) || !filter_var($data['nbQuestions'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
    http_response_code(400);
    echo json_encode(['error' => 'Requête invalide. Assurez-vous que nbQuestions est un entier positif.']);
    exit;
}

$nbQuestions = $data['nbQuestions'];

// Définir les prix en centimes
$basePrices = [
    1 => 600,
    3 => 1500,
    5 => 2800,
];

if (!array_key_exists($nbQuestions, $basePrices)) {
    http_response_code(400);
    echo json_encode(['error' => 'Nombre de questions invalide.']);
    exit;
}

// Conversion du prix
$convertedPrice = $basePrices[$nbQuestions];
if ($deviseLocale !== $defaultCurrency) {
    $rate = $exchangeRates[$deviseLocale];
    if (!isset($rate) || $rate <= 0) {
        http_response_code(500);
        echo json_encode(['error' => "Taux de conversion invalide pour la devise {$deviseLocale}."]);
        exit;
    }
    $convertedPrice = intval($convertedPrice * $rate);
} 

if (!$convertedPrice) {
    http_response_code(500);
    echo json_encode(['error' => "Impossible de calculer le prix."]);
    exit;
}

error_log("Devise locale : {$deviseLocale}");

// Traductions dynamiques
$defaultLang = 'fr';
$supportedLangs = ['fr', 'en', 'es', 'de', 'it', 'pt', 'zh', 'ja', 'ko', 'ru'];
$translations = [
    'fr' => ['product_name' => "Voyance - {nbQuestions} question(s)", 'error_invalid_questions' => "Nombre de questions invalide."],
    'en' => ['product_name' => "Fortune Telling - {nbQuestions} question(s)", 'error_invalid_questions' => "Invalid number of questions."],
    // Ajoutez plus de traductions ici selon les besoins
];

$clientLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? $defaultLang, 0, 2);
$lang = in_array($clientLang, $supportedLangs) ? $clientLang : $defaultLang;
$text = $translations[$lang] ?? $translations[$defaultLang];
$productName = str_replace('{nbQuestions}', $nbQuestions, $text['product_name']);

// Créer une session Stripe
try {
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency' => strtolower($deviseLocale),
                'product_data' => [
                    'name' => $productName,
                ],
                'unit_amount' => $convertedPrice,
            ],
            'quantity' => 1,
        ]],
        'mode' => 'payment',
        'success_url' => 'https://clairvoyancemedium.github.io/success.html',
        'cancel_url' => 'https://clairvoyancemedium.github.io/echec.html',
        'locale' => $lang,
    ]);

    echo json_encode(['sessionId' => $session->id, 'currency' => $deviseLocale, 'price' => $convertedPrice]);
} catch (\Stripe\Exception\ApiErrorException $e) {
    error_log("Erreur Stripe : " . $e->getMessage()); // Log de l'erreur
    http_response_code(500);
    echo json_encode(['error' => "Erreur Stripe : " . $e->getMessage()]);
}
