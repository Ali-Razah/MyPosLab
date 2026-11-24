<?php

namespace Modules\MplCustomerDisplay\Tests;

use App\Models\Role;
use App\Models\User;
use App\Services\ModulesService;
use Illuminate\Foundation\Application;
use Illuminate\Support\Str;
use Tests\TestCase as ApplicationTestCase;

abstract class TestCase extends ApplicationTestCase
{
    const MODULE = 'MplCustomerDisplay';

    /**
     * Enables the module and runs its pending migrations on the isolated test
     * database, then boots a fresh application so module routes, listeners and
     * providers are registered exactly like in production.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        if ( $this->prepareModule( $app ) ) {
            $app->flush();

            $app = parent::createApplication();
        }

        return $app;
    }

    /**
     * @return bool whether the application must be rebooted
     */
    protected function prepareModule( Application $app ): bool
    {
        /** @var ModulesService $modules */
        $modules = $app->make( ModulesService::class );
        $module = $modules->get( self::MODULE );

        if ( $module === false ) {
            $this->fail( 'The MplCustomerDisplay module cannot be found.' );
        }

        $reboot = false;

        if ( ! $module[ 'enabled' ] ) {
            $result = $modules->enable( self::MODULE );

            if ( ! is_array( $result ) || ( $result[ 'status' ] ?? null ) !== 'success' ) {
                $this->fail( 'Unable to enable the MplCustomerDisplay module: ' . json_encode( $result ) );
            }

            $reboot = true;
        }

        if ( count( $modules->getMigrations( self::MODULE ) ) > 0 ) {
            $modules->runAllMigration( self::MODULE );
            $reboot = true;
        }

        return $reboot;
    }

    protected function adminUser(): User
    {
        return User::whereHas( 'roles', fn( $query ) => $query->where( 'namespace', Role::ADMIN ) )->firstOrFail();
    }

    protected function createUserWithRole( string $roleNamespace ): User
    {
        $user = new User;
        $user->username = 'mplcd_' . Str::lower( Str::random( 10 ) );
        $user->email = $user->username . '@example.test';
        $user->password = bcrypt( 'password' );
        $user->first_name = 'Test';
        $user->active = true;
        $user->save();

        $user->assignRole( $roleNamespace );

        return $user->fresh();
    }
}
