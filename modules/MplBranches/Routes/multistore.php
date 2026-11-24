<?php

/**
 * Loaded by MultiStore inside its authenticated store dashboard group:
 * no "dashboard" prefix and no authentication middleware here.
 */

use App\Services\ModulesService;

ModulesService::loadModuleFile( 'MplBranches', 'Routes/dashboard' );
