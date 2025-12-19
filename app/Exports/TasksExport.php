<?php

namespace App\Exports;

use App\Models\Event;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TasksExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle
{
    public function __construct(
        protected Event $event
    ) {}

    public function collection(): Collection
    {
        return $this->event->tasks()->with('assignedUser')->orderBy('due_date')->get();
    }

    public function headings(): array
    {
        return [
            'Titre',
            'Description',
            'Statut',
            'Priorité',
            'Assigné à',
            'Date d\'échéance',
            'Complété le',
        ];
    }

    public function map($task): array
    {
        return [
            $task->title,
            $task->description ?? '',
            $this->getStatusLabel($task->status),
            $this->getPriorityLabel($task->priority),
            $task->assignedUser?->name ?? '',
            $task->due_date?->format('d/m/Y') ?? '',
            $task->completed_at?->format('d/m/Y H:i') ?? '',
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
        return 'Tâches';
    }

    protected function getStatusLabel(string $status): string
    {
        return match ($status) {
            'todo' => 'À faire',
            'in_progress' => 'En cours',
            'completed' => 'Terminé',
            'cancelled' => 'Annulé',
            default => $status,
        };
    }

    protected function getPriorityLabel(string $priority): string
    {
        return match ($priority) {
            'low' => 'Basse',
            'medium' => 'Moyenne',
            'high' => 'Haute',
            default => $priority,
        };
    }
}
