const stripe = require('stripe')(process.env.STRIPE_SECRET_KEY);

exports.handler = async (event) => {
    const sig = event.headers['stripe-signature'];
    const endpointSecret = process.env.STRIPE_WEBHOOK_SECRET;

    let stripeEvent;

    try {
        stripeEvent = stripe.webhooks.constructEvent(event.body, sig, endpointSecret);
    } catch (err) {
        console.error('Webhook signature verification failed:', err);
        return {
            statusCode: 400,
            body: JSON.stringify({ error: 'Webhook Error' }),
        };
    }

    // Vérifiez le type d'événement
    if (stripeEvent.type === 'checkout.session.completed') {
        const session = stripeEvent.data.object;
        console.log('Paiement reçu pour la session:', session.id);
        // Traitez vos données ici, comme enregistrer dans une base de données ou envoyer un email.
    }

    return {
        statusCode: 200,
        body: JSON.stringify({ success: true }),
    };
};