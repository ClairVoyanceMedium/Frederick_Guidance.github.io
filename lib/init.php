<?php

// Charger la bibliothèque Stripe
require_once __DIR__ . '/stripe-php/init.php'; // Vérifiez que le chemin est correct

// Définir votre clé secrète Stripe
$stripeSecretKey = 'sk_live_qFFqmqh3jYq4iczMGXnf9qZk'; // Remplacez par votre clé secrète

// Vérifier si la clé Stripe est valide
if (!preg_match('/^sk_[a-zA-Z0-9]{24}$/', $stripeSecretKey)) {
    http_response_code(500);
    echo json_encode(['error' => "Clé Stripe invalide."]);
    exit;
}

// Configurer Stripe avec la clé secrète
\Stripe\Stripe::setApiKey($stripeSecretKey);

// Options additionnelles si nécessaires
// Exemple : configurer une version API spécifique
// \Stripe\Stripe::setApiVersion('2022-11-15');
