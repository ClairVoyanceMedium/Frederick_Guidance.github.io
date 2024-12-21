<?php
require_once 'lib/init.php'; // Inclure Stripe PHP

\Stripe\Stripe::setApiKey('sk_live_votre_cle_secrete'); // Remplacez par votre clé secrète

header('Content-Type: application/json');

try {
    // Lire les données envoyées par le front-end
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $nbQuestions = $data['nbQuestions'];
    $prices = [
        1 => 600,  // 6 € pour 1 question
        3 => 1500, // 15 € pour 3 questions
        5 => 2800, // 28 € pour 5 questions
    ];

    if (!array_key_exists($nbQuestions, $prices)) {
        throw new Exception("Nombre de questions invalide.");
    }

    // Créer une session Stripe
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency' => 'eur',
                'product_data' => ['name' => "Voyance - $nbQuestions question(s)"],
                'unit_amount' => $prices[$nbQuestions],
            ],
            'quantity' => 1,
        ]],
        'mode' => 'payment',
        'success_url' => 'https://votre-site.com/success',
        'cancel_url' => 'https://votre-site.com/cancel',
    ]);

    echo json_encode(['id' => $session->id]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()<?php
require_once 'lib/init.php'; // Inclure Stripe PHP

\Stripe\Stripe::setApiKey('sk_live_votre_cle_secrete'); // Remplacez par votre clé secrète

header('Content-Type: application/json');

try {
    // Lire les données envoyées par le front-end
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $nbQuestions = $data['nbQuestions'];
    $prices = [
        1 => 600,  // 6 € pour 1 question
        3 => 1500, // 15 € pour 3 questions
        5 => 2800, // 28 € pour 5 questions
    ];

    if (!array_key_exists($nbQuestions, $prices)) {
        throw new Exception("Nombre de questions invalide.");
    }

    // Créer une session Stripe
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency' => 'eur',
                'product_data' => ['name' => "Voyance - $nbQuestions question(s)"],
                'unit_amount' => $prices[$nbQuestions],
            ],
            'quantity' => 1,
        ]],
        'mode' => 'payment',
        'success_url' => 'https://votre-site.com/success',
        'cancel_url' => 'https://votre-site.com/cancel',
    ]);

    echo json_encode(['id' => $session->id]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
]);
}
?>
