<?php

class ExpressPay
{
    /**
     * 
     * Получение вью из файла.
     * 
     * @param string $name Название файла вью
     * @param array  $args Аргументы для передачи на вью
     * 
     */
    public static function view($name, array $args = array())
    {
        foreach ($args as $key => $val) {
            $$key = $val;
        }

        $file = EXPRESSPAY__PLUGIN_DIR . 'views/' . $name . '.php';

        include($file);
    }

    /**
     * 
     * Подключение стилей и скирптов в административной части интеграции
     * 
     */
    static function plugin_admin_styles()
    {
        //CSS
        wp_enqueue_style('pluginAdminCssEp', plugins_url('css/styles.css', __FILE__), array(), get_plugin_version());
        wp_enqueue_style('pluginAdminCssBst', plugins_url('css/bootstrap.min.css', __FILE__), array(), get_plugin_version());
        wp_enqueue_style('pluginAdminCss', plugins_url('css/admin.css', __FILE__), array(), get_plugin_version());
        
        //JS
        wp_enqueue_script('pluginAdminJsJsd', plugins_url('js/popper.min.js', __FILE__));
        wp_enqueue_script('pluginAdminJsBst', plugins_url('js/bootstrap.min.js', __FILE__));
        wp_enqueue_script('pluginAdminJs', plugins_url('js/admin.js', __FILE__), array('jquery'), get_plugin_version());
    }

    /**
     * 
     * Подключение стилей и скриптов в клиентской части интеграции
     * 
     */
    static function plugin_client_styles()
    {
        //CSS
        wp_enqueue_style('pluginPaymentCss', plugins_url('css/payment.css', __FILE__), array(), get_plugin_version());

        //JS
        wp_enqueue_script('pluginPaymentJs', plugins_url('js/shortcode.js', __FILE__), array('jquery'), get_plugin_version());
    }

    /**
     * 
     * Формирование цифровой подписи
     * 
     * @param array  $signatureParams Список передаваемых параметров
     * @param string $secretWord      Секретное слово
     * @param string $method          Метод формирования цифровой подписи
     * 
     * @return string $hash           Сформированная цифровая подпись
     * 
     */
    public static function computeSignature($signatureParams, $secretWord, $method)
    {
        $normalizedParams = array_change_key_case($signatureParams, CASE_LOWER);
        $mapping = array(
            "add-invoice" => array(
                "token",
                "accountno",
                "amount",
                "currency",
                "expiration",
                "info",
                "surname",
                "firstname",
                "patronymic",
                "city",
                "street",
                "house",
                "building",
                "apartment",
                "isnameeditable",
                "isaddresseditable",
                "isamounteditable"
            ),
            "get-details-invoice" => array(
                "token",
                "id"
            ),
            "cancel-invoice" => array(
                "token",
                "id"
            ),
            "status-invoice" => array(
                "token",
                "id"
            ),
            "get-list-invoices" => array(
                "token",
                "from",
                "to",
                "accountno",
                "status"
            ),
            "get-list-payments" => array(
                "token",
                "from",
                "to",
                "accountno"
            ),
            "get-details-payment" => array(
                "token",
                "id"
            ),
            "add-card-invoice"  =>  array(
                "token",
                "accountno",
                "expiration",
                "amount",
                "currency",
                "info",
                "returnurl",
                "failurl",
                "language",
                "pageview",
                "sessiontimeoutsecs",
                "expirationdate"
            ),
            "card-invoice-form"  =>  array(
                "token",
                "cardinvoiceno"
            ),
            "status-card-invoice" => array(
                "token",
                "cardinvoiceno",
                "language"
            ),
            "reverse-card-invoice" => array(
                "token",
                "cardinvoiceno"
            ),
            "get-qr-code"          => array(
                "token",
                "invoiceid",
                "viewtype",
                "imagewidth",
                "imageheight"
            ),
            "add-web-invoice"      => array(
                "token",
                "serviceid",
                "accountno",
                "amount",
                "currency",
                "expiration",
                "info",
                "surname",
                "firstname",
                "patronymic",
                "city",
                "street",
                "house",
                "building",
                "apartment",
                "isnameeditable",
                "isaddresseditable",
                "isamounteditable",
                "emailnotification",
                "smsphone",
                "returntype",
                "returnurl",
                "failurl"
            ),
            "add-webcard-invoice" => array(
                "token",
                "serviceid",
                "accountno",
                "expiration",
                "amount",
                "currency",
                "info",
                "returnurl",
                "failurl",
                "language",
                "sessiontimeoutsecs",
                "expirationdate",
                "returntype"
            ),
            "response-web-invoice" => array(
                "token",
                "expresspayaccountnumber",
                "expresspayinvoiceno"
            ),
            "bind-card" => array(
                "token",
                "serviceid",
                "writeoffperiod",
                "amount",
                "currency",
                "info",
                "returnurl",
                "failurl",
                "language",
                "returntype"
            ),
            "response-bind-card" => array(
                "token",
                "expresspayaccountnumber",
                "customerid"
            ),
            "unbind-card" => array(
                "token",
                "serviceid",
                "customerid"
            ),
            "approve-unbind-card" => array(
                "token",
                "customerid",
                "serviceid"
            ),
            "notification" => array(
                "data"
            )
        );
        $apiMethod = $mapping[$method];
        $result = "";
        foreach ($apiMethod as $item) {
            $result .= $normalizedParams[$item];
        }
        $hash = strtoupper(hash_hmac('sha1', $result, $secretWord));
        return $hash;
    }

