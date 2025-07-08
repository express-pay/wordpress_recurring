jQuery(document).ready(function () {
    let type_id = GetParameterValues('type_id');
    let result = GetParameterValues('result');
    let expressPayAccountNumber = GetParameterValues('ExpressPayAccountNumber');
    let expressPayInvoiceNo = GetParameterValues('ExpressPayInvoiceNo');
    let expressPayServiceId = GetParameterValues('ServiceId');
    let expressPayCustomerId = GetParameterValues('CustomerId');
    let signature = GetParameterValues('Signature');


    if (result !== undefined) {
        checkInvoice(result, signature, expressPayAccountNumber, expressPayInvoiceNo, expressPayCustomerId, type_id);
    }
    else if (type_id !== undefined) {
        let $paymentMethod = jQuery('.payment-method[data-id="' + type_id + '"]');
        
        if ($paymentMethod.length) {
            jQuery('.payment-method').removeClass('active');
            $paymentMethod.addClass('active');
            
            $paymentMethod.find('.payment-radio').prop('checked', true);
            
            jQuery('#selected-payment-method-type').val($paymentMethod.attr('data-type'));
            jQuery('#selected-payment-method-name').val($paymentMethod.attr('data-name'));
            
            let selectedType = $paymentMethod.attr('data-type');
            if (selectedType === 'card') {
                jQuery('#other-methods-details').hide();
                jQuery('#payment-notice').show();
                jQuery('#payment-recurrence').show();
                jQuery('#payment-details').show();
            } 
            else if (selectedType === 'epos') {
                jQuery('#other-methods-details').hide();
                jQuery('#payment-notice').hide();
                jQuery('#payment-recurrence').hide();
                jQuery('#payment-details').show();
            } 
            else if (selectedType === 'other') {
                jQuery('#payment-details').hide();
                jQuery('#other-methods-details').show();
            }
        }
    }
    
    jQuery('.payment-method').on('click', function () {
        let selectedPaymentMethod = jQuery(this).attr('data-id');
        let selectedPaymentMethodType = jQuery(this).attr('data-type');
        let selectedPaymentMethodName = jQuery(this).attr('data-name');
        let isRecurrent = jQuery(this).attr('data-recurrent') === '1';

        jQuery('.payment-method').removeClass('active');
        jQuery(this).addClass('active');

        jQuery('#selected-payment-method-type').val(selectedPaymentMethod);
        jQuery('#selected-payment-method-name').val(selectedPaymentMethodName);

        jQuery(this).find('.payment-radio').prop('checked', true);

        if (selectedPaymentMethodType === 'card') {
            // jQuery('#other-methods-details').hide();
            jQuery('#payment-notice').show();
            jQuery('#payment-details').show();

            if (isRecurrent) {
                jQuery('#payment-recurrence').show();
            } else {
                jQuery('#payment-recurrence').hide();
            }
        } 
        else if (selectedPaymentMethodType === 'epos' || selectedPaymentMethodType === 'erip') {
            // jQuery('#other-methods-details').hide();
            jQuery('#payment-notice').hide();
            jQuery('#payment-recurrence').hide();
            jQuery('#payment-details').show();
        } 
        // else if (selectedPaymentMethodType === 'other') {
        //     jQuery('#payment-details').hide();
        //     jQuery('#other-methods-details').show();
        // }
    });

    jQuery('.amount-btn:not(.custom-amount-btn)').on('click', function() {
        let amount = jQuery(this).data('value'); 
        jQuery('#expresspay-payment-sum').val(amount).hide();
        
        jQuery('.amount-btn').removeClass('active');
        jQuery(this).addClass('active');
    });
    
    // Обработчик кнопки "?? BYN"
    jQuery('#custom-amount-btn').on('click', function() {
        jQuery('#expresspay-payment-sum').val('').show().focus();
        jQuery('.amount-btn').removeClass('active');
        jQuery(this).addClass('active');
    });
    
    // Если пользователь вводит сумму вручную
    jQuery('#expresspay-payment-sum').on('input', function() {
        if (jQuery(this).is(':visible')) {
            jQuery('.amount-btn').removeClass('active');
            jQuery('#custom-amount-btn').addClass('active');
        }
    });

    jQuery('.button-group .payment-btn').click(function () {
        jQuery('.button-group .payment-btn').removeClass('active');
        jQuery(this).addClass('active');

        jQuery('#expresspay-payment-recurrency').val(jQuery(this).val());
    });

    jQuery('#payment-type-info').on('click', function() {
        jQuery('#info-modal').fadeIn();
    });

    jQuery('#close-info-modal').on('click', function() {
        jQuery('#info-modal').fadeOut();
    });

    jQuery(window).on('click', function(event) {
        if (jQuery(event.target).is('#info-modal')) {
            jQuery('#info-modal').fadeOut();
        }
    });
    
    jQuery('#btn_info_step').click(function () {
        jQuery('.field').removeClass('error');
        jQuery('.error-message').hide();
        
        let isValid = true;
        let type = jQuery('#selected-payment-method-type').val();
        let sum = jQuery('#expresspay-payment-sum').val();
        let agreementChecked = jQuery('#expresspay-user-agreement').is(':checked');
        let phone = jQuery('#expresspay-payment-phone').val().trim();
        let email = jQuery('#expresspay-payment-email').val().trim();
        let lastName = jQuery('#expresspay-payment-last-name').val().trim();
        let firstName = jQuery('#expresspay-payment-name').val().trim();
        
        if (type == undefined) {
            return false;
        }

        if (isNaN(sum) || sum < 1) {
            jQuery('#expresspay-payment-sum').closest('.field').addClass('error');
            jQuery('#expresspay-payment-sum').next('.error-message').show();
            isValid = false;
        }

        if (!lastName) {
            jQuery('#expresspay-payment-last-name').closest('.field').addClass('error');
            jQuery('#expresspay-payment-last-name').next('.error-message').show();
            isValid = false;
        }

        if (!firstName) {
            jQuery('#expresspay-payment-name').closest('.field').addClass('error');
            jQuery('#expresspay-payment-name').next('.error-message').show();
            isValid = false;
        }

        if (!email) {
            jQuery('#expresspay-payment-email').closest('.field').addClass('error');
            jQuery('#expresspay-payment-email').next('.error-message').show();
            isValid = false;
        } else if (!validateEmail(email)) {
            jQuery('#expresspay-payment-email').closest('.field').addClass('error');
            jQuery('#expresspay-payment-email').next('.error-message').text('Введите корректный email').show();
            isValid = false;
        }

        // if (!phone) {
        //     jQuery('#expresspay-payment-phone').closest('.field').addClass('error');
        //     jQuery('#expresspay-payment-phone').next('.error-message').show();
        //     isValid = false;
        // } else if (!validatePhone(phone)) {
        //     jQuery('#expresspay-payment-phone').closest('.field').addClass('error');
        //     jQuery('#expresspay-payment-phone').next('.error-message').text('Введите телефон в формате +375XXXXXXXXX').show();
        //     isValid = false;
        // }

        if (!isValid) {
            return false;
        }
        
        if (!agreementChecked) {
            return false;
        }
                
        getFormData();
    });
    
    function validatePhone(phone) {
        const phonePattern = /^\+375\d{9}$/;
        return phonePattern.test(phone);
    }

    function validateEmail(email) {
        const emailPattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
        return emailPattern.test(email);
    }

    jQuery('.response_step').on('click', '#unbind_card_btn', function () {
        unbindCard(type_id, expressPayCustomerId, expressPayServiceId, signature);
    });

    jQuery('#replay_btn').click(function () {
        let url = window.location.href;

        let expressPayAccountNumber = GetParameterValues('ExpressPayAccountNumber');
        let expressPayInvoiceNo = GetParameterValues('ExpressPayInvoiceNo');
        let signature = GetParameterValues('Signature');
        let type_id = GetParameterValues('type_id');

        url = url.substring(0, url.indexOf('type_id') - 1);

        window.location.href = url;
    });   

    function getFormData() {
        let type_id = jQuery('#selected-payment-method-type').val();
        let amount = jQuery('#expresspay-payment-sum').val();

        let recurrency = jQuery('#expresspay-payment-recurrency').val();

        let last_name = jQuery('#expresspay-payment-last-name').val();
        let first_name = jQuery('#expresspay-payment-name').val();
        let patronymic = jQuery('#expresspay-payment-secondname').val();
        let info = jQuery('#expresspay-payment-purpose').val();
        let email = jQuery('#expresspay-payment-email').val();
        let phone = jQuery('#expresspay-payment-phone').val();
        let visibility = jQuery('#expresspay-show-agreement').is(':checked') ? 1 : 0;

        let url = jQuery('#ajax-url').val();

        jQuery(function ($) {
            $.ajax({
                type: "GET",
                url: url,
                data: {
                    action: 'get_form_data',
                    type_id: type_id,
                    amount: amount,
                    recurrency: recurrency,
                    last_name: last_name,
                    first_name: first_name,
                    patronymic: patronymic,
                    info: info,
                    email: email,
                    phone: phone,
                    visibility: visibility,
                    url: window.location.href
                },
                success: function (response) {
                    response = $.parseJSON(response);

                    setFormValue(response);

                    jQuery('#expresspay-payment-form').submit();
                }
            });
        });
    }

    function setFormValue(options) {
        jQuery('#expresspay-payment-form').attr('action', options.Action);
        jQuery('#expresspay-payment-service-id').val(options.ServiceId);
        jQuery('#expresspay-payment-account-no').val(options.AccountNo);
        jQuery('#expresspay-payment-write-off-period').val(options.WriteOffPeriod);
        jQuery('#expresspay-payment-amount').val(options.Amount);
        jQuery('#expresspay-payment-currency').val(options.Currency);
        jQuery('#expresspay-payment-info').val(options.Info);
        jQuery('#expresspay-payment-surname').val(options.Surname);
        jQuery('#expresspay-payment-first-name').val(options.FirstName);
        jQuery('#expresspay-payment-patronymic').val(options.Patronymic);
        jQuery('#expresspay-payment-is-name-editable').val(options.IsNameEditable);
        jQuery('#expresspay-payment-is-address-editable').val(options.IsAddressEditable);
        jQuery('#expresspay-payment-is-amount-editable').val(options.IsAmountEditable);
        jQuery('#expresspay-payment-email-notification').val(options.EmailNotification);
        jQuery('#expresspay-payment-sms-phone').val(options.SmsPhone);
        jQuery('#expresspay-payment-signature').val(options.Signature);
        jQuery('#expresspay-payment-return-url').val(options.ReturnUrl);
        jQuery('#expresspay-payment-fail-url').val(options.FailUrl);
    }

    function GetParameterValues(param) {
        let params = new URLSearchParams(window.location.search);
        return params.has(param) ? params.get(param) : undefined;
    }

    function checkInvoice(result, signature, account_no, invoice_no, customer_id, type_id) {
        let url = jQuery('#ajax-url').val();
    
        jQuery('#info_step').hide();
        jQuery('#replay_btn').hide();
        
        jQuery('#response_step').show();
        jQuery(function ($) {
            $.ajax({
                type: "GET",
                url: url,
                data: {
                    action: 'check_invoice',
                    result: result,
                    type_id: type_id,
                    signature: signature,
                    account_no: account_no,
                    invoice_no: invoice_no,
                    customer_id: customer_id
                },
                success: function (data) {
                    let responseData;
                    try {
                        responseData = JSON.parse(data);
                    } catch (e) {
                        jQuery('#response_message').html('Ошибка обработки ответа сервера. Попробуйте позже.');
                        jQuery('#replay_btn').show();
                        return;
                    }
                    
                    if (responseData.status === "success") {
                        jQuery('#replay_btn').show().html('Продолжить');
                    } else {
                        jQuery('#replay_btn').show();
                    }
                    
                    jQuery('#response_message').html(responseData.message);
                },
                error: function (xhr, status, error) {
                    jQuery('#response_message').html('Ошибка соединения с сервером. Проверьте подключение и попробуйте снова.');
                    jQuery('#replay_btn').show();
                }
            });
        });
    }

    function unbindCard(type_id, customer_id, service_id, signature) {
        let url = jQuery('#ajax-url').val();
    
        jQuery('#response_message').html('');

        jQuery(function ($) {
            $.ajax({
                type: "GET",
                url: url,
                data: {
                    action: 'unbind_card',
                    type_id: type_id,
                    service_id: service_id,
                    customer_id: customer_id,
                    signature: signature
                },
                success: function (data) {
                    let responseData;
                    try {
                        responseData = JSON.parse(data);
                    } catch (e) {
                        jQuery('#response_message').html('Ошибка обработки ответа сервера. Попробуйте позже.');
                        jQuery('#replay_btn').show();
                        return;
                    }
                    
                    const status = responseData.status || 'fail';
                    const message = responseData.message || 'Неизвестная ошибка.';

                    jQuery('#response_message').html(message);

                    jQuery('#replay_btn').show().html('Продолжить');
                },
                error: function (xhr, status, error) {
                    jQuery('#response_message').html('Ошибка соединения с сервером. Проверьте подключение и попробуйте снова.');
                    jQuery('#replay_btn').show();
                }
            });
        });
    }

    jQuery('#load-more-payments').click(function() {
        const $button = jQuery(this);
        const currentState = $button.data('state');
        const groupSize = parseInt($button.data('group-size'));
        const visibleCount = parseInt($button.data('visible-count'));
        
        let nextGroup;
        if (currentState === 'initial') {
            nextGroup = 1;
            $button.data('state', 'group1');
        } else {
            const currentGroupNum = parseInt(currentState.replace('group', ''));
            nextGroup = currentGroupNum + 1;
            $button.data('state', 'group' + nextGroup);
        }
        
        const groupSelector = '.payment-card.hidden[data-group="group' + nextGroup + '"]';
        const $nextGroupCards = jQuery(groupSelector);
        
        $nextGroupCards.removeClass('hidden').addClass('visible');
        
        const nextNextGroup = nextGroup + 1;
        const hasMoreGroups = jQuery('.payment-card.hidden[data-group="group' + nextNextGroup + '"]').length > 0;
        
        if (!hasMoreGroups) {
            $button.hide();
        }
    });
});