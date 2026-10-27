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
use App\Models\RawMaterial;
use App\Models\RawMaterialTransfer;
use App\Models\RawMaterialTransferDetail;
use App\Models\RawMaterialBigStoreDetail;
use App\Models\RawMaterialSmallStoreDetail;
use App\Models\RawMaterialBigStore;
use App\Models\RawMaterialExtraBigStore;
use App\Models\RawMaterialExtraBigStoreDetail;
use App\Models\RawMaterialExtraBigReport;
use App\Models\RawMaterialSmallStore;
use App\Models\RawMaterialRequisitionDetail;
use App\Models\RawMaterialRequisition;
use App\Models\RawMaterialBigReport;
use App\Models\RawMaterialSmallReport;
use App\Models\Accounting\AccountJournalEntryDetail;
use App\Models\Accounting\AccountLedger;
use Carbon\Carbon;
use PDF;
use Validator;
use Excel;
use Mail;

class RawMaterialTransferController extends Controller
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
        if (is_null($this->user) || !$this->user->can('raw_material_transfer.view')) {
            abort(403, 'Sorry !! You are Unauthorized to view any transfer !');
        }
        $query = RawMaterialTransfer::orderBy('id','desc');
        if ($request->filled('date_debut') && $request->filled('date_fin')) {
            $dateDebut = \Carbon\Carbon::parse($request->date_debut)->startOfDay();
            $dateFin = \Carbon\Carbon::parse($request->date_fin)->endOfDay();
            $query->whereBetween('date', [$dateDebut, $dateFin]);
        } else {
            $query->take(100);
        }
        $transfers = $query->get();
        $requisitions = RawMaterialRequisition::whereIn('requisition_no', $transfers->pluck('requisition_no'))->get()->keyBy('requisition_no');
        return view('backend.pages.raw_material_transfer.index', compact('transfers', 'requisitions'));
    }

    public function exportToExcel(Request $request)
    {
        if (is_null($this->user) || !$this->user->can('raw_material_transfer.view')) {
            abort(403, 'Sorry !! You are Unauthorized to export transfers !');
        }
        $dateDebut = $request->input('date_debut') ?? $request->input('start_date');
        $dateFin = $request->input('date_fin') ?? $request->input('end_date');
        if (!$dateDebut || !$dateFin) {
            return redirect()->route('admin.raw-material-transfers.index')
                ->with('error', __('messages.export_select_date_range'));
        }
        $filename = 'transferts_matieres_premieres_' . \Carbon\Carbon::parse($dateDebut)->format('Y-m-d') . '_' . \Carbon\Carbon::parse($dateFin)->format('Y-m-d') . '.xlsx';
        return \Excel::download(new \App\Exports\RawMaterialTransferExport(), $filename);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    public function create($requisition_no)
    {
        if (is_null($this->user) || !$this->user->can('raw_material_transfer.create')) {
            abort(403, 'Sorry !! You are Unauthorized to create any transfer !');
        }

        $requisition = RawMaterialRequisition::where('requisition_no', $requisition_no)->first();
        if (!$requisition || (int) $requisition->status !== 4) {
            session()->flash('error', 'Requisition must be approved (status 4) before creating a transfer !!');
            return redirect()->route('admin.raw-material-transfers.index');
        }

        if (RawMaterialTransfer::where('requisition_no', $requisition_no)->exists()) {
            session()->flash('error', 'A transfer already exists for this requisition !!');
            return redirect()->route('admin.raw-material-transfers.index');
        }

        $raw_materials  = RawMaterial::orderBy('name','asc')->get();
        $origin_stores = RawMaterialBigStore::all();
        $destination_stores = RawMaterialSmallStore::all();
        $datas = RawMaterialRequisitionDetail::with('rawMaterial.rawMaterialMeasurement')
            ->where('requisition_no', $requisition_no)->get();
        return view('backend.pages.raw_material_transfer.create', compact('raw_materials','requisition_no','datas','origin_stores','destination_stores'));
    }

    public function createFromBig($requisition_no)
    {
        if (is_null($this->user) || !$this->user->can('raw_material_transfer.create')) {
            abort(403, 'Sorry !! You are Unauthorized to create any transfer !');
        }

        $requisition = RawMaterialRequisition::where('requisition_no', $requisition_no)->first();
        if (!$requisition || (int) $requisition->status !== 4) {
            session()->flash('error', 'Requisition must be approved (status 4) before creating a transfer !!');
            return redirect()->route('admin.raw-material-transfers.index');
        }

        if (RawMaterialTransfer::where('requisition_no', $requisition_no)->exists()) {
            session()->flash('error', 'A transfer already exists for this requisition !!');
            return redirect()->route('admin.raw-material-transfers.index');
        }

        $raw_materials  = RawMaterial::orderBy('name','asc')->get();
        $origin_stores = RawMaterialExtraBigStore::all();
        $destination_stores = RawMaterialBigStore::all();
        $datas = RawMaterialRequisitionDetail::with('rawMaterial.rawMaterialMeasurement')
            ->where('requisition_no', $requisition_no)->get();
        return view('backend.pages.raw_material_transfer.create_from_big', compact('raw_materials','requisition_no','datas','origin_stores','destination_stores'));
    }

    public function portion($transfer_no)
    {
        if (is_null($this->user) || !$this->user->can('raw_material_transfer.portion')) {
            abort(403, 'Sorry !! You are Unauthorized to create any transfer !');
        }

        $raw_materials  = RawMaterial::where('store_type','!=',2)->orderBy('name','asc')->get();
        $origin_stores = RawMaterialBigStore::all();
        $destination_stores = RawMaterialSmallStore::all();
        $data = RawMaterialTransferDetail::with('rawMaterial.rawMaterialMeasurement')
            ->where('transfer_no', $transfer_no)->first();
        $datas = RawMaterialTransferDetail::with('rawMaterial.rawMaterialMeasurement')
            ->where('transfer_no', $transfer_no)->get();
        return view('backend.pages.raw_material_transfer.portion', compact('raw_materials','transfer_no','datas','origin_stores','destination_stores','data'));
    }

    public function validatePortion($transfer_no)
    {
        if (is_null($this->user) || !$this->user->can('raw_material_transfer.portion')) {
            abort(403, 'Sorry !! You are Unauthorized to portion any transfer !');
        }

        session()->flash('error', 'Fonctionnalité non disponible');
        return redirect()->back();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    public function store(Request $request)
    {

        if (is_null($this->user) || !$this->user->can('raw_material_transfer.create')) {
            abort(403, 'Sorry !! You are Unauthorized to create any transfer !');
        }

        $rules = array(
                'r_material_id.*'  => 'required',
                'date'  => 'required',
                'quantity_requisitioned.*'  => 'required',
                'price.*'  => 'required',
                'quantity_transfered.*'  => 'required',
                'origin_store_id'  => 'required',
                'requisition_no'  => 'required',
                'destination_store_id'  => 'required',
                'description'  => 'required'
            );

            $error = Validator::make($request->all(),$rules);

            if($error->fails()){
                return redirect()->back()->withErrors($error)->withInput();
            }

            try {DB::beginTransaction();

            $r_material_id = $request->r_material_id;
            $date = $request->date;
            $origin_store_id = $request->origin_store_id;
            $requisition_no = $request->requisition_no;
            $type_store = $request->type_store;
            $description =$request->description; 
            $destination_store_id = $request->destination_store_id;
            $quantity_requisitioned = $request->quantity_requisitioned;
            $price = $request->price;
            $quantity_transfered = $request->quantity_transfered;
            

            $latest = RawMaterialTransfer::latest()->first();
            if ($latest) {
               $transfer_no = 'BT' . (str_pad((int)$latest->id + 1, 4, '0', STR_PAD_LEFT)); 
            }else{
               $transfer_no = 'BT' . (str_pad((int)0 + 1, 4, '0', STR_PAD_LEFT));  
            }

            $created_by = $this->user->name;

            $transfer_signature = config('app.tin_number_company').Carbon::parse(Carbon::now())->format('YmdHis')."/".$transfer_no;


            for( $count = 0; $count < count($r_material_id); $count++ ){
                $total_value_requisitioned = $quantity_requisitioned[$count] * $price[$count];
                $total_value_transfered = $quantity_transfered[$count] * $price[$count];
                $data = array(
                    'r_material_id' => $r_material_id[$count],
                    'date' => $date,
                    'quantity_requisitioned' => $quantity_requisitioned[$count],
                    'quantity_transfered' => $quantity_transfered[$count],
                    'price' => $price[$count],
                    'total_value_requisitioned' => $total_value_requisitioned,
                    'total_value_transfered' => $total_value_transfered,
                    'requisition_no' => $requisition_no,
                    'type_store' => $type_store,
                    'origin_store_id' => $origin_store_id,
                    'destination_store_id' => $destination_store_id,
                    'transfer_no' => $transfer_no,
                    'transfer_signature' => $transfer_signature,
                    'created_by' => $created_by,
                    'description' => $description,
                    'status' => 1,
                    'created_at' => \Carbon\Carbon::now()

                );
                $insert_data[] = $data;

                
            }
            RawMaterialTransferDetail::insert($insert_data);


            //create transfer
            $transfer = new RawMaterialTransfer();
            $transfer->date = $date;
            $transfer->transfer_no = $transfer_no;
            $transfer->transfer_signature = $transfer_signature;
            $transfer->requisition_no = $requisition_no;
            $transfer->type_store = $type_store;
            $transfer->origin_store_id = $origin_store_id;
            $transfer->destination_store_id = $destination_store_id;
            $transfer->created_by = $created_by;
            $transfer->status = 1;
            $transfer->description = $description;
            $transfer->created_at = \Carbon\Carbon::now();
            $transfer->save();

            // Update requisition status to 5 (transferred in pending)
            RawMaterialRequisition::where('requisition_no', '=', $requisition_no)
                ->update(['status' => 5]);
            RawMaterialRequisitionDetail::where('requisition_no', '=', $requisition_no)
                ->update(['status' => 5]);

            DB::commit();
            session()->flash('success', 'transfer has been created !!');
            return redirect()->route('admin.raw-material-transfers.index');
        } catch (\Exception $e) {
            // An error occured; cancel the transaction...

            DB::rollback();

            // and throw the error again.

            throw $e;
        }
            
    }

    public function storeFromBig(Request $request)
    {

        if (is_null($this->user) || !$this->user->can('raw_material_transfer.create')) {
            abort(403, 'Sorry !! You are Unauthorized to create any transfer !');
        }

        $rules = array(
                'r_material_id.*'  => 'required',
                'date'  => 'required',
                'quantity_requisitioned.*'  => 'required',
                'price.*'  => 'required',
                'quantity_transfered.*'  => 'required',
                'origin_extra_store_id'  => 'required',
                'requisition_no'  => 'required',
                'dest_bg_store_id'  => 'required',
                'description'  => 'required'
            );

            $error = Validator::make($request->all(),$rules);

            if($error->fails()){
                return redirect()->back()->withErrors($error)->withInput();
            }

            try {DB::beginTransaction();

            $r_material_id = $request->r_material_id;
            $date = $request->date;
            $origin_extra_store_id = $request->origin_extra_store_id;
            $requisition_no = $request->requisition_no;
            $type_store = $request->type_store;
            $description =$request->description; 
            $dest_bg_store_id = $request->dest_bg_store_id;
            $quantity_requisitioned = $request->quantity_requisitioned;
            $price = $request->price;
            $quantity_transfered = $request->quantity_transfered;
            

            $latest = RawMaterialTransfer::latest()->first();
            if ($latest) {
               $transfer_no = 'BT' . (str_pad((int)$latest->id + 1, 4, '0', STR_PAD_LEFT)); 
            }else{
               $transfer_no = 'BT' . (str_pad((int)0 + 1, 4, '0', STR_PAD_LEFT));  
            }

            $created_by = $this->user->name;

            $transfer_signature = config('app.tin_number_company').Carbon::parse(Carbon::now())->format('YmdHis')."/".$transfer_no;


            for( $count = 0; $count < count($r_material_id); $count++ ){
                $total_value_requisitioned = $quantity_requisitioned[$count] * $price[$count];
                $total_value_transfered = $quantity_transfered[$count] * $price[$count];
                $data = array(
                    'r_material_id' => $r_material_id[$count],
                    'date' => $date,
                    'quantity_requisitioned' => $quantity_requisitioned[$count],
                    'quantity_transfered' => $quantity_transfered[$count],
                    'price' => $price[$count],
                    'total_value_requisitioned' => $total_value_requisitioned,
                    'total_value_transfered' => $total_value_transfered,
                    'requisition_no' => $requisition_no,
                    'type_store' => $type_store,
                    'origin_extra_store_id' => $origin_extra_store_id,
                    'dest_bg_store_id' => $dest_bg_store_id,
                    'transfer_no' => $transfer_no,
                    'transfer_signature' => $transfer_signature,
                    'created_by' => $created_by,
                    'description' => $description,
                    'status' => 1,
                    'created_at' => \Carbon\Carbon::now()

                );
                $insert_data[] = $data;

                
            }
            RawMaterialTransferDetail::insert($insert_data);


            //create transfer
            $transfer = new RawMaterialTransfer();
            $transfer->date = $date;
            $transfer->transfer_no = $transfer_no;
            $transfer->transfer_signature = $transfer_signature;
            $transfer->requisition_no = $requisition_no;
            $transfer->type_store = $type_store;
            $transfer->origin_extra_store_id = $origin_extra_store_id;
            $transfer->dest_bg_store_id = $dest_bg_store_id;
            $transfer->created_by = $created_by;
            $transfer->status = 1;
            $transfer->description = $description;
            $transfer->created_at = \Carbon\Carbon::now();
            $transfer->save();

            // Update requisition status to 5 (transferred in pending)
            RawMaterialRequisition::where('requisition_no', '=', $requisition_no)
                ->update(['status' => 5]);
            RawMaterialRequisitionDetail::where('requisition_no', '=', $requisition_no)
                ->update(['status' => 5]);

            DB::commit();
            session()->flash('success', 'transfer has been created !!');
            return redirect()->route('admin.raw-material-transfers.index');
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
    public function show($transfer_no)
    {
        //
        $transfer = RawMaterialTransfer::with(['originStore', 'originExtraStore', 'destinationStore', 'destinationBigStore'])
            ->where('transfer_no', $transfer_no)->first();
        
        if (is_null($transfer)) {
            session()->flash('error', 'Transfer not found !!');
            return redirect()->route('admin.raw-material-transfers.index');
        }
        
        $transferDetails = RawMaterialTransferDetail::with(['rawMaterial.rawMaterialMeasurement'])
            ->where('transfer_no', $transfer_no)->get();
        
        // Calculate totals
        $totalQuantityRequisitioned = $transferDetails->sum('quantity_requisitioned');
        $totalQuantityTransfered = $transferDetails->sum('quantity_transfered');
        $totalQuantityPortion = $transferDetails->sum('quantity_transfered');
        $totalValueTransfered = $transferDetails->sum('total_value_transfered');
        
        return view('backend.pages.raw_material_transfer.show', compact('transfer', 'transferDetails', 'totalQuantityRequisitioned', 'totalQuantityTransfered', 'totalQuantityPortion', 'totalValueTransfered'));
         
    }

    public function storePortion(Request $request, $transfer_no)
    {
        if (is_null($this->user) || !$this->user->can('raw_material_transfer.portion')) {
            abort(403, 'Sorry !! You are Unauthorized to portion any raw material !');
        }

        $rules = array(
                'r_material_id.*'  => 'required',
                'quantity_portion.*'  => 'required',
                'price.*'  => 'required',
                'quantity_transfered.*'  => 'required',
                'description_portion'  => 'required'
            );

            $error = Validator::make($request->all(),$rules);

            if($error->fails()){
                return response()->json([
                    'error' => $error->errors()->all(),
                ]);
            }

            try {DB::beginTransaction();

            $r_material_id = $request->r_material_id;
            $date_portion = \Carbon\Carbon::now();
            $origin_store_id = $request->origin_store_id;
            $description_portion =$request->description_portion; 
            $destination_store_id = $request->destination_store_id;
            //$unit_portion = $request->unit_portion;
            $quantity_portion = $request->quantity_portion;
            $price = $request->price;
            $quantity_transfered = $request->quantity_transfered ?? $request->quantity_portion;
            $portioned_by = $this->user->name;

            for( $count = 0; $count < count($r_material_id); $count++ ){
                $qty = $quantity_portion[$count] ?? $quantity_transfered[$count] ?? 0;
                $value_portion = $qty * $price[$count];
                $data = array(
                    'quantity_transfered' => $qty,
                    'total_value_transfered' => $value_portion,
                    'description' => $description_portion,
                    'updated_by' => $portioned_by,
                    'updated_at' => \Carbon\Carbon::now()
                );
                RawMaterialTransferDetail::where('transfer_no',$transfer_no)->where('r_material_id',$r_material_id[$count])->update($data);
            }

            RawMaterialTransfer::where('transfer_no', '=', $transfer_no)
                ->update(['updated_by' => $portioned_by]);
            RawMaterialTransferDetail::where('transfer_no', '=', $transfer_no)
                ->update(['updated_by' => $portioned_by]);

            DB::commit();
            session()->flash('success', 'Raw material has been portioned successfully !!');
            return redirect()->route('admin.raw-material-transfers.index');
        } catch (\Exception $e) {
            // An error occured; cancel the transaction...

            DB::rollback();

            // and throw the error again.

            throw $e;
        }
            
        
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($transfer_no)
    {
        if (is_null($this->user) || !$this->user->can('raw_material_transfer.edit')) {
            abort(403, 'Sorry !! You are Unauthorized to edit any transfer !');
        }

        $transfer = RawMaterialTransfer::where('transfer_no', $transfer_no)->first();
        if (is_null($transfer)) {
            session()->flash('error', 'Transfer not found !!');
            return redirect()->route('admin.raw-material-transfers.index');
        }

        // Check if requisition status is 6 (cannot edit when fully transferred)
        $requisition = RawMaterialRequisition::where('requisition_no', $transfer->requisition_no)->first();
        if ($requisition && $requisition->status == 6) {
            session()->flash('error', 'Cannot edit a transfer when requisition status is 6 (Transferred) !!');
            return redirect()->route('admin.raw-material-transfers.index');
        }

        // Get transfer details
        $transferDetails = RawMaterialTransferDetail::with(['rawMaterial.rawMaterialMeasurement'])
            ->where('transfer_no', $transfer_no)->get();

        // Get requisition details (for reference data)
        $requisitionDetails = RawMaterialRequisitionDetail::with(['rawMaterial.rawMaterialMeasurement'])
            ->where('requisition_no', $transfer->requisition_no)->get();

        // Get all raw materials for dropdown
        $raw_materials = RawMaterial::orderBy('name', 'asc')->get();

        // Get stores based on transfer type
        if ($transfer->type_store == 'md') {
            $origin_stores = RawMaterialBigStore::all();
            $destination_stores = RawMaterialSmallStore::all();
        } else {
            $origin_stores = RawMaterialExtraBigStore::all();
            $destination_stores = RawMaterialBigStore::all();
        }

        return view('backend.pages.raw_material_transfer.edit', compact(
            'transfer', 
            'transferDetails', 
            'requisitionDetails', 
            'raw_materials', 
            'origin_stores', 
            'destination_stores',
            'requisition'
        ));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $transfer_no)
    {
        if (is_null($this->user) || !$this->user->can('raw_material_transfer.edit')) {
            abort(403, 'Sorry !! You are Unauthorized to edit any transfer !');
        }

        $transfer = RawMaterialTransfer::where('transfer_no', $transfer_no)->first();
        if (is_null($transfer)) {
            session()->flash('error', 'Transfer not found !!');
            return redirect()->route('admin.raw-material-transfers.index');
        }

        // Check if requisition status is 6 (cannot edit when fully transferred)
        $requisition = RawMaterialRequisition::where('requisition_no', $transfer->requisition_no)->first();
        if ($requisition && $requisition->status == 6) {
            session()->flash('error', 'Cannot edit a transfer when requisition status is 6 (Transferred) !!');
            return redirect()->route('admin.raw-material-transfers.index');
        }

        $rules = array(
            'r_material_id.*'  => 'required',
            'date'  => 'required',
            'quantity_requisitioned.*'  => 'required',
            'price.*'  => 'required',
            'quantity_transfered.*'  => 'required',
            'description'  => 'required'
        );

        // Add store validation based on type
        if ($transfer->type_store == 'md') {
            $rules['origin_store_id'] = 'required';
            $rules['destination_store_id'] = 'required';
        } else {
            $rules['origin_extra_store_id'] = 'required';
            $rules['dest_bg_store_id'] = 'required';
        }

        $error = Validator::make($request->all(), $rules);

        if ($error->fails()) {
            return response()->json([
                'error' => $error->errors()->all(),
            ]);
        }

        try {
            DB::beginTransaction();

            $r_material_id = $request->r_material_id;
            $date = \Carbon\Carbon::parse($request->date);
            $description = $request->description;
            $quantity_requisitioned = $request->quantity_requisitioned;
            $price = $request->price;
            $quantity_transfered = $request->quantity_transfered;
            $updated_by = $this->user->name;

            // Update store IDs based on type
            if ($transfer->type_store == 'md') {
                $origin_store_id = $request->origin_store_id;
                $destination_store_id = $request->destination_store_id;
                $transfer->origin_store_id = $origin_store_id;
                $transfer->destination_store_id = $destination_store_id;
            } else {
                $origin_extra_store_id = $request->origin_extra_store_id;
                $dest_bg_store_id = $request->dest_bg_store_id;
                $transfer->origin_extra_store_id = $origin_extra_store_id;
                $transfer->dest_bg_store_id = $dest_bg_store_id;
            }

            // Delete old transfer details
            RawMaterialTransferDetail::where('transfer_no', $transfer_no)->delete();

            // Insert updated transfer details
            $insert_data = [];
            for ($count = 0; $count < count($r_material_id); $count++) {
                $total_value_requisitioned = $quantity_requisitioned[$count] * $price[$count];
                $total_value_transfered = $quantity_transfered[$count] * $price[$count];
                
                $data = array(
                    'r_material_id' => $r_material_id[$count],
                    'date' => $date,
                    'quantity_requisitioned' => $quantity_requisitioned[$count],
                    'quantity_transfered' => $quantity_transfered[$count],
                    'price' => $price[$count],
                    'total_value_requisitioned' => $total_value_requisitioned,
                    'total_value_transfered' => $total_value_transfered,
                    'requisition_no' => $transfer->requisition_no,
                    'type_store' => $transfer->type_store,
                    'transfer_no' => $transfer_no,
                    'transfer_signature' => $transfer->transfer_signature,
                    'created_by' => $transfer->created_by, // Keep original creator
                    'updated_by' => $updated_by,
                    'description' => $description,
                    'status' => $transfer->status, // Keep original status
                    'created_at' => $transfer->created_at, // Keep original created_at
                    'updated_at' => \Carbon\Carbon::now()
                );

                // Add store IDs based on type
                if ($transfer->type_store == 'md') {
                    $data['origin_store_id'] = $origin_store_id;
                    $data['destination_store_id'] = $destination_store_id;
                } else {
                    $data['origin_extra_store_id'] = $origin_extra_store_id;
                    $data['dest_bg_store_id'] = $dest_bg_store_id;
                }

                $insert_data[] = $data;
            }

            RawMaterialTransferDetail::insert($insert_data);

            // Update main transfer
            $transfer->date = $date;
            $transfer->description = $description;
            $transfer->updated_by = $updated_by;
            $transfer->save();

            DB::commit();
            session()->flash('success', 'Transfer has been updated !!');
            return redirect()->route('admin.raw-material-transfers.index');
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    public function bonTransfert($transfer_no)
    {
        if (is_null($this->user) || !$this->user->can('raw_material_transfer.create')) {
            abort(403, 'Sorry !! You are Unauthorized!');
        }
        $setting = DB::table('settings')->orderBy('created_at','desc')->first();
        $transfer_no = RawMaterialTransfer::where('transfer_no', $transfer_no)->value('transfer_no');
        $datas = RawMaterialTransferDetail::where('transfer_no', $transfer_no)->get();
        $data = RawMaterialTransfer::where('transfer_no', $transfer_no)->first();
        $description = RawMaterialTransfer::where('transfer_no', $transfer_no)->value('description');
        $requisition_no = RawMaterialTransfer::where('transfer_no', $transfer_no)->value('requisition_no');
        $transfer_signature = RawMaterialTransfer::where('transfer_no', $transfer_no)->value('transfer_signature');
        $date = RawMaterialTransfer::where('transfer_no', $transfer_no)->value('date');
        $totalValueTransfered = DB::table('raw_material_transfer_details')
            ->where('transfer_no', '=', $transfer_no)
            ->sum('total_value_transfered');
        $totalValueRequisitioned = DB::table('raw_material_transfer_details')
            ->where('transfer_no', '=', $transfer_no)
            ->sum('total_value_requisitioned');
        $pdf = PDF::setOptions(['defaultFont' => 'dejavu sans', 'isRemoteEnabled' => true])->loadView('backend.pages.document.raw_material_transfert', compact('datas','transfer_no','totalValueTransfered','totalValueRequisitioned','data','description','requisition_no','setting','date','transfer_signature'), [], 'UTF-8');

        Storage::put('public/pdf/raw_material_transfert/'.$transfer_no.'.pdf', $pdf->output());

        // download pdf file
        return $pdf->download('BON DE TRANSFERT '.$transfer_no.'.pdf');
        
    }

    public function print($transfer_no) { return $this->bonTransfert($transfer_no); }

    public function validateTransfer($transfer_no)
    {
       if (is_null($this->user) || !$this->user->can('raw_material_transfer.validate')) {
            abort(403, 'Sorry !! You are Unauthorized to validate any transfer !');
        }

        try {DB::beginTransaction();

            RawMaterialTransfer::where('transfer_no', '=', $transfer_no)
                ->update(['status' => 2,'validated_by' => $this->user->name]);
            RawMaterialTransferDetail::where('transfer_no', '=', $transfer_no)
                ->update(['status' => 2,'validated_by' => $this->user->name]);

            DB::commit();
            session()->flash('success', 'transfer has been validated !!');
            return back();
        } catch (\Exception $e) {
            // An error occured; cancel the transaction...

            DB::rollback();

            // and throw the error again.

            throw $e;
        }

    }

    public function reject($transfer_no)
    {
       if (is_null($this->user) || !$this->user->can('raw_material_transfer.reject')) {
            abort(403, 'Sorry !! You are Unauthorized to reject any transfer !');
        }

        try {DB::beginTransaction();

        RawMaterialTransfer::where('transfer_no', '=', $transfer_no)
                ->update(['status' => -1,'rejected_by' => $this->user->name]);
        RawMaterialTransferDetail::where('transfer_no', '=', $transfer_no)
                ->update(['status' => -1,'rejected_by' => $this->user->name]);

        DB::commit();
            session()->flash('success', 'Transfer has been rejected !!');
            return back();
        } catch (\Exception $e) {
            // An error occured; cancel the transaction...

            DB::rollback();

            // and throw the error again.

            throw $e;
        }

    }

    public function reset($transfer_no)
    {
       if (is_null($this->user) || !$this->user->can('raw_material_transfer.reset')) {
            abort(403, 'Sorry !! You are Unauthorized to reset any transfer !');
        }

        try {DB::beginTransaction();

        RawMaterialTransfer::where('transfer_no', '=', $transfer_no)
                ->update(['status' => 1,'reseted_by' => $this->user->name]);
        RawMaterialTransferDetail::where('transfer_no', '=', $transfer_no)
                ->update(['status' => 1,'reseted_by' => $this->user->name]);

        DB::commit();
            session()->flash('success', 'Transfer has been reseted !!');
            return back();
        } catch (\Exception $e) {
            // An error occured; cancel the transaction...

            DB::rollback();

            // and throw the error again.

            throw $e;
        }

    }

    public function confirm($transfer_no)
    {
       if (is_null($this->user) || !$this->user->can('raw_material_transfer.confirm')) {
            abort(403, 'Sorry !! You are Unauthorized to confirm any transfer !');
        }

        try {DB::beginTransaction();

        RawMaterialTransfer::where('transfer_no', '=', $transfer_no)
                ->update(['status' => 3,'confirmed_by' => $this->user->name]);
            RawMaterialTransferDetail::where('transfer_no', '=', $transfer_no)
                ->update(['status' => 3,'confirmed_by' => $this->user->name]);

        DB::commit();
            session()->flash('success', 'Transfer has been confirmed !!');
            return back();
        } catch (\Exception $e) {
            // An error occured; cancel the transaction...

            DB::rollback();

            // and throw the error again.

            throw $e;
        }

    }

    public function approuve($transfer_no)
    {
       if (is_null($this->user) || !$this->user->can('raw_material_transfer.approuve')) {
            abort(403, 'Sorry !! You are Unauthorized to approuve any transfer !');
        }

        try {DB::beginTransaction();

        $transfer = RawMaterialTransfer::where('transfer_no', $transfer_no)->lockForUpdate()->first();
        if (!$transfer) {
            DB::rollBack();
            session()->flash('error', 'Transfer not found !!');
            return back();
        }
        if ($this->abortIfStockDocumentAlreadyApproved($transfer)) {
            return back();
        }
        $transferMainDate = $transfer->date;

        $datas = RawMaterialTransferDetail::where('transfer_no', $transfer_no)->get();

        // Initialize arrays
        $reportBigStoreData = [];
        $reportBigStoreDataBg = [];
        $reportMediumStoreData = [];
        $reportSmallStoreData = [];
        $flag_food_bg = 0;
        $flag_food_md = 0;
        $flag_food_sm = 0;

        // Accounting variables for type_store == 'md' (big → small)
        $totalMdAccountingValue = 0;
        $mdOriginAccountId = null;
        $mdDestinationAccountId = null;
        $mdTransferDate = null;

        // Accounting variables for type_store == 'bg' (extra-big → big)
        $totalBgAccountingValue = 0;
        $bgOriginAccountId = null;
        $bgDestinationAccountId = null;
        $bgTransferDate = null;

        foreach ($datas as $data) {

            if ($data->type_store == 'md') {
                $code_store_origin = RawMaterialBigStore::where('id', $data->origin_store_id)->value('code');
                $code_store_destination = RawMaterialSmallStore::where('id', $data->destination_store_id)->value('code');

                $valeurStockInitialOrigine = RawMaterialBigStoreDetail::where('code', $code_store_origin)->where('r_material_id', '!=', '')->where('r_material_id', $data->r_material_id)->value('total_cump_value') ?? 0;
                $quantityStockInitialOrigine = RawMaterialBigStoreDetail::where('code', $code_store_origin)->where('r_material_id', '!=', '')->where('r_material_id', $data->r_material_id)->value('quantity') ?? 0;

                $valeurStockInitialDestination = RawMaterialSmallStoreDetail::where('code', $code_store_destination)->where('r_material_id', '!=', '')->where('r_material_id', $data->r_material_id)->value('total_cump_value') ?? 0;
                $quantityStockInitialDestination = RawMaterialSmallStoreDetail::where('code', $code_store_destination)->where('r_material_id', '!=', '')->where('r_material_id', $data->r_material_id)->value('quantity') ?? 0;

                // Get CUMP from origin store
                $cump = RawMaterialBigStoreDetail::where('code', $code_store_origin)->where('r_material_id', '!=', '')->where('r_material_id', $data->r_material_id)->value('cump') ?? 0;
                if ($cump == 0) {
                    $cump = RawMaterial::where('id', $data->r_material_id)->value('cump') ?? 0;
                    if ($cump == 0) {
                        $cump = RawMaterial::where('id', $data->r_material_id)->value('purchase_price') ?? 0;
                    }
                }

                if ($data->quantity_transfered > $quantityStockInitialOrigine) {
                    DB::rollBack();
                    session()->flash('error', 'Why do you want transfering quantity you do not have in your store?please rewrite a valid quantity!');
                    return redirect()->back();
                }

                $quantityRestantBigStore = $quantityStockInitialOrigine - $data->quantity_transfered;
                $value_stockout = $data->quantity_transfered * $cump;
                $quantity_stock_final_origin = $quantityStockInitialOrigine - $data->quantity_transfered;
                $value_stock_final_origin = $quantity_stock_final_origin * $cump;

                $value_stockin = $data->quantity_transfered * $cump;
                $quantity_stock_final_destination = $quantityStockInitialDestination + $data->quantity_transfered;
                $value_stock_final_destination = $quantity_stock_final_destination * $cump;

                // Accumulate accounting values for type_store == 'md'
                $originStore = RawMaterialBigStore::where('code', $code_store_origin)->first();
                $destinationStore = RawMaterialSmallStore::where('code', $code_store_destination)->first();

                if ($mdOriginAccountId === null && $originStore && $destinationStore) {
                    $mdOriginAccountId = $originStore->default_account_id;
                    $mdDestinationAccountId = $destinationStore->default_account_id;
                    $mdTransferDate = $transferMainDate;
                }
                $totalMdAccountingValue += $value_stockout;

                // Report for origin store (SORTIE TRANSFERT)
                $reportBigStore = array(
                    'r_material_id' => $data->r_material_id,
                    'quantity_stock_initial' => $quantityStockInitialOrigine,
                    'value_stock_initial' => $valeurStockInitialOrigine,
                    'code_store' => $code_store_origin,
                    'date' => $data->date,
                    'quantity_stockout' => $data->quantity_transfered,
                    'value_stockout' => $value_stockout,
                    'quantity_stock_final' => $quantity_stock_final_origin,
                    'value_stock_final' => $value_stock_final_origin,
                    'cump' => $cump,
                    'document_no' => $transfer_no,
                    'type_transaction' => "SORTIE TRANSFERT",
                    'description' => $data->description,
                    'created_by' => $this->user->name,
                    'created_at' => \Carbon\Carbon::now()
                );
                $reportBigStoreData[] = $reportBigStore;

                // Report for destination store (ENTREE TRANSFERT)
                $reportSmallStore = array(
                    'r_material_id' => $data->r_material_id,
                    'quantity_stock_initial' => $quantityStockInitialDestination,
                    'value_stock_initial' => $valeurStockInitialDestination,
                    'code_store' => $code_store_destination,
                    'date' => $data->date,
                    'quantity_stockin' => $data->quantity_transfered,
                    'value_stockin' => $value_stockin,
                    'quantity_stock_final' => $quantity_stock_final_destination,
                    'value_stock_final' => $value_stock_final_destination,
                    'cump' => $cump,
                    'document_no' => $transfer_no,
                    'type_transaction' => "ENTREE TRANSFERT",
                    'description' => $data->description,
                    'created_by' => $this->user->name,
                    'created_at' => \Carbon\Carbon::now()
                );
                $reportSmallStoreData[] = $reportSmallStore;

                $bigStore = array(
                    'r_material_id' => $data->r_material_id,
                    'quantity' => $quantityRestantBigStore,
                    'purchase_price' => $cump,
                    'cump' => $cump,
                    'total_purchase_value' => $quantityRestantBigStore * $cump,
                    'total_cump_value' => $quantityRestantBigStore * $cump,
                    'created_by' => $this->user->name,
                    'verified' => false,
                    'created_at' => \Carbon\Carbon::now()
                );

                $smStore = array(
                    'r_material_id' => $data->r_material_id,
                    'quantity' => $quantity_stock_final_destination,
                    'purchase_price' => $cump,
                    'cump' => $cump,
                    'total_cump_value' => $value_stock_final_destination,
                    'total_purchase_value' => $value_stock_final_destination,
                    'verified' => false,
                    'created_by' => $this->user->name,
                    'created_at' => \Carbon\Carbon::now()
                );

                $raw_material = RawMaterialSmallStoreDetail::where('code', $code_store_destination)->where('r_material_id', $data->r_material_id)->value('r_material_id');

                RawMaterialBigStoreDetail::where('code', $code_store_origin)->where('r_material_id', $data->r_material_id)
                    ->update($bigStore);

                $flag_food_md = 1;

                if (!empty($raw_material)) {
                    RawMaterialSmallStoreDetail::where('code', $code_store_destination)->where('r_material_id', $data->r_material_id)
                        ->update($smStore);
                } else {
                    $smallStoreData = array(
                        'r_material_id' => $data->r_material_id,
                        'quantity' => $data->quantity_transfered,
                        'cump' => $cump,
                        'code' => $code_store_destination,
                        'purchase_price' => $cump,
                        'total_cump_value' => $value_stockin,
                        'total_purchase_value' => $value_stockin,
                        'verified' => false,
                        'created_by' => $this->user->name,
                        'created_at' => \Carbon\Carbon::now()
                    );
                    RawMaterialSmallStoreDetail::insert($smallStoreData);
                }

                $flag_food_sm = 1;

            } elseif ($data->type_store == 'bg') {
                $code_store_origin = RawMaterialExtraBigStore::where('id', $data->origin_extra_store_id)->value('code');
                $code_store_destination = RawMaterialBigStore::where('id', $data->dest_bg_store_id)->value('code');

                $valeurStockInitialOrigine = RawMaterialExtraBigStoreDetail::where('code', $code_store_origin)->where('r_material_id', '!=', '')->where('r_material_id', $data->r_material_id)->value('total_cump_value') ?? 0;
                $quantityStockInitialOrigine = RawMaterialExtraBigStoreDetail::where('code', $code_store_origin)->where('r_material_id', '!=', '')->where('r_material_id', $data->r_material_id)->value('quantity') ?? 0;

                $valeurStockInitialDestination = RawMaterialBigStoreDetail::where('code', $code_store_destination)->where('r_material_id', '!=', '')->where('r_material_id', $data->r_material_id)->value('total_cump_value') ?? 0;
                $quantityStockInitialDestination = RawMaterialBigStoreDetail::where('code', $code_store_destination)->where('r_material_id', '!=', '')->where('r_material_id', $data->r_material_id)->value('quantity') ?? 0;

                // Get CUMP from origin store
                $cump = RawMaterialExtraBigStoreDetail::where('code', $code_store_origin)->where('r_material_id', '!=', '')->where('r_material_id', $data->r_material_id)->value('cump') ?? 0;
                if ($cump == 0) {
                    $cump = RawMaterial::where('id', $data->r_material_id)->value('cump') ?? 0;
                    if ($cump == 0) {
                        $cump = RawMaterial::where('id', $data->r_material_id)->value('purchase_price') ?? 0;
                    }
                }

                if ($data->quantity_transfered > $quantityStockInitialOrigine) {
                    DB::rollBack();
                    session()->flash('error', 'Why do you want transfering quantity you do not have in your store?please rewrite a valid quantity!');
                    return redirect()->back();
                }

                $quantityRestantBigStore = $quantityStockInitialOrigine - $data->quantity_transfered;
                $value_stockout = $data->quantity_transfered * $cump;
                $quantity_stock_final_origin = $quantityStockInitialOrigine - $data->quantity_transfered;
                $value_stock_final_origin = $quantity_stock_final_origin * $cump;

                $value_stockin = $data->quantity_transfered * $cump;
                $quantity_stock_final_destination = $quantityStockInitialDestination + $data->quantity_transfered;
                $value_stock_final_destination = $quantity_stock_final_destination * $cump;

                // Accumulate accounting values for type_store == 'bg'
                $originStore = RawMaterialExtraBigStore::where('code', $code_store_origin)->first();
                $destinationStore = RawMaterialBigStore::where('code', $code_store_destination)->first();

                if ($bgOriginAccountId === null && $originStore && $destinationStore) {
                    $bgOriginAccountId = $originStore->default_account_id;
                    $bgDestinationAccountId = $destinationStore->default_account_id;
                    $bgTransferDate = $transferMainDate;
                }
                $totalBgAccountingValue += $value_stockout;

                // Report for origin store (SORTIE TRANSFERT)
                $reportExtraBigStore = array(
                    'r_material_id' => $data->r_material_id,
                    'quantity_stock_initial' => $quantityStockInitialOrigine,
                    'value_stock_initial' => $valeurStockInitialOrigine,
                    'code_store' => $code_store_origin,
                    'date' => $data->date,
                    'quantity_stockout' => $data->quantity_transfered,
                    'value_stockout' => $value_stockout,
                    'quantity_stock_final' => $quantity_stock_final_origin,
                    'value_stock_final' => $value_stock_final_origin,
                    'cump' => $cump,
                    'document_no' => $transfer_no,
                    'type_transaction' => "SORTIE TRANSFERT",
                    'description' => $data->description,
                    'created_by' => $this->user->name,
                    'created_at' => \Carbon\Carbon::now()
                );
                $reportBigStoreDataBg[] = $reportExtraBigStore;

                // Report for destination store (ENTREE TRANSFERT)
                $reportMediumStore = array(
                    'r_material_id' => $data->r_material_id,
                    'quantity_stock_initial' => $quantityStockInitialDestination,
                    'value_stock_initial' => $valeurStockInitialDestination,
                    'code_store' => $code_store_destination,
                    'date' => $data->date,
                    'quantity_stockin' => $data->quantity_transfered,
                    'value_stockin' => $value_stockin,
                    'quantity_stock_final' => $quantity_stock_final_destination,
                    'value_stock_final' => $value_stock_final_destination,
                    'cump' => $cump,
                    'document_no' => $transfer_no,
                    'type_transaction' => "ENTREE TRANSFERT",
                    'description' => $data->description,
                    'created_by' => $this->user->name,
                    'created_at' => \Carbon\Carbon::now()
                );
                $reportMediumStoreData[] = $reportMediumStore;

                $extraBigStore = array(
                    'r_material_id' => $data->r_material_id,
                    'quantity' => $quantityRestantBigStore,
                    'purchase_price' => $cump,
                    'cump' => $cump,
                    'total_purchase_value' => $quantityRestantBigStore * $cump,
                    'total_cump_value' => $quantityRestantBigStore * $cump,
                    'created_by' => $this->user->name,
                    'verified' => false,
                    'created_at' => \Carbon\Carbon::now()
                );

                $mdStore = array(
                    'r_material_id' => $data->r_material_id,
                    'quantity' => $quantity_stock_final_destination,
                    'purchase_price' => $cump,
                    'cump' => $cump,
                    'total_cump_value' => $value_stock_final_destination,
                    'total_purchase_value' => $value_stock_final_destination,
                    'verified' => false,
                    'created_by' => $this->user->name,
                    'created_at' => \Carbon\Carbon::now()
                );

                $raw_material = RawMaterialBigStoreDetail::where('code', $code_store_destination)->where('r_material_id', $data->r_material_id)->value('r_material_id');

                RawMaterialExtraBigStoreDetail::where('code', $code_store_origin)->where('r_material_id', $data->r_material_id)
                    ->update($extraBigStore);

                $flag_food_bg = 1;

                if (!empty($raw_material)) {
                    RawMaterialBigStoreDetail::where('code', $code_store_destination)->where('r_material_id', $data->r_material_id)
                        ->update($mdStore);
                } else {
                    $mediumStoreData = array(
                        'r_material_id' => $data->r_material_id,
                        'quantity' => $data->quantity_transfered,
                        'cump' => $cump,
                        'code' => $code_store_destination,
                        'purchase_price' => $cump,
                        'total_cump_value' => $value_stockin,
                        'total_purchase_value' => $value_stockin,
                        'verified' => false,
                        'created_by' => $this->user->name,
                        'created_at' => \Carbon\Carbon::now()
                    );
                    RawMaterialBigStoreDetail::insert($mediumStoreData);
                }
            }

        }

        // Accounting entries for type_store == 'bg'
        if ($flag_food_bg != 0 && $totalBgAccountingValue > 0) {
            if (!$bgOriginAccountId || !$bgDestinationAccountId) {
                DB::rollBack();
                session()->flash('error', 'Extra-big or big store default account not configured. Please configure it first !!');
                return back();
            }

            $originLedger = AccountLedger::where('account_id', $bgOriginAccountId)->first();
            $currentDebit = (float) ($originLedger->debit ?? 0);
            $currentCredit = (float) ($originLedger->credit ?? 0);
            $currentBalance = $currentDebit - $currentCredit;

            if ($currentBalance <= 0) {
                DB::rollBack();
                session()->flash('error', 'Impossible d\'approuver le transfert. Le compte du grand stock a une balance de ' . number_format($currentBalance, 2) . '. Il n\'y a pas de valeur en stock ou la valeur a déjà été transférée.');
                return redirect()->back();
            }

            if ($currentBalance < $totalBgAccountingValue) {
                DB::rollBack();
                session()->flash('error', 'Balance insuffisante du compte du grand stock. Balance actuelle: ' . number_format($currentBalance, 2) . ', Valeur transférée demandée: ' . number_format($totalBgAccountingValue, 2) . '. Il n\'y a pas suffisamment de valeur en stock pour effectuer ce transfert.');
                return redirect()->back();
            }

            $originLedgerState = $this->updateLedgerTotals($bgOriginAccountId, 'credit', $totalBgAccountingValue);

            AccountJournalEntryDetail::insert([[
                'date' => $bgTransferDate,
                'document_no' => $transfer_no,
                'description' => 'Transfert GRAND STOCK VERS STOCK INTERMEDIAIRE',
                'account_id' => $bgOriginAccountId,
                'libelle' => 'Transfert GRAND STOCK VERS STOCK INTERMEDIAIRE',
                'credit' => $totalBgAccountingValue,
                'debit' => 0,
                'balance' => $originLedgerState['balance'],
                'active' => 1,
                'created_at' => Carbon::now()
            ]]);

            $destinationLedgerState = $this->updateLedgerTotals($bgDestinationAccountId, 'debit', $totalBgAccountingValue);

            AccountJournalEntryDetail::insert([[
                'date' => $bgTransferDate,
                'document_no' => $transfer_no,
                'description' => 'Transfert GRAND STOCK VERS STOCK INTERMEDIAIRE',
                'account_id' => $bgDestinationAccountId,
                'libelle' => 'Transfert GRAND STOCK VERS STOCK INTERMEDIAIRE',
                'debit' => $totalBgAccountingValue,
                'credit' => 0,
                'balance' => $destinationLedgerState['balance'],
                'active' => 1,
                'created_at' => Carbon::now()
            ]]);
        }

        // Accounting entries for type_store == 'md'
        if ($flag_food_md != 0 && $totalMdAccountingValue > 0) {
            if (!$mdOriginAccountId || !$mdDestinationAccountId) {
                DB::rollBack();
                session()->flash('error', 'Intermediate or small store default account not configured. Please configure it first !!');
                return back();
            }

            $originLedgerState = $this->updateLedgerTotals($mdOriginAccountId, 'credit', $totalMdAccountingValue);

            AccountJournalEntryDetail::insert([[
                'date' => $mdTransferDate ?? Carbon::now(),
                'document_no' => $transfer_no,
                'description' => 'Transfert STOCK INTERMEDIAIRE VERS PETIT STOCK',
                'account_id' => $mdOriginAccountId,
                'libelle' => 'Transfert STOCK INTERMEDIAIRE VERS PETIT STOCK',
                'credit' => $totalMdAccountingValue,
                'debit' => 0,
                'balance' => $originLedgerState['balance'],
                'active' => 1,
                'created_at' => Carbon::now()
            ]]);

            $destinationLedgerState = $this->updateLedgerTotals($mdDestinationAccountId, 'debit', $totalMdAccountingValue);

            AccountJournalEntryDetail::insert([[
                'date' => $mdTransferDate ?? Carbon::now(),
                'document_no' => $transfer_no,
                'description' => 'Transfert STOCK INTERMEDIAIRE VERS PETIT STOCK',
                'account_id' => $mdDestinationAccountId,
                'libelle' => 'Transfert STOCK INTERMEDIAIRE VERS PETIT STOCK',
                'debit' => $totalMdAccountingValue,
                'credit' => 0,
                'balance' => $destinationLedgerState['balance'],
                'active' => 1,
                'created_at' => Carbon::now()
            ]]);
        }

        // Insert reports independently for bg and md paths
        if ($flag_food_bg != 0) {
            if (!empty($reportBigStoreDataBg)) {
                RawMaterialExtraBigReport::insert($reportBigStoreDataBg);
            }
            if (!empty($reportMediumStoreData)) {
                RawMaterialBigReport::insert($reportMediumStoreData);
            }
        }
        if ($flag_food_md != 0) {
            if (!empty($reportBigStoreData)) {
                RawMaterialBigReport::insert($reportBigStoreData);
            }
            if (!empty($reportSmallStoreData)) {
                RawMaterialSmallReport::insert($reportSmallStoreData);
            }
        }

        if ($flag_food_bg == 0 && $flag_food_md == 0 && $flag_food_sm == 0) {
            DB::rollBack();
            session()->flash('error', __('messages.transfer_no_stock_processed'));
            return back();
        }

        RawMaterialTransfer::where('transfer_no', '=', $transfer_no)
                        ->update(['status' => 4, 'approuved_by' => $this->user->name]);
        RawMaterialTransferDetail::where('transfer_no', '=', $transfer_no)
                        ->update(['status' => 4, 'approuved_by' => $this->user->name]);

        // Update requisition status to 6 (closed)
        $requisition_no = RawMaterialTransfer::where('transfer_no', $transfer_no)->value('requisition_no');
        if ($requisition_no) {
            RawMaterialRequisition::where('requisition_no', $requisition_no)->update(['status' => 6]);
            RawMaterialRequisitionDetail::where('requisition_no', $requisition_no)->update(['status' => 6]);
        }

        $successMessage = 'Transfer has been done successfully!';
        if ($flag_food_bg != 0 && !empty($reportBigStoreDataBg)) {
            $successMessage .= ' From: ' . $reportBigStoreDataBg[0]['code_store'];
            if (!empty($reportMediumStoreData)) {
                $successMessage .= ' To: ' . $reportMediumStoreData[0]['code_store'];
            }
        } elseif (!empty($reportBigStoreData)) {
            $successMessage .= ' From: ' . $reportBigStoreData[0]['code_store'];
            if (!empty($reportSmallStoreData)) {
                $successMessage .= ' To: ' . $reportSmallStoreData[0]['code_store'];
            }
        }

        DB::commit();

        session()->flash('success', $successMessage);
        return back();
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $transfer_no
     * @return \Illuminate\Http\Response
     */
    public function destroy($transfer_no)
    {
        if (is_null($this->user) || !$this->user->can('raw_material_transfer.delete')) {
            abort(403, 'Sorry !! You are Unauthorized to delete any transfer !');
        }

        $transfer = RawMaterialTransfer::where('transfer_no', $transfer_no)->first();
        if (is_null($transfer)) {
            session()->flash('error', 'Transfer not found !!');
            return back();
        }

        // Refuse delete if transfer already approved (stock moved)
        if ((int) $transfer->status === 4) {
            session()->flash('error', 'Cannot delete an approved transfer (status 4) !!');
            return back();
        }

        // Check if requisition status is 6 (cannot delete when fully transferred)
        $requisition = RawMaterialRequisition::where('requisition_no', $transfer->requisition_no)->first();
        if ($requisition && (int) $requisition->status === 6) {
            session()->flash('error', 'Cannot delete a transfer when requisition status is 6 (Transferred) !!');
            return back();
        }

        try {DB::beginTransaction();

        $requisitionNo = $transfer->requisition_no;
        $requisitionStatus = $requisition ? (int) $requisition->status : null;

        $transfer->delete();
        RawMaterialTransferDetail::where('transfer_no', $transfer_no)->delete();

        // If requisition was pending transfer (5), restore to approved (4)
        if ($requisitionStatus === 5) {
            RawMaterialRequisition::where('requisition_no', $requisitionNo)->update(['status' => 4]);
            RawMaterialRequisitionDetail::where('requisition_no', $requisitionNo)->update(['status' => 4]);
        }

        DB::commit();
            session()->flash('success', 'Transfer has been deleted !!');
            return back();
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }

    }

    /**
     * Update ledger totals for accounting entries
     *
     * @param int $accountId
     * @param string $type ('debit' or 'credit')
     * @param float $amount
     * @return array
     */
    protected function updateLedgerTotals(int $accountId, string $type, float $amount): array
    {
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
}
