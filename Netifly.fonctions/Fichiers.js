// Importation des modules nécessaires
const stripe = require('stripe');
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');
const winston = require('winston'); // Pour un logging avancé

// Configuration du logger
const logger = winston.createLogger({
    level: 'info',
    format: winston.format.combine(
        winston.format.timestamp(),
        winston.format.json()
    ),
    transports: [
        new winston.transports.Console(),
        new winston.transports.File({ filename: 'webhook.log' })
    ]
});

// Vérification de la clé de chiffrement au démarrage
if (!process.env.KEY_SECRET) {
    logger.error('La clé de chiffrement (KEY_SECRET) est absente. Vérifiez les variables d\'environnement.');
    throw new Error('KEY_SECRET manquante. L\'application ne peut pas démarrer.');
}

// Fichier contenant les clés chiffrées
const ENCRYPTED_KEYS_FILE = path.resolve(__dirname, './encrypted_keys.json');
const KEY_ROTATION_INTERVAL = 30 * 24 * 60 * 60 * 1000; // 30 jours

// Fonction pour chiffrer une clé
function encryptKey(key) {
    const algorithm = 'aes-256-cbc';
    const secretKey = process.env.KEY_SECRET;
    const iv = crypto.randomBytes(16);

    const cipher = crypto.createCipheriv(algorithm, Buffer.from(secretKey, 'hex'), iv);
    let encrypted = cipher.update(key);
    encrypted = Buffer.concat([encrypted, cipher.final()]);

    return { iv: iv.toString('hex'), content: encrypted.toString('hex') };
}

// Fonction pour déchiffrer une clé
function decryptKey(encryptedKey) {
    try {
        const algorithm = 'aes-256-cbc';
        const secretKey = process.env.KEY_SECRET;
        const iv = Buffer.from(encryptedKey.iv, 'hex');
        const encryptedText = Buffer.from(encryptedKey.content, 'hex');

        const decipher = crypto.createDecipheriv(algorithm, Buffer.from(secretKey, 'hex'), iv);
        let decrypted = decipher.update(encryptedText);
        decrypted = Buffer.concat([decrypted, decipher.final()]);

        return decrypted.toString();
    } catch (err) {
        logger.error('Erreur lors du déchiffrement des clés:', err);
        throw new Error('Échec du déchiffrement des clés. Vérifiez la configuration.');
    }
}

// Chargement et déchiffrement des clés
function loadKeys() {
    try {
        const encryptedKeys = JSON.parse(fs.readFileSync(ENCRYPTED_KEYS_FILE, 'utf8'));
        return {
            STRIPE_SECRET_KEY: decryptKey(encryptedKeys.STRIPE_SECRET_KEY),
            STRIPE_ENDPOINT_SECRET: decryptKey(encryptedKeys.STRIPE_ENDPOINT_SECRET),
            lastRotation: encryptedKeys.lastRotation
        };
    } catch (err) {
        logger.error('Erreur lors du chargement des clés:', err);
        throw new Error('Impossible de charger les clés. Vérifiez le fichier des clés chiffrées.');
    }
}

// Rotation automatique des clés
function rotateKeys() {
    const now = Date.now();
    const keys = loadKeys();

    if (now - keys.lastRotation > KEY_ROTATION_INTERVAL) {
        logger.info('Rotation des clés Stripe en cours.');
        const newStripeKeys = {
            STRIPE_SECRET_KEY: encryptKey(`sk_live_${crypto.randomBytes(24).toString('hex')}`),
            STRIPE_ENDPOINT_SECRET: encryptKey(`whsec_${crypto.randomBytes(24).toString('hex')}`),
            lastRotation: now
        };
        fs.writeFileSync(ENCRYPTED_KEYS_FILE, JSON.stringify(newStripeKeys, null, 2));
        logger.info('Rotation des clés Stripe terminée avec succès.');
    }
}

// Appel à la rotation des clés au démarrage
rotateKeys();

// Chargement sécurisé des clés Stripe
const keys = loadKeys();
const stripeInstance = stripe(keys.STRIPE_SECRET_KEY);

// Gestion centralisée des événements Stripe
const eventHandlers = {
    'checkout.session.completed': (data) => {
        logger.info(`Paiement confirmé pour la session: ${data.id}`);
        // Logique métier spécifique ici (mise à jour DB, envoi d'emails, etc.)
    },
    'invoice.payment_succeeded': (data) => {
        logger.info(`Paiement réussi pour la facture: ${data.id}`);
        // Logique métier spécifique ici
    },
    'payment_intent.succeeded': (data) => {
        logger.info(`Paiement réussi pour le paiement intent: ${data.id}`);
        // Logique métier spécifique ici
    },
};

// Validation des données Stripe
function validateStripeData(type, data) {
    const validations = {
        'checkout.session.completed': () => {
            if (!data.id || typeof data.id !== 'string') {
                throw new Error(`Validation échouée pour ${type}: ID de session manquant ou invalide.`);
            }
            if (!data.amount_total || typeof data.amount_total !== 'number') {
                throw new Error(`Validation échouée pour ${type}: Montant total manquant ou invalide.`);
            }
        },
        'invoice.payment_succeeded': () => {
            if (!data.id || typeof data.id !== 'string') {
                throw new Error(`Validation échouée pour ${type}: ID de facture manquant ou invalide.`);
            }
        },
        'payment_intent.succeeded': () => {
            if (!data.id || typeof data.id !== 'string') {
                throw new Error(`Validation échouée pour ${type}: ID de paiement intent manquant ou invalide.`);
            }
        },
    };

    if (validations[type]) {
        validations[type]();
    } else {
        logger.warn(`Aucune validation spécifique définie pour le type d'événement: ${type}`);
    }
}

// Handler pour le webhook
exports.handler = async (event) => {
    if (!event || !event.headers || !event.body) {
        logger.error('Requête mal formée. Vérifiez les entrées.');
        return {
            statusCode: 400,
            body: JSON.stringify({ error: 'Bad Request' }),
        };
    }

    const sig = event.headers['stripe-signature'];
    const endpointSecret = keys.STRIPE_ENDPOINT_SECRET;

    let stripeEvent;

    try {
        stripeEvent = stripeInstance.webhooks.constructEvent(event.body, sig, endpointSecret);
    } catch (err) {
        logger.error('Échec de la vérification de la signature du webhook:', err);
        return {
            statusCode: 400,
            body: JSON.stringify({ error: 'Webhook signature verification failed' }),
        };
    }

    try {
        validateStripeData(stripeEvent.type, stripeEvent.data.object);

        const handler = eventHandlers[stripeEvent.type];
        if (handler) {
            handler(stripeEvent.data.object);
        } else {
            logger.warn(`Aucun gestionnaire pour l'événement: ${stripeEvent.type}`);
        }

        return {
            statusCode: 200,
            body: JSON.stringify({ received: true }),
        };
    } catch (err) {
        logger.error('Erreur lors du traitement de l’événement Stripe:', err);
        return {
            statusCode: 500,
            body: JSON.stringify({ error: 'Internal Server Error' }),
        };
    }
};
