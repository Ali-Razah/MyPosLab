@extends( 'layout.dashboard' )

@section( 'layout.dashboard.body' )
<div>
    @include( Hook::filter( 'ns-dashboard-header-file', '../common/dashboard-header' ) )
    <div id="dashboard-content" class="px-4">
        @include( 'common.dashboard.title' )
        <div v-pre class="ns-box rounded shadow mb-4">
            <div class="ns-box-header p-2 border-b">
                <span class="text-fontcolor">{{ __m( 'Current branch', 'MplBranches' ) }}:</span>
                <strong class="text-fontcolor">{{ $current->name }}</strong>
                <span class="text-fontcolor-soft">({{ $current->code }})</span>
            </div>
            <div class="ns-box-body p-2">
                @if ( $branches->isEmpty() )
                <p class="text-fontcolor-soft">{{ __m( 'You are not assigned to any branch yet. Ask an administrator to add you to a branch; until then your sales are recorded for the default branch.', 'MplBranches' ) }}</p>
                @else
                <form method="POST" action="{{ ns()->route( 'mplbranches.dashboard.switch.post' ) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <div class="flex flex-col">
                        <label for="mplbranches-branch" class="text-fontcolor mb-1">{{ __m( 'Branch', 'MplBranches' ) }}</label>
                        <select id="mplbranches-branch" name="branch_id" class="ns-input border rounded p-2">
                            @foreach( $branches as $branch )
                            <option value="{{ $branch->id }}" @selected( $branch->id === $current->id )>{{ $branch->name }} ({{ $branch->code }})</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-fontcolor-soft mt-1">{{ __m( 'Sales on a cash register are always recorded for the register branch. Stock is shared by all branches.', 'MplBranches' ) }}</p>
                    </div>
                    <div class="ns-button info">
                        <button type="submit" class="rounded px-4 py-2">{{ __m( 'Switch', 'MplBranches' ) }}</button>
                    </div>
                </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
