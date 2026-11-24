<?php

namespace Modules\MplBranches\Filters;

use App\Models\Order;
use Modules\MplBranches\Services\BranchService;
use Throwable;

class ReceiptFilter
{
    /**
     * Receipt template that was active before MplBranches wrapped it, so other
     * modules overriding "ns-web-receipt-template" keep working.
     */
    public static string $innerTemplate = 'pages.dashboard.orders.templates._receipt';

    public static function wrapReceiptTemplate( string $template ): string
    {
        self::$innerTemplate = $template;

        return 'MplBranches::receipt.wrapper';
    }

    /**
     * Adds {branch_*} tags usable in the receipt column A/B templates (Settings > Invoices).
     */
    public static function templateMapping( array $tags, Order $order ): array
    {
        try {
            /**
             * The core renders the receipt column templates unescaped ({!! !!}).
             */
            return array_merge( $tags, array_map(
                fn ( $value ) => e( (string) $value ),
                app()->make( BranchService::class )->getReceiptData( $order )
            ) );
        } catch ( Throwable $exception ) {
            return $tags;
        }
    }
}
