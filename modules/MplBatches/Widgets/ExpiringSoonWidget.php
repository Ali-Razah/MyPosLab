<?php

namespace Modules\MplBatches\Widgets;

use App\Services\WidgetService;

class ExpiringSoonWidget extends WidgetService
{
    protected $vueComponent = 'mplBatchesExpiringSoonWidget';

    protected string $layout = '1x2';

    /**
     * The list grows with the space given: allow taller/wider variants only.
     */
    protected string $layoutPolicy = 'restricted';

    protected array $supportedLayouts = [ '1x2', '1x3', '2x2' ];

    public function __construct()
    {
        $this->name = __m( 'Expiring Soon', 'MplBatches' );
        $this->description = __m( 'Counts expired and soon-to-expire batches and lists the most urgent ones.', 'MplBatches' );
        $this->permission = 'mplbatches.read';
    }

    /**
     * Counts are loaded by the component from the permission-protected API;
     * only the (store-aware) report link is shared here.
     */
    public function getData(): array
    {
        return [
            'reportUrl' => ns()->route( 'mplbatches.report' ),
        ];
    }
}
