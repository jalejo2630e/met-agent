<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ClientTemplateExport implements FromArray, WithHeadings
{
    public function __construct(
        protected array $headers,
        protected array $exampleRow
    ) {}

    public function array(): array
    {
        return [$this->exampleRow];
    }

    public function headings(): array
    {
        return $this->headers;
    }
}
