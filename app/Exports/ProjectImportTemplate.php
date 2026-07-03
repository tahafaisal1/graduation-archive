<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProjectImportTemplate implements FromArray, WithHeadings, WithStyles
{
    use Exportable;

    public function headings(): array
    {
        return [
            'project_title',
            'description',
            'academic_year',
            'department_code',
            'specialization_name',
            'supervisor_email',
            'student_1_name',
            'student_1_reg',
            'student_2_name',
            'student_2_reg',
            'student_3_name',
            'student_3_reg',
            'final_score',
        ];
    }

    public function array(): array
    {
        return [
            [
                '[EXAMPLE] نظام إدارة المخزون',
                'مشروع تخرج لتطوير نظام إدارة المخزون باستخدام Laravel',
                '2023/2024',
                'CS',
                'هندسة البرمجيات',
                'supervisor@college.edu',
                'أحمد محمد علي',
                'REG-2020-001',
                'فاطمة حسن خالد',
                'REG-2020-002',
                '',
                '',
                '85.50',
            ],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1E40AF'],
                ],
            ],
            2 => [
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'FEF9C3'],
                ],
                'font' => ['italic' => true, 'color' => ['rgb' => '92400E']],
            ],
        ];
    }
}