    /**
     * Обновление статуса счета с проверкой рекуррентности
     * 
     * @param string $account_no Номер счета
     * @param int $status Новый статус счета
     * @return bool Успешность выполнения операции
     */
    public static function updateInvoiceStatus($account_no, $status)
    {
        global $wpdb;
        
        $is_recurrent = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT recurrence FROM " . EXPRESSPAY_TABLE_INVOICES_NAME . " 
                WHERE account_no = %s",
                $account_no
            )
        );
        
        if ($is_recurrent === null) {
            return false;
        }
        
        if ($is_recurrent) {
            return true;
        }
        
        $result = $wpdb->update(
            EXPRESSPAY_TABLE_INVOICES_NAME,
            ['status' => $status],
            ['account_no' => $account_no],
            ['%d'],  
            ['%s']
        );
        
        return $result !== false;
    }

    /**
     * Создание/обновление рекуррентного счета
     * 
     * @param string $account_no Номер счета от агрегатора
     * @param int $customer_id ID привязки карты
     * @param int $status Статус
     * @return bool Успешность выполнения операции
     */
    public static function handleRecurrentInvoice($account_no, $customer_id, $status) 
    {
        global $wpdb;
        
        $exists_by_customer_id = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . EXPRESSPAY_TABLE_INVOICES_NAME . " 
            WHERE customer_id = %d",
            $customer_id
        ));

        if (!$exists_by_customer_id) {
            $exists_by_account_no = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM " . EXPRESSPAY_TABLE_INVOICES_NAME . " 
                WHERE account_no = %s",
                $account_no
            ));

            if (!$exists_by_account_no) {
                return false;
            } else {
                $result = $wpdb->update(
                    EXPRESSPAY_TABLE_INVOICES_NAME,
                    [
                        'status' => $status,
                        'customer_id' => $customer_id
                    ],
                    ['account_no' => $account_no],
                    ['%d', '%d'],
                    ['%s']
                );
            }
        } else {
            $result = $wpdb->update(
                EXPRESSPAY_TABLE_INVOICES_NAME,
                ['status' => $status],
                ['customer_id' => $customer_id],
                ['%d'],
                ['%d']
            );
        }

        return $result !== false;
    }

    /**
     * 
     * Обновление даты оплаты счета
     * 
     * @param string $account_no        Номер счета
     * @param string $dateofpayment     Дата оплаты счета
     * @return bool Успешность выполнения операции
     * 
     */
    public static function updateInvoiceDateOfPayment($account_no, $dateofpayment)
    {
        global $wpdb;

        $is_recurrent = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT recurrence FROM " . EXPRESSPAY_TABLE_INVOICES_NAME . " 
                WHERE account_no = %s",
                $account_no
            )
        );
        
        if ($is_recurrent === null) {
            return false;
        }
        
        if ($is_recurrent) {
            return true;
        }

        return $wpdb->update(
            EXPRESSPAY_TABLE_INVOICES_NAME,
            ['dateofpayment' => $dateofpayment],
            ['account_no' => $account_no],
            ['%s'],
            ['%s']
        ) !== false;
    }

    
    /**
     * 
     * Обновление даты оплаты счета
     * 
     * @param string $account_no        Номер счета
     * @param string $dateofpayment     Дата оплаты счета
     * @return bool Успешность выполнения операции
     * 
     */
    public static function updateRecInvoiceDateOfPayment($customer_id, $dateofpayment)
    {
        global $wpdb;

        return $wpdb->update(
            EXPRESSPAY_TABLE_INVOICES_NAME,
            ['dateofpayment' => $dateofpayment],
            ['customer_id' => $customer_id],
            ['%s'],
            ['%d']
        ) !== false;
    }


    /**
     * Добавление записи о новом успешном платеже.
     * 
     * Проверяет, существует ли уже платеж с таким номером — в случае дубликата возвращает 'duplicate'.
     * Если счет рекуррентный — возврат 'recurrent_skip' без вставки.
     * В случае ошибки вставки — 'insert_error'.
     * При успешной вставке — 'ok'.
     * 
     * @param object $data Объект с данными уведомления
     * @return string Статус выполнения: 'duplicate', 'recurrent_skip', 'not_found', 'insert_error', 'ok'
     */
    public static function addNewPayment($data)
    {
        global $wpdb;

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM " . EXPRESSPAY_TABLE_PAYMENTS . " 
                WHERE payment_no = %d", 
                $data->PaymentNo
            )
        );

        if ($exists) {
            error_log("Платеж с номером {$data->PaymentNo} уже существует");
            return 'duplicate';
        }

        $invoice_data = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT options, recurrence FROM " . EXPRESSPAY_TABLE_INVOICES_NAME . " 
                WHERE account_no = %s",
                $data->AccountNo
            )
        );
        
        if (!$invoice_data) {
            error_log("Не найден счет для accountNo={$data->AccountNo}");
            return 'not_found';
        }

        $invoice_options = $invoice_data->options ?? null;
        $is_recurrent = !empty($invoice_data->recurrence);

        if ($is_recurrent) {
            return 'recurrent_skip';
        }

        if ($invoice_options) {
            $options = json_decode($invoice_options, true);

            $visibility = $options['Visibility'] ?? false;
            $emailNotification = $options['EmailNotification'] ?? false;

            $surname = $options['Surname'] ?? '';
            $firstName = $options['FirstName'] ?? '';
            $patronymic = $options['Patronymic'] ?? '';
            $payerParts = array_filter([$surname, $firstName, $patronymic]);
        } else {
            $visibility = false;
            $emailNotification = false;
            $payerParts = [];
        }

        $payer = 'Благотворитель';

        if (!empty($data->Payer)) {
            $payer = sanitize_text_field($data->Payer);
        } elseif ($payerParts) {
            $payer = implode(' ', $payerParts);
        }

        if (!empty($emailNotification) && is_email($emailNotification)) {
            self::send_success_email($emailNotification, $data->Amount, $payer);
        } else {
            error_log(sprintf(
                "Не удалось отправить email-уведомление: некорректный или отсутствующий email для account_no=%s. Значение: %s",
                $data->AccountNo,
                var_export($emailNotification, true)
            ));
        }

        $inserted = $wpdb->insert(
            EXPRESSPAY_TABLE_PAYMENTS,
            [
                'payment_no' => $data->PaymentNo,
                'account_no' => $data->AccountNo,
                'amount' => str_replace(',', '.', (string)$data->Amount),
                'dateofpayment' => $data->Created,
                'payer' => $payer,
                'service' => $data->Service,
                'visibility' => $visibility
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%d']
        );

        if (!$inserted) {
            error_log("Ошибка при вставке платежа: payment_no={$data->PaymentNo}, account_no={$data->AccountNo}");
            return 'insert_error';
        }

        return 'ok';
    }


    /**
     * Добавление записи о новом успешном рекуррентном платеже.
     * 
     * Проверяет, существует ли уже платеж с заданным номером. Если такой платеж найден, 
     * возвращает 'duplicate'. Если не найден счет по customer_id — возвращает 'not_found'.
     * При ошибке вставки записи в базу — 'insert_error'. В случае успешного добавления 
     * платежа возвращает 'ok'.
     * 
     * Также выполняется попытка извлечь email для уведомления и логируется его отсутствие или некорректность.
     * 
     * @param object $data Объект с данными из уведомления о платеже (ожидаются поля: PaymentNo, AccountNo, CustomerId, Amount, Created, Payer, Service)
     * @return string Статус выполнения операции: 'ok', 'duplicate', 'not_found', 'insert_error'
     */
    public static function addNewRecPayment($data)
    {
        global $wpdb;

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM " . EXPRESSPAY_TABLE_PAYMENTS . " 
                WHERE payment_no = %d", 
                $data->PaymentNo
            )
        );

        if ($exists) {
            error_log("Платеж с номером {$data->PaymentNo} уже существует");
            return 'duplicate';
        }
        
        $invoice_data_by_customer_id = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT options, options_id FROM " . EXPRESSPAY_TABLE_INVOICES_NAME . " 
                WHERE customer_id = %d",
                $data->CustomerId
            )
        );

        if (!$invoice_data_by_customer_id) {
            $invoice_data_by_account_no = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT options, options_id FROM " . EXPRESSPAY_TABLE_INVOICES_NAME . " 
                    WHERE account_no = %s",
                    $data->AccountNo
                )
            );
            if (!$invoice_data_by_account_no) {
                error_log("Не найден счет для customer_id={$data->CustomerId}, account_no={$data->AccountNo}");
                return 'not_found';
            } else {
                $invoice_data = $invoice_data_by_account_no;
            }
        } else {
            $invoice_data = $invoice_data_by_customer_id;
        }

        $invoice_options = $invoice_data->options;
        $type_id = (int) $invoice_data->options_id;

        if ($invoice_options) {
            $options = json_decode($invoice_options, true);
            $visibility = $options['Visibility'] ?? false;
            $emailNotification = $options['EmailNotification'] ?? false;

            $surname = $options['Surname'] ?? '';
            $firstName = $options['FirstName'] ?? '';
            $patronymic = $options['Patronymic'] ?? '';
            $payerParts = array_filter([$surname, $firstName, $patronymic]);
        } else {
            $visibility = false;
            $emailNotification = false;
            $payerParts = [];
        }

        $payer = 'Благотворитель';

        if (!empty($data->Payer)) {
            $payer = sanitize_text_field($data->Payer);
        } elseif ($payerParts) {
            $payer = implode(' ', $payerParts);
        }

        if (!empty($emailNotification) && is_email($emailNotification)) {
            $query = $wpdb->prepare( "SELECT options FROM " . EXPRESSPAY_TABLE_PAYMENT_METHOD_NAME . " WHERE id = %d", $type_id);
            $response = $wpdb->get_row($query);
            $options = json_decode($response->options);

            $unbind_url = self::getUnbindLink($options->Token, $options->ServiceId, $data->CustomerId, $type_id, $options->SecretWord);
            self::send_subscription_email($emailNotification, $data->Amount, $data->CustomerId, $unbind_url, $payer);
        } else {
            error_log(sprintf(
                "Не удалось отправить email-уведомление: некорректный или отсутствующий email для customer_id=%d. Значение: %s",
                $data->CustomerId,
                var_export($emailNotification, true)
            ));
        }

        $inserted = $wpdb->insert(
            EXPRESSPAY_TABLE_PAYMENTS,
            [
                'payment_no' => $data->PaymentNo,
                'account_no' => $data->AccountNo,
                'customer_id' => $data->CustomerId,
                'amount' => str_replace(',', '.', (string)$data->Amount),
                'dateofpayment' => $data->Created,
                'payer' => $payer,
                'service' => $data->Service,
                'visibility' => $visibility
            ],
            ['%d', '%s', '%d', '%s', '%s', '%s', '%s', '%d']
        );

        if (!$inserted) {
            error_log("Ошибка при вставке платежа для customer_id={$data->CustomerId}, payment_no={$data->PaymentNo}");
            return 'insert_error';
        }

        return 'ok';
    }


    
    /**
     * Удаление записи о платеже (отмена платежа)
     *
     * @param int $payment_no Номер платежа
     * @return bool Успешность выполнения операции
     */
    public static function deletePayment($payment_no)
    {
        global $wpdb;

        return $wpdb->delete(
            EXPRESSPAY_TABLE_PAYMENTS,
            ['payment_no' => $payment_no],
            ['%d']
        ) !== false;
    }


    /**
     * Хук обработки активации плагина
     */
    static function plugin_activation() {
        global $wpdb;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset_collate = $wpdb->get_charset_collate();

        $options_table_name = $wpdb->prefix . 'expresspay_options';
        $sql = "CREATE TABLE $options_table_name (
            id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
            name TINYTEXT NOT NULL,
            type TINYTEXT NOT NULL,
            options TEXT NULL,
            isactive TINYINT NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";
        dbDelta($sql);

        $invoices_table_name = $wpdb->prefix . 'expresspay_invoices';
        $sql = "CREATE TABLE $invoices_table_name (
            id INT NOT NULL AUTO_INCREMENT,
            account_no VARCHAR(30) NOT NULL,
            recurrence BOOLEAN NOT NULL DEFAULT FALSE,
            customer_id INT NULL,
            amount DECIMAL(10,2) NOT NULL,
            datecreated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            status TINYINT NOT NULL DEFAULT 0,
            dateofpayment DATETIME NULL DEFAULT NULL,
            options TEXT NULL,
            options_id MEDIUMINT(9) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY (account_no),
            FOREIGN KEY (options_id) REFERENCES $options_table_name(id)
        ) $charset_collate;";
        dbDelta($sql);

        $payments_table_name = $wpdb->prefix . 'expresspay_payments';
        $sql = "CREATE TABLE $payments_table_name (
            payment_no INT NOT NULL,
            account_no VARCHAR(30) NOT NULL,
            customer_id INT NULL,
            amount DECIMAL(10,2) NOT NULL,
            dateofpayment DATETIME NOT NULL,
            payer VARCHAR(255) NULL DEFAULT NULL,
            service VARCHAR(255) NULL DEFAULT NULL,
            visibility BOOLEAN NOT NULL DEFAULT FALSE,
            PRIMARY KEY (payment_no)
        ) $charset_collate;";
        dbDelta($sql);

        add_option('expresspay_plugin_ult', '');
        add_option('expresspay_db_version', '1.0');
    }


    /**
     * Хук обработки деактивации плагина
     */
    static function plugin_deactivation() {
        global $wpdb;
        
        $payments_table_name = $wpdb->prefix . 'expresspay_payments';
        $invoices_table_name = $wpdb->prefix . 'expresspay_invoices';
        $options_table_name = $wpdb->prefix . 'expresspay_options';

        $wpdb->query("DROP TABLE IF EXISTS $payments_table_name");
        $wpdb->query("DROP TABLE IF EXISTS $invoices_table_name");
        // $wpdb->query("DROP TABLE IF EXISTS $options_table_name"); // Оставляем методы оплаты

        delete_option('expresspay_plugin_ult');
        delete_option('expresspay_db_version');
    }


    /**
     * 
     * Хук обработки удаления плагина
     * 
     */
    static function plugin_uninstall()
    {
        global $wpdb;

        $payments_table_name = EXPRESSPAY_TABLE_PAYMENTS;
        $invoices_table_name = EXPRESSPAY_TABLE_INVOICES_NAME;
        $options_table_name = EXPRESSPAY_TABLE_PAYMENT_METHOD_NAME;

        $wpdb->query("DROP TABLE IF EXISTS $payments_table_name");
        $wpdb->query("DROP TABLE IF EXISTS $invoices_table_name");
        $wpdb->query("DROP TABLE IF EXISTS $options_table_name");

        delete_option('expresspay_plugin_is_active');
        delete_option('expresspay_plugin_ult');
    }


    static function log_error_exception($name, $message, $e)
    {
        self::log($name, "ERROR", $message . '; EXCEPTION MESSAGE - ' . $e->getMessage() . '; EXCEPTION TRACE - ' . $e->getTraceAsString());
    }

    static function log_error($name, $message)
    {
        self::log($name, "ERROR", $message);
    }

    static function log_info($name, $message)
    {
        self::log($name, "INFO", $message);
    }

    static function log($name, $type, $message)
    {
        $log_url = wp_upload_dir();
        $log_url = $log_url['basedir'] . "/expresspay";

        if (!file_exists($log_url)) {
            $is_created = mkdir($log_url, 0777);

            if (!$is_created)
                return;
        }

        $log_url .= '/express-pay-' . date('Y.m.d') . '.log';

        file_put_contents($log_url, $type . " - IP - " . sanitize_text_field($_SERVER['REMOTE_ADDR']) . "; DATETIME - " . date("Y-m-d H:i:s") . "; USER AGENT - " . sanitize_text_field($_SERVER['HTTP_USER_AGENT']) . "; FUNCTION - " . $name . "; MESSAGE - " . $message . ';' . PHP_EOL, FILE_APPEND);
    }


    /**
     * 
     * Получение Qr-кода
     * 
     * @param string $token      Токен
     * @param int    $invoice_id  Номер счета в сервисе Эксрпесс Платежи
     * @param string $secretWord Секретное слово
     * 
     * @return base64 QR-код или false в случае ошибки
     */
    public static function getQrCode($token, $invoice_id, $secretWord)
    {
        $request_params = array(
            'Token' => $token,
            'InvoiceId' => $invoice_id,
            'ViewType' => 'base64'
        );

        $request_params = http_build_query($request_params);
        $url = 'https://api.express-pay.by/v1/qrcode/getqrcode/';

        $response = wp_remote_get($url . '?' . $request_params);

        if (is_wp_error($response)) {
            error_log('ExpressPay API Error: ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            error_log('ExpressPay API Error: Empty response body');
            return false;
        }

        $response_data = json_decode($body);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('ExpressPay API Error: Invalid JSON - ' . json_last_error_msg());
            return false;
        }

        if (!isset($response_data->QrCodeBody)) {
            error_log('ExpressPay API Error: QrCodeBody is missing in response');
            return false;
        }

        return $response_data->QrCodeBody;
    }


    /**
     * Получение ссылки для перехода в банкинг
     * 
     * @param string $token      Токен
     * @param int    $invoice_id  Номер счета в сервисе Экспресс Платежи
     * @param string $secretWord Секретное слово
     * 
     * @return string|false Ссылка на QR-код или false в случае ошибки
     */
    public static function getQrCodeLink($token, $invoice_id, $secretWord)
    {
        $request_params = array(
            'Token' => $token,
            'InvoiceId' => $invoice_id,
            'ViewType' => 'text'
        );

        $request_params = http_build_query($request_params);
        $url = 'https://api.express-pay.by/v1/qrcode/getqrcode/';

        $response = wp_remote_get($url . '?' . $request_params);

        if (is_wp_error($response)) {
            error_log('ExpressPay API Error: ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            error_log('ExpressPay API Error: Empty response body');
            return false;
        }

        $response_data = json_decode($body);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('ExpressPay API Error: Invalid JSON - ' . json_last_error_msg());
            return false;
        }

        if (!isset($response_data->QrCodeBody)) {
            error_log('ExpressPay API Error: QrCodeBody is missing in response');
            return false;
        }

        return $response_data->QrCodeBody;
    }

    /**
     * Получение информационного HTML-сообщения о привязанной карте с кнопкой отвязки
     * 
     * @param string $token         Токен мерчанта
     * @param string $service_id    Номер услуги
     * @param int    $customer_id   ID привязки карты
     * @param string $secretWord    Секретное слово
     * 
     * @return string HTML-сообщение
     */
    public static function getCardInfoMessage($token, $service_id, $customer_id, $secretWord)
    {
        $signature_params = array(
            'token' => $token,
            'serviceid' => $service_id,
            'customerid' => $customer_id
        );

        $signature = self::computeSignature($signature_params, $secretWord, 'unbind-card');

        $url = "https://api.express-pay.by/v1/recurringpayment/bind/" . urlencode($customer_id);
        $url_with_query = add_query_arg([
            'ServiceId' => $service_id,
            'Signature' => $signature
        ], $url);

        $response = wp_remote_get($url_with_query);

        if (is_wp_error($response)) {
            error_log('ExpressPay API GetCardInfo Error: ' . $response->get_error_message());
            return '<p>Не удалось получить информацию о карте. Попробуйте позже или свяжите с технической поддержкой.</p>';
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('ExpressPay API JSON Error: ' . json_last_error_msg());
            return '<p>Ошибка обработки данных. Попробуйте позже или свяжите с технической поддержкой.</p>';
        }

        if (!isset($data['Status']) || !isset($data['Card'])) {
            error_log('ExpressPay API Error: Invalid response structure');
            return '<p>Некорректный ответ от сервиса. Попробуйте позже или свяжите с технической поддержкой.</p>';
        }

        $status = sanitize_text_field($data['Status']);
        $card_number = sanitize_text_field($data['Card']);
        $exp_date = isset($data['OfferExpDate']) ? sanitize_text_field($data['OfferExpDate']) : null;

        $html = '<div class="card-info-container">';
        $html .= '<h2>Информация о карте</h2>';
        $html .= '<p><strong>Номер карты:</strong> ' . esc_html($card_number) . '</p>';
        
        if ($exp_date) {
            $html .= '<p><strong>Действует до:</strong> ' . esc_html($exp_date) . '</p>';
        }
        
        $html .= '<p><strong>Статус:</strong> ' . esc_html($status) . '</p>';
        
        if (strtolower($status) === 'привязана') {
            $unbind_link = self::getUnbindLink($token, $service_id, $customer_id, $secretWord);
            $html .= '<div>';
            $html .= '<button class="unbind-btn" id="unbind_card_btn">Отвязать карту</button>';
            $html .= '</div>';
        }
        
        $html .= '</div>';

        return $html;
    }

    /**
     * Отвязка карты
     * 
     * @param string $token         Токен
     * @param string $service_id    Номер услуги
     * @param int    $customer_id   ID привязки карты
     * @param string $secretWord    Секретное слово
     * 
     * @return bool Успешность выполнения операции
     */
    public static function unbindCard($token, $service_id, $customer_id, $secretWord)
    {
        $signature_params = array(
            'token' => $token,
            'serviceid' => $service_id,
            'customerid' => $customer_id
        );
        
        $signature = self::computeSignature($signature_params, $secretWord, 'unbind-card');
        
        $url = "https://api.express-pay.by/v1/recurringpayment/unbind/" . urlencode($customer_id);
        $url_with_query = add_query_arg([
            'ServiceId' => $service_id,
            'Signature' => $signature
        ], $url);

        $response = wp_remote_request($url_with_query, array(
            'method'  => 'DELETE',
            'timeout' => 15
        ));

        if (is_wp_error($response)) {
            error_log('ExpressPay API Error: ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            error_log('ExpressPay API Error: Empty response body');
            return false;
        }

        $response_data = json_decode($body);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('ExpressPay API Error: Invalid JSON - ' . json_last_error_msg());
            return false;
        }

        if (isset($response_data->Rc)) {
            if ($response_data->Rc === "0" || $response_data->Rc === 0) {
                return true;
            } else {
                $errorDetails = [
                    'error' => $response_data->Text ?? 'No error message',
                    'customerId' => $response_data->CustomerId ?? 'unknown',
                    'batchTimestamp' => $response_data->BatchTimestamp ?? null,
                    'rawResponse' => json_encode($response_data)
                ];
                
                error_log('Ошибка отвязки карты: ' . print_r($errorDetails, true));
                return false;
            }
        } else {
            error_log('Некорректный формат ответа при отвязке карты: ' . json_encode($response_data));
            return false;
        }
    }

    /**
     * Получение ссылки на отвязку карты
     * 
     * @param string $token         Токен
     * @param string $service_id    Номер услуги
     * @param int    $customer_id   ID привязки карты
     * @param int    $type_id       ID метода оплаты
     * @param string $secretWord    Секретное слово
     * 
     * @return string Ссылка на отвязку карты
     */
    public static function getUnbindLink($token, $service_id, $customer_id, $type_id, $secretWord)
    {
        global $wpdb;

        $page_id = $wpdb->get_var(
            "SELECT ID FROM {$wpdb->posts} 
            WHERE post_type = 'page' 
            AND post_status = 'publish'
            AND post_password = ''
            AND (
                post_content LIKE '%[expresspay_payment]%' 
                OR post_content LIKE '%[expresspay_payment %]%'
                OR post_content LIKE '% [expresspay_payment %]%'
                OR post_content LIKE '% [expresspay_payment]%'
            )
            LIMIT 1"
        );
        
        $base_url = $page_id ? get_permalink($page_id) : home_url();

        $url = add_query_arg(array(
            'type_id' => $type_id,
            'result' => 2,
            'CustomerId' => $customer_id,
            'ServiceId' => $service_id
        ), $base_url);
        
        return $url;
    }

    /**
     * 
     * Отправка письма об успешной оплате
     * 
     * @param string $email      Почта благотворителя
     * @param string $amount     Сумма платежа
     * @param string $full_name  ФИО благотворителя
     * 
     */
    public static function send_success_email($email, $amount, $full_name="Благотворитель") {
        if (!$email) return;

        $subject = 'Благодарим за поддержку!';
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        $message = "
            <p>Уважаемый(ая), <strong>{$full_name}</strong>!<br>
            Ваш платеж на сумму <strong>{$amount} BYN</strong> прошел успешно.<br>
            Благодарим Вас за помощь.</p>
        ";

        wp_mail($email, $subject, $message, $headers);
    }

    /**
     * Отправка письма о подключении рекуррентного платежа
     *
     * @param string $email         Почта благотворителя
     * @param string $amount        Сумма регулярного платежа
     * @param string $binding_id    Идентификатор привязки карты
     * @param string $unbind_url    Ссылка для отвязки карты
     * @param string $full_name     ФИО благотворителя
     */
    public static function send_subscription_email($email, $amount, $customer_id, $unbind_url, $full_name = "Благотворитель")
    {
        if (!$email) return;

        $subject = 'Подключен регулярный платеж';
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        $message = "
            <p>Уважаемый(ая), <strong>{$full_name}</strong>!</p>
            <p>Вы успешно подключились к ежемесячным рекуррентным списаниям.</p>
            <p><strong>Сумма платежа:</strong> {$amount} BYN<br>
            <strong>Идентификатор привязки:</strong> {$customer_id}</p>

            <p>Списание указанной суммы будет производиться автоматически <strong>один раз в месяц</strong>.</p>

            <p>Вы можете в любой момент отказаться от регулярных списаний, отвязав карту по следующей ссылке:<br>
            <a href='{$unbind_url}'>Отменить рекуррентные списания</a></p>

            <p>Если у вас возникли вопросы или трудности — свяжитесь с нашей технической поддержкой.</p>
        ";

        wp_mail($email, $subject, $message, $headers);
    }

    /**
     * 
     * Отправка письма об ошибке при оплате
     * 
     * @param string $email      Почта благотворителя
     * @param string $amount     Сумма платежа
     * @param string $full_name  ФИО благотворителя
     * 
     */
    public static function send_fail_email($email, $amount, $full_name="Благотворитель") {
        if (!$email) return;

        $subject = 'Ошибка при обработке платежа';
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        $message = "
            <p>Информационное сообщение</a></p>
            <hr>
            <p>Уважаемый(ая), <strong>{$full_name}</strong>!<br>
            К сожалению, платеж на сумму <strong>{$amount} BYN</strong> не был успешно обработан.<br>
            Пожалуйста, проверьте введенные данные или повторите попытку позднее.</p>
            <hr>
            <p>Сообщение сгенерировано автоматически.</p>
        ";

        wp_mail($email, $subject, $message, $headers);
    }
}