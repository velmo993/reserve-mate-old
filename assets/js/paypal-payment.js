document.addEventListener('DOMContentLoaded', function () {
    function initializePayPal() {
        const paypalButtonContainer = document.querySelector('#paypal-button-container');
        const totalCost = document.getElementById('total-payment-cost').value;
        if (!paypalButtonContainer) {
            return;
        }

        paypal.Buttons({
            style: {
                height: 45,
            },
            createOrder: function (data, actions) {
                return actions.order.create({
                    purchase_units: [{
                        amount: {
                            value: totalCost
                        }
                    }]
                });
            },
            onApprove: function (data, actions) {
                return actions.order.capture().then(function (details) {
                    document.getElementById('paypalPaymentID').value = data.orderID;

                    document.getElementById('select-room-form').submit();
                });
            },
            onError: function (err) {
                console.error('PayPal payment error: ', err);
                alert('Payment failed. Please try again.');
            }
        }).render('#paypal-button-container');
        paypalButtonContainer.parentElement.style.padding = "1rem 0";
    }

    document.addEventListener('paypalFormRendered', initializePayPal);
    if (document.querySelector('#select-room-form')) {
        initializePayPal();
    }
});