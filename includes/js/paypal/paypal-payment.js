document.addEventListener('DOMContentLoaded', function () {
    function initializePayPal() {
        const paypalButtonContainer = document.querySelector('#paypal-button-container');
        if (!paypalButtonContainer) {
            return;
        }

        paypal.Buttons({
            createOrder: function (data, actions) {
                return actions.order.create({
                    purchase_units: [{
                        amount: {
                            value: '100.00' // Get the total cost here!
                        }
                    }]
                });
            },
            onApprove: function (data, actions) {
                return actions.order.capture().then(function (details) {
                    // Handle the order details here!
                    alert('Transaction completed by ' + details.payer.name.given_name);
                    
                    // After successful transaction, do something!
                    const form = document.querySelector('#select-room-form');
                    form.submit();
                });
            },
            onError: function (err) {
                console.error('PayPal payment error: ', err);
            }
        }).render('#paypal-button-container');
    }

    document.addEventListener('paypalFormRendered', initializePayPal);
    
    initializePayPal();
});