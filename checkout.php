<?php
// Charger les fichiers nécessaires et initialiser Stripe
require_once 'lib/init.php';

// Utiliser un fichier de configuration ou une variable d'environnement pour stocker la clé secrète
$stripeSecretKey = getenv('sk_live_qFFqmqh3jYq4iczMGXnf9qZk');
if (!$stripeSecretKey) {
    http_response_code(500);
    echo json_encode(['error' => "La configuration de la clé secrète Stripe est manquante ou incorrecte."]);
    exit;
}

// Initialiser Stripe avec la clé secrète
\Stripe\Stripe::setApiKey($stripeSecretKey);

try {
    // Vérifiez si le cookie existe déjà
    if (!isset($_COOKIE['deviseLocale'])) {
        // Détection de la devise via une API (ou une méthode personnalisée)
        $geoResponse = file_get_contents('https://ipapi.co/json/');
        $geoData = json_decode($geoResponse, true);
        $deviseLocale = $geoData['currency'] ?? $defaultCurrency;

        // Définir un cookie sécurisé
        setcookie('deviseLocale', $deviseLocale, [
            'expires' => time() + 86400, // 1 jour
            'path' => '/',
            'domain' => 'clairvoyancemedium.github.io', // Assurez-vous que cela correspond à votre domaine
            'secure' => true, // HTTPS obligatoire
            'httponly' => true, // Interdit l'accès au JavaScript
            'samesite' => 'Strict', // Empêche les envois inter-sites
        ]);
    } else {
        // Récupérer la devise depuis le cookie
        $deviseLocale = $_COOKIE['deviseLocale'];
    }
} catch (Exception $e) {
    // En cas d'erreur, utiliser la devise par défaut
    $deviseLocale = $defaultCurrency;
}

// Définir les en-têtes de réponse JSON
header('Content-Type: application/json');

