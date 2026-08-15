<?php

namespace App\Http\Resources\Penjualan;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TandaTerimaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [

            'id' => $this->id,

            'nomor_tanda_terima' =>
                'TT-' .
                date('Y') .
                '-' .
                str_pad($this->id, 6, '0', STR_PAD_LEFT),

            'tanggal' => $this->tanggal,

            'nama_penerima' => $this->nama_penerima,

            'jabatan' => $this->jabatan,

            'catatan' => $this->catatan,

            'penjualan' => [

                'id' => $this->penjualan?->id,

                'nomor_penjualan' =>
                    $this->penjualan?->nomor_penjualan,

                'tanggal' =>
                    $this->penjualan?->tanggal,

                'status' =>
                    $this->penjualan?->status,

            ],

            'customer' => [

                'id' =>
                    $this->penjualan?->listOrderPenawaran?->sppg?->id,

                'nama_sppg' =>
                    $this->penjualan?->listOrderPenawaran?->sppg?->nama_sppg,

            ],

            'gudang' => [

                'id' =>
                    $this->penjualan?->gudang?->id,

                'nama_gudang' =>
                    $this->penjualan?->gudang?->nama_gudang,

            ],

            'details' => $this->whenLoaded('penjualan', function () {

                return $this->penjualan->details->map(function ($detail) {

                    return [

                        'barang' => [

                            'id' => $detail->barang?->id,

                            'sku_barang' =>
                                $detail->barang?->sku_barang,

                            'nama_barang' =>
                                $detail->barang?->nama_barang,

                            'satuan' =>
                                $detail->barang?->satuan?->nama_satuan,

                        ],

                        'qty' => $detail->qty,

                        'harga' => $detail->harga,

                        'subtotal' => $detail->subtotal,

                    ];
                });
            }),

        ];
    }
}