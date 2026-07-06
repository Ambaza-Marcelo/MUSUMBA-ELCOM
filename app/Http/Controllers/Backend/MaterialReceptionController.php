<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Backend\Concerns\GuardsStockApproval;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Storage;
use App\Models\Material;
use App\Models\Item;
use App\Models\MaterialReception;
use App\Models\MaterialReceptionDetail;
use App\Models\MaterialBigStoreDetail;
use App\Models\MaterialBigStore;
use App\Models\MaterialExtraBigStore;
use App\Models\MaterialExtraBigStoreDetail;
use App\Models\MaterialSupplierOrderDetail;
use App\Models\MaterialSupplierOrder;
use App\Models\MaterialPurchase;
use App\Models\MaterialPurchaseDetail;
use App\Models\MaterialBigReport;
use App\Models\MaterialExtraBigReport;
use App\Models\FinishedGoodsStore;
use App\Models\GoodsStore;
use App\Models\FinishedGoodsStoreDetail;
use App\Models\GoodsStoreDetail;
use App\Models\FinishedGoodReport;
use App\Models\GoodsStoreReport;
use App\Models\Supplier;
use App\Models\Accounting\AccountCostCenter;
use App\Models\Accounting\AccountJournalEntry;
use App\Models\Accounting\AccountJournalEntryDetail;
use App\Models\Accounting\AccountLedger;
use App\Models\Accounting\AccountAccount;
use App\Models\Accounting\AccountSetting;
use App\Models\Accounting\CashOutFlowRequest;
use App\Models\Accounting\CashOutFlowRequestDetail;
use App\Exports\MaterialReceptionExport;
use Carbon\Carbon;
use PDF;
use Validator;
use Excel;
use Mail;

class MaterialReceptionController extends Controller
{
    use GuardsStockApproval;

