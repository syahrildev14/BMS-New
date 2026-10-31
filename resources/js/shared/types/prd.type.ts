export interface Dapur {
  id: number;
  code: string;
  name: string;
  address: string | null;
  pic_name: string | null;
  pic_phone: string | null;
}

export interface Supplier {
  id: number;
  code: string;
  name: string;
  address: string | null;
  pic_name: string | null;
  pic_phone: string | null;
}

export interface Satuan {
  id: number;
  code: string;
  name: string;
}

export interface Item {
  id: number;
  sku: string;
  name: string;
  category: 'DRY_GOODS' | 'WET_GOODS';
  satuan_id: number;
  default_supplier_id: number | null;
  margin_type: 'PERCENTAGE' | 'FIXED_IDR';
  margin_value: string;
  base_cost_price: string;
  base_selling_price: string;
  is_active: boolean;
  satuan?: Satuan;
  default_supplier?: Supplier | null;
}

export interface DailyPrice {
  id: number;
  item_id: number;
  date: string;
  cost_price: string;
  calculated_price: string;
}

export type OrderStatus = 'DRAFT' | 'SUBMITTED' | 'LOCKED' | 'PROCESSING' | 'DELIVERED' | 'COMPLETED' | 'CANCELLED';

export interface OrderItem {
  id: number;
  order_id: number;
  item_id: number;
  supplier_id: number;
  surat_jalan_id: number | null;
  is_addon: boolean;
  qty_ordered: string;
  qty_received: string | null;
  qty_rejected: string | null;
  rejection_reason: string | null;
  estimated_price: string;
  final_cost_price: string | null;
  final_selling_price: string | null;
  item?: Item;
  supplier?: Supplier;
}

export interface Order {
  id: number;
  order_number: string;
  dapur_id: number;
  delivery_date: string;
  status: OrderStatus;
  notes: string | null;
  pdf_url: string | null;
  tanda_terima_pdf_url: string | null;
  dapur?: Dapur;
  order_items?: OrderItem[];
  surat_jalans?: SuratJalan[];
}

export interface SuratJalan {
  id: number;
  sj_number: string;
  order_id: number;
  type: 'PRIMARY' | 'ADDON';
  sequence_number: number;
  pdf_url: string | null;
  tanda_terima_pdf_url: string | null;
  printed_by: number;
  order?: Order;
}

export type FinanceDocumentStatus = 'DRAFT' | 'ISSUED' | 'PAID' | 'OVERDUE' | 'VOID';

export interface Invoice {
  id: number;
  invoice_number: string;
  order_id: number;
  dapur_id: number;
  supplier_id: number;
  total_amount: string;
  status: FinanceDocumentStatus;
  pdf_url: string | null;
  due_date: string | null;
  paid_at: string | null;
  payment_method: string | null;
  payment_reference: string | null;
  order?: Order;
  dapur?: Dapur;
  supplier?: Supplier;
}

export interface SupplierBill {
  id: number;
  bill_number: string;
  order_id: number;
  dapur_id: number;
  supplier_id: number;
  total_amount: string;
  status: FinanceDocumentStatus;
  due_date: string | null;
  paid_at: string | null;
  payment_method: string | null;
  payment_reference: string | null;
  order?: Order;
  dapur?: Dapur;
  supplier?: Supplier;
}

export interface FinanceSummary {
  revenue: number;
  cogs: number;
  gross_margin: number;
  margin_percent: number;
  ar_outstanding: number;
  ap_outstanding: number;
  cash_in: number;
  cash_out: number;
  net_cash_flow: number;
}

export interface FinanceTrendPoint {
  date: string;
  revenue: number;
  cogs: number;
  margin: number;
}

export interface FinanceAgingBucket {
  bucket: string;
  total: number;
  count: number;
}

export interface FinanceBreakdownRow {
  id: number;
  label: string;
  revenue: number;
  cogs: number;
  margin: number;
}

export interface FinanceCategoryBreakdown {
  category: 'DRY_GOODS' | 'WET_GOODS';
  revenue: number;
  cogs: number;
  margin: number;
}

export interface DashboardTrendPoint {
  label: string;
  value: number;
}

export interface DashboardWidgets {
  dapur?: { open_orders: number; pending_stt: number; trend: DashboardTrendPoint[] };
  ops?: { orders_pending_sj: number; pending_revisions?: number };
  purchasing?: { draft_pos: number };
  gudang?: { pos_awaiting_receive: number; out_of_stock_items: number };
  finance?: {
    filters: { from: string; to: string; dapur_id: number | null; supplier_id: number | null };
    summary: FinanceSummary;
    trend: FinanceTrendPoint[];
    arAging: FinanceAgingBucket[];
    apAging: FinanceAgingBucket[];
    byDapur: FinanceBreakdownRow[];
    bySupplier: FinanceBreakdownRow[];
    byCategory: FinanceCategoryBreakdown[];
    dapurs: { id: number; name: string }[];
    suppliers: { id: number; name: string }[];
    draft_invoices?: number;
    draft_bills?: number;
  };
}

export interface Role {
  id: number;
  name: string;
  permissions?: { id: number; name: string }[];
}

export interface Permission {
  id: number;
  name: string;
}

export interface AuditLog {
  id: number;
  user_id: number;
  action: string;
  description: string | null;
  old_data: Record<string, unknown> | null;
  new_data: { order_id: number; items: { id: number; item_id: number; supplier_id: number; qty_ordered: string; estimated_price: string }[] } | null;
  created_at: string;
}

export interface Gudang {
  id: number;
  code: string;
  name: string;
  address: string | null;
}

export type PurchaseOrderStatus = 'DRAFT' | 'SENT' | 'PARTIALLY_RECEIVED' | 'RECEIVED' | 'CANCELLED';

export interface PurchaseOrderItem {
  id: number;
  purchase_order_id: number;
  item_id: number;
  qty_ordered: string;
  qty_received: string;
  unit_cost: string;
  item?: Item;
}

export interface PurchaseOrder {
  id: number;
  po_number: string;
  supplier_id: number;
  gudang_id: number;
  triggered_by_order_id: number | null;
  status: PurchaseOrderStatus;
  sent_at: string | null;
  created_at: string;
  supplier?: Supplier;
  gudang?: Gudang;
  triggered_by_order?: Order | null;
  items?: PurchaseOrderItem[];
}

export interface StockItemRow {
  id: number;
  sku: string;
  name: string;
  satuan: Satuan | null;
  qty_on_hand: number;
}

export interface AppUser {
  id: number;
  name: string;
  email: string;
  email_verified_at?: string | null;
  dapur_id: number | null;
  supplier_id: number | null;
  is_active: boolean;
  avatar_url?: string | null;
  roles?: Role[];
  dapur?: Dapur | null;
  supplier?: Supplier | null;
}
