<?php


class ExpressPayPayment
{

    /**
     * Рендеринг шорткода
     */
    static function payment_callback($atts, $content = null)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . "expresspay_options";

        $response = $wpdb->get_results("SELECT id, name, type, options, isactive FROM $table_name where isactive = 1");

        ob_start();

        ExpressPay::plugin_client_styles();

        if (count($response) == 0) {
            ExpressPay::view("payment_method_empty", array('response' => $response));
        } else {
            ExpressPay::view("payment_form", array('atts' => $atts, 'response' => $response, 'ajax_url' => admin_url('admin-ajax.php')));
        }

        return ob_get_clean();
    }

    /**
     * Рендеринг шорткода для истории платежей
     */
    static function payment_history_callback($atts, $content = null) {
        global $wpdb;
    
        $payments = $wpdb->get_results(
            "SELECT payment_no, amount, dateofpayment, payer 
            FROM " . EXPRESSPAY_TABLE_PAYMENTS . " 
            WHERE visibility = 1 
            ORDER BY dateofpayment DESC 
            LIMIT 25"
        );

        ob_start();

        ExpressPay::plugin_client_styles();
    
        if (empty($payments)) {
            ExpressPay::view("payment_history_empty", array());
        } else {
            ExpressPay::view("payment_history", array('payments' => $payments));
        }
    
        return ob_get_clean();
    }

    /**
     * Получение данных для заполнения формы
     * 
     * @return json Ответ на клиента
     */
    static function get_form_data()
    {
        $type_id = sanitize_text_field($_REQUEST['type_id']);

        global $wpdb;

        $query = $wpdb->prepare("SELECT id, name, type, options, isactive FROM " . EXPRESSPAY_TABLE_PAYMENT_METHOD_NAME . " WHERE id = %d", $type_id);
        $response = $wpdb->get_row($query);

        if ($response && $response->isactive == 1) {
            $max_id = $wpdb->get_row("SELECT max(id) as id FROM " . EXPRESSPAY_TABLE_INVOICES_NAME);
            $numeric_part = $max_id && $max_id->id ? $max_id->id + 1 : 1;
            $account_no = date('md') . '-' . $numeric_part;

            $options = json_decode($response->options);

            $amount = sanitize_text_field($_REQUEST['amount'] ?? '');
            $recurrency = sanitize_text_field($_REQUEST['recurrency'] ?? '');

            $last_name = sanitize_text_field($_REQUEST['last_name'] ?? '');
            $first_name = sanitize_text_field($_REQUEST['first_name'] ?? '');
            $patronymic = sanitize_text_field($_REQUEST['patronymic'] ?? '');
            $email = sanitize_email($_REQUEST['email'] ?? '');
            $phone = sanitize_text_field($_REQUEST['phone'] ?? '');
            $visibility = isset($_REQUEST['visibility']) ? (int)$_REQUEST['visibility'] : 0;
            $url = sanitize_text_field($_REQUEST['url'] ?? '');

            $info = 'Добровольные взносы';

            $noperiod = 0;
            $everyday = 1;
            $everyweek = 2;
            $everymonth = 3;
            $every3month = 4;
            $every6month = 5;
            $everyyear = 6;

            $BYN = 933;
            $USD = 840;
            $EUR = 978;
            $RUB = 643;

            if ($options->SendSms) {
                $client_phone = preg_replace('/[^0-9]/', '', $phone);
                $client_phone = substr($client_phone, -9);
                $client_phone = "375$client_phone";
            } else {
                $client_phone = $phone;
            }

            $signatureParams = array(
                "Token" => $options->Token,
                "ServiceId" => $options->ServiceId,
                "AccountNo" => $account_no,
                "Amount" => $amount,
                "Currency" => $BYN,
                "Info" => $info,
                "Surname" => $last_name,
                "FirstName" => $first_name,
                "Patronymic" => $patronymic,
                "EmailNotification" => $email,
                "SmsPhone" => $client_phone,
                "Visibility" => $visibility,
                "ReturnType" => "redirect",
                "ReturnUrl" => add_query_arg(['type_id' => $type_id, 'result' => 1], $url),
                "FailUrl" => add_query_arg(['type_id' => $type_id, 'result' => 0], $url),
                "Action" => $options->TestMode == 1 ? $options->SandboxUrl : $options->ApiUrl
            );

            if ($response->type == 'card') {
                if($recurrency == "true") {
                    $signatureParams["Action"] .= "recurringpayment/bind";
                    $signatureParams["WriteOffPeriod"] = $everymonth;
                    $signatureParams['Signature'] = ExpressPay::computeSignature($signatureParams, $options->SecretWord, 'bind-card');
                    $wpdb->insert(
                        EXPRESSPAY_TABLE_INVOICES_NAME,
                        array(
                            'account_no' => $account_no,
                            'recurrence' => TRUE,
                            'amount' => $amount,
                            'options' => json_encode($signatureParams),
                            'options_id' => $type_id
                        ),
                        array('%s', '%d', '%f', '%s', '%d')
                    );
                } else {
                    $signatureParams["Action"] .= "web_cardinvoices";
                    $signatureParams['Signature'] = ExpressPay::computeSignature($signatureParams, $options->SecretWord, 'add-webcard-invoice');
                    
                    $wpdb->insert(
                        EXPRESSPAY_TABLE_INVOICES_NAME,
                        array(
                            'account_no' => $account_no,  
                            'amount' => $amount,
                            'options' => json_encode($signatureParams),
                            'options_id' => $type_id
                        ),
                        array('%s', '%f', '%s', '%d')
                    );
                }
            } else {
                $signatureParams["IsNameEditable"] = $options->CanChangeName;
                $signatureParams["IsAddressEditable"] = $options->CanChangeAddress;
                $signatureParams["IsAmountEditable"] = $options->CanChangeAmount;
                $signatureParams["Action"] .= "web_invoices";
                $signatureParams['Signature'] = ExpressPay::computeSignature($signatureParams, $options->SecretWord, 'add-web-invoice');

                $wpdb->insert(
                    EXPRESSPAY_TABLE_INVOICES_NAME,
                    array(
                        'account_no' => $account_no,
                        'amount' => $amount,
                        'options' => json_encode($signatureParams),
                        'options_id' => $type_id
                    ),
                    array('%s', '%f', '%s', '%d')
                );
            }

            unset($signatureParams['Token']);

            echo json_encode($signatureParams);
        }
        wp_die();
    }

    /**
     * Функция обработки ответа от API
     * 
     * @return json Ответ на клиента
     */
    static function check_invoice()
    {
        $result = (int) sanitize_text_field($_REQUEST['result'] ?? '');
        $type_id = (int) sanitize_text_field($_REQUEST['type_id'] ?? '');
        $signature = sanitize_text_field($_REQUEST['signature'] ?? '');
        $account_no = sanitize_text_field($_REQUEST['account_no'] ?? '');
        $invoice_no = (int) sanitize_text_field($_REQUEST['invoice_no'] ?? '');
        $customer_id = (int) sanitize_text_field($_REQUEST['customer_id'] ?? '');
        
        global $wpdb;
        $callback = array();

        $query = $wpdb->prepare("SELECT options FROM " . EXPRESSPAY_TABLE_PAYMENT_METHOD_NAME . " WHERE id = %d", $type_id);
        $response = $wpdb->get_row($query);
        $options = json_decode($response->options);

        if (empty($customer_id)) {
            if ($result) {
                switch ($options->Type) {
                    case 'erip':
                        $message = self::getEripMessage($options, $invoice_no, $account_no);
                        break;
                    case 'epos':
                        $message = self::getEposMessage($options, $invoice_no, $account_no);
                        break;
                    case 'card':
                        $message = __('Оплата прошла успешно. Огромное спасибо за Вашу поддержку!', 'wordpress_expresspay');
                        break;
                }
                $callback["status"] = "success";
                $callback["message"] = $message;
            } else {
                $callback["status"] = "fail";
                $callback["message"] = __('Ошибка платежа. Попробуйте позже или свяжите с технической поддержкой.', 'wordpress_expresspay');
            }
        } 
        else {
            if ($result == 1) {
                $message = __('Карта привязана. Ежемесячные списания успешно подключены. Огромное спасибо за Вашу поддержку!', 'wordpress_expresspay');
                $callback["status"] = "success";
                $callback["message"] = $message;
            } else if ($result == 2) {
                $message = ExpressPay::getCardInfoMessage($options->Token, $options->ServiceId, $customer_id, $options->SecretWord);
                
                if (!$message) {
                    $callback["status"] = "fail";
                    $callback["message"] = __('Ошибка. Не удалось получить информацию о карте. Попробуйте позже или свяжите с технической поддержкой.', 'wordpress_expresspay');
                } else {
                    $callback["status"] = "success";
                    $callback["message"] = $message;
                }
            } else {
                $callback["status"] = "fail";
                $callback["message"] = __('Ошибка привязки карты. Попробуйте позже.', 'wordpress_expresspay');
            }
        }

        echo json_encode($callback);
        wp_die();
    }

    /**
     * Функция отвязки карты
     *
     * @return json Ответ на клиента
     */
    static function unbind_card()
    {
        $type_id     = (int) sanitize_text_field($_REQUEST['type_id'] ?? '');
        $customer_id = (int) sanitize_text_field($_REQUEST['customer_id'] ?? '');
        $service_id  = (int) sanitize_text_field($_REQUEST['service_id'] ?? '');
        $signature   = sanitize_text_field($_REQUEST['signature'] ?? '');

        global $wpdb;
        $callback = array();
        $errorMessage = __('Не удалось отвязать карту. Попробуйте позже или свяжитесь с технической поддержкой.', 'wordpress_expresspay');

        $query = $wpdb->prepare( "SELECT options FROM " . EXPRESSPAY_TABLE_PAYMENT_METHOD_NAME . " WHERE id = %d", $type_id);
        $response = $wpdb->get_row($query);

        if (!$response || empty($response->options)) {
            $callback["status"] = "fail";
            $callback["message"] = $errorMessage;
            error_log("Ошибка запроса параметоров метода оплаты. TypeId = $type_id");
            echo json_encode($callback);
            wp_die();
        }

        $options = json_decode($response->options);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $callback["status"] = "fail";
            $callback["message"] = $errorMessage;
            error_log("Ошибка декодирования параметоров метода оплаты. TypeId = $type_id");
            echo json_encode($callback);
            wp_die();
        }

        if ($service_id !== (int) $options->ServiceId) {
            $callback["status"] = "fail";
            $callback["message"] = $errorMessage;
            error_log("Ошибка. Не совпадает ServiceId. ServiceId: $service_id");
            echo json_encode($callback);
            wp_die();
        }
        
        $result = ExpressPay::unbindCard($options->Token, $service_id, $customer_id, $options->SecretWord);

        if (!$result) {
            $callback["status"] = "fail";
            $callback["message"] = $errorMessage;
            error_log("Ошибка. Метод unbindCard() вернул false. ServiceId: $service_id. CustomerId: $customer_id");
        } else {
            $callback["status"] = "success";
            $callback["message"] = "Ваша карта успешно отвязана.";
        }

        echo json_encode($callback);
        wp_die();
    }

    /**
     * Получение информационного сообщения для способа оплаты ЕРИП
     * 
     * @param object $options    Настройки интеграции
     * @param int    $invoice_no Номер счета сервиса Экспресс Платежи
     * @param string $account_no Номер счета, присвоенный интеграцией
     * 
     * @return string $message Сформированное сообщение
     */
    static function getEripMessage($options, $invoice_no, $account_no)
    {
        $qr_code_link = ExpressPay::getQrCodeLink($options->Token, $invoice_no, $options->SecretWord);
        $qr_code = ExpressPay::getQrCode($options->Token, $invoice_no, $options->SecretWord);

        $message_success_erip = __('<h3>Счёт добавлен в систему ЕРИП для оплаты</h3>
            <h4>Номер вашего счета: ##order_id##</h4>
            <div style="text-align: center; margin-bottom: 20px;">
                <div>##qr_code##</div>
                <div><a href="##erip_link##" target="_blank" class="link-button">Перейти в банкинг</a></div>
            </div>
            <div style="text-align: left;">
                <p>Вы можете легко сделать платеж в приложении вашего банка — просто отсканируйте <strong>QR-код</strong> или нажмите кнопку <strong>«Перейти в банкинг»</strong>.</p>
                <p>Если данные способы вам не подходят, вы можете сделать пожертвование в любой системе, позволяющей производить оплату через ЕРИП:</p>
                <ol>
                    <li>В списке услуг ЕРИП выберите: <strong>Сервис E-POS → E-POS – оплата товаров и услуг</strong>.</li>
                    <li>Введите код <strong>##epos_code##</strong> и нажмите «Продолжить».</li>
                    <li>Проверьте корректность информации.</li>
                    <li>Произведите пожертвование.</li>
                </ol>
            </div>', 'wordpress_expresspay');

        $message_success_erip = str_replace("##order_id##", $account_no, $message_success_erip);
        $message_success_erip = str_replace("##erip_path##", $options->EripPath, $message_success_erip);

        if (!empty($qr_code)) {
            $message_success_erip = str_replace("##qr_code##", '<img src="data:image/jpeg;base64,' . $qr_code . '" width="200" height="200" alt="QR-код для оплаты"/>', $message_success_erip);
        } else {
            $message_success_erip = str_replace("##qr_code##", '<p>QR-код временно недоступен.</p>', $message_success_erip);
        }

        if (!empty($qr_code_link)) {
            $message_success_erip = str_replace("##erip_link##", $qr_code_link, $message_success_erip);
        } else {
            $message_success_erip = str_replace("##erip_link##", '#', $message_success_erip);
        }

        return $message_success_erip;
    }

    

    /**
     * Получение информационного сообщения для способа оплаты E-POS
     * 
     * @param object $options    Настройки интеграции
     * @param int    $invoice_no Номер счета сервиса Эксперсс Платежи
     * @param string $account_no Номер счета, присвоенный интеграцией
     * 
     * @return string $message Сформированное сообщение с QR-кодом и кнопкой для перехода в банкинг
     */
    static function getEposMessage($options, $invoice_no, $account_no)
    {
        $qr_code_link = ExpressPay::getQrCodeLink($options->Token, $invoice_no, $options->SecretWord);

        $qr_code = ExpressPay::getQrCode($options->Token, $invoice_no, $options->SecretWord);

        $message_success_epos = __('<h3>Счет добавлен в систему E-POS для оплаты</h3>
            <h4>Номер вашего счета: ##epos_code##</h4>
            <div style="text-align: center; margin-bottom: 20px;">
                <div>##qr_code##</div>
                <div><a href="##epos_link##" target="_blank" class="link-button">Перейти в банкинг</a></div>
            </div>
            <div style="text-align: left;">
                <p>Вы можете легко сделать платеж в приложении вашего банка — просто отсканируйте <strong>QR-код</strong> или нажмите кнопку <strong>«Перейти в банкинг»</strong>.</p>
                <p>Если данные способы вам не подходят, вы можете сделать пожертвование в любой системе, позволяющей производить оплату через ЕРИП:</p>
                <ol>
                    <li>В списке услуг ЕРИП выберите: <strong>Сервис E-POS → E-POS – оплата товаров и услуг</strong>.</li>
                    <li>Введите код <strong>##epos_code##</strong> и нажмите «Продолжить».</li>
                    <li>Проверьте корректность информации.</li>
                    <li>Произведите пожертвование.</li>
                </ol>
            </div>', 'wordpress_expresspay');
    

        $epos_code  = $options->ServiceProviderCode . "-";
        $epos_code .= $options->ServiceEposCode . "-";
        $epos_code .= $account_no;

        if (!empty($qr_code)) {
            $message_success_epos = str_replace("##qr_code##", '<img src="data:image/jpeg;base64,' . $qr_code . '" width="200" height="200"/>', $message_success_epos);
        } else {
            $message_success_epos = str_replace("##qr_code##", 'QR-код не найден.', $message_success_epos);
        }

        if (!empty($qr_code_link)) {
            $message_success_epos = str_replace("##epos_link##", $qr_code_link, $message_success_epos);
        } else {
            $message_success_epos = str_replace("##epos_link##", '#', $message_success_epos);
        }

        $message_success_epos = str_replace("##epos_code##", $epos_code, $message_success_epos);

        return $message_success_epos;
    }


    /**
     * Получение и обработка уведомления
     */
    static function receive_notification()
    {
        // Путь к лог-файлу
        $log_file = WP_CONTENT_DIR . '/expresspay-notifications.log';
        
        $log_data = [
            'time' => current_time('mysql'),
            'ip' => $_SERVER['REMOTE_ADDR'],
            'method' => $_SERVER['REQUEST_METHOD']
        ];
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = isset($_REQUEST['Data']) ? stripcslashes(sanitize_text_field($_REQUEST['Data'])) : '';
            $decoded_data = json_decode($data, true);
            
            $log_data['notification_data'] = $decoded_data;
            
            file_put_contents(
                $log_file,
                json_encode($log_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n",
                FILE_APPEND
            );
            
            if (!empty($decoded_data['CmdType'])) {
                switch ($decoded_data['CmdType']) {
                    case '1': // Новый платеж (разовый)
                        $result = ExpressPay::addNewPayment((object)$decoded_data);
                        if ($result !== 'ok') {
                            error_log("Ошибка при обработке CmdType 1: $result. Данные: $decoded_data");
                        } else {
                            ExpressPay::updateInvoiceDateOfPayment($decoded_data['AccountNo'], $decoded_data['Created']);
                        }
                        break;
                        
                    case '2': // Отмена платежа
                        $result = ExpressPay::deletePayment($decoded_data['PaymentNo']);
                        if (!$result) {
                            error_log("Ошибка при обработке CmdType 2. Данные: $decoded_data");
                        }
                        break;
                        
                    case '3': // Изменение статуса обычного счета
                        $result = ExpressPay::updateInvoiceStatus($decoded_data['AccountNo'], $decoded_data['Status']);
                        if (!$result) {
                            error_log("Ошибка при обработке CmdType 3. Данные: $decoded_data");
                        }
                        break;
                        
                    case '6': // Статус привязки карты (рекуррентный платеж)
                        $result = ExpressPay::handleRecurrentInvoice(
                            $decoded_data['AccountNo'],
                            $decoded_data['CustomerId'],
                            $decoded_data['Status']
                        );
                        if (!$result) {
                            error_log("Ошибка при обработке CmdType 6. Данные: $decoded_data");
                        }
                        break;
                        
                    case '7': // Рекуррентный платёж
                        $result = ExpressPay::addNewRecPayment((object)$decoded_data);
                        if ($result !== 'ok') {
                            error_log("Ошибка при обработке CmdType 7: $result. Данные: $decoded_data");
                        } else {
                            ExpressPay::updateRecInvoiceDateOfPayment($decoded_data['CustomerId'], $decoded_data['Created']);
                        }
                        break;
                        
                    default:
                        error_log("Неизвестный CmdType: " . $decoded_data['CmdType']);
                        break;
                }
            }
            
            wp_send_json_success(['message' => 'Notification received']);
        }
        
        status_header(405);
        wp_send_json_error(['message' => 'Invalid request']);
    }
}
