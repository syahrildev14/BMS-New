export interface Satuan {
  id: number;
  kode_satuan: string;
  nama_satuan: string;
  created_at: string;
  updated_at: string;
}

export type SatuanCreatePayload = Pick<Satuan, 'kode_satuan' | 'nama_satuan'>;

export type SatuanUpdatePayload = Partial<SatuanCreatePayload>;
