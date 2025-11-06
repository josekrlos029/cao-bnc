<?php

namespace App\Exports;

use App\Models\Transaction;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TransactionsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected $transactions;

    public function __construct($transactions)
    {
        $this->transactions = $transactions;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return $this->transactions;
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Exchange',
            'Número de Orden',
            'Tipo de Orden',
            'Activo',
            'Moneda',
            'Precio Unitario',
            'Cantidad',
            'Comisión',
            'Recibida',
            'Precio Total',
            'Cliente',
            'Tipo de Documento',
            'Número de Documento',
            'Tipo de Pago',
            'Fecha',
        ];
    }

    /**
     * @param Transaction $transaction
     * @return array
     */
    public function map($transaction): array
    {
        return [
            $transaction->exchange ?? '-',
            $transaction->order_number ?? '-',
            $transaction->order_type ?? '-',
            $transaction->asset_type ?? '-',
            $transaction->fiat_type ?? '-',
            $transaction->price ?? 0,
            $transaction->quantity ?? 0,
            $transaction->commission ?? 0,
            $transaction->amount ?? 0,
            $transaction->total_price ?? 0,
            $transaction->counter_party ?? '-',
            $transaction->dni_type ?? '-',
            $transaction->counter_party_dni ?? '-',
            $transaction->payment_method ?? '-',
            $transaction->binance_create_time ? $transaction->binance_create_time->format('Y-m-d H:i:s') : '-',
        ];
    }

    /**
     * @param Worksheet $sheet
     * @return array
     */
    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}

