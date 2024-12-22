// Importation des modules nécessaires
const stripe = require('stripe');
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');

// Chemin sécurisé pour les clés chiffrées
const ENCRYPTED_KEYS_FILE = path.resolve(__dirname, './encrypted_keys.json');

// Fonction pour déchiffrer une clé avec gestion des erreurs
function decryptKey(encryptedKey) {
    try {
        const algorithm = 'aes-256-cbc';
        const secretKey = process.env.KEY_SECRET; // Clé de déchiffrement stockée en environnement
        if (!secretKey) throw new Error('Clé de chiffrement absente dans les variables d’environnement.');

        const iv = Buffer.from(encryptedKey.iv, 'hex');
        const encryptedText = Buffer.from(encryptedKey.content, 'hex');

        const decipher = crypto.createDecipheriv(algorithm, Buffer.from(secretKey, 'hex'), iv);
        let decrypted = decipher.update(encryptedText);
        decrypted = Buffer.concat([decrypted, decipher.final()]);

        return decrypted.toString();
    } catch (err) {
        console.error('Erreur lors du déchiffrement des clés:', err);
        throw new Error('Échec du déchiffrement des clés. Vérifiez la configuration.');
    }
}

// Chargement des clés Stripe depuis un fichier chiffré
function loadKeys() {
    try {
        if (!fs.existsSync(ENCRYPTED_KEYS_FILE)) {
            throw new Error(`Fichier ${ENCRYPTED_KEYS_FILE} introuvable.`);
        }

        const encryptedKeys = JSON.parse(fs.readFileSync(ENCRYPTED_KEYS_FILE, 'utf8'));
        return {
            STRIPE_SECRET_KEY: decryptKey(encryptedKeys.STRIPE_SECRET_KEY),
            STRIPE_ENDPOINT_SECRET: decryptKey(encryptedKeys.STRIPE_ENDPOINT_SECRET),
        };
    } catch (err) {
        console.error('Erreur lors du chargement des clés Stripe:', err);
        throw new Error('Impossible de charger les clés Stripe.');
    }
}

// Initialisation de Stripe avec les clés déchiffrées
const keys = loadKeys();
const stripeInstance = stripe(keys.STRIPE_SECRET_KEY);

exports.handler = async (event) => {
    // Vérification des données entrantes
    if (!event || !event.headers || !event.body) {
        console.error('Requête mal formée. Vérifiez les données entrantes.');
        return {
            statusCode: 400,
            body: JSON.stringify({ error: 'Requête mal formée' }),
        };
    }

    const sig = event.headers['stripe-signature'];
    const endpointSecret = keys.STRIPE_ENDPOINT_SECRET;

    let stripeEvent;

    try {
        // Vérification initiale avec Stripe
        stripeEvent = stripeInstance.webhooks.constructEvent(event.body, sig, endpointSecret);

        // Vérification supplémentaire (intégrité des données)
        const computedSignature = crypto
            .createHmac('sha256', endpointSecret)
            .update(event.body, 'utf8')
            .digest('hex');

        if (!event.headers['stripe-signature'].includes(computedSignature)) {
            throw new Error('Signature calculée non valide.');
        }
    } catch (err) {
        console.error('Erreur lors de la vérification de la signature Stripe:', err);
        return {
            statusCode: 400,
            body: JSON.stringify({ error: 'Signature invalide' }),
        };
    }

    // Traitement de l'événement Stripe
    try {
        switch (stripeEvent.type) {
            case 'checkout.session.completed':
                const session = stripeEvent.data.object;
                console.log('Paiement confirmé pour la session:', session.id);
                // Ajouter ici la logique métier (mise à jour DB, notifications, etc.)
                break;

            case 'invoice.payment_succeeded':
                console.log('Paiement de facture réussi:', stripeEvent.data.object.id);
                // Gérer ici les paiements réussis pour les factures
                break;

            case 'customer.subscription.deleted':
                console.log('Abonnement annulé pour le client:', stripeEvent.data.object.customer);
                // Gérer ici les abonnements annulés
                break;

            default:
                console.warn('Type d’événement non pris en charge:', stripeEvent.type);
        }

        return {
            statusCode: 200,
            body: JSON.stringify({ received: true }),
        };
    } catch (err) {
        console.error('Erreur lors du traitement de l’événement Stripe:', err);
        return {
            statusCode: 500,
            body: JSON.stringify({ error: 'Erreur interne du serveur' }),
        };
    }
};

// Notes pour maximiser la sécurité :
// 1. Utilisez un gestionnaire de secrets (AWS Secrets Manager, Azure Key Vault).
// 2. Appliquez des permissions restrictives au fichier des clés chiffrées : `chmod 600 encrypted_keys.json`.
// 3. Protégez `KEY_SECRET` en le stockant uniquement dans des variables d’environnement ou des secrets sécurisés.
// 4. Testez le webhook avec `stripe listen --forward-to` pour simuler des événements et vérifier la logique.
// 5. Ajoutez des alertes sur les logs pour surveiller les anomalies.

