<div class="expresspay-payment" id="expresspay-payment">
    <input type="hidden" id="ajax-url" value="<?php echo esc_html($ajax_url); ?>" />

    <div class="info_step" id="info_step">

        <h2 class="info-step-title">Сделать пожертвование</h2>

        <?php foreach ($response as $row) : ?>
            <?php $options = json_decode($row->options, true); $isRecurrent = ($row->type === 'card' && isset($options['Recurrence']) && $options['Recurrence'] == 1);?>
            <div class="payment-method" 
                data-id="<?php echo esc_html($row->id); ?>" 
                data-type="<?php echo esc_html($row->type); ?>" 
                data-name="<?php echo esc_html($row->name); ?>"
                data-recurrent="<?php echo $isRecurrent ? '1' : '0'; ?>">
                <input type="radio" name="payment_method_radio" value="<?php echo esc_html($row->type); ?>" class="payment-radio">
                <div class="payment-icon">
                    <img src="<?php echo esc_url(plugins_url('views/admin/icons/' . esc_html($row->type) . '.png', dirname(__FILE__))); ?>" alt="icon">
                </div>
                <div class="payment-name">
                    <?php echo esc_html($row->name); ?>
                </div>
            </div>
        <?php endforeach; ?>
        <!-- <div class="payment-method" data-id="other-methods" data-type="other" data-name="Другие способы">
            <input type="radio" name="payment_method_radio" value="other" class="payment-radio">
            <div class="payment-icon">
                <img src="<?php echo esc_url(plugins_url('views/admin/icons/other.png', dirname(__FILE__))); ?>" alt="icon">
            </div>
            <div class="payment-name">Другие способы</div>
        </div> -->
        <input type="hidden" id="selected-payment-method-type" name="payment_method" value="">
        <input type="hidden" id="selected-payment-method-name" name="payment_method_name" value="">

        <div class="payment-details" id="payment-details">
            <div class="row" id="payment-notice">
                <div class="card-notice">
                    <p>
                        <?php 
                        echo esc_html__('Ваша карта должна быть подключена к услуге ', 'wordpress_expresspay');
                        echo '<strong>3D_Secure</strong>';
                        ?>
                    </p>
                </div>
            </div>

            <input type="hidden" id="expresspay-payment-purpose" value="<?php echo esc_attr($info); ?>" />

            <div class="row" id="payment-sum">
                <div class="label">
                    <label for="expresspay-payment-sum"><?php esc_html_e('Amount', 'wordpress_expresspay') ?></label>
                </div>
                <div class="field">
                    <div class="amount-buttons">
                        <button type="button" class="amount-btn" data-value="5">5 BYN</button>
                        <button type="button" class="amount-btn active" data-value="10">10 BYN</button>
                        <button type="button" class="amount-btn" data-value="25">25 BYN</button>
                        <button type="button" class="amount-btn custom-amount-btn" id="custom-amount-btn">?? BYN</button>
                    </div>
                    <input type="text" id="expresspay-payment-sum" placeholder="<?php esc_html_e('Другая сумма', 'wordpress_expresspay') ?>" style="display: none;" value="10" />
                    <div class="error-message">Необходимо ввести сумму</div>
                </div>
            </div>

            <div class="fio-section" id='fio-section'>
                <div class="row">
                    <div class="label">
                        <label for="expresspay-payment-last-name"><?php esc_html_e('Surname', 'wordpress_expresspay') ?></label>
                    </div>
                    <div class="field">
                        <input type="text" id="expresspay-payment-last-name" placeholder="<?php esc_html_e('Фамилия', 'wordpress_expresspay') ?>" />
                        <div class="error-message">Необходимо ввести фамилию</div>
                    </div>
                </div>
                <div class="row">
                    <div class="label">
                        <label for="expresspay-payment-name"><?php esc_html_e('Name', 'wordpress_expresspay') ?></label>
                    </div>
                    <div class="field">
                        <input type="text" id="expresspay-payment-name" placeholder="<?php esc_html_e('Имя', 'wordpress_expresspay') ?>"/>
                        <div class="error-message">Необходимо ввести имя</div>
                    </div>
                </div>
            </div>

            <div class="row" id="expresspay-payment-email-container">
                <div class="label">
                    <label for="expresspay-payment-email"><?php esc_html_e('E-mail', 'wordpress_expresspay') ?></label>
                </div>
                <div class="field">
                    <input type="text" id="expresspay-payment-email" placeholder="<?php esc_html_e('E-mail', 'wordpress_expresspay') ?>" required/>
                    <div class="error-message">Необходимо ввести e-mail</div>
                </div>
            </div>

            <div class="row" id="expresspay-payment-phone-container">
                <div class="label">
                    <label for="expresspay-payment-phone"><?php esc_html_e('Mobile number', 'wordpress_expresspay') ?></label>
                </div>
                <div class="field">
                    <input type="text" id="expresspay-payment-phone" placeholder="<?php esc_html_e('Мобильный телефон', 'wordpress_expresspay') ?>" />
                    <div class="error-message">Необходимо ввести моб. телефон</div>
                </div>
            </div>

            <div class="row" id="payment-recurrence">
                <div class="label">
                    <label><?php esc_html_e('Payment type', 'wordpress_expresspay') ?></label>
                </div>
                <div class="field">
                    <div class="button-group">
                        <button type="button" id="btn-once" class="payment-btn" value="false">
                            <?php echo 'Единожды' ?>
                        </button>
                        <span class="info-icon">
                            <img src="<?php echo esc_url(plugins_url('views/admin/icons/info.png', dirname(__FILE__))); ?>" id="payment-type-info" alt="Info" title="Что это такое?">
                        </span>
                        <button type="button" id="btn-recurring" class="payment-btn active" value="true">
                            <?php echo 'Ежемесячно' ?>
                        </button>
                    </div>
                    <input type="hidden" id="expresspay-payment-recurrency" name="recurrency" value="false">
                </div>
            </div>

            <div id="info-modal" class="modal">
                <div class="modal-content">
                    <span class="close-btn" id="close-info-modal">&times;</span>
                    <h2>Что такое рекуррентные платежи?</h2>
                    <p>Каждый месяц с Вашей банковской карты будет происходить автоматическое списание суммы, которую вы установили. Это значит, что Организация каждый месяц автоматически будет получать Ваше пожертвование.</p>
                    <p>Вы можете перечислять любую, удобную Вам сумму.</p>
                    <p>Списания с карты проводит банк. У нас нет номера Вашей карты, все Ваши банковские данные хранятся только в банке.</p>
                    <p>Вы можете отменить подписку на ежемесячные списания по ссылке, которая будет отправлена на электронную почту после совершения пожертвования.</p>
                </div>
            </div>

            <div class="row expresspay-agreement">
                <input type="checkbox" id="expresspay-user-agreement" />
                <label for="expresspay-user-agreement">
                    <div class="expresspay-agreement-text">
                        <?php echo 'Я согласен с условиями публичной оферты'; ?>
                    </div>
                </label>
            </div>

            <div class="row expresspay-agreement">
                <input type="checkbox" id="expresspay-show-agreement" />
                <label for="expresspay-show-agreement">
                    <?php echo 'Я хочу, чтобы мои платежи были видны на сайте'; ?>
                </label>
            </div>

            <form class="expresspay-payment-form" method="POST" id="expresspay-payment-form">
                <input type="hidden" name="ServiceId" id="expresspay-payment-service-id" value="" />
                <input type="hidden" name="WriteOffPeriod" id="expresspay-payment-write-off-period" value="" />
                <input type="hidden" name="AccountNo" id="expresspay-payment-account-no" value="" />
                <input type="hidden" name="Amount" id="expresspay-payment-amount" value="" />
                <input type="hidden" name="Currency" id="expresspay-payment-currency" value="" />
                <input type="hidden" name="Info" id="expresspay-payment-info" value="" />
                <input type="hidden" name="Surname" id="expresspay-payment-surname" value="" />
                <input type="hidden" name="FirstName" id="expresspay-payment-first-name" value="" />
                <input type="hidden" name="Patronymic" id="expresspay-payment-patronymic" value="" />
                <input type="hidden" name="IsNameEditable" id="expresspay-payment-is-name-editable" value="" />
                <input type="hidden" name="IsAddressEditable" id="expresspay-payment-is-address-editable" value="" />
                <input type="hidden" name="IsAmountEditable" id="expresspay-payment-is-amount-editable" value="" />
                <input type="hidden" name="EmailNotification" id="expresspay-payment-email-notification" value="" />
                <input type="hidden" name="ReturnType" id="expresspay-payment-return-type" value="redirect" />
                <input type="hidden" name="ReturnUrl" id="expresspay-payment-return-url" value="" />
                <input type="hidden" name="FailUrl" id="expresspay-payment-fail-url" value="" />
                <input type="hidden" name="SmsPhone" id="expresspay-payment-sms-phone" value="" />
                <input type="hidden" name="Signature" id="expresspay-payment-signature" value="" />
            </form>

            <div class="row">
                <button class="confirm_btn" id="btn_info_step"><?php esc_html_e('Pay', 'wordpress_expresspay') ?></button>
            </div>
        </div>

        <!-- <div class="payment-details" id="other-methods-details">
            <ul>
                <li><a href="erip" target="_blank">ЕРИП</a></li>
                <li><a href="ussd" target="_blank">USSD-запрос</a></li>
                <li><a href="bank-transfer" target="_blank">Банковский перевод</a></li>
            </ul>
        </div> -->
    </div>
    
    <div class="response_step" id="response_step">
        <div class="response-row">
            <div id="response_message" class="response-message"></div>
        </div>
        <div class="response-row">
            <button class="confirm_btn" id="replay_btn"><?php esc_html_e('Repeat', 'wordpress_expresspay') ?></button>
        </div>
    </div>
</div>