try {
    // Définir la langue par défaut et les langues supportées
    $defaultLang = 'fr';
    $supportedLangs = ['fr', 'en', 'es', 'de', 'it', 'pt', 'zh', 'ja', 'ko', 'ru', 'ar', 'hi', 'bn', 'ms', 'id', 'th', 'vi', 'tr', 'nl', 'pl', 'sv', 'no', 'da', 'fi', 'el', 'he', 'cs', 'sk', 'hu', 'ro', 'bg', 'uk', 'sr', 'hr', 'lt', 'lv', 'et', 'sl', 'mt', 'ga', 'cy', 'is', 'sq'];

    // Détecter la langue client
    $clientLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? $defaultLang, 0, 2);
    $lang = in_array($clientLang, $supportedLangs) ? $clientLang : $defaultLang;

    // Chargement des traductions par langue
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
        'hi' => ['product_name' => "ज्योतिष - {nbQuestions} प्रश्न", 'error_invalid_questions' => "अवैध प्रश्न संख्या।"],
        'bn' => ['product_name' => "ভবিষ্যৎবাণী - {nbQuestions} প্রশ্ন", 'error_invalid_questions' => "অবৈধ প্রশ্ন সংখ্যা।"],
        'ms' => ['product_name' => "Meramal Nasib - {nbQuestions} soalan", 'error_invalid_questions' => "Bilangan soalan tidak sah."],
        'id' => ['product_name' => "Meramal Nasib - {nbQuestions} pertanyaan", 'error_invalid_questions' => "Jumlah pertanyaan tidak valid."],
        'th' => ['product_name' => "การพยากรณ์ - {nbQuestions} คำถาม", 'error_invalid_questions' => "จำนวนคำถามไม่ถูกต้อง"],
        'vi' => ['product_name' => "Bói toán - {nbQuestions} câu hỏi", 'error_invalid_questions' => "Số câu hỏi không hợp lệ."],
        'tr' => ['product_name' => "Fal - {nbQuestions} soru", 'error_invalid_questions' => "Geçersiz soru sayısı."],
        'nl' => ['product_name' => "Waarzeggen - {nbQuestions} vraag(en)", 'error_invalid_questions' => "Ongeldig aantal vragen."],
        'pl' => ['product_name' => "Wróżbiarstwo - {nbQuestions} pytanie(a)", 'error_invalid_questions' => "Nieprawidłowa liczba pytań."],
        'sv' => ['product_name' => "Spådom - {nbQuestions} fråga(or)", 'error_invalid_questions' => "Ogiltigt antal frågor."],
        'no' => ['product_name' => "Spådom - {nbQuestions} spørsmål", 'error_invalid_questions' => "Ugyldig antall spørsmål."],
        'da' => ['product_name' => "Spådom - {nbQuestions} spørgsmål", 'error_invalid_questions' => "Ugyldigt antal spørgsmål."],
        'fi' => ['product_name' => "Ennustaminen - {nbQuestions} kysymys(tä)", 'error_invalid_questions' => "Virheellinen kysymysten määrä."],
        'el' => ['product_name' => "Μαντεία - {nbQuestions} ερώτηση(εις)", 'error_invalid_questions' => "Μη έγκυρος αριθμός ερωτήσεων."],
        'he' => ['product_name' => "ניחוש עתידות - {nbQuestions} שאלה", 'error_invalid_questions' => "מספר שאלות לא חוקי."],
        'cs' => ['product_name' => "Věštění - {nbQuestions} otázka(y)", 'error_invalid_questions' => "Neplatný počet otázek."],
        'sk' => ['product_name' => "Veštenie - {nbQuestions} otázka(y)", 'error_invalid_questions' => "Neplatný počet otázok."],
        'hu' => ['product_name' => "Jóslás - {nbQuestions} kérdés(ek)", 'error_invalid_questions' => "Érvénytelen kérdésszám."],
        'ro' => ['product_name' => "Prezicere - {nbQuestions} întrebare(i)", 'error_invalid_questions' => "Număr de întrebări nevalid."],
        'bg' => ['product_name' => "Предсказание - {nbQuestions} въпрос(и)", 'error_invalid_questions' => "Невалиден брой въпроси."],
        'uk' => ['product_name' => "Ворожіння - {nbQuestions} питання(ь)", 'error_invalid_questions' => "Недійсна кількість питань."],
        'sr' => ['product_name' => "Прорицање - {nbQuestions} питање(а)", 'error_invalid_questions' => "Неважећи број питања."],
        'hr' => ['product_name' => "Proricanje - {nbQuestions} pitanje(a)", 'error_invalid_questions' => "Nevažeći broj pitanja."],
        'lt' => ['product_name' => "Būrimas - {nbQuestions} klausimas(ai)", 'error_invalid_questions' => "Neteisingas klausimų skaičius."],
        'lv' => ['product_name' => "Zīlēšana - {nbQuestions} jautājums(i)", 'error_invalid_questions' => "Nederīgs jautājumu skaits."],
        'et' => ['product_name' => "Ennustamine - {nbQuestions} küsimus(ed)", 'error_invalid_questions' => "Kehtetu küsimuste arv."],
        'sl' => ['product_name' => "Prerokovanje - {nbQuestions} vprašanje(a)", 'error_invalid_questions' => "Neveljavno število vprašanj."],
        'mt' => ['product_name' => "Ħsieb - {nbQuestions} mistoqsija(jiet)", 'error_invalid_questions' => "Numru ta' mistoqsijiet invalidu."],
        'ga' => ['product_name' => "Tuar - {nbQuestions} ceist(eanna)", 'error_invalid_questions' => "Líon ceisteanna neamhbhailí."],
        'cy' => ['product_name' => "Darogan - {nbQuestions} cwestiwn(nau)", 'error_invalid_questions' => "Nifer anghywir o gwestiynau."],
        'is' => ['product_name' => "Spádómur - {nbQuestions} spurning(ar)", 'error_invalid_questions' => "Ógildur fjöldi spurninga."],
        'sq' => ['product_name' => "Parashikim - {nbQuestions} pyetje(ve)", 'error_invalid_questions' => "Numër pyetjesh i pavlefshëm."],
    ];
    $text = $translations[$lang] ?? $translations[$defaultLang];

    // Lire les données d'entrée envoyées par le client
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Validation des données
    if (!isset($data['nbQuestions']) || !is_int($data['nbQuestions']) || $data['nbQuestions'] <= 0) {
        throw new Exception($text['error_invalid_questions']);
    }
    $nbQuestions = $data['nbQuestions'];

    // Définir les prix en centimes
    $prices = [
        1 => 600,  // 6 € pour 1 question
        3 => 1500, // 15 € pour 3 questions
        5 => 2800, // 28 € pour 5 questions
    ];

    if (!array_key_exists($nbQuestions, $prices)) {
        throw new Exception($text['error_invalid_questions']);
    }

    // Créer une session Stripe Checkout
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency' => 'eur',
                'product_data' => [
                    'name' => str_replace('{nbQuestions}', $nbQuestions, $text['product_name']),
                ],
                'unit_amount' => $prices[$nbQuestions],
            ],
            'quantity' => 1,
        ]],
        'mode' => 'payment',
        'success_url' => 'https://votre-site.com/success', // URL de redirection après succès
        'cancel_url' => 'https://votre-site.com/cancel',   // URL de redirection après annulation
    ]);

    // Retourner l'ID de la session au client
    echo json_encode(['id' => $session->id]);
} catch (Exception $e) {
    // Gérer les erreurs
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