    //
     public $user;

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::guard('admin')->user();
            return $next($request);
        });
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (is_null($this->user) || !$this->user->can('material_reception.view')) {
            abort(403, 'Sorry !! You are Unauthorized to view any reception !');
        }

        $query = MaterialReception::query();
        
        // Si des dates sont fournies, filtrer par période
        if ($request->filled('date_debut') && $request->filled('date_fin')) {
            $dateDebut = Carbon::parse($request->date_debut)->startOfDay();
            $dateFin = Carbon::parse($request->date_fin)->endOfDay();
            $query->whereBetween('created_at', [$dateDebut, $dateFin]);
        } else {
            // Par défaut, afficher les 100 lignes les plus récentes
            $query->take(100);
        }
        
        $receptions = $query->with(['destinationStore', 'destinationExtraStore'])
            ->orderBy('id', 'desc')
            ->get();
        return view('backend.pages.material_reception.index', compact('receptions'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create($order_no)
    {
        if (is_null($this->user) || !$this->user->can('material_reception.create')) {
            abort(403, 'Sorry !! You are Unauthorized to create any reception !');
        }

        $materials = Material::where('store_type', '!=', 2)->orderBy('name', 'asc')->get();
        $material_medium_stores = MaterialBigStore::all();
        $material_big_stores = MaterialExtraBigStore::all();
        $goods_stores = GoodsStore::orderBy('name')->get();
        $suppliers = Supplier::all();
        $data = MaterialSupplierOrder::where('order_no', $order_no)->first();
        $reception = null;
        if (!$data) {
            abort(404, 'Commande fournisseur introuvable.');
        }
        if ($data->status == -5) {
            $reception = MaterialReception::where('order_no', $order_no)->orderBy('reception_no', 'desc')->first();
            $datas = $reception
                ? MaterialReceptionDetail::with(['material.materialMeasurement', 'item'])->where('reception_no', $reception->reception_no)->get()
                : collect();
        } elseif ($data->status == 4) {
            $datas = MaterialSupplierOrderDetail::with(['material.materialMeasurement', 'item'])->where('order_no', $order_no)->get();
        } else {
            abort(403, 'Sorry !! You are Unauthorized!');
        }

        return view('backend.pages.material_reception.create', array_merge(
            compact('materials', 'order_no', 'datas', 'suppliers', 'data', 'material_big_stores', 'material_medium_stores', 'goods_stores', 'reception'),
            $this->receptionOrderLineFlags($datas),
            ['storePreviewByLine' => $this->previewLineStores($datas)]
        ));
    }

    public function createWithoutRemaining($order_no)
    {
        if (is_null($this->user) || !$this->user->can('material_reception.create')) {
            abort(403, 'Sorry !! You are Unauthorized to create any reception !');
        }

        $materials = Material::where('store_type', '!=', 2)->orderBy('name', 'asc')->get();
        $material_medium_stores = MaterialBigStore::all();
        $material_big_stores = MaterialExtraBigStore::all();
        $goods_stores = GoodsStore::orderBy('name')->get();
        $suppliers = Supplier::orderBy('supplier_name', 'asc')->get();
        $data = MaterialSupplierOrderDetail::with(['material.materialMeasurement', 'item'])->where('order_no', $order_no)->first();
        $datas = MaterialSupplierOrderDetail::with(['material.materialMeasurement', 'item'])->where('order_no', $order_no)->get();
        $reception = null;

        return view('backend.pages.material_reception.create_without', array_merge(
            compact('materials', 'order_no', 'datas', 'data', 'material_big_stores', 'suppliers', 'material_medium_stores', 'goods_stores', 'reception'),
            $this->receptionOrderLineFlags($datas),
            ['storePreviewByLine' => $this->previewLineStores($datas)]
        ));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    public function store(Request $request)
    {

        if (is_null($this->user) || !$this->user->can('material_reception.create')) {
            abort(403, 'Sorry !! You are Unauthorized to create any reception !');
        }

        $orderDetails = $this->buildSubmittedLinesFromRequest($request);
        $lineFlags = $this->receptionOrderLineFlags($orderDetails);

        $rules = array(
                'date'  => 'required',
                'quantity_ordered.*'  => 'required',
                'purchase_price.*'  => 'required',
                'quantity_received.*'  => 'required',
                'order_no'  => 'required',
                'invoice_no'  => 'required',
                'receptionist'  => 'required',
                'vat_supplier_payer'  => 'required',
                'type_reception'  => 'required',
                'invoice_currency'  => 'required',
                'description'  => 'required|max:490'
            );

            $error = Validator::make($request->all(), $rules);
            $this->validateReceptionDestinations($error, $request, $lineFlags);

            if($error->fails()){
                return redirect()->back()->withErrors($error)->withInput();
            }

            try {DB::beginTransaction();

            $material_id = $request->material_id ?? [];
            $item_id = $request->item_id ?? [];
            $date = $request->date;
            $vat_supplier_payer = $request->vat_supplier_payer;
            $origin_store_id = $request->origin_store_id;
            $invoice_currency = $request->invoice_currency;
            $handingover = $request->handingover;
            $receptionist = $request->receptionist;
            $vat_rate = $request->vat_rate;
            $type_reception = $request->type_reception;
            $order_no = $request->order_no;
            $invoice_no = $request->invoice_no;
            $description =$request->description; 
            $quantity_ordered = $request->quantity_ordered;
            $purchase_price = $request->purchase_price;
            $selling_price = $request->selling_price;
            $quantity_received = $request->quantity_received;
            $supplier_id = $request->supplier_id;

            $order = MaterialSupplierOrder::where('order_no',$order_no)->first();
            

            $latest = MaterialReception::orderBy('id','desc')->first();
            if ($latest) {
               $reception_no = 'REC' . (str_pad((int)$latest->id + 1, 4, '0', STR_PAD_LEFT)); 
            }else{
               $reception_no = 'REC' . (str_pad((int)0 + 1, 4, '0', STR_PAD_LEFT));  
            }

            if (!empty($vat_rate)) {
                $vat_rate = $vat_rate;
            }else{
                $vat_rate = 0;
            }

            $created_by = $this->user->name;

            $reception_signature = config('app.tin_number_company').Carbon::parse(Carbon::now())->format('YmdHis')."/".$reception_no;

            $headerStore = null;
            $rowCount = max(count($material_id), count($item_id), count($quantity_received));
            for( $count = 0; $count < $rowCount; $count++ ){
                if($vat_supplier_payer == 1){
                    $price_nvat = ($purchase_price[$count]*$quantity_received[$count]);
                    $vat = ($price_nvat* $vat_rate)/100;
                    $price_wvat = $price_nvat + $vat;
                    $total_amount_purchase = $price_wvat; 

                }else{
                    $price_nvat = ($purchase_price[$count]*$quantity_received[$count]);
                    $vat = 0;
                    $price_wvat = $price_nvat + $vat;
                    $total_amount_purchase = $price_wvat;
                }
                
                $total_amount_ordered = $quantity_ordered[$count] * $purchase_price[$count];
                $total_amount_received = $total_amount_purchase;


                if ($order->status == -5) {
                    $total_quantity_received = DB::table('material_reception_details')
                    ->where('order_no', '=', $order_no)
                    ->sum('quantity_received');

                    $quantity_remaining = $quantity_ordered[$count] - ($total_quantity_received + $quantity_received[$count]);

                }else{
                    $quantity_remaining = $quantity_ordered[$count] - $quantity_received[$count];
                }

                if ($quantity_remaining > 0) {
                    $type_reception = 0;
                }else{
                    $type_reception = 1;
                }

                $data = array(
                    'material_id' => $material_id[$count] ?? null,
                    'item_id' => $item_id[$count] ?? null,
                    'date' => $date,
                    'quantity_ordered' => $quantity_ordered[$count],
                    'quantity_received' => $quantity_received[$count],
                    'quantity_remaining' => $quantity_remaining,
                    'vat_rate' => $vat_rate,
                    'purchase_price' => $purchase_price[$count],
                    'type_reception' => $type_reception,
                    'total_amount_ordered' => $total_amount_ordered,
                    'total_amount_received' => $total_amount_received,
                    'total_amount_purchase' => $total_amount_purchase,
                    'order_no' => $order_no,
                    'invoice_no' => $invoice_no,
                    'invoice_currency' => $invoice_currency,
                    'vat' => $vat,
                    'price_nvat' => $price_nvat,
                    'price_wvat' => $price_wvat,
                    'supplier_id' => $supplier_id,
                    'vat_supplier_payer' => $vat_supplier_payer,
                    'receptionist' => $receptionist,
                    'handingover' => $handingover,
                    'reception_no' => $reception_no,
                    'reception_signature' => $reception_signature,
                    'created_by' => $created_by,
                    'description' => $description,
                    'status' => 1,
                    'created_at' => \Carbon\Carbon::now()
                );

                $data = $this->applyRowDestination(
                    $data,
                    $this->parseLineId($material_id[$count] ?? null),
                    $this->parseLineId($item_id[$count] ?? null)
                );
                if ($headerStore === null) {
                    $headerStore = $data;
                }
                $insert_data[] = $data;
            }
            MaterialReceptionDetail::insert($insert_data);


            //create reception
            $reception = new MaterialReception();
            $reception->date = $date;
            $reception->reception_no = $reception_no;
            $reception->reception_signature = $reception_signature;
            $reception->order_no = $order_no;
            $reception->vat_supplier_payer = $vat_supplier_payer;
            $reception->invoice_no = $invoice_no;
            $reception->type_store = $headerStore['type_store'] ?? null;
            $reception->code_store = $headerStore['code_store'] ?? null;
            $reception->invoice_currency = $invoice_currency;
            $reception->receptionist = $receptionist;
            $reception->vat_rate = $vat_rate;
            $reception->type_reception = $type_reception;
            $reception->handingover = $handingover;
            $reception->supplier_id = $supplier_id;
            $reception->destination_store_id = $headerStore['destination_store_id'] ?? null;
            $reception->dest_extra_store_id = $headerStore['dest_extra_store_id'] ?? null;
            $reception->created_by = $created_by;
            $reception->status = 1;
            $reception->description = $description;
            $reception->created_at = \Carbon\Carbon::now();
            $reception->save();

            DB::commit();
            session()->flash('success', 'reception has been created !!');
            return redirect()->route('admin.material-receptions.index');
        } catch (\RuntimeException $e) {
            DB::rollback();
            return redirect()->back()->withErrors(['stock' => $e->getMessage()])->withInput();
        } catch (\Exception $e) {
            // An error occured; cancel the transaction...

            DB::rollback();

            // and throw the error again.

            throw $e;
        }
            
    }

    public function storeWithoutRemaining(Request $request)
    {

        if (is_null($this->user) || !$this->user->can('material_reception.create')) {
            abort(403, 'Sorry !! You are Unauthorized to create any reception !');
        }

        $orderDetails = $this->buildSubmittedLinesFromRequest($request);
        $lineFlags = $this->receptionOrderLineFlags($orderDetails);

        $rules = array(
                'date'  => 'required',
                'quantity_ordered.*'  => 'required',
                'purchase_price.*'  => 'required',
                'quantity_received.*'  => 'required',
                'order_no'  => 'required',
                'invoice_no'  => 'required',
                'receptionist'  => 'required',
                'vat_supplier_payer'  => 'required',
                'type_reception'  => 'required',
                'invoice_currency'  => 'required',
                'description'  => 'required|max:490'
            );

            $error = Validator::make($request->all(), $rules);
            $error->after(function ($validator) use ($request) {
                $material_id = $request->material_id ?? [];
                $item_id = $request->item_id ?? [];
                $quantity_received = $request->quantity_received ?? [];
                $n = max(count($material_id), count($item_id), count($quantity_received));
                for ($i = 0; $i < $n; $i++) {
                    $mid = trim((string)($material_id[$i] ?? ''));
                    $iid = trim((string)($item_id[$i] ?? ''));
                    if ($mid === '' && $iid === '') {
                        $validator->errors()->add('material_id.'.$i, __('messages.each_line_must_contain_item_or_material'));
                    }
                }
            });
            $this->validateReceptionDestinations($error, $request, $lineFlags);

            if($error->fails()){
                return redirect()->back()->withErrors($error)->withInput();
            }

            try {DB::beginTransaction();

            $material_id = $request->material_id ?? [];
            $item_id = $request->item_id ?? [];
            $date = $request->date;
            $vat_supplier_payer = $request->vat_supplier_payer;
            $origin_store_id = $request->origin_store_id;
            $invoice_currency = $request->invoice_currency;
            $handingover = $request->handingover;
            $receptionist = $request->receptionist;
            $vat_rate = $request->vat_rate;
            $type_reception = $request->type_reception;
            $order_no = $request->order_no;
            $invoice_no = $request->invoice_no;
            $description =$request->description; 
            $quantity_ordered = $request->quantity_ordered;
            $purchase_price = $request->purchase_price;
            $selling_price = $request->selling_price;
            $quantity_received = $request->quantity_received;
            $supplier_id = $request->supplier_id;
            

            $latest = MaterialReception::orderBy('id','desc')->first();
            if ($latest) {
               $reception_no = 'M-RN' . (str_pad((int)$latest->id + 1, 4, '0', STR_PAD_LEFT)); 
            }else{
               $reception_no = 'M-RN' . (str_pad((int)0 + 1, 4, '0', STR_PAD_LEFT));  
            }

            if (!empty($vat_rate)) {
                $vat_rate = $vat_rate;
            }else{
                $vat_rate = 0;
            }

            $created_by = $this->user->name;

            $reception_signature = config('app.tin_number_company').Carbon::parse(Carbon::now())->format('YmdHis')."/".$reception_no;

            $headerStore = null;
            $rowCountRn = max(count($material_id), count($item_id), count($quantity_received));
            for( $count = 0; $count < $rowCountRn; $count++ ){
                if($vat_supplier_payer == 1){
                    $price_nvat = ($purchase_price[$count]*$quantity_received[$count]);
                    $vat = ($price_nvat* $vat_rate)/100;
                    $price_wvat = $price_nvat + $vat;
                    $total_amount_purchase = $price_wvat; 

                }else{
                    $price_nvat = ($purchase_price[$count]*$quantity_received[$count]);
                    $vat = 0;
                    $price_wvat = $price_nvat + $vat;
                    $total_amount_purchase = $price_wvat;
                }

                $total_amount_ordered = $quantity_ordered[$count] * $purchase_price[$count];
                $total_amount_received = $total_amount_purchase;

                $data = array(
                    'material_id' => $material_id[$count] ?? null,
                    'item_id' => $item_id[$count] ?? null,
                    'date' => $date,
                    'quantity_ordered' => $quantity_ordered[$count],
                    'quantity_received' => $quantity_received[$count],
                    'quantity_remaining' => $quantity_received[$count] - $quantity_ordered[$count],
                    'vat_rate' => $vat_rate,
                    'purchase_price' => $purchase_price[$count],
                    'type_reception' => $type_reception,
                    'total_amount_ordered' => $total_amount_ordered,
                    'total_amount_received' => $total_amount_received,
                    'total_amount_purchase' => $total_amount_purchase,
                    'order_no' => $order_no,
                    'invoice_no' => $invoice_no,
                    'invoice_currency' => $invoice_currency,
                    'vat' => $vat,
                    'price_nvat' => $price_nvat,
                    'price_wvat' => $price_wvat,
                    'supplier_id' => $supplier_id,
                    'vat_supplier_payer' => $vat_supplier_payer,
                    'receptionist' => $receptionist,
                    'handingover' => $handingover,
                    'reception_no' => $reception_no,
                    'reception_signature' => $reception_signature,
                    'created_by' => $created_by,
                    'description' => $description,
                    'status' => 1,
                    'created_at' => \Carbon\Carbon::now()
                );

                $data = $this->applyRowDestination(
                    $data,
                    $this->parseLineId($material_id[$count] ?? null),
                    $this->parseLineId($item_id[$count] ?? null)
                );
                if ($headerStore === null) {
                    $headerStore = $data;
                }
                $insert_data[] = $data;
            }
            MaterialReceptionDetail::insert($insert_data);


            //create reception
            $reception = new MaterialReception();
            $reception->date = $date;
            $reception->reception_no = $reception_no;
            $reception->reception_signature = $reception_signature;
            $reception->order_no = $order_no;
            $reception->vat_supplier_payer = $vat_supplier_payer;
            $reception->invoice_no = $invoice_no;
            $reception->invoice_currency = $invoice_currency;
            $reception->receptionist = $receptionist;
            $reception->type_store = $headerStore['type_store'] ?? null;
            $reception->code_store = $headerStore['code_store'] ?? null;
            $reception->vat_rate = $vat_rate;
            $reception->type_reception = $type_reception;
            $reception->handingover = $handingover;
            $reception->supplier_id = $supplier_id;
            $reception->destination_store_id = $headerStore['destination_store_id'] ?? null;
            $reception->dest_extra_store_id = $headerStore['dest_extra_store_id'] ?? null;
            $reception->created_by = $created_by;
            $reception->status = 1;
            $reception->description = $description;
            $reception->created_at = \Carbon\Carbon::now();
            $reception->save();

            MaterialSupplierOrder::where('order_no', '=', $order_no)
                ->update(['flag1' => 1]);
            MaterialSupplierOrderDetail::where('order_no', '=', $order_no)
                ->update(['flag1' => 1]);

            DB::commit();
            session()->flash('success', 'reception has been created !!');
            return redirect()->route('admin.material-receptions.index');
        } catch (\RuntimeException $e) {
            DB::rollback();
            return redirect()->back()->withErrors(['stock' => $e->getMessage()])->withInput();
        } catch (\Exception $e) {
            // An error occured; cancel the transaction...

            DB::rollback();

            // and throw the error again.

            throw $e;
        }
            
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($reception_no)
    {
        //
        $reception = MaterialReception::with(['supplier', 'destinationStore', 'destinationExtraStore'])->where('reception_no', $reception_no)->firstOrFail();
        $receptionDetails = MaterialReceptionDetail::with(['material.materialMeasurement', 'item', 'supplier', 'destinationStore', 'destinationExtraStore'])
            ->where('reception_no', $reception_no)->get();
        
        // Calculate totals
        $totalQuantityOrdered = $receptionDetails->sum('quantity_ordered');
        $totalQuantityReceived = $receptionDetails->sum('quantity_received');
        $totalAmountReceived = $receptionDetails->sum('total_amount_received');

        $suppliers = Supplier::orderBy('supplier_name', 'asc')->get();
        
        return view('backend.pages.material_reception.show', compact('reception', 'receptionDetails', 'totalQuantityOrdered', 'totalQuantityReceived', 'totalAmountReceived', 'suppliers'));
    }

    /**
     * Update only the supplier for the reception (permission: supplier.edit).
     */
    public function updateSupplier(Request $request, $reception_no)
    {
        if (is_null($this->user) || !$this->user->can('supplier.edit')) {
            abort(403, 'Vous n\'êtes pas autorisé à modifier le fournisseur.');
        }
        $request->validate(['supplier_id' => 'required|exists:suppliers,id']);
        $reception = MaterialReception::where('reception_no', $reception_no)->firstOrFail();
        $reception->update(['supplier_id' => $request->supplier_id]);
        return redirect()->route('admin.material-receptions.show', $reception_no)->with('success', __('messages.supplier_updated'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($order_no)
    {
        if (is_null($this->user) || !$this->user->can('material_reception.edit')) {
            abort(403, 'Sorry !! You are Unauthorized to edit any reception !');
        }

        $reception_no = MaterialReception::where('order_no',$order_no)->value('reception_no');

        $materials = Material::where('store_type', '!=', 2)->orderBy('name', 'asc')->get();
        $material_medium_stores = MaterialBigStore::all();
        $material_big_stores = MaterialExtraBigStore::all();
        $goods_stores = GoodsStore::orderBy('name')->get();
        $suppliers = Supplier::orderBy('supplier_name', 'asc')->get();
        $data = MaterialSupplierOrderDetail::with(['material.materialMeasurement', 'item'])->where('order_no', $order_no)->first();
        $datas = MaterialSupplierOrderDetail::with(['material.materialMeasurement', 'item'])->where('order_no', $order_no)->get();
        $reception = MaterialReception::where('reception_no', $reception_no)->first();

        return view('backend.pages.material_reception.edit', array_merge(
            compact('materials', 'order_no', 'reception_no', 'datas', 'data', 'material_big_stores', 'material_medium_stores', 'suppliers', 'goods_stores', 'reception'),
            $this->receptionOrderLineFlags($datas),
            ['storePreviewByLine' => $this->previewLineStores($datas)]
        ));
        
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $reception_no)
    {
        if (is_null($this->user) || !$this->user->can('material_reception.edit')) {
            abort(403, 'Sorry !! You are Unauthorized to edit any reception !');
        }

        $reception = MaterialReception::where('reception_no',$reception_no)->first();

        $orderDetails = $this->buildSubmittedLinesFromRequest($request);
        $lineFlags = $this->receptionOrderLineFlags($orderDetails);

        $rules = array(
                'date'  => 'required',
                'quantity_ordered.*'  => 'required',
                'purchase_price.*'  => 'required',
                'quantity_received.*'  => 'required',
                'order_no'  => 'required',
                'invoice_no'  => 'required',
                'receptionist'  => 'required',
                'vat_supplier_payer'  => 'required',
                'type_reception'  => 'required',
                'invoice_currency'  => 'required',
                'supplier_id'  => 'required',
                'description'  => 'required|max:490'
            );

            $error = Validator::make($request->all(), $rules);
            $error->after(function ($validator) use ($request) {
                $material_id = $request->material_id ?? [];
                $item_id = $request->item_id ?? [];
                $quantity_received = $request->quantity_received ?? [];
                $n = max(count($material_id), count($item_id), count($quantity_received));
                for ($i = 0; $i < $n; $i++) {
                    $mid = trim((string)($material_id[$i] ?? ''));
                    $iid = trim((string)($item_id[$i] ?? ''));
                    if ($mid === '' && $iid === '') {
                        $validator->errors()->add('material_id.'.$i, __('messages.each_line_must_contain_item_or_material'));
                    }
                }
            });
            $this->validateReceptionDestinations($error, $request, $lineFlags);

            if($error->fails()){
                return redirect()->back()->withErrors($error)->withInput();
            }

            try {DB::beginTransaction();

            $material_id = $request->material_id ?? [];
            $item_id = $request->item_id ?? [];
            $date = $request->date;
            $vat_supplier_payer = $request->vat_supplier_payer;
            $origin_store_id = $request->origin_store_id;
            $invoice_currency = $request->invoice_currency;
            $handingover = $request->handingover;
            $receptionist = $request->receptionist;
            $vat_rate = $request->vat_rate;
            $type_reception = $request->type_reception;
            $order_no = $request->order_no;
            $invoice_no = $request->invoice_no;
            $description =$request->description; 
            $quantity_ordered = $request->quantity_ordered;
            $purchase_price = $request->purchase_price;
            $selling_price = $request->selling_price;
            $quantity_received = $request->quantity_received;
            $supplier_id = $request->supplier_id;
            

            if (!empty($vat_rate)) {
                $vat_rate = $vat_rate;
            }else{
                $vat_rate = 0;
            }

            $created_by = $this->user->name;

            $reception_signature = MaterialReception::where('reception_no',$reception_no)->value('reception_signature');

            $headerStore = null;
            $rowCountUpd = max(count($material_id), count($item_id), count($quantity_received));
            for( $count = 0; $count < $rowCountUpd; $count++ ){
                if($vat_supplier_payer == 1){
                    $price_nvat = ($purchase_price[$count]*$quantity_received[$count]);
                    $vat = ($price_nvat* $vat_rate)/100;
                    $price_wvat = $price_nvat + $vat;
                    $total_amount_purchase = $price_wvat; 

                }else{
                    $price_nvat = ($purchase_price[$count]*$quantity_received[$count]);
                    $vat = 0;
                    $price_wvat = $price_nvat + $vat;
                    $total_amount_purchase = $price_wvat;
                }

                $total_amount_ordered = $quantity_ordered[$count] * $purchase_price[$count];
                $total_amount_received = $total_amount_purchase;

                $data = array(
                    'material_id' => $material_id[$count] ?? null,
                    'item_id' => $item_id[$count] ?? null,
                    'date' => $date,
                    'quantity_ordered' => $quantity_ordered[$count],
                    'quantity_received' => $quantity_received[$count],
                    'quantity_remaining' => $quantity_received[$count] - $quantity_ordered[$count],
                    'vat_rate' => $vat_rate,
                    'purchase_price' => $purchase_price[$count],
                    'type_reception' => $type_reception,
                    'total_amount_ordered' => $total_amount_ordered,
                    'total_amount_received' => $total_amount_received,
                    'total_amount_purchase' => $total_amount_purchase,
                    'order_no' => $order_no,
                    'invoice_no' => $invoice_no,
                    'invoice_currency' => $invoice_currency,
                    'vat' => $vat,
                    'price_nvat' => $price_nvat,
                    'price_wvat' => $price_wvat,
                    'supplier_id' => $supplier_id,
                    'vat_supplier_payer' => $vat_supplier_payer,
                    'receptionist' => $receptionist,
                    'handingover' => $handingover,
                    'reception_no' => $reception_no,
                    'reception_signature' => $reception_signature,
                    'created_by' => $created_by,
                    'description' => $description,
                    'status' => 1,
                    'created_at' => \Carbon\Carbon::now()
                );

                $data = $this->applyRowDestination(
                    $data,
                    $this->parseLineId($material_id[$count] ?? null),
                    $this->parseLineId($item_id[$count] ?? null)
                );
                if ($headerStore === null) {
                    $headerStore = $data;
                }
                $insert_data[] = $data;
            }

            MaterialReceptionDetail::where('reception_no',$reception_no)->delete();

            MaterialReceptionDetail::insert($insert_data);


            //edit reception
            $reception->date = $date;
            $reception->order_no = $order_no;
            $reception->vat_supplier_payer = $vat_supplier_payer;
            $reception->invoice_no = $invoice_no;
            $reception->invoice_currency = $invoice_currency;
            $reception->receptionist = $receptionist;
            $reception->type_store = $headerStore['type_store'] ?? null;
            $reception->code_store = $headerStore['code_store'] ?? null;
            $reception->vat_rate = $vat_rate;
            $reception->type_reception = $type_reception;
            $reception->handingover = $handingover;
            $reception->supplier_id = $supplier_id;
            $reception->destination_store_id = $headerStore['destination_store_id'] ?? null;
            $reception->dest_extra_store_id = $headerStore['dest_extra_store_id'] ?? null;
            $reception->created_by = $created_by;
            $reception->status = 1;
            $reception->description = $description;
            $reception->created_at = \Carbon\Carbon::now();
            $reception->save();

            DB::commit();
            session()->flash('success', 'reception has been created !!');
            return redirect()->route('admin.material-receptions.index');
        } catch (\RuntimeException $e) {
            DB::rollback();
            return redirect()->back()->withErrors(['stock' => $e->getMessage()])->withInput();
        } catch (\Exception $e) {
            // An error occured; cancel the transaction...

            DB::rollback();

            // and throw the error again.

            throw $e;
        }

        
    }

    public function fiche_reception($reception_no)
    {
        if (is_null($this->user) || !$this->user->can('material_reception.create')) {
            abort(403, 'Sorry !! You are Unauthorized to print handover document!');
        }
        $setting = DB::table('settings')->orderBy('created_at','desc')->first();
        $code = MaterialReception::where('reception_no', $reception_no)->value('reception_no');
        $datas = MaterialReceptionDetail::with(['material', 'item'])->where('reception_no', $reception_no)->get();
        $receptionniste = MaterialReception::where('reception_no', $reception_no)->value('receptionist');
        $description = MaterialReception::where('reception_no', $reception_no)->value('description');
        $data = MaterialReception::with('supplier')->where('reception_no', $reception_no)->first();
        $supplier = $data;
        $invoice_no = MaterialReception::where('reception_no', $reception_no)->value('invoice_no');
        $invoice_currency = MaterialReception::where('reception_no', $reception_no)->value('invoice_currency');
        $reception_signature = MaterialReception::where('reception_no', $reception_no)->value('reception_signature');
        $date = MaterialReception::where('reception_no', $reception_no)->value('date');
        $totalValue = DB::table('material_reception_details')
            ->where('reception_no', '=', $reception_no)
            ->sum('total_amount_purchase');
        $price_nvat = DB::table('material_reception_details')
            ->where('reception_no', '=', $reception_no)
            ->sum('price_nvat');
        $vat = DB::table('material_reception_details')
            ->where('reception_no', '=', $reception_no)
            ->sum('vat');
        $amountInWords = \App\Helpers\NumberToWordsFrench::convert((int) round($totalValue)) . ' francs burundais';
        $pdf = PDF::setOptions(['defaultFont' => 'dejavu sans', 'isRemoteEnabled' => true])
            ->loadView('backend.pages.document.material_reception', compact('datas','code','totalValue','receptionniste','description','supplier','data','invoice_no','setting','date','reception_signature','invoice_currency','price_nvat','vat','amountInWords'), [], 'UTF-8');

        Storage::put('public/pdf/fiche_reception_materiel/'.$reception_no.'.pdf', $pdf->output());

        // download pdf file
        return $pdf->download('FICHE DE RECEPTION DES MATERIELS '.$reception_no.'.pdf');
        
    }

    public function print($reception_no) { return $this->fiche_reception($reception_no); }

    public function validateReception($reception_no)
    {
       if (is_null($this->user) || !$this->user->can('material_reception.validate')) {
            abort(403, 'Sorry !! You are Unauthorized to validate any reception !');
        }

        try {DB::beginTransaction();

            MaterialReception::where('reception_no', '=', $reception_no)
                ->update(['status' => 2,'validated_by' => $this->user->name]);
            MaterialReceptionDetail::where('reception_no', '=', $reception_no)
                ->update(['status' => 2,'validated_by' => $this->user->name]);

        DB::commit();
            session()->flash('success', 'reception has been validated !!');
            return back();
        } catch (\Exception $e) {
            // An error occured; cancel the transaction...

            DB::rollback();

            // and throw the error again.

            throw $e;
        }

    }

    public function reject($reception_no)
    {
       if (is_null($this->user) || !$this->user->can('material_reception.reject')) {
            abort(403, 'Sorry !! You are Unauthorized to reject any reception !');
        }

        try {DB::beginTransaction();

        MaterialReception::where('reception_no', '=', $reception_no)
                ->update(['status' => -1,'rejected_by' => $this->user->name]);
        MaterialReceptionDetail::where('reception_no', '=', $reception_no)
                ->update(['status' => -1,'rejected_by' => $this->user->name]);

        DB::commit();
            session()->flash('success', 'Reception has been rejected !!');
            return back();
        } catch (\Exception $e) {
            // An error occured; cancel the transaction...

            DB::rollback();

            // and throw the error again.

            throw $e;
        }
    }

    public function reset($reception_no)
    {
       if (is_null($this->user) || !$this->user->can('material_reception.reset')) {
            abort(403, 'Sorry !! You are Unauthorized to reset any reception !');
        }

        try {DB::beginTransaction();

        MaterialReception::where('reception_no', '=', $reception_no)
                ->update(['status' => 1,'reseted_by' => $this->user->name]);
        MaterialReceptionDetail::where('reception_no', '=', $reception_no)
                ->update(['status' => 1,'reseted_by' => $this->user->name]);

        DB::commit();
            session()->flash('success', 'Reception has been reseted !!');
            return back();
        } catch (\Exception $e) {
            // An error occured; cancel the transaction...

            DB::rollback();

            // and throw the error again.

            throw $e;
        }

    }

    public function confirm($reception_no)
    {
       if (is_null($this->user) || !$this->user->can('material_reception.confirm')) {
            abort(403, 'Sorry !! You are Unauthorized to confirm any reception !');
        }

        try {DB::beginTransaction();

        MaterialReception::where('reception_no', '=', $reception_no)
                ->update(['status' => 3,'confirmed_by' => $this->user->name]);
            MaterialReceptionDetail::where('reception_no', '=', $reception_no)
                ->update(['status' => 3,'confirmed_by' => $this->user->name]);

        DB::commit();
            session()->flash('success', 'Reception has been confirmed !!');
            return back();
        } catch (\Exception $e) {
            // An error occured; cancel the transaction...

            DB::rollback();

            // and throw the error again.

            throw $e;
        }

    }

    public function approuve($reception_no)
    {
       if (is_null($this->user) || !$this->user->can('material_reception.approuve')) {
            abort(403, 'Sorry !! You are Unauthorized to confirm any reception !');
        }


        try {DB::beginTransaction();

        $reception = MaterialReception::where('reception_no', $reception_no)->lockForUpdate()->first();
        if (!$reception) {
            DB::rollBack();
            session()->flash('error', 'Réception introuvable.');
            return back();
        }
        if ($this->abortIfStockDocumentAlreadyApproved($reception)) {
            return back();
        }
        $accountSetting = AccountSetting::where('active', 1)->first();
        if (!$accountSetting) {
            DB::rollback();
            session()->flash('error', __('messages.approval_accounts_required'));
            return back();
        }

        $datas = MaterialReceptionDetail::where('reception_no', $reception_no)->get();

        // Initialize arrays
        $reportBigStoreData = [];
        $reportExtraBigStoreData = [];
        $reportFinishedGoodData = [];
        $reportGoodsStoreData = [];
        $flag_md = 0;
        $flag_big = 0;
        $total_material_price_nvat = 0;
        $total_item_fg_price_nvat = 0;
        $total_item_goods_price_nvat = 0;

        foreach($datas as $data){

                if (!empty($data->item_id)) {
                    // Article (produit fini ou marchandise) -> stock FG ou Goods + rapport
                    $item = Item::find($data->item_id);
                    if (!$item) {
                        DB::rollback();
                        session()->flash('error', 'Article introuvable (item_id).');
                        return back();
                    }
                    $qtyReceived = (float) $data->quantity_received;
                    $purchasePrice = (float) ($data->purchase_price ?? 0);

                    if ($item->store_type == Item::STORE_TYPE_FINISHED_GOODS) {
                        $store = FinishedGoodsStore::first();
                        if (!$store) {
                            DB::rollback();
                            session()->flash('error', 'Aucun magasin produits finis configuré.');
                            return back();
                        }
                        $code_store = $store->code;
                        $storeDetail = FinishedGoodsStoreDetail::where('code_store', $code_store)->where('item_id', $data->item_id)->first();
                        if (!$storeDetail) {
                            DB::rollback();
                            session()->flash('error', 'Article non enregistré dans le stock produits finis: ' . ($item->name ?? ''));
                            return back();
                        }
                        $quantityStockInitial = (float) ($storeDetail->quantity ?? 0);
                        $valueStockInitial = $quantityStockInitial * (float) ($storeDetail->cump ?? 0);
                        $valeurAcquisition = $qtyReceived * $purchasePrice;
                        $valeurTotalUnite = $qtyReceived + $quantityStockInitial;
                        $newCump = $valeurTotalUnite > 0 ? ($valueStockInitial + $valeurAcquisition) / $valeurTotalUnite : $purchasePrice;
                        $quantityTotal = $quantityStockInitial + $qtyReceived;
                        $value_stockin = $qtyReceived * $newCump;
                        $value_stock_final = $quantityTotal * $newCump;

                        $storeDetail->quantity = $quantityTotal;
                        $storeDetail->cump = $newCump;
                        $storeDetail->updated_by = $this->user->name;
                        $storeDetail->save();

                        $reportFinishedGoodData[] = [
                            'code_store' => $code_store,
                            'item_id' => $data->item_id,
                            'quantity_stock_initial' => $quantityStockInitial,
                            'value_stock_initial' => $valueStockInitial,
                            'quantity_stockin' => $qtyReceived,
                            'value_stockin' => $value_stockin,
                            'stock_total' => $quantityTotal,
                            'quantity_stockout' => 0,
                            'value_stockout' => 0,
                            'quantity_stock_final' => $quantityTotal,
                            'value_stock_final' => $value_stock_final,
                            'cump' => $newCump,
                            'date' => $data->date,
                            'created_by' => $this->user->name,
                            'type_transaction' => 'ACHAT',
                            'document_no' => $data->reception_no,
                            'description' => $data->description ?? '',
                            'created_at' => \Carbon\Carbon::now(),
                        ];
                        $total_item_fg_price_nvat += (float) ($data->price_nvat ?? 0);
                    } else {
                        $code_store = trim((string) ($reception->code_store ?? $data->code_store ?? ''));
                        $store = $code_store !== ''
                            ? GoodsStore::where('code', $code_store)->first()
                            : GoodsStore::first();
                        if (!$store) {
                            DB::rollback();
                            session()->flash('error', 'Aucun magasin marchandises configuré.');
                            return back();
                        }
                        $code_store = $store->code;
                        if ($item->code_store && $item->code_store !== $code_store) {
                            DB::rollback();
                            session()->flash('error', __('messages.reception_item_wrong_goods_store', ['name' => $item->name ?? '']));
                            return back();
                        }
                        $storeDetail = GoodsStoreDetail::where('code_store', $code_store)->where('item_id', $data->item_id)->first();
                        if (!$storeDetail) {
                            DB::rollback();
                            session()->flash('error', __('messages.reception_item_not_in_goods_store', ['name' => $item->name ?? '']));
                            return back();
                        }
                        $quantityStockInitial = (float) ($storeDetail->quantity ?? 0);
                        $valueStockInitial = $quantityStockInitial * (float) ($storeDetail->cump ?? 0);
                        $valeurAcquisition = $qtyReceived * $purchasePrice;
                        $valeurTotalUnite = $qtyReceived + $quantityStockInitial;
                        $newCump = $valeurTotalUnite > 0 ? ($valueStockInitial + $valeurAcquisition) / $valeurTotalUnite : $purchasePrice;
                        $quantityTotal = $quantityStockInitial + $qtyReceived;
                        $value_stockin = $qtyReceived * $newCump;
                        $value_stock_final = $quantityTotal * $newCump;

                        $storeDetail->quantity = $quantityTotal;
                        $storeDetail->cump = $newCump;
                        $storeDetail->updated_by = $this->user->name;
                        $storeDetail->save();

                        $reportGoodsStoreData[] = [
                            'code_store' => $code_store,
                            'item_id' => $data->item_id,
                            'quantity_stock_initial' => $quantityStockInitial,
                            'value_stock_initial' => $valueStockInitial,
                            'quantity_stockin' => $qtyReceived,
                            'value_stockin' => $value_stockin,
                            'stock_total' => $quantityTotal,
                            'quantity_stockout' => 0,
                            'value_stockout' => 0,
                            'quantity_stock_final' => $quantityTotal,
                            'value_stock_final' => $value_stock_final,
                            'cump' => $newCump,
                            'date' => $data->date,
                            'created_by' => $this->user->name,
                            'type_transaction' => 'ACHAT',
                            'document_no' => $data->reception_no,
                            'description' => $data->description ?? '',
                            'created_at' => \Carbon\Carbon::now(),
                        ];
                        $total_item_goods_price_nvat += (float) ($data->price_nvat ?? 0);
                    }

                    if ($data->purchase_no) {
                        MaterialPurchase::where('purchase_no', '=', $data->purchase_no)->update(['status' => 6]);
                        MaterialPurchaseDetail::where('purchase_no', '=', $data->purchase_no)->update(['status' => 6]);
                    } else {
                        MaterialSupplierOrder::where('order_no', '=', $data->order_no)->update(['status' => 5]);
                        MaterialSupplierOrderDetail::where('order_no', '=', $data->order_no)->update(['status' => 5]);
                    }
                } elseif (!empty($data->destination_store_id)) {
                    $total_material_price_nvat += (float) ($data->price_nvat ?? 0);
                    $code_store_destination = MaterialBigStore::where('id',$data->destination_store_id)->value('code');

                $valeurStockInitialDestination = MaterialBigStoreDetail::where('code',$code_store_destination)->where('material_id','!=', '')->where('material_id', $data->material_id)->value('total_cump_value') ?? 0;
                $quantityStockInitialDestination = MaterialBigStoreDetail::where('code',$code_store_destination)->where('material_id','!=', '')->where('material_id', $data->material_id)->value('quantity') ?? 0;
                $quantityTotalBigStore = $quantityStockInitialDestination + $data->quantity_received;

                $valeurAcquisition = $data->quantity_received * $data->purchase_price;

                $valeurTotalUnite = $data->quantity_received + $quantityStockInitialDestination;
                if ($valeurTotalUnite == 0) {
                    $cump = $data->purchase_price;
                }else{
                    $cump = ($valeurStockInitialDestination + $valeurAcquisition) / $valeurTotalUnite;
                }
                
                // Calculate value_stockin using cump
                $value_stockin = $data->quantity_received * $cump;
                
                // Calculate value_stock_final using cump
                $quantity_stock_final = $quantityStockInitialDestination + $data->quantity_received;
                $value_stock_final = $quantity_stock_final * $cump;

                $reportBigStore = array(
                    'material_id' => $data->material_id,
                    'quantity_stock_initial' => $quantityStockInitialDestination,
                    'value_stock_initial' => $valeurStockInitialDestination,
                    'code_store' => $code_store_destination,
                    'date' => $data->date,
                    'quantity_stockin' => $data->quantity_received,
                    'value_stockin' => $value_stockin,
                    'quantity_stock_final' => $quantity_stock_final,
                    'value_stock_final' => $value_stock_final,
                    'type_transaction' => 'ACHAT',
                    'cump' => $cump,
                    'document_no' => $data->reception_no,
                    'created_by' => $this->user->name,
                    'description' => $data->description,
                    'created_at' => \Carbon\Carbon::now()
                );
                $reportBigStoreData[] = $reportBigStore;

                    $bigStore = array(
                        'material_id' => $data->material_id,
                        'quantity' => $quantityTotalBigStore,
                        'purchase_price' => $cump,
                        'total_purchase_value' => $quantityTotalBigStore * $cump,
                        'cump' => $cump,
                        'total_cump_value' => $quantityTotalBigStore * $cump,
                        'created_by' => $this->user->name,
                        'verified' => false
                    );

                    $materialData = array(
                        'id' => $data->material_id,
                        'quantity' => $quantityTotalBigStore,
                        'cump' => $cump,
                        'purchase_price' => $cump
                    );

                    Material::where('id',$data->material_id)
                        ->update($materialData);

                        $material = MaterialBigStoreDetail::where('code',$code_store_destination)->where("material_id",$data->material_id)->value('material_id');

                        if (!empty($material)) {
                            MaterialBigStoreDetail::where('code',$code_store_destination)->where('material_id',$data->material_id)
                        ->update($bigStore);
                        $flag_md = 1;
                        }else{
                            $flag_md = 0;
                            DB::rollback();
                            session()->flash('error', 'this item is not saved in the stock');
                            return back();
                        }


                        if ($data->purchase_no) {
                            MaterialPurchase::where('purchase_no', '=', $data->purchase_no)
                            ->update(['status' => 6]);
                            MaterialPurchaseDetail::where('purchase_no', '=', $data->purchase_no)
                            ->update(['status' => 6]);

                        }else{
                            MaterialSupplierOrder::where('order_no', '=', $data->order_no)
                            ->update(['status' => 5]);
                            MaterialSupplierOrderDetail::where('order_no', '=', $data->order_no)
                            ->update(['status' => 5]);
                        }


                } elseif(!empty($data->dest_extra_store_id)){
                    $total_material_price_nvat += (float) ($data->price_nvat ?? 0);
                    $code_store_destination = MaterialExtraBigStore::where('id',$data->dest_extra_store_id)->value('code');

                $valeurStockInitialDestination = MaterialExtraBigStoreDetail::where('code',$code_store_destination)->where('material_id','!=', '')->where('material_id', $data->material_id)->value('total_cump_value') ?? 0;
                $quantityStockInitialDestination = MaterialExtraBigStoreDetail::where('code',$code_store_destination)->where('material_id','!=', '')->where('material_id', $data->material_id)->value('quantity') ?? 0;
                $quantityTotalExtraBigStore = $quantityStockInitialDestination + $data->quantity_received;

                $valeurAcquisition = $data->quantity_received * $data->purchase_price;

                $valeurTotalUnite = $data->quantity_received + $quantityStockInitialDestination;
                if ($valeurTotalUnite == 0) {
                    $cump = $data->purchase_price;
                }else{
                    $cump = ($valeurStockInitialDestination + $valeurAcquisition) / $valeurTotalUnite;
                }
                
                // Calculate value_stockin using cump
                $value_stockin = $data->quantity_received * $cump;
                
                // Calculate value_stock_final using cump
                $quantity_stock_final = $quantityStockInitialDestination + $data->quantity_received;
                $value_stock_final = $quantity_stock_final * $cump;

                $reportExtraBigStore = array(
                    'material_id' => $data->material_id,
                    'quantity_stock_initial' => $quantityStockInitialDestination,
                    'value_stock_initial' => $valeurStockInitialDestination,
                    'code_store' => $code_store_destination,
                    'date' => $data->date,
                    'quantity_stockin' => $data->quantity_received,
                    'value_stockin' => $value_stockin,
                    'quantity_stock_final' => $quantity_stock_final,
                    'value_stock_final' => $value_stock_final,
                    'type_transaction' => 'ACHAT',
                    'cump' => $cump,
                    'document_no' => $data->reception_no,
                    'created_by' => $this->user->name,
                    'description' => $data->description,
                    'created_at' => \Carbon\Carbon::now()
                );
                $reportExtraBigStoreData[] = $reportExtraBigStore;

                    $extraBigStore = array(
                        'material_id' => $data->material_id,
                        'quantity' => $quantityTotalExtraBigStore,
                        'purchase_price' => $cump,
                        'total_purchase_value' => $quantityTotalExtraBigStore * $cump,
                        'cump' => $cump,
                        'total_cump_value' => $quantityTotalExtraBigStore * $cump,
                        'created_by' => $this->user->name,
                        'verified' => false
                    );

                    $materialData = array(
                        'id' => $data->material_id,
                        'quantity' => $quantityTotalExtraBigStore,
                        'cump' => $cump,
                        'purchase_price' => $cump
                    );

                    Material::where('id',$data->material_id)
                        ->update($materialData);

                        $material = MaterialExtraBigStoreDetail::where('code',$code_store_destination)->where("material_id",$data->material_id)->value('material_id');

                        if (!empty($material)) {
                            MaterialExtraBigStoreDetail::where('code',$code_store_destination)->where('material_id',$data->material_id)
                        ->update($extraBigStore);
                        $flag_big = 1;
                        }else{
                            $flag_big = 0;
                            DB::rollback();
                            session()->flash('error', 'this item is not saved in the extra big stock');
                            return back();
                        }


                        if ($data->purchase_no) {
                            MaterialPurchase::where('purchase_no', '=', $data->purchase_no)
                            ->update(['status' => 6]);
                            MaterialPurchaseDetail::where('purchase_no', '=', $data->purchase_no)
                            ->update(['status' => 6]);

                        }else{
                            MaterialSupplierOrder::where('order_no', '=', $data->order_no)
                            ->update(['status' => 5]);
                            MaterialSupplierOrderDetail::where('order_no', '=', $data->order_no)
                            ->update(['status' => 5]);
                        }

                }else{
                    session()->flash('error', 'oops!Something wrong');
                    return back();
                }
  
        }


        if ($flag_md != 0 && !empty($reportBigStoreData)) {
            MaterialBigReport::insert($reportBigStoreData);
        }
        
        if ($flag_big != 0 && !empty($reportExtraBigStoreData)) {
            MaterialExtraBigReport::insert($reportExtraBigStoreData);
        }

        if (!empty($reportFinishedGoodData)) {
            FinishedGoodReport::insert($reportFinishedGoodData);
        }
        if (!empty($reportGoodsStoreData)) {
            GoodsStoreReport::insert($reportGoodsStoreData);
        }

        //start accounting features
        
            $totalValue = DB::table('material_reception_details')
            ->where('reception_no', '=', $reception_no)
            ->sum('total_amount_purchase');

            $item_price_nvat = DB::table('material_reception_details')
            ->where('reception_no', '=', $reception_no)
            ->sum('price_nvat');

            $vat = DB::table('material_reception_details')
            ->where('reception_no', '=', $reception_no)
            ->sum('vat');

            $order_no = $reception->order_no;

            $dataCashOutFlow = CashOutFlowRequest::where('purchase_no',$order_no)->first();

            // Initialize arrays
            $insert_journal_debit_data = [];
            $insert_journal_debit_data_acc2 = [];
            $insert_journal_credit_dataAccEmp = [];
            $insert_journal_credit_data = [];

            if (!empty($dataCashOutFlow->id)) {
                //start debit operations (stock: material and/or items FG/Goods)
                if ($total_material_price_nvat > 0) {
                    $id_acc_stock = null;
                    if ((string) ($reception->type_store ?? '') === '0') {
                        $id_acc_stock = MaterialExtraBigStore::where('id', $reception->dest_extra_store_id)->value('default_account_id');
                    } else {
                        $id_acc_stock = MaterialBigStore::where('id', $reception->destination_store_id)->value('default_account_id');
                    }
                    if (empty($id_acc_stock)) {
                        DB::rollback();
                        session()->flash('error', __('messages.approval_accounts_required'));
                        return redirect()->back();
                    }
                    $stockLedgerState = $this->updateLedgerTotals($id_acc_stock, 'debit', $total_material_price_nvat);
                    $insert_journal_debit_data[] = [
                        'date' => $reception->date,
                        'document_no' => $reception->reception_no,
                        'description' => $reception->description,
                        'account_id' => $id_acc_stock,
                        'libelle' => $reception->description,
                        'debit' => $total_material_price_nvat,
                        'balance' => $stockLedgerState['balance'],
                        'created_at' => \Carbon\Carbon::now()
                    ];
                }
                if ($total_item_fg_price_nvat > 0) {
                    $fgStore = FinishedGoodsStore::first();
                    if (!$fgStore || empty($fgStore->default_account_id)) {
                        DB::rollback();
                        session()->flash('error', __('messages.approval_accounts_required'));
                        return redirect()->back();
                    }
                    $fgLedgerState = $this->updateLedgerTotals($fgStore->default_account_id, 'debit', $total_item_fg_price_nvat);
                    $insert_journal_debit_data[] = [
                        'date' => $reception->date,
                        'document_no' => $reception->reception_no,
                        'description' => $reception->description,
                        'account_id' => $fgStore->default_account_id,
                        'libelle' => $reception->description,
                        'debit' => $total_item_fg_price_nvat,
                        'balance' => $fgLedgerState['balance'],
                        'created_at' => \Carbon\Carbon::now()
                    ];
                }
                if ($total_item_goods_price_nvat > 0) {
                    $goodsStore = $this->resolveGoodsStoreFromReception($reception);
                    if (!$goodsStore || empty($goodsStore->default_account_id)) {
                        DB::rollback();
                        session()->flash('error', __('messages.approval_accounts_required'));
                        return redirect()->back();
                    }
                    $goodsLedgerState = $this->updateLedgerTotals($goodsStore->default_account_id, 'debit', $total_item_goods_price_nvat);
                    $insert_journal_debit_data[] = [
                        'date' => $reception->date,
                        'document_no' => $reception->reception_no,
                        'description' => $reception->description,
                        'account_id' => $goodsStore->default_account_id,
                        'libelle' => $reception->description,
                        'debit' => $total_item_goods_price_nvat,
                        'balance' => $goodsLedgerState['balance'],
                        'created_at' => \Carbon\Carbon::now()
                    ];
                }
            AccountJournalEntryDetail::insert($insert_journal_debit_data);

            //vat
            $vat_deduc_account_id = AccountSetting::where('active',1)->value('vat_deduc_account_id');

            if (empty($vat_deduc_account_id)) {
                DB::rollback();
                session()->flash('error', 'Account ID for VAT deduction not configured. Please configure vat_deduc_account_id in Account Settings.');
                return redirect()->back();
            }

            $vatLedgerState = $this->updateLedgerTotals($vat_deduc_account_id, 'debit', $vat);
            
            $journalEntryDebitdataAcc2 = array(
                    'date' => $reception->date,
                    'document_no' => $reception->reception_no,
                    'description' => "TVA DEDUCTIBLE",
                    'account_id' => $vat_deduc_account_id,
                    'libelle' => "TVA DEDUCTIBLE",
                    'debit' => $vat,
                    'balance' => $vatLedgerState['balance'],
                    'created_at' => \Carbon\Carbon::now()
                );
                $insert_journal_debit_data_acc2[] = $journalEntryDebitdataAcc2;

            AccountJournalEntryDetail::insert($insert_journal_debit_data_acc2);
            
            //end debit operation

            //start credit acc_employee operations
            if (empty($dataCashOutFlow->employe->cash_out_account_id)) {
                DB::rollback();
                session()->flash('error', 'Cash Out Account ID not found for the employee ('.$dataCashOutFlow->employe->firstname.') Please configure cash_out_account_id for the employee.');
                return redirect()->back();
            }

            $employeeLedgerState = $this->updateLedgerTotals($dataCashOutFlow->employe->cash_out_account_id, 'credit', $totalValue);

            $journalEntryCreditDataAccEmp = array(
                    'date' => $reception->date,
                    'document_no' => $reception->reception_no,
                    'description' => $reception->description,
                    'account_id' => $dataCashOutFlow->employe->cash_out_account_id,
                    'libelle' => $reception->description,
                    'credit' => $totalValue,
                    'balance' => $employeeLedgerState['balance'],
                    'created_at' => \Carbon\Carbon::now()
                );
                $insert_journal_credit_dataAccEmp[] = $journalEntryCreditDataAccEmp;

            AccountJournalEntryDetail::insert($insert_journal_credit_dataAccEmp);


            MaterialReception::where('reception_no', '=', $reception_no)
                ->update(['flag2' => 1]);
            MaterialReceptionDetail::where('reception_no', '=', $reception_no)
                ->update(['flag2' => 1]);

            //end credit operations
            }else{
                //start debit operations (stock: material and/or items FG/Goods)
                if ($total_material_price_nvat > 0) {
                    $id_acc_stock = null;
                    if ((string) ($reception->type_store ?? '') === '0') {
                        $id_acc_stock = MaterialExtraBigStore::where('id', $reception->dest_extra_store_id)->value('default_account_id');
                    } else {
                        $id_acc_stock = MaterialBigStore::where('id', $reception->destination_store_id)->value('default_account_id');
                    }
                    if (empty($id_acc_stock)) {
                        DB::rollback();
                        session()->flash('error', __('messages.approval_accounts_required'));
                        return redirect()->back();
                    }
                    $stockLedgerState = $this->updateLedgerTotals($id_acc_stock, 'debit', $total_material_price_nvat);
                    $insert_journal_debit_data[] = [
                        'date' => $reception->date,
                        'document_no' => $reception->reception_no,
                        'description' => $reception->description,
                        'account_id' => $id_acc_stock,
                        'libelle' => $reception->description,
                        'debit' => $total_material_price_nvat,
                        'balance' => $stockLedgerState['balance'],
                        'created_at' => \Carbon\Carbon::now()
                    ];
                }
                if ($total_item_fg_price_nvat > 0) {
                    $fgStore = FinishedGoodsStore::first();
                    if (!$fgStore || empty($fgStore->default_account_id)) {
                        DB::rollback();
                        session()->flash('error', __('messages.approval_accounts_required'));
                        return redirect()->back();
                    }
                    $fgLedgerState = $this->updateLedgerTotals($fgStore->default_account_id, 'debit', $total_item_fg_price_nvat);
                    $insert_journal_debit_data[] = [
                        'date' => $reception->date,
                        'document_no' => $reception->reception_no,
                        'description' => $reception->description,
                        'account_id' => $fgStore->default_account_id,
                        'libelle' => $reception->description,
                        'debit' => $total_item_fg_price_nvat,
                        'balance' => $fgLedgerState['balance'],
                        'created_at' => \Carbon\Carbon::now()
                    ];
                }
                if ($total_item_goods_price_nvat > 0) {
                    $goodsStore = $this->resolveGoodsStoreFromReception($reception);
                    if (!$goodsStore || empty($goodsStore->default_account_id)) {
                        DB::rollback();
                        session()->flash('error', __('messages.approval_accounts_required'));
                        return redirect()->back();
                    }
                    $goodsLedgerState = $this->updateLedgerTotals($goodsStore->default_account_id, 'debit', $total_item_goods_price_nvat);
                    $insert_journal_debit_data[] = [
                        'date' => $reception->date,
                        'document_no' => $reception->reception_no,
                        'description' => $reception->description,
                        'account_id' => $goodsStore->default_account_id,
                        'libelle' => $reception->description,
                        'debit' => $total_item_goods_price_nvat,
                        'balance' => $goodsLedgerState['balance'],
                        'created_at' => \Carbon\Carbon::now()
                    ];
                }
            AccountJournalEntryDetail::insert($insert_journal_debit_data);
            //vat
            $vat_deduc_account_id = AccountSetting::where('active',1)->value('vat_deduc_account_id');

            if (empty($vat_deduc_account_id)) {
                DB::rollback();
                session()->flash('error', 'Account ID for VAT deduction not configured. Please configure vat_deduc_account_id in Account Settings.');
                return redirect()->back();
            }

            $vatLedgerState = $this->updateLedgerTotals($vat_deduc_account_id, 'debit', $vat);
            
            $journalEntryDebitdataAcc2 = array(
                    'date' => $reception->date,
                    'document_no' => $reception->reception_no,
                    'description' => "TVA DEDUCTIBLE",
                    'account_id' => $vat_deduc_account_id,
                    'libelle' => "TVA DEDUCTIBLE",
                    'debit' => $vat,
                    'balance' => $vatLedgerState['balance'],
                    'created_at' => \Carbon\Carbon::now()
                );
                $insert_journal_debit_data_acc2[] = $journalEntryDebitdataAcc2;

            AccountJournalEntryDetail::insert($insert_journal_debit_data_acc2);
            
            //end debit operation

            //start credit operations
            if (empty($reception->supplier->account_id)) {
                DB::rollback();
                session()->flash('error', 'Account ID not found for the supplier. Please configure account_id for the supplier.');
                return redirect()->back();
            }

            $supplierLedgerState = $this->updateLedgerTotals($reception->supplier->account_id, 'credit', $totalValue);

            $journalEntryCreditdata = array(
                    'date' => $reception->date,
                    'document_no' => $reception->reception_no,
                    'description' => $reception->description,
                    'account_id' => $reception->supplier->account_id,
                    'libelle' => $reception->description,
                    'credit' => $totalValue,
                    'balance' => $supplierLedgerState['balance'],
                    'created_at' => \Carbon\Carbon::now()
                );
                $insert_journal_credit_data[] = $journalEntryCreditdata;

            AccountJournalEntryDetail::insert($insert_journal_credit_data);

            //end credit operations
            }
        //end accounting features

            $purchase_no = MaterialSupplierOrder::where('order_no',$reception->order_no)->value('purchase_no');
        
            if ($reception->type_reception == 0) {
                MaterialSupplierOrder::where('order_no', '=', $reception->order_no)
                ->update(['status' => -5]);
                MaterialSupplierOrderDetail::where('order_no', '=', $reception->order_no)
                ->update(['status' => -5]);
            }else{
                MaterialSupplierOrder::where('order_no', '=', $reception->order_no)
                ->update(['status' => 5]);
                MaterialSupplierOrderDetail::where('order_no', '=', $reception->order_no)
                ->update(['status' => 5]);
            }

            MaterialPurchase::where('purchase_no', '=', $purchase_no)
            ->update(['status' => 6]);
            MaterialPurchaseDetail::where('purchase_no', '=', $purchase_no)
             ->update(['status' => 6]);


            MaterialReception::where('reception_no', '=', $reception_no)
                            ->update(['status' => 4,'approuved_by' => $this->user->name]);
            MaterialReceptionDetail::where('reception_no', '=', $reception_no)
                            ->update(['status' => 4,'approuved_by' => $this->user->name]);
        DB::commit();
            $successMessage = 'Reception has been done successfully!';
            if ($flag_md != 0 && !empty($reportBigStoreData)) {
                $code_store = !empty($reportBigStoreData[0]['code_store']) ? $reportBigStoreData[0]['code_store'] : '';
                $successMessage .= ' Stock: ' . $code_store;
            }
            if ($flag_big != 0 && !empty($reportExtraBigStoreData)) {
                $code_store = !empty($reportExtraBigStoreData[0]['code_store']) ? $reportExtraBigStoreData[0]['code_store'] : '';
                $successMessage .= ' Extra Big Stock: ' . $code_store;
            }
            session()->flash('success', $successMessage);
            return back();
        } catch (\Exception $e) {
            // An error occured; cancel the transaction...

            DB::rollback();

            // and throw the error again.

            throw $e;
        }

    }

    public function exportToExcel(Request $request)
    {
        if (is_null($this->user) || !$this->user->can('material_reception.view')) {
            abort(403, 'Sorry !! You are Unauthorized to export receptions !');
        }
        $dateDebut = $request->input('date_debut') ?? $request->input('start_date');
        $dateFin = $request->input('date_fin') ?? $request->input('end_date');
        if (!$dateDebut || !$dateFin) {
            return redirect()->route('admin.material-receptions.index')
                ->with('error', __('messages.export_select_date_range'));
        }
        $filename = 'receptions_materiels_' . \Carbon\Carbon::parse($dateDebut)->format('Y-m-d') . '_' . \Carbon\Carbon::parse($dateFin)->format('Y-m-d') . '.xlsx';
        return Excel::download(new MaterialReceptionExport(), $filename);
    }

    protected function buildSubmittedLinesFromRequest(Request $request): \Illuminate\Support\Collection
    {
        $materialIds = $request->material_id ?? [];
        $itemIds = $request->item_id ?? [];
        $rowCount = max(count($materialIds), count($itemIds));
        $lines = collect();

        for ($i = 0; $i < $rowCount; $i++) {
            $materialId = trim((string) ($materialIds[$i] ?? ''));
            $itemId = trim((string) ($itemIds[$i] ?? ''));
            if ($materialId === '' && $itemId === '') {
                continue;
            }

            $line = new \stdClass();
            $line->material_id = $materialId !== '' ? (int) $materialId : null;
            $line->item_id = $itemId !== '' ? (int) $itemId : null;
            if ($line->material_id) {
                $line->material = Material::find($line->material_id);
            }
            if ($line->item_id) {
                $line->item = Item::find($line->item_id);
            }
            $lines->push($line);
        }

        return $lines;
    }

    protected function receptionOrderLineFlags($datas): array
    {
        $collection = collect($datas);
        $itemsById = $collection
            ->pluck('item')
            ->filter()
            ->keyBy('id');

        $goodsItemIds = [];
        $finishedGoodsItemIds = [];
        $goodsStoreCodes = [];

        foreach ($collection as $line) {
            if (empty($line->item_id)) {
                continue;
            }

            $item = $line->item ?? $itemsById->get($line->item_id) ?? Item::find($line->item_id);
            if (!$item) {
                continue;
            }

            if ($item->isGoods()) {
                $goodsItemIds[] = (int) $line->item_id;
                $code = trim((string) ($item->code_store ?? ''));
                if ($code !== '') {
                    $goodsStoreCodes[] = $code;
                }
            } elseif ($item->isFinishedGoods()) {
                $finishedGoodsItemIds[] = (int) $line->item_id;
            }
        }

        $hasMaterials = $collection->contains(fn ($line) => !empty($line->material_id));
        $hasGoodsItems = count($goodsItemIds) > 0;
        $hasFinishedGoodsItems = count($finishedGoodsItemIds) > 0;
        $hasItems = $hasGoodsItems || $hasFinishedGoodsItems;

        $uniqueGoodsCodes = array_values(array_unique($goodsStoreCodes));
        $suggestedCodeStore = count($uniqueGoodsCodes) === 1 ? $uniqueGoodsCodes[0] : '';

        $compatibleGoodsStores = $this->compatibleGoodsStoresForItemIds($goodsItemIds);
        if ($suggestedCodeStore !== '' && !$compatibleGoodsStores->contains('code', $suggestedCodeStore)) {
            $preferredStore = GoodsStore::where('code', $suggestedCodeStore)->first();
            if ($preferredStore) {
                $compatibleGoodsStores = $compatibleGoodsStores
                    ->push($preferredStore)
                    ->unique('id')
                    ->values();
            }
        }

        $materialSuggestions = $this->suggestMaterialStores($collection);

        return array_merge([
            'hasMaterials' => $hasMaterials,
            'hasItems' => $hasItems,
            'hasGoodsItems' => $hasGoodsItems,
            'hasFinishedGoodsItems' => $hasFinishedGoodsItems,
            'suggestedCodeStore' => $suggestedCodeStore,
            'compatible_goods_stores' => $compatibleGoodsStores,
            'goodsStoreConflict' => count($uniqueGoodsCodes) > 1,
        ], $materialSuggestions);
    }

    protected function compatibleGoodsStoresForItemIds(array $itemIds): \Illuminate\Support\Collection
    {
        $itemIds = array_values(array_unique(array_filter($itemIds)));
        $stores = GoodsStore::orderBy('name')->get();

        if (empty($itemIds)) {
            return $stores;
        }

        return $stores->filter(function ($store) use ($itemIds) {
            foreach ($itemIds as $itemId) {
                if (!GoodsStoreDetail::where('code_store', $store->code)->where('item_id', $itemId)->exists()) {
                    return false;
                }
            }
            return true;
        })->values();
    }

    protected function suggestMaterialStores(\Illuminate\Support\Collection $lines): array
    {
        $typeStores = [];
        $destExtraIds = [];
        $destMediumIds = [];

        foreach ($lines as $line) {
            if (empty($line->material_id)) {
                continue;
            }

            $material = $line->material ?? Material::find($line->material_id);
            if (!$material) {
                continue;
            }

            $storeType = (string) ($material->store_type ?? '');
            $code = trim((string) ($material->code_store ?? ''));
            if ($code === '') {
                continue;
            }

            if ($storeType === '0') {
                $store = MaterialExtraBigStore::where('code', $code)->first();
                if ($store) {
                    $typeStores[] = '0';
                    $destExtraIds[] = (int) $store->id;
                }
            } elseif ($storeType === '1') {
                $store = MaterialBigStore::where('code', $code)->first();
                if ($store) {
                    $typeStores[] = '1';
                    $destMediumIds[] = (int) $store->id;
                }
            }
        }

        $uniqueTypes = array_values(array_unique($typeStores));
        $uniqueExtra = array_values(array_unique($destExtraIds));
        $uniqueMedium = array_values(array_unique($destMediumIds));

        return [
            'suggestedTypeStore' => count($uniqueTypes) === 1 ? $uniqueTypes[0] : '',
            'suggestedDestExtraStoreId' => count($uniqueExtra) === 1 ? $uniqueExtra[0] : '',
            'suggestedDestinationStoreId' => count($uniqueMedium) === 1 ? $uniqueMedium[0] : '',
            'materialStoreConflict' => count($uniqueTypes) > 1,
        ];
    }

    protected function parseLineId($value): ?int
    {
        $value = trim((string) ($value ?? ''));
        return $value !== '' ? (int) $value : null;
    }

    protected function previewLineStores($datas): array
    {
        $previews = [];
        foreach (collect($datas)->values() as $index => $line) {
            $resolved = $this->resolveLineStore(
                !empty($line->material_id) ? (int) $line->material_id : null,
                !empty($line->item_id) ? (int) $line->item_id : null
            );
            $previews[$index] = $resolved['error'] ?? ($resolved['label'] ?? __('messages.reception_destination_unspecified'));
        }

        return $previews;
    }

    protected function resolveLineStore(?int $materialId, ?int $itemId): array
    {
        $result = [
            'type_store' => null,
            'destination_store_id' => null,
            'dest_extra_store_id' => null,
            'code_store' => null,
            'label' => null,
            'error' => null,
        ];

        if ($materialId) {
            $material = Material::find($materialId);
            if (!$material) {
                $result['error'] = __('messages.material_not_found');
                return $result;
            }

            $storeType = (string) ($material->store_type ?? '');
            $code = trim((string) ($material->code_store ?? ''));

            if ($code === '') {
                $bigDetail = MaterialBigStoreDetail::where('material_id', $materialId)->first();
                if ($bigDetail) {
                    $code = trim((string) ($bigDetail->code ?? ''));
                    $storeType = '1';
                } else {
                    $extraDetail = MaterialExtraBigStoreDetail::where('material_id', $materialId)->first();
                    if ($extraDetail) {
                        $code = trim((string) ($extraDetail->code ?? ''));
                        $storeType = '0';
                    }
                }
            }

            if ($code === '') {
                $result['error'] = __('messages.reception_material_no_store_config', ['name' => $material->name ?? '']);
                return $result;
            }

            if ($storeType === '0') {
                $store = MaterialExtraBigStore::where('code', $code)->first();
                if (!$store) {
                    $result['error'] = __('messages.reception_material_not_in_store', ['name' => $material->name ?? '']);
                    return $result;
                }
                $result['type_store'] = '0';
                $result['dest_extra_store_id'] = $store->id;
                $result['label'] = trim($store->code . ' - ' . $store->name);
                return $result;
            }

            if ($storeType === '1') {
                $store = MaterialBigStore::where('code', $code)->first();
                if (!$store) {
                    $result['error'] = __('messages.reception_material_not_in_store', ['name' => $material->name ?? '']);
                    return $result;
                }
                $result['type_store'] = '1';
                $result['destination_store_id'] = $store->id;
                $result['label'] = trim($store->code . ' - ' . $store->name);
                return $result;
            }

            $result['error'] = __('messages.reception_material_no_store_config', ['name' => $material->name ?? '']);
            return $result;
        }

        if ($itemId) {
            $item = Item::find($itemId);
            if (!$item) {
                $result['error'] = __('messages.item_not_found');
                return $result;
            }

            if ($item->isFinishedGoods()) {
                $result['label'] = __('messages.reception_finished_goods_store_label');
                return $result;
            }

            if ($item->isGoods()) {
                $code = trim((string) ($item->code_store ?? ''));
                if ($code === '') {
                    $detail = GoodsStoreDetail::where('item_id', $itemId)->first();
                    $code = $detail ? trim((string) ($detail->code_store ?? '')) : '';
                }
                if ($code === '') {
                    $result['error'] = __('messages.reception_item_not_in_goods_store', ['name' => $item->name ?? '']);
                    return $result;
                }
                $store = GoodsStore::where('code', $code)->first();
                if (!$store) {
                    $result['error'] = __('messages.reception_item_not_in_goods_store', ['name' => $item->name ?? '']);
                    return $result;
                }
                $result['type_store'] = MaterialReception::TYPE_STORE_GOODS;
                $result['code_store'] = $code;
                $result['label'] = $store ? trim($store->code . ' - ' . $store->name) : $code;
                return $result;
            }
        }

        return $result;
    }

    protected function applyRowDestination(array $data, ?int $materialId, ?int $itemId): array
    {
        $resolved = $this->resolveLineStore($materialId, $itemId);
        if ($resolved['error']) {
            throw new \RuntimeException($resolved['error']);
        }

        $data['type_store'] = $resolved['type_store'];
        $data['destination_store_id'] = $resolved['destination_store_id'];
        $data['dest_extra_store_id'] = $resolved['dest_extra_store_id'];
        $data['code_store'] = $resolved['code_store'];

        return $data;
    }

    protected function validateReceptionDestinations($validator, Request $request, array $lineFlags): void
    {
        $validator->after(function ($v) use ($request) {
            $lines = $this->buildSubmittedLinesFromRequest($request);
            foreach ($lines as $line) {
                $resolved = $this->resolveLineStore(
                    !empty($line->material_id) ? (int) $line->material_id : null,
                    !empty($line->item_id) ? (int) $line->item_id : null
                );
                if ($resolved['error']) {
                    $v->errors()->add('stock', $resolved['error']);
                    return;
                }
            }
        });
    }

    protected function resolveReceptionTypeStore(Request $request, array $lineFlags): ?string
    {
        if ($lineFlags['hasMaterials']) {
            return (string) ($request->type_store ?? '');
        }

        if ($lineFlags['hasGoodsItems']) {
            return MaterialReception::TYPE_STORE_GOODS;
        }

        return $request->type_store;
    }

    protected function resolveGoodsStoreFromReception(MaterialReception $reception): ?GoodsStore
    {
        $code = trim((string) ($reception->code_store ?? ''));
        if ($code !== '') {
            return GoodsStore::where('code', $code)->first();
        }

        return GoodsStore::first();
    }

    protected function updateLedgerTotals(?int $accountId, string $type, float $amount): array
    {
        if (is_null($accountId)) {
            throw new \InvalidArgumentException('Account ID cannot be null');
        }

        $ledger = AccountLedger::firstOrNew(['account_id' => $accountId]);

        $currentDebit = (float) ($ledger->debit ?? 0);
        $currentCredit = (float) ($ledger->credit ?? 0);

        if ($type === 'debit') {
            $currentDebit += $amount;
        } else {
            $currentCredit += $amount;
        }

        $ledger->debit = $currentDebit;
        $ledger->credit = $currentCredit;
        $ledger->balance = $currentDebit - $currentCredit;
        $ledger->save();

        return [
            'debit' => $currentDebit,
            'credit' => $currentCredit,
            'balance' => $ledger->balance,
        ];
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $reception_no
     * @return \Illuminate\Http\Response
     */
    public function destroy($reception_no)
    {
        if (is_null($this->user) || !$this->user->can('material_reception.delete')) {
            abort(403, 'Sorry !! You are Unauthorized to delete any reception !');
        }
        /*
        try {DB::beginTransaction();

        $reception = MaterialReception::where('reception_no',$reception_no)->first();
        if (!is_null($reception)) {
            $reception->delete();
            MaterialReceptionDetail::where('reception_no',$reception_no)->delete();
        }

        DB::commit();
            session()->flash('success', 'Reception has been deleted !!');
            return back();
        } catch (\Exception $e) {
            // An error occured; cancel the transaction...

            DB::rollback();

            // and throw the error again.

            throw $e;
        }
        */
    }
}
