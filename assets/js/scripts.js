(function ($) {
    let params = window.tkl_params ?? [];
    var price;
    var quantity;
    var product;
    var type;
    var wrapper;
    var qty;
    var data = {};
    var isCheckout = false;
    var popup = $('.tkl-popup-content');
    var row = popup.find('.js-row-default');

    if ($('.product-type-grouped').length > 0) {
        type = 'grouped';
        wrapper = $('.product-type-grouped');
        product = {};
        qty = wrapper.find('.qty');
        qty.each(function (i, e) {
            var product_id = $(e).attr('name').replace(/quantity\[/g, '').replace(/]/g, '');
            product[product_id] = $(e).val();

            $(e).on('change', function () {
                var product_id = $(this).attr('name').replace(/quantity\[/g, '').replace(/]/g, '');
                product[product_id] = $(this).val();
            });
        });

    }
    if ($('.product-type-variable').length > 0) {
        var product_variation = false;

        type = 'variable';
        wrapper = $('.product-type-variable');
        qty = wrapper.find('.qty');
        quantity = qty.val();
        qty.on('change', function () {
            quantity = $(this).val();
        });
        product = wrapper.find('input[name=product_id]').val();

        jQuery('.variations_form').each(function () {
            jQuery(this).on('found_variation', function (event, variation) {
                product_variation = variation;
                price = variation.display_price;
            });
        });
    }

    $(document.body).on('found_variation', function (event, variation) {
        data['type'] = 'variable';
        data['variation_id'] = variation.variation_id;
        wrapper = $('.product-type-variable');
        qty = wrapper.find('.qty');
        data['qty'] = qty.val();
        qty.on('change', function () {
            data['qty'] = $(this).val();
            sendRequest(data);
        });
        data['product'] = wrapper.find('input[name=product_id]').val();
        data['price'] = variation.display_price;
        sendRequest(data);
    });


    $(window).on('load', function(){
        if ($('div.product.product-type-simple').length > 0) {
            data['type'] = 'simple';
            wrapper = $('.product-type-simple');
            qty = wrapper.find('.qty');
            data['qty'] = qty.val();
            data['product'] = wrapper.find('.single_add_to_cart_button').val();
            sendRequest(data);

            qty.on('change', function () {
                data['qty'] = $(this).val();
                sendRequest(data);
            });
        }

        if($('.cart_totals .wc-proceed-to-checkout').length > 0){
            checkoutUpdated()
        }

        if($('form.checkout.woocommerce-checkout').length > 0){
            data['price'] = $('.woocommerce-checkout-review-order-table .order-total .woocommerce-Price-amount').text();
            data['checkout'] = true;
            isCheckout = true;
            sendRequest(data);
        }

    });

    function checkoutUpdated(){
        data['price'] = $('.order-total .woocommerce-Price-amount').text();
        data['checkout'] = true;
        sendRequest(data);
    }

    $(document.body).on('applied_coupon', checkoutUpdated);
    $(document.body).on('removed_coupon', checkoutUpdated);
    $(document.body).on('updated_cart_totals', checkoutUpdated);


    function sendRequest(data) {
        $.post(
            params.ajax_url,
            {
                action: 'get_tkl_loan_data',
                nonce: params.nonce,
                data: data
            },
            ajaxResponse,
            'json'
        );
    }

    function ajaxResponse(response) {
        if (response.success) {
            if(isCheckout){
                var desc = "Starting at $" + response.data.min + "/mo. <span class='js-tkl-popup'>Learn More</span>";
                $('.payment_box.payment_method_turnkeylendergateway p').html(desc);
                populateContentPopup(response.data);
            }else{
                $('.js-loan-button').html("Starting at $" + response.data.min + "/mo. <span class='js-tkl-popup'>Learn More</span>");
                populateContentPopup(response.data);
            }
        }else{
            $('.tkl-popup-data').html(response.data);
        }
    }

    $(document.body).on('click', '.js-tkl-popup', function () {
        togglePopup();
    });

    function togglePopup() {
        $('.tkl-popup-content').toggle();
        $('.tkl-overlay').toggle();
    }

    function populateContentPopup(content){
        $('.js-data-rows').empty();
        popup.find('.js-tkl-popup-price').html('$' + content.loanAmount);

        $.each(content.body, function(i, e){
            var current_row = row.clone();
            current_row.addClass('js-row-' + i);

            current_row.find('.js-APR').html(number_format(e.APR, 2, '.', ',') + "%");
            current_row.find('.js-CreditProduct').html(e.CreditProduct);
            current_row.find('.js-Interest').html('$'+number_format(e.Interest, 2, '.', ','));
            current_row.find('.js-LoanAmount').html('$'+e.LoanAmount);
            current_row.find('.js-NumberOfPayments').html('$'+e.NumberOfPayments);
            current_row.find('.js-Total').html('$'+number_format(e.Total, 2, '.', ','));

            var periodic_amount = "<span>$" + e.PaymentAmount + "</span> / " + e.Periodicity.PeriodKind.toLowerCase() ;
            current_row.find('.js-PaymentAmount').html(periodic_amount);

            $('.js-data-rows').append(current_row);
        });

        row.remove();
    }

    number_format = function (number, decimals, dec_point, thousands_sep) {
        number = number.toFixed(decimals);

        var nstr = number.toString();
        nstr += '';
        x = nstr.split('.');
        x1 = x[0];
        x2 = x.length > 1 ? dec_point + x[1] : '';
        var rgx = /(\d+)(\d{3})/;

        while (rgx.test(x1))
            x1 = x1.replace(rgx, '$1' + thousands_sep + '$2');

        return x1 + x2;
    }
})(jQuery)