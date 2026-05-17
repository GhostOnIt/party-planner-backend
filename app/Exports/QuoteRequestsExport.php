<?php

namespace App\Exports;

use App\Models\QuoteRequest;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class QuoteRequestsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle
{
    public function __construct(
        protected array $filters = []
    ) {}

    public function collection(): Collection
    {
        $query = QuoteRequest::query()
            ->with(['currentStage', 'assignedAdmin:id,name', 'user:id,name,email'])
            ->withCount('offers');

        if (!empty($this->filters['stage_id'])) {
            $query->where('current_stage_id', $this->filters['stage_id']);
        }

        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        if (!empty($this->filters['outcome'])) {
            $query->where('outcome', $this->filters['outcome']);
        }

        if (!empty($this->filters['assigned_admin_id'])) {
            $query->where('assigned_admin_id', $this->filters['assigned_admin_id']);
        }

        if (!empty($this->filters['date_from'])) {
            $query->where('created_at', '>=', $this->filters['date_from']);
        }

        if (!empty($this->filters['date_to'])) {
            $query->where('created_at', '<=', $this->filters['date_to']);
        }

        if (!empty($this->filters['budget_min'])) {
            $query->where('budget_estimate', '>=', $this->filters['budget_min']);
        }

        if (!empty($this->filters['budget_max'])) {
            $query->where('budget_estimate', '<=', $this->filters['budget_max']);
        }

        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('tracking_code', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%")
                    ->orWhere('contact_email', 'like', "%{$search}%");
            });
        }

        return $query->latest()->get();
    }

    public function headings(): array
    {
        return [
            'Code',
            'Société',
            'Contact',
            'Email',
            'Téléphone',
            'Besoins',
            'Budget',
            'Équipe',
            'Étape',
            'Statut',
            'Issue',
            'Nb offres',
            'Date création',
        ];
    }

    public function map($quoteRequest): array
    {
        return [
            $quoteRequest->tracking_code,
            $quoteRequest->company_name,
            $quoteRequest->contact_name,
            $quoteRequest->contact_email,
            $quoteRequest->contact_phone,
            $quoteRequest->business_needs,
            $quoteRequest->budget_estimate ? number_format($quoteRequest->budget_estimate, 0, ',', ' ') : '',
            $quoteRequest->team_size ?? '',
            $quoteRequest->currentStage?->name ?? 'Sans étape',
            $this->getStatusLabel($quoteRequest->status),
            $this->getOutcomeLabel($quoteRequest->outcome),
            $quoteRequest->offers_count,
            $quoteRequest->created_at?->format('d/m/Y H:i'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1F2937'],
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'Demandes Business';
    }

    protected function getStatusLabel(string $status): string
    {
        return match ($status) {
            'open' => 'Ouverte',
            'closed' => 'Clôturée',
            default => $status,
        };
    }

    protected function getOutcomeLabel(?string $outcome): string
    {
        return match ($outcome) {
            'won' => 'Gagnée',
            'lost' => 'Perdue',
            null => '',
            default => $outcome,
        };
    }
}
