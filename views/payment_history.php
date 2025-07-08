<div class="expresspay-payment-history">
    <h2 class="info-step-title">История платежей</h2>
    <p class="total-amount">Собрано уже более <strong><?php echo number_format(3000, 0, ',', ' '); ?> BYN</strong></p>

    <div class="payments-list">
        <?php 
        // Первые 5 платежей (видны сразу)
        $initial_payments = array_slice($payments, 0, 5);
        foreach ($initial_payments as $payment): ?>
            <div class="payment-card visible">
                <div class="payment-user"><?php echo esc_html($payment->payer); ?></div>
                <div class="payment-amount"><?php echo number_format($payment->amount, 0, ',', ' '); ?> BYN</div>
                <div class="payment-date"><?php echo date('d.m.Y H:i', strtotime($payment->dateofpayment)); ?></div>
            </div>
        <?php endforeach; ?>

        <?php 
        // Следующие 10 платежей (скрыты, группа 1)
        $group1_payments = array_slice($payments, 5, 10);
        foreach ($group1_payments as $payment): ?>
            <div class="payment-card hidden" data-group="group1">
                <div class="payment-user"><?php echo esc_html($payment->payer); ?></div>
                <div class="payment-amount"><?php echo number_format($payment->amount, 0, ',', ' '); ?> BYN</div>
                <div class="payment-date"><?php echo date('d.m.Y H:i', strtotime($payment->dateofpayment)); ?></div>
            </div>
        <?php endforeach; ?>

        <?php 
        // Последние 10 платежей (скрыты, группа 2)
        $group2_payments = array_slice($payments, 15, 10);
        foreach ($group2_payments as $payment): ?>
            <div class="payment-card hidden" data-group="group2">
                <div class="payment-user"><?php echo esc_html($payment->payer); ?></div>
                <div class="payment-amount"><?php echo number_format($payment->amount, 0, ',', ' '); ?> BYN</div>
                <div class="payment-date"><?php echo date('d.m.Y H:i', strtotime($payment->dateofpayment)); ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <button id="load-more-payments" class="expresspay-button" data-state="initial">Загрузить ещё</button>
</div>