@extends( 'layout.dashboard' )

@section( 'layout.dashboard.header' )
@parent
@moduleViteAssets('Resources/css/style.css', 'MplOwnerApp')
@endsection

@section( 'layout.dashboard.with-title' )
<div class="oa:grid oa:gap-4 oa:md:grid-cols-2 oa:max-w-4xl">
    <div class="ns-box oa:rounded-lg oa:border oa:border-box-edge oa:p-6 oa:flex oa:flex-col oa:items-center oa:gap-4">
        <img src="{{ $qrCodeDataUri }}" width="260" height="260" alt="{{ __m( 'QR code to open the owner dashboard', 'MplOwnerApp' ) }}" class="oa:rounded-md oa:bg-white oa:p-2">
        <p class="oa:text-sm oa:text-fontcolor-soft oa:text-center oa:break-all">{{ $ownerUrl }}</p>
        <div class="ns-button info">
            <a href="{{ $ownerUrl }}" class="oa:inline-flex oa:items-center oa:gap-2 oa:rounded-lg oa:px-4 oa:py-2">
                <i class="las la-mobile"></i>
                <span>{{ __m( 'Open Owner Dashboard', 'MplOwnerApp' ) }}</span>
            </a>
        </div>
    </div>
    <div class="ns-box oa:rounded-lg oa:border oa:border-box-edge oa:p-6 oa:text-fontcolor">
        <h3 class="oa:text-lg oa:font-semibold oa:mb-3">{{ __m( 'Install on your phone', 'MplOwnerApp' ) }}</h3>
        <ol class="oa:list-decimal oa:ps-5 oa:space-y-2 oa:text-sm">
            <li>{{ __m( 'Scan the QR code with your phone camera and sign in with your owner account.', 'MplOwnerApp' ) }}</li>
            <li>{{ __m( 'Android (Chrome): open the browser menu and choose "Install app" or "Add to Home screen".', 'MplOwnerApp' ) }}</li>
            <li>{{ __m( 'iPhone (Safari): tap the Share button, then "Add to Home Screen".', 'MplOwnerApp' ) }}</li>
            <li>{{ __m( 'Launch "MyPosLab" from your home screen: the dashboard refreshes every minute and keeps the last data available offline.', 'MplOwnerApp' ) }}</li>
        </ol>
        <p class="oa:mt-4 oa:text-xs oa:text-fontcolor-soft">{{ __m( 'Only administrators and store administrators can open the owner dashboard.', 'MplOwnerApp' ) }}</p>
    </div>
</div>
@endsection
