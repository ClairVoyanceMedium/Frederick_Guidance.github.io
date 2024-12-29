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
    'es' => ['product_name' => "Adivinación - {nbQuestions} pregunta(s)", 'error_invalid_questions' => "Número de preguntas inválido."],
    'zh' => ['product_name' => "占卜 - {nbQuestions} 个问题", 'error_invalid_questions' => "问题数量无效。"],
    'hi' => ['product_name' => "ज्योतिष - {nbQuestions} प्रश्न", 'error_invalid_questions' => "अवैध प्रश्न संख्या।"],
    'ar' => ['product_name' => "التنجيم - {nbQuestions} سؤال(أسئلة)", 'error_invalid_questions' => "عدد الأسئلة غير صالح."],
    'pt' => ['product_name' => "Adivinhação - {nbQuestions} pergunta(s)", 'error_invalid_questions' => "Número de perguntas inválido."],
    'ru' => ['product_name' => "Гадание - {nbQuestions} вопрос(ов)", 'error_invalid_questions' => "Недопустимое количество вопросов."],
    'ja' => ['product_name' => "占い - {nbQuestions} 質問", 'error_invalid_questions' => "無効な質問数。"],
    'de' => ['product_name' => "Wahrsagen - {nbQuestions} Frage(n)", 'error_invalid_questions' => "Ungültige Anzahl von Fragen."],
    'it' => ['product_name' => "Divinazione - {nbQuestions} domanda(e)", 'error_invalid_questions' => "Numero di domande non valido."],
    'ko' => ['product_name' => "점 - {nbQuestions} 질문", 'error_invalid_questions' => "유효하지 않은 질문 수입니다."],
    'vi' => ['product_name' => "Bói toán - {nbQuestions} câu hỏi", 'error_invalid_questions' => "Số câu hỏi không hợp lệ."],
    'tr' => ['product_name' => "Fal - {nbQuestions} soru", 'error_invalid_questions' => "Geçersiz soru sayısı."],
    'pl' => ['product_name' => "Wróżbiarstwo - {nbQuestions} pytanie/a", 'error_invalid_questions' => "Nieprawidłowa liczba pytań."],
    'nl' => ['product_name' => "Waarzeggerij - {nbQuestions} vraag/vragen", 'error_invalid_questions' => "Ongeldig aantal vragen."],
    'id' => ['product_name' => "Ramalan - {nbQuestions} pertanyaan", 'error_invalid_questions' => "Jumlah pertanyaan tidak valid."],
    'th' => ['product_name' => "การทำนาย - {nbQuestions} คำถาม", 'error_invalid_questions' => "จำนวนคำถามไม่ถูกต้อง"],
    'ms' => ['product_name' => "Ramalan - {nbQuestions} soalan", 'error_invalid_questions' => "Bilangan soalan tidak sah."],
    'fa' => ['product_name' => "فالگیری - {nbQuestions} سوال", 'error_invalid_questions' => "تعداد سوال نامعتبر است."],
    'uk' => ['product_name' => "Ворожіння - {nbQuestions} питання", 'error_invalid_questions' => "Неприпустима кількість питань."],
    'ro' => ['product_name' => "Ghicire - {nbQuestions} întrebare/întrebări", 'error_invalid_questions' => "Număr de întrebări invalid."],
    'hu' => ['product_name' => "Jóslás - {nbQuestions} kérdés(ek)", 'error_invalid_questions' => "Érvénytelen kérdésszám."],
    'sv' => ['product_name' => "Spådom - {nbQuestions} fråga/or", 'error_invalid_questions' => "Ogiltigt antal frågor."],
    'fi' => ['product_name' => "Ennustus - {nbQuestions} kysymys/tä", 'error_invalid_questions' => "Virheellinen kysymysten määrä."],
    'no' => ['product_name' => "Spådom - {nbQuestions} spørsmål", 'error_invalid_questions' => "Ugyldig antall spørsmål."],
    'cs' => ['product_name' => "Věštění - {nbQuestions} otázka/y", 'error_invalid_questions' => "Neplatný počet otázek."],
    'da' => ['product_name' => "Spådom - {nbQuestions} spørgsmål", 'error_invalid_questions' => "Ugyldigt antal spørgsmål."],
    'el' => ['product_name' => "Μαντεία - {nbQuestions} ερώτηση/ερωτήσεις", 'error_invalid_questions' => "Μη έγκυρος αριθμός ερωτήσεων."],
    'bg' => ['product_name' => "Гадаене - {nbQuestions} въпрос(а)", 'error_invalid_questions' => "Невалиден брой въпроси."],
    'he' => ['product_name' => "חיזוי - {nbQuestions} שאלה/ות", 'error_invalid_questions' => "מספר שאלות לא חוקי."],
    'lt' => ['product_name' => "Būrimas - {nbQuestions} klausimas/klausimai", 'error_invalid_questions' => "Neteisingas klausimų skaičius."],
    'lv' => ['product_name' => "Zīlēšana - {nbQuestions} jautājums/jautājumi", 'error_invalid_questions' => "Nederīgs jautājumu skaits."],
    'sk' => ['product_name' => "Veštenie - {nbQuestions} otázka/y", 'error_invalid_questions' => "Neplatný počet otázok."],
    'hr' => ['product_name' => "Proricanje - {nbQuestions} pitanje/a", 'error_invalid_questions' => "Nevažeći broj pitanja."],
    'sr' => ['product_name' => "Прорицање - {nbQuestions} питање/питања", 'error_invalid_questions' => "Неправилан број питања."],
    'sl' => ['product_name' => "Prerokovanje - {nbQuestions} vprašanje/vprašanja", 'error_invalid_questions' => "Neveljavno število vprašanj."],
    'et' => ['product_name' => "Ennustamine - {nbQuestions} küsimus/küsimused", 'error_invalid_questions' => "Kehtetu küsimuste arv."],
    'sq' => ['product_name' => "Fall - {nbQuestions} pyetje", 'error_invalid_questions' => "Numër pyetjesh i pavlefshëm."],
    'az' => ['product_name' => "Fal - {nbQuestions} sual(lar)", 'error_invalid_questions' => "Sual sayı yanlışdır."],
    'ka' => ['product_name' => "წინასწარმეტყველება - {nbQuestions} კითხვა(ები)", 'error_invalid_questions' => "არასწორი კითხვების რაოდენობა."]

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
