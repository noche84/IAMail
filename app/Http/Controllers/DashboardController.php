<?php

namespace App\Http\Controllers;

use App\Models\AiMailAnalysis;
use App\Models\EmailAccount;
use App\Models\AiLearnedExample;
use App\Models\AiMailCorrection;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_today' => AiMailAnalysis::whereDate('created_at', today())->count(),
            'pending_review' => AiMailAnalysis::needsReview()->count(),
            'auto_classified' => AiMailAnalysis::autoClassified()->count(),
            'spam_blocked' => AiMailAnalysis::spam()->recent(7)->count(),
            'corrections_week' => AiMailCorrection::where('created_at', '>=', now()->subWeek())->count(),
            'learned_examples' => AiLearnedExample::active()->count(),
            'total_cost_month' => AiMailAnalysis::where('created_at', '>=', now()->startOfMonth())->sum('ai_cost_eur'),
        ];

        $topCategories = AiMailAnalysis::recent(7)
            ->selectRaw('category, COUNT(*) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit(8)
            ->pluck('total', 'category');

        $accounts = EmailAccount::active()->orderByDesc('total_collected')->get();

        $needsReview = AiMailAnalysis::needsReview()
            ->orderByRaw("FIELD(urgency, 'critical', 'high', 'medium', 'low')")
            ->limit(10)
            ->get();

        return view('dashboard.index', compact('stats', 'topCategories', 'accounts', 'needsReview'));
    }
}
