<?php

namespace App\Services\Tenant;

use App\Models\Tenant\OrderOfService;
use App\Models\Tenant\QaTemplate;
use App\Models\Tenant\QaInspection;
use App\Models\Tenant\QaDefect;
use App\Models\Tenant\OrderFeedback;
use App\Models\Tenant\Warranty;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class QualityService
{
    public function createInspection(OrderOfService $order, ?int $templateId, string $inspectorId): QaInspection
    {
        return DB::connection('tenant')->transaction(function () use ($order, $templateId, $inspectorId) {
            $itemsChecked = [];

            if ($templateId) {
                $template = QaTemplate::findOrFail($templateId);
                foreach ($template->items as $item) {
                    $itemsChecked[] = [
                        'name' => $item,
                        'status' => 'pending',
                        'notes' => '',
                    ];
                }
            }

            return QaInspection::create([
                'order_of_service_id' => $order->id,
                'qa_template_id' => $templateId,
                'inspector_id' => $inspectorId,
                'status' => QaInspection::STATUS_PENDING,
                'items_checked' => $itemsChecked,
            ]);
        });
    }

    public function updateInspectionItems(QaInspection $inspection, array $items): QaInspection
    {
        if ($inspection->status !== QaInspection::STATUS_PENDING) {
            throw new \Exception('Não é possível alterar os itens de uma inspeção já finalizada.');
        }

        $inspection->update([
            'items_checked' => $items,
        ]);

        return $inspection;
    }

    public function completeInspection(QaInspection $inspection, string $status, ?string $notes): QaInspection
    {
        if (!in_array($status, [QaInspection::STATUS_PASSED, QaInspection::STATUS_FAILED])) {
            throw new InvalidArgumentException('Status de inspeção inválido.');
        }

        if ($inspection->status !== QaInspection::STATUS_PENDING) {
            throw new \Exception('Esta inspeção já está finalizada.');
        }

        $inspection->update([
            'status' => $status,
            'notes' => $notes,
            'completed_at' => now(),
        ]);

        return $inspection;
    }

    public function logDefect(QaInspection $inspection, string $description, string $severity): QaDefect
    {
        if (empty(trim($description))) {
            throw new InvalidArgumentException('A descrição do defeito é obrigatória.');
        }

        if (!in_array($severity, [QaDefect::SEVERITY_LOW, QaDefect::SEVERITY_MEDIUM, QaDefect::SEVERITY_HIGH, QaDefect::SEVERITY_CRITICAL])) {
            throw new InvalidArgumentException('Severidade do defeito inválida.');
        }

        return QaDefect::create([
            'qa_inspection_id' => $inspection->id,
            'description' => $description,
            'severity' => $severity,
            'status' => QaDefect::STATUS_OPEN,
        ]);
    }

    public function resolveDefect(QaDefect $defect, string $userId): QaDefect
    {
        if ($defect->status === QaDefect::STATUS_RESOLVED) {
            throw new \Exception('Este defeito já está resolvido.');
        }

        $defect->update([
            'status' => QaDefect::STATUS_RESOLVED,
            'resolved_at' => now(),
            'resolved_by' => $userId,
        ]);

        return $defect;
    }

    public function registerFeedback(OrderOfService $order, int $rating, int $npsScore, ?string $comments): OrderFeedback
    {
        if ($rating < 1 || $rating > 5) {
            throw new InvalidArgumentException('O rating deve ser entre 1 e 5.');
        }

        if ($npsScore < 0 || $npsScore > 10) {
            throw new InvalidArgumentException('A nota NPS deve ser entre 0 e 10.');
        }

        return OrderFeedback::updateOrCreate(
            ['order_of_service_id' => $order->id],
            [
                'rating' => $rating,
                'nps_score' => $npsScore,
                'comments' => $comments,
            ]
        );
    }

    public function issueWarranty(OrderOfService $order, string $type, int $durationDays, ?string $terms): Warranty
    {
        if (!in_array($type, [Warranty::TYPE_FULL, Warranty::TYPE_PARTS, Warranty::TYPE_LABOR])) {
            throw new InvalidArgumentException('Tipo de garantia inválido.');
        }

        if ($durationDays <= 0) {
            throw new InvalidArgumentException('A duração da garantia deve ser maior que zero.');
        }

        $startDate = now();
        $endDate = now()->addDays($durationDays);

        return Warranty::create([
            'order_of_service_id' => $order->id,
            'type' => $type,
            'duration_days' => $durationDays,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'terms' => $terms,
            'status' => Warranty::STATUS_ACTIVE,
        ]);
    }

    public function getQualityMetrics(): array
    {
        $totalInspections = QaInspection::whereIn('status', [QaInspection::STATUS_PASSED, QaInspection::STATUS_FAILED])->count();
        $passedInspections = QaInspection::where('status', QaInspection::STATUS_PASSED)->count();

        $passRate = $totalInspections > 0 ? round(($passedInspections / $totalInspections) * 100, 2) : 100.0;

        $openDefects = QaDefect::whereIn('status', [QaDefect::STATUS_OPEN, QaDefect::STATUS_IN_REWORK])->count();

        $defectsBySeverity = QaDefect::select('severity', DB::raw('count(*) as count'))
            ->groupBy('severity')
            ->pluck('count', 'severity')
            ->toArray();

        $totalFeedback = OrderFeedback::count();
        $promoters = OrderFeedback::where('nps_score', '>=', 9)->count();
        $detractors = OrderFeedback::where('nps_score', '<=', 6)->count();

        $npsScore = $totalFeedback > 0 ? round((($promoters - $detractors) / $totalFeedback) * 100, 2) : 0.0;
        $averageRating = round((float) (OrderFeedback::avg('rating') ?? 0.0), 2);

        return [
            'inspections' => [
                'total' => $totalInspections,
                'passed' => $passedInspections,
                'pass_rate_percentage' => $passRate,
            ],
            'defects' => [
                'open_count' => $openDefects,
                'by_severity' => $defectsBySeverity,
            ],
            'customer_feedback' => [
                'total_responses' => $totalFeedback,
                'nps_score' => $npsScore,
                'average_rating' => $averageRating,
            ],
        ];
    }
}
