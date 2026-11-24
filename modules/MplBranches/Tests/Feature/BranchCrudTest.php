<?php

namespace Modules\MplBranches\Tests\Feature;

use App\Models\Role;
use Illuminate\Support\Str;
use Modules\MplBranches\Crud\BranchCrud;
use Modules\MplBranches\Models\Branch;
use Modules\MplBranches\Models\BranchRegister;
use Modules\MplBranches\Models\BranchUser;
use Modules\MplBranches\Tests\MplBranchesTestCase;

final class BranchCrudTest extends MplBranchesTestCase
{
    private function payload( array $general = [], array $assignments = [] ): array
    {
        return [
            'name' => 'Sharjah Grocery ' . Str::random( 4 ),
            'general' => array_merge( [
                'code' => 'shj-' . Str::lower( Str::random( 6 ) ),
                'active' => 1,
                'is_default' => 0,
                'address' => 'Al Majaz 2',
                'city' => 'Sharjah',
                'emirate' => 'Sharjah',
                'phone' => '+971 6 000 0000',
                'email' => '',
                'trn' => '100000000000003',
            ], $general ),
            'receipt' => [
                'receipt_header' => 'Welcome',
                'receipt_footer' => 'Thank you',
            ],
            'assignments' => array_merge( [
                'user_ids' => [],
                'register_ids' => [],
            ], $assignments ),
        ];
    }

    public function test_a_branch_is_created_with_users_and_registers(): void
    {
        $this->actingAsUser( $this->admin() );

        $cashier = $this->createStaff( Role::STORECASHIER );
        $register = $this->createRegister();

        $response = $this->json( 'POST', 'api/crud/' . BranchCrud::IDENTIFIER, $this->payload( [], [
            'user_ids' => [ $cashier->id ],
            'register_ids' => [ $register->id ],
        ] ) );

        $response->assertOk()->assertJsonPath( 'status', 'success' );

        $branch = Branch::findOrFail( $response->json( 'data.entry.id' ) );

        $this->assertStringStartsWith( 'SHJ-', $branch->code );
        $this->assertFalse( $branch->is_default );
        $this->assertTrue( BranchUser::where( [ 'branch_id' => $branch->id, 'user_id' => $cashier->id ] )->first()->is_primary );
        $this->assertSame( (int) $branch->id, (int) BranchRegister::where( 'register_id', $register->id )->value( 'branch_id' ) );

        // editing replaces the assignments
        $response = $this->json( 'PUT', 'api/crud/' . BranchCrud::IDENTIFIER . '/' . $branch->id, $this->payload( [ 'code' => $branch->code ], [
            'user_ids' => [],
            'register_ids' => [],
        ] ) );
        $response->assertOk();
        $this->assertFalse( BranchUser::where( 'branch_id', $branch->id )->exists() );
        $this->assertFalse( BranchRegister::where( 'branch_id', $branch->id )->exists() );

        // the crud list is readable
        $this->json( 'GET', 'api/crud/' . BranchCrud::IDENTIFIER )->assertOk();
    }

    public function test_branch_code_must_be_unique(): void
    {
        $this->actingAsUser( $this->admin() );
        $existing = $this->createBranch();

        $response = $this->json( 'POST', 'api/crud/' . BranchCrud::IDENTIFIER, $this->payload( [ 'code' => $existing->code ] ) );

        $response->assertStatus( 422 );
        $this->assertSame( 1, Branch::where( 'code', $existing->code )->count() );
    }

    public function test_only_one_default_branch_is_kept(): void
    {
        $this->actingAsUser( $this->admin() );
        $previousDefault = $this->branchService()->getDefaultBranch();

        $response = $this->json( 'POST', 'api/crud/' . BranchCrud::IDENTIFIER, $this->payload( [ 'is_default' => 1 ] ) );
        $response->assertOk();

        $newDefault = Branch::findOrFail( $response->json( 'data.entry.id' ) );

        $this->assertSame( 1, Branch::where( 'is_default', true )->count() );
        $this->assertTrue( $newDefault->fresh()->is_default );
        $this->assertFalse( $previousDefault->fresh()->is_default );

        // the only default branch cannot lose its flag
        $response = $this->json( 'PUT', 'api/crud/' . BranchCrud::IDENTIFIER . '/' . $newDefault->id, $this->payload( [ 'code' => $newDefault->code, 'is_default' => 0 ] ) );
        $response->assertForbidden();
        $this->assertTrue( $newDefault->fresh()->is_default );

        // the default branch cannot be deactivated
        $response = $this->json( 'PUT', 'api/crud/' . BranchCrud::IDENTIFIER . '/' . $newDefault->id, $this->payload( [ 'code' => $newDefault->code, 'is_default' => 1, 'active' => 0 ] ) );
        $response->assertForbidden();
        $this->assertTrue( $newDefault->fresh()->active );

        // the default branch cannot be deleted
        $response = $this->json( 'DELETE', 'api/crud/' . BranchCrud::IDENTIFIER . '/' . $newDefault->id );
        $response->assertForbidden();
        $this->assertNotNull( Branch::find( $newDefault->id ) );

        // restore the original default for the other tests
        $previousDefault = $previousDefault->fresh();
        $previousDefault->is_default = true;
        $previousDefault->save();
        $this->branchService()->makeDefault( $previousDefault );
        $this->assertSame( 1, Branch::where( 'is_default', true )->count() );
    }

    public function test_a_branch_with_orders_cannot_be_deleted_but_can_be_deactivated(): void
    {
        $this->actingAsUser( $this->admin() );

        $branch = $this->createBranch();
        $register = $this->createRegister();
        $this->branchService()->syncBranchRegisters( $branch, [ $register->id ] );
        $this->createPaidOrder( [ 'register_id' => $register->id ] );

        $response = $this->json( 'DELETE', 'api/crud/' . BranchCrud::IDENTIFIER . '/' . $branch->id );
        $response->assertForbidden();
        $this->assertNotNull( Branch::find( $branch->id ) );

        $response = $this->json( 'PUT', 'api/crud/' . BranchCrud::IDENTIFIER . '/' . $branch->id, $this->payload( [ 'code' => $branch->code, 'active' => 0 ] ) );
        $response->assertOk();
        $this->assertFalse( $branch->fresh()->active );

        // a branch without orders can be deleted, and its assignments are removed
        $empty = $this->createBranch();
        $cashier = $this->createStaff();
        $this->branchService()->syncBranchUsers( $empty, [ $cashier->id ] );

        $this->json( 'DELETE', 'api/crud/' . BranchCrud::IDENTIFIER . '/' . $empty->id )->assertOk();
        $this->assertNull( Branch::find( $empty->id ) );
        $this->assertFalse( BranchUser::where( 'branch_id', $empty->id )->exists() );
    }
}
