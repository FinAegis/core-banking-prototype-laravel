<?php

namespace App\Http\Controllers;

use App\Models\GcuVotingProposal;
use App\Models\GcuVote;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class GcuVotingController extends Controller
{
    /**
     * Display voting dashboard
     */
    public function index()
    {
        $activeProposals = GcuVotingProposal::active()
            ->with('creator')
            ->orderBy('voting_ends_at', 'asc')
            ->get();
            
        $upcomingProposals = GcuVotingProposal::upcoming()
            ->with('creator')
            ->orderBy('voting_starts_at', 'asc')
            ->limit(5)
            ->get();
            
        $pastProposals = GcuVotingProposal::past()
            ->with('creator')
            ->orderBy('voting_ends_at', 'desc')
            ->limit(10)
            ->get();
            
        // Get user's GCU balance for voting power
        $gcuBalance = 0;
        if (Auth::check()) {
            $account = Auth::user()->accounts()->first();
            if ($account) {
                $gcuBalance = $account->balances()
                    ->where('asset_code', 'GCU')
                    ->first()?->balance ?? 0;
            }
        }
            
        return view('gcu.voting.index', compact(
            'activeProposals', 
            'upcomingProposals', 
            'pastProposals',
            'gcuBalance'
        ));
    }
    
    /**
     * Show proposal details
     */
    public function show(GcuVotingProposal $proposal)
    {
        $proposal->load(['creator', 'votes']);
        
        // Check if user has voted
        $userVote = null;
        $gcuBalance = 0;
        
        if (Auth::check()) {
            $userVote = $proposal->votes()
                ->where('user_uuid', Auth::user()->uuid)
                ->first();
                
            $account = Auth::user()->accounts()->first();
            if ($account) {
                $gcuBalance = $account->balances()
                    ->where('asset_code', 'GCU')
                    ->first()?->balance ?? 0;
            } else {
                $gcuBalance = 0;
            }
        }
        
        // Calculate vote distribution
        $voteDistribution = [
            'for' => $proposal->votes_for,
            'against' => $proposal->votes_against,
            'abstain' => $proposal->total_votes_cast - $proposal->votes_for - $proposal->votes_against,
        ];
        
        return view('gcu.voting.show', compact('proposal', 'userVote', 'gcuBalance', 'voteDistribution'));
    }
    
    /**
     * Cast a vote
     */
    public function vote(Request $request, GcuVotingProposal $proposal)
    {
        if (!$proposal->isVotingActive()) {
            return back()->with('error', 'Voting is not active for this proposal.');
        }
        
        $request->validate([
            'vote' => 'required|in:for,against,abstain',
        ]);
        
        // Get user's GCU balance
        $account = Auth::user()->accounts()->first();
        if (!$account) {
            return back()->with('error', 'You need an account to vote.');
        }
        
        $gcuBalance = $account->balances()
            ->where('asset_code', 'GCU')
            ->first()?->balance ?? 0;
            
        if ($gcuBalance <= 0) {
            return back()->with('error', 'You need GCU holdings to vote.');
        }
        
        DB::transaction(function () use ($request, $proposal, $gcuBalance) {
            // Create or update vote
            $vote = GcuVote::updateOrCreate(
                [
                    'proposal_id' => $proposal->id,
                    'user_uuid' => Auth::user()->uuid,
                ],
                [
                    'vote' => $request->vote,
                    'voting_power' => $gcuBalance,
                ]
            );
            
            // Generate and save signature
            $vote->signature = $vote->generateSignature();
            $vote->save();
            
            // Update proposal vote counts
            $this->updateProposalVoteCounts($proposal);
        });
        
        return back()->with('success', 'Your vote has been recorded successfully.');
    }
    
    /**
     * Update proposal vote counts
     */
    protected function updateProposalVoteCounts(GcuVotingProposal $proposal)
    {
        $votes = $proposal->votes()->get();
        
        $votesFor = $votes->where('vote', 'for')->sum('voting_power');
        $votesAgainst = $votes->where('vote', 'against')->sum('voting_power');
        $totalVotes = $votes->sum('voting_power');
        
        $proposal->update([
            'votes_for' => $votesFor,
            'votes_against' => $votesAgainst,
            'total_votes_cast' => $totalVotes,
        ]);
    }
    
    /**
     * Show create proposal form (admin only)
     */
    public function create()
    {
        $this->authorize('create', GcuVotingProposal::class);
        
        $currentComposition = config('platform.gcu.composition');
        
        return view('gcu.voting.create', compact('currentComposition'));
    }
    
    /**
     * Store new proposal (admin only)
     */
    public function store(Request $request)
    {
        $this->authorize('create', GcuVotingProposal::class);
        
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'rationale' => 'required|string',
            'voting_starts_at' => 'required|date|after:now',
            'voting_ends_at' => 'required|date|after:voting_starts_at',
            'minimum_participation' => 'required|numeric|min:1|max:100',
            'minimum_approval' => 'required|numeric|min:1|max:100',
            'composition' => 'required|array',
            'composition.*' => 'required|numeric|min:0|max:100',
        ]);
        
        // Validate composition totals 100%
        $total = array_sum($validated['composition']);
        if ($total != 100) {
            return back()->withErrors(['composition' => 'Composition must total exactly 100%']);
        }
        
        // Calculate total GCU supply
        $totalGcuSupply = \App\Models\AccountBalance::where('asset_code', 'GCU')
            ->sum('balance');
        
        $proposal = GcuVotingProposal::create([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'rationale' => $validated['rationale'],
            'proposed_composition' => $validated['composition'],
            'current_composition' => config('platform.gcu.composition'),
            'status' => 'active',
            'voting_starts_at' => $validated['voting_starts_at'],
            'voting_ends_at' => $validated['voting_ends_at'],
            'minimum_participation' => $validated['minimum_participation'],
            'minimum_approval' => $validated['minimum_approval'],
            'total_gcu_supply' => $totalGcuSupply,
            'created_by' => Auth::id(),
        ]);
        
        return redirect()->route('gcu.voting.show', $proposal)
            ->with('success', 'Proposal created successfully.');
    }
}