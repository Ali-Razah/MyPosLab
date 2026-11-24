<link rel="icon" type="image/svg+xml" href="{{ asset( 'svg/myposlab-icon.svg' ) }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset( 'images/brand/favicon-32.png' ) }}">
<link rel="alternate icon" href="{{ asset( 'favicon.ico' ) }}">
<link rel="apple-touch-icon" href="{{ asset( 'apple-touch-icon.png' ) }}">
<meta name="theme-color" content="#4F46E5">
<meta name="application-name" content="MyPosLab">
<?php

use App\Classes\Output;
use App\Events\RenderHeaderEvent;
use Illuminate\Routing\Route;

if ( request()->route() instanceof Route ) {
    echo Output::dispatch( 
        RenderHeaderEvent::class, 
        request()->route()->getName() 
    );
}
