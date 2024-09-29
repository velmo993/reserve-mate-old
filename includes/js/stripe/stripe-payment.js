document.addEventListener('DOMContentLoaded', function () {
    function initializeStripe() {
        const cardElement = document.querySelector('#card-element');
        if (!cardElement) {
            return;
        }

        const stripe = Stripe(stripe_vars.stripePublicKey);
        const elements = stripe.elements();
        const card = elements.create('card', {
            style: {
                base: {
                    fontSize: '16px',
                    color: '#32325d',
                    '::placeholder': {
                        color: '#aab7c4',
                    },
                },
                invalid: {
                    color: '#fa755a',
                },
            },
        });
        card.mount('#card-element');

        const form = document.querySelector('#select-room-form');
        form.addEventListener('submit', function(event) {
            event.preventDefault();

            stripe.createToken(card).then(function(result) {
                if (result.error) {
                    document.getElementById('card-errors').textContent = result.error.message;
                } else {
                    form.querySelector('input[name="stripeToken"]').value = result.token.id; 
                    form.submit();
                }
            });
        });
    }

    document.addEventListener('stripeFormRendered', initializeStripe);
    
    initializeStripe();
});
