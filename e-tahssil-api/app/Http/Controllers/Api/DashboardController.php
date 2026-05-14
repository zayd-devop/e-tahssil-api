<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\ProductionCard;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function getStats(Request $request)
    {
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;

        // ---------------------------------------------------------
        // 1. CALCUL DES KPIs (Pour le mois en cours)
        // ---------------------------------------------------------
        $kpiStats = ProductionCard::selectRaw('SUM(montant_recouvre) as total_amount, SUM(dossiers_executes) as total_collected_files, SUM(dossiers_notifies) as total_notified_files')
        ->whereMonth('created_at', $currentMonth)
        ->whereYear('created_at', $currentYear)
        ->first();

        // Trouver le meilleur greffier du mois (celui qui a le plus de dossiers traités)
        $topClerkData = ProductionCard::selectRaw('
            employee_name,
            SUM(COALESCE(dossiers_notifies, 0) + COALESCE(dossiers_executes, 0)) as total_score
        ')
        ->whereMonth('created_at', $currentMonth)
        ->whereYear('created_at', $currentYear)
        ->groupBy('employee_name')
        ->orderByDesc('total_score')
        ->first();

        $kpis = [
            "total_collected_amount" => (float) ($kpiStats->total_amount ?? 0),
            "total_collected_files" => (int) ($kpiStats->total_collected_files ?? 0),
            "total_notified_files" => (int) ($kpiStats->total_notified_files ?? 0),
            "top_clerk" => [
                "name" => $topClerkData ? $topClerkData->employee_name : "لا يوجد",
                "initials" => $topClerkData ? $this->getInitials($topClerkData->employee_name) : "-"
            ]
        ];

        // ---------------------------------------------------------
        // 2. DONNÉES MENSUELLES (Pour les graphiques : Janvier à Décembre)
        // ---------------------------------------------------------
        $monthlyStats = ProductionCard::selectRaw('
            MONTH(created_at) as month,
            SUM(dossiers_executes) as files,
            SUM(montant_recouvre) as amount,
            SUM(dossiers_notifies) as notifications
        ')
        ->whereYear('created_at', $currentYear)
        ->groupBy('month')
        ->get()
        ->keyBy('month');

        $monthsNames = [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
            5 => 'ماي', 6 => 'يونيو', 7 => 'يوليوز', 8 => 'غشت',
            9 => 'شتنبر', 10 => 'أكتوبر', 11 => 'نونبر', 12 => 'دجنبر'
        ];

        $monthlyData = [];
        for ($i = 1; $i <= 12; $i++) {
            $stat = $monthlyStats->get($i);
            $monthlyData[] = [
                'name' => $monthsNames[$i],
                'files' => $stat ? (int) $stat->files : 0,
                'amount' => $stat ? (float) $stat->amount : 0,
                'notifications' => $stat ? (int) $stat->notifications : 0
            ];
        }

        // ---------------------------------------------------------
        // 3 & 4. DONNÉES DES GREFFIERS (Doughnut Chart + Tableau Détaillé)
        // ---------------------------------------------------------
// ---------------------------------------------------------
        // 3 & 4. DONNÉES DES GREFFIERS (Doughnut Chart + Tableau Détaillé)
        // ---------------------------------------------------------

        // 1. جلب إحصائيات بطاقات الإنتاج (نضيف user_id في الـ select)
        $clerksStats = ProductionCard::query()
            ->selectRaw('
                user_id,
                employee_name,
                SUM(COALESCE(dossiers_notifies, 0)) as total_notifications,
                SUM(COALESCE(dossiers_executes, 0)) as total_executions,
                SUM(COALESCE(montant_recouvre, 0)) as total_amount,
                SUM(COALESCE(contrainte, 0)) as total_coercion,
                SUM(COALESCE(pv_positif_count, 0) + COALESCE(pv_negatif_count, 0)) as total_reports,
                SUM(COALESCE(dossiers_annulation, 0) + COALESCE(dossiers_iskatat, 0)) as total_cancellations
            ')
            ->whereMonth('created_at', $currentMonth)
            ->whereYear('created_at', $currentYear)
            ->groupBy('user_id', 'employee_name')
            ->get();

        // 2. 🔥 جلب إحصائيات "إجراء يوجه" (Procedures) لكل مستخدم في هذا الشهر
        $directedProcedures = \App\Models\Procedure::query()
            ->selectRaw('user_id, COUNT(*) as total_directed')
            ->whereMonth('created_at', $currentMonth)
            ->whereYear('created_at', $currentYear)
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->pluck('total_directed', 'user_id'); // يعطينا مصفوفة: [user_id => count]

        $colors = ['#003366', '#D4AF37', '#10b981', '#3b82f6', '#64748b', '#f59e0b', '#8b5cf6'];
        $bgColors = ['bg-blue-100 text-blue-700', 'bg-amber-100 text-amber-700', 'bg-emerald-100 text-emerald-700', 'bg-purple-100 text-purple-700'];

        $notificationsData = [];
        $collectionsData = [];
        $amountsData = [];
        $clerksData = [];

        $totalNotifications = 0;
        $totalCollections = 0;
        $totalAmounts = 0;

        foreach ($clerksStats as $index => $stat) {
            $color = $colors[$index % count($colors)];
            $bgColor = $bgColors[$index % count($bgColors)];
            $name = $stat->employee_name ?: 'بدون اسم';

            // 🔥 هنا نقوم بجلب عدد "إجراء يوجه" لهذا الموظف المحدد (وإذا لم يكن لديه شيء نضع 0)
            $userDirectedCount = $directedProcedures->get($stat->user_id, 0);

            // Données pour le Doughnut Chart
            $notificationsData[] = ['name' => $name, 'value' => (int) $stat->total_notifications, 'fill' => $color];
            $collectionsData[] = ['name' => $name, 'value' => (int) $stat->total_executions, 'fill' => $color];
            $amountsData[] = ['name' => $name, 'value' => (float) $stat->total_amount, 'fill' => $color];

            $totalNotifications += $stat->total_notifications;
            $totalCollections += $stat->total_executions;
            $totalAmounts += $stat->total_amount;

            // Données pour le Tableau détaillé
            $clerksData[] = [
                'id' => $index + 1,
                'name' => $name,
                'initials' => $this->getInitials($name),
                'color' => $bgColor,
                'notifications' => (int) $stat->total_notifications,
                'executions' => (int) $stat->total_executions,
                'amount' => number_format($stat->total_amount, 2, ',', ' ') . ' د.م',
                'coercion' => (int) $stat->total_coercion,
                'reports' => (int) $stat->total_reports,
                'cancellations' => (int) $stat->total_cancellations,
                'directed' => (int) $userDirectedCount // 🔥 تم ربط الرقم بنجاح!
            ];
        }

        $clerksCount = $clerksStats->count() ?: 1; // Éviter division par zéro

        $productivityDataSets = [
            'notifications' => [
                'data' => $notificationsData,
                'average' => round($totalNotifications / $clerksCount),
                'unit' => 'ملف'
            ],
            'collections' => [
                'data' => $collectionsData,
                'average' => round($totalCollections / $clerksCount),
                'unit' => 'ملف'
            ],
            'amounts' => [
                'data' => $amountsData,
                'average' => round($totalAmounts / $clerksCount),
                'unit' => 'د.م'
            ]
        ];

        return response()->json([
            'kpis' => $kpis,
            'monthlyData' => $monthlyData,
            'productivityDataSets' => $productivityDataSets,
            'clerksData' => $clerksData
        ]);
    }

    /**
     * دالة مساعدة لاستخراج الحروف الأولى من اسم الموظف (مثال: فاطمة الزهراء -> ف.ز)
     */
    private function getInitials($name)
    {
        if (empty($name)) return 'م';
        $words = explode(' ', trim($name));
        $initials = mb_substr($words[0], 0, 1, 'UTF-8');
        if (isset($words[1])) {
            $initials .= '.' . mb_substr($words[1], 0, 1, 'UTF-8');
        }
        return $initials;
    }
}
