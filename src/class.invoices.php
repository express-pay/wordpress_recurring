<?php
class ExpressPayInvoicesAndPayments
{
    /**
     * 
     * Рендеринг страницы счетов
     * 
     */
    static function get_invoices_page()
    {
        ExpressPay::view('admin/admin_header');

        global $wpdb;

        $table_name = EXPRESSPAY_TABLE_INVOICES_NAME;

        $response = $wpdb->get_results(
            "SELECT 
                account_no,
                recurrence, 
                amount, 
                datecreated, 
                status, 
                options, 
                options_id, 
                dateofpayment 
            FROM $table_name 
            ORDER BY datecreated DESC 
            LIMIT 25"
        );

        if (count($response) == 0) {
            ExpressPay::view(
                'admin/admin_list_invoices_emty',
                array(
                    'url' => get_option('expresspay_plugin_ult')
                )
            );
        } else {
            ExpressPay::view(
                'admin/admin_list_invoices',
                array(
                    'response' => $response,
                    'url' => get_option('expresspay_plugin_ult')
                )
            );
        }

        ExpressPay::view('admin/admin_footer');
    }
}
