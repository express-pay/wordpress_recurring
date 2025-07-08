<div class="expresspay-payment-history">
    <h2 class="info-step-title">История платежей</h2>
    
    <?php if ($collected !== false): ?>
        <p class="total-amount">Собрано уже более <strong> <?php echo number_format($collected, 0, ',', ' ');?> BYN</strong></p>
    <?php endif; ?>

    <div class="payments-list">
        <?php
        $visible_count = 5; // Количество изначально видимых платежей
        $group_size = 10;   // Количество платежей в каждой скрытой группе
        
        $visible_payments = array_slice($payments, 0, $visible_count);
        $hidden_groups = array();
        
        for ($i = $visible_count; $i < count($payments); $i += $group_size) {
            $group_num = ($i - $visible_count) / $group_size + 1;
            $hidden_groups["group{$group_num}"] = array_slice($payments, $i, $group_size);
        }
        ?>

        <?php foreach ($visible_payments as $payment): ?>
            <div class="payment-card visible">
                <div class="payment-user"><?php echo esc_html($payment->payer); ?></div>
                <div class="payment-amount"><?php echo number_format($payment->amount, 0, ',', ' '); ?> BYN</div>
                <div class="payment-date"><?php echo date('d.m.Y H:i', strtotime($payment->dateofpayment)); ?></div>
            </div>
        <?php endforeach; ?>

        <?php foreach ($hidden_groups as $group_name => $group_payments): ?>
            <?php foreach ($group_payments as $payment): ?>
                <div class="payment-card hidden" data-group="<?php echo esc_attr($group_name); ?>">
                    <div class="payment-user"><?php echo esc_html($payment->payer); ?></div>
                    <div class="payment-amount"><?php echo number_format($payment->amount, 0, ',', ' '); ?> BYN</div>
                    <div class="payment-date"><?php echo date('d.m.Y H:i', strtotime($payment->dateofpayment)); ?></div>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($hidden_groups)): ?>
        <button id="load-more-payments" class="expresspay-button" 
            data-state="initial" 
            data-visible-count="<?php echo $visible_count; ?>" 
            data-group-size="<?php echo $group_size; ?>">
            Загрузить ещё
        </button>
    <?php endif; ?>
</div>