<script>
    if ( 'caches' in window ) {
        caches.keys()
            .then( ( keys ) => Promise.all( keys.filter( ( key ) => key.startsWith( 'mplownerapp-' ) ).map( ( key ) => caches.delete( key ) ) ) )
            .catch( () => {} );
    }
</script>
