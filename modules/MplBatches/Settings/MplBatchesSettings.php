<?php

namespace Modules\MplBatches\Settings;

use App\Classes\FormInput;
use App\Services\Helper;
use App\Services\SettingsPage;
use Modules\MplBatches\Services\BatchService;

class MplBatchesSettings extends SettingsPage
{
    const IDENTIFIER = 'mplbatches';

    const AUTOLOAD = true;

    public function __construct()
    {
        $options = ns()->option;

        $this->form = [
            'title' => __m( 'Batches & Expiry Settings', 'MplBatches' ),
            'description' => __m( 'Configure batch tracking, expiry alerts and selling restrictions.', 'MplBatches' ),
            'tabs' => [
                'general' => [
                    'label' => __m( 'General', 'MplBatches' ),
                    'fields' => [
                        FormInput::switch(
                            label: __m( 'Enable Batch Tracking', 'MplBatches' ),
                            name: BatchService::OPTION_ENABLED,
                            options: Helper::kvToJsOptions( [
                                'yes' => __m( 'Yes', 'MplBatches' ),
                                'no' => __m( 'No', 'MplBatches' ),
                            ] ),
                            value: $options->get( BatchService::OPTION_ENABLED, 'yes' ),
                            description: __m( 'When disabled, receiving and selling stock no longer creates or updates batches, and no expiry alert is sent.', 'MplBatches' ),
                        ),
                        FormInput::number(
                            label: __m( 'Alert Window (days)', 'MplBatches' ),
                            name: BatchService::OPTION_ALERT_DAYS,
                            value: $options->get( BatchService::OPTION_ALERT_DAYS, 30 ),
                            validation: 'required|integer|min:1|max:365',
                            description: __m( 'Batches expiring within this number of days are listed in the daily expiry notification and the report.', 'MplBatches' ),
                        ),
                        FormInput::number(
                            label: __m( 'Critical Threshold (days)', 'MplBatches' ),
                            name: BatchService::OPTION_CRITICAL_DAYS,
                            value: $options->get( BatchService::OPTION_CRITICAL_DAYS, 7 ),
                            validation: 'required|integer|min:0|max:365',
                            description: __m( 'Batches expiring within this number of days are flagged as critical (should be sold or returned first).', 'MplBatches' ),
                        ),
                        FormInput::select(
                            label: __m( 'Block Selling Expired Batches', 'MplBatches' ),
                            name: BatchService::OPTION_BLOCK_EXPIRED,
                            options: Helper::kvToJsOptions( [
                                BatchService::BLOCK_FOLLOW_PRODUCT => __m( 'Follow product setting (On Expiration)', 'MplBatches' ),
                                BatchService::BLOCK_ALWAYS => __m( 'Always block', 'MplBatches' ),
                                BatchService::BLOCK_NEVER => __m( 'Never block', 'MplBatches' ),
                            ] ),
                            value: $options->get( BatchService::OPTION_BLOCK_EXPIRED, BatchService::BLOCK_FOLLOW_PRODUCT ),
                            description: __m( '"Follow product setting" blocks a sale that needs expired batch stock only for products that expire with "Prevent Sales". Core still refuses scanning an expired procurement barcode in every mode.', 'MplBatches' ),
                        ),
                    ],
                ],
            ],
        ];
    }
}
