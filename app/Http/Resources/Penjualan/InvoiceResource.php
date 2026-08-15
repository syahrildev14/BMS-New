<?php

namespace App\Http\Resources\Penjualan;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [

            'nomor_invoice' =>
                'INV-' .
                date('Y') .
                '-' .
                str_pad($this->id, 6, '0', STR_PAD_LEFT),

            'nomor_penjualan' => $this->nomor_penjualan,

            'tanggal_invoice' => now()->toDateString(),

            'tanggal_penjualan' => $this->tanggal,

            'customer' => [

                'id' => $this->listOrderPenawaran?->sppg?->id,

                'nama_sppg' =>
                    $this->listOrderPenawaran?->sppg?->nama_sppg,

            ],

            'gudang' => [

                'id' => $this->gudang?->id,

                'nama_gudang' => $this->gudang?->nama_gudang,

            ],

            'penerima' => [

                'nama' => $this->tandaTerima?->nama_penerima,

                'jabatan' => $this->tandaTerima?->jabatan,

                'tanggal_terima' => $this->tandaTerima?->tanggal,

            ],

            'details' => $this->details->map(function ($detail) {

                return [

                    'barang' => [

                        'id' => $detail->barang?->id,

                        'sku_barang' => $detail->barang?->sku_barang,

                        'nama_barang' => $detail->barang?->nama_barang,

                        'satuan' => $detail->barang?->satuan?->nama_satuan,

                    ],

                    'qty' => $detail->qty,

                    'harga' => $detail->harga,

                    'subtotal' => $detail->subtotal,

                ];
            }),

            'grand_total' => $this->details->sum('subtotal'),

            'catatan' => $this->keterangan,

        ];
    }
}