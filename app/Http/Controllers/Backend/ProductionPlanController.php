<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use PDF;
use Validator;
use Excel;
use App\Models\ProductionPlan;
use App\Models\ProductionPlanDetail;
use App\Models\RawMaterial;
use App\Models\Item;
use App\Models\ItemDetail;

class ProductionPlanController extends Controller
{
    public $user;

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::guard('admin')->user();
            return $next($request);
        });
    }

    public function index(Request $request)
    {
        if (is_null($this->user) || !$this->user->can('production_plan.view')) {
            abort(403, 'Désolé !! Vous n\'êtes pas autorisé à voir les plans de production.');
        }
        $query = ProductionPlan::orderBy('id', 'desc');
        if ($request->filled('date_debut') && $request->filled('date_fin')) {
            $dateDebut = Carbon::parse($request->date_debut)->startOfDay();
            $dateFin = Carbon::parse($request->date_fin)->endOfDay();
            $query->whereBetween('production_plan_date', [$dateDebut, $dateFin]);
        } else {
            $query->take(100);
        }
        $plans = $query->get();
        return view('backend.pages.production_plan.index', compact('plans'));
    }

    public function exportToExcel(Request $request)
    {
        if (is_null($this->user) || !$this->user->can('production_plan.view')) {
            abort(403, 'Désolé !! Vous n\'êtes pas autorisé à exporter les plans de production.');
        }
        $dateDebut = $request->input('date_debut') ?? $request->input('start_date');
        $dateFin = $request->input('date_fin') ?? $request->input('end_date');
        if (!$dateDebut || !$dateFin) {
            return redirect()->route('admin.production-plan.index')
                ->with('error', __('messages.export_select_date_range'));
        }
        $filename = 'plans_production_' . Carbon::parse($dateDebut)->format('Y-m-d') . '_' . Carbon::parse($dateFin)->format('Y-m-d') . '.xlsx';
        return Excel::download(new \App\Exports\ProductionPlanExport(), $filename);
    }

    public function create()
    {
        if (is_null($this->user) || !$this->user->can('production_plan.create')) {
            abort(403, 'Désolé !! Vous n\'êtes pas autorisé à créer un plan de production.');
        }

        $raw_materials = RawMaterial::where('status', 'active')->orderBy('name', 'asc')->get();
        $itemDetails = ItemDetail::with(['rawMaterial.rawMaterialMeasurement'])
            ->whereNotNull('r_material_id')
            ->where('r_material_id', '>', 0)
            ->get();

        $plannedStartDefault = Carbon::now()->format('Y-m-d\TH:i');
        $plannedFinishDefault = Carbon::now()->addDay()->format('Y-m-d\TH:i');

        return view('backend.pages.production_plan.create', compact(
            'raw_materials',
            'itemDetails',
            'plannedStartDefault',
            'plannedFinishDefault'
        ));
    }

    /**
     * Ajax search: finished goods for production planning.
     */
    public function searchItems(Request $request)
    {
        if (is_null($this->user) || (!$this->user->can('production_plan.create') && !$this->user->can('production_plan.edit'))) {
            abort(403, 'Désolé !! Vous n\'êtes pas autorisé à rechercher des articles.');
        }

        $q = trim((string) $request->input('q', ''));
        $limit = min(max((int) $request->input('limit', 25), 1), 50);

        $query = Item::active()->orderBy('name', 'asc');
        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('name', 'like', '%' . $q . '%')
                    ->orWhere('code', 'like', '%' . $q . '%');
            });
        }

        $results = $query->limit($limit)->get(['id', 'code', 'name', 'cump'])->map(function ($item) {
            return [
                'id' => (int) $item->id,
                'label' => trim(($item->code ?? '') . ' - ' . ($item->name ?? '')),
                'text' => trim(($item->code ?? '') . ' - ' . ($item->name ?? '')),
                'code' => (string) ($item->code ?? ''),
                'cump' => (float) ($item->cump ?? 0),
            ];
        })->values();

        return response()->json(['results' => $results]);
    }

    private function resolvePlanDates(Request $request): array
    {
        $planDate = Carbon::parse($request->production_plan_date);
        $plannedStart = $request->planned_start_time
            ? Carbon::parse($request->planned_start_time)
            : Carbon::now();
        $plannedFinish = $request->planned_finish_time
            ? Carbon::parse($request->planned_finish_time)
            : Carbon::now()->addDay();

        return [$planDate, $plannedStart, $plannedFinish];
    }

    public function store(Request $request)
    {
        if (is_null($this->user) || !$this->user->can('production_plan.create')) {
            abort(403, 'Désolé !! Vous n\'êtes pas autorisé à créer un plan de production.');
        }

        $rules = [
            'production_plan_date' => 'required',
            'item_id.*' => 'required',
            'item_quantity.*' => 'required',
            'r_material_id.*' => 'required',
            'material_quantity.*' => 'required',
            'description' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            [$plan_date, $planned_start, $planned_finish] = $this->resolvePlanDates($request);

            $latest = ProductionPlan::latest()->first();
            $plan_no = $latest ? 'PP' . str_pad((int)$latest->id + 1, 4, '0', STR_PAD_LEFT) : 'PP0001';

            $tin = config('app.tin_number_company', 'TIN');
            $signature = $tin . Carbon::now()->format('YmdHis') . '/' . $plan_no;
            $created_by = $this->user->name;

            $r_material_id = $request->r_material_id ?? [];
            $material_quantity = $request->material_quantity ?? [];
            $material_cost = $request->material_cost ?? [];
            $total_material_cost = 0;
            for ($i = 0; $i < count($r_material_id); $i++) {
                if (empty($r_material_id[$i])) continue;
                $qty = floatval($material_quantity[$i] ?? 0);
                $cost = floatval($material_cost[$i] ?? 0);
                if ($cost <= 0) {
                    $rm = RawMaterial::find($r_material_id[$i]);
                    $cost = $rm ? ($rm->cump ?? 0) : 0;
                }
                $total_material_cost += $qty * $cost;
            }

            $labor = floatval($request->estimated_labor_cost ?? 0);
            $overhead = floatval($request->estimated_overhead_cost ?? 0);
            $total_estimated_cost = $labor + $total_material_cost + $overhead;

            $plan = new ProductionPlan();
            $plan->prodution_plan_number = $plan_no;
            $plan->production_plan_date = $plan_date;
            $plan->start_date = $planned_start->copy()->startOfDay();
            $plan->end_date = $planned_finish->copy()->startOfDay();
            $plan->production_signature = $signature;
            $plan->status = '0';
            $plan->description = $request->description;
            $plan->created_by = $created_by;
            $plan->work_center = $request->work_center;
            $plan->operation_sequence = $request->operation_sequence;
            $plan->routing_notes = $request->routing_notes;
            $plan->estimated_labor_cost = $labor;
            $plan->estimated_material_cost = $total_material_cost;
            $plan->estimated_overhead_cost = $overhead;
            $plan->total_estimated_cost = $total_estimated_cost;
            $plan->planned_start_time = $planned_start;
            $plan->planned_finish_time = $planned_finish;
            $plan->save();

            // Raw material details
            for ($i = 0; $i < count($r_material_id); $i++) {
                if (empty($r_material_id[$i])) continue;
                $qty = floatval($material_quantity[$i] ?? 0);
                if ($qty <= 0) continue;
                $cost = floatval($material_cost[$i] ?? 0);
                if ($cost <= 0) {
                    $rm = RawMaterial::find($r_material_id[$i]);
                    $cost = $rm ? ($rm->cump ?? 0) : 0;
                }
                $d = new ProductionPlanDetail();
                $d->production_number = $plan_no;
                $d->production_date = $plan_date;
                $d->start_date = $planned_start->copy()->startOfDay();
                $d->end_date = $planned_finish->copy()->startOfDay();
                $d->production_signature = $signature;
                $d->status = '0';
                $d->r_material_id = $r_material_id[$i];
                $d->material_quantity = $qty;
                $d->material_cost = $cost;
                $d->total_material_cost = $qty * $cost;
                $d->created_by = $created_by;
                $d->save();
            }

            // Finished goods details
            $item_id = $request->item_id ?? [];
            $item_quantity = $request->item_quantity ?? [];
            $cost_price = $request->cost_price ?? [];
            foreach ($item_id as $idx => $id) {
                if (empty($id)) continue;
                $qty = floatval($item_quantity[$idx] ?? 0);
                if ($qty <= 0) continue;
                $cost = isset($cost_price[$idx]) ? floatval($cost_price[$idx]) : $total_estimated_cost;
                $d = new ProductionPlanDetail();
                $d->production_number = $plan_no;
                $d->production_date = $plan_date;
                $d->start_date = $planned_start->copy()->startOfDay();
                $d->end_date = $planned_finish->copy()->startOfDay();
                $d->production_signature = $signature;
                $d->status = '0';
                $d->item_id = $id;
                $d->item_quantity = $qty;
                $d->cost_price = $cost;
                $d->total_item_cost = $qty * $cost;
                $d->created_by = $created_by;
                $d->save();
            }

            DB::commit();
            session()->flash('success', 'Plan de production créé avec succès !!');
            return redirect()->route('admin.production-plan.index');
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('ProductionPlan store: ' . $e->getMessage());
            session()->flash('error', 'Erreur: ' . $e->getMessage());
            return back()->withInput();
        }
    }

    public function show($plan_number)
    {
        if (is_null($this->user) || !$this->user->can('production_plan.view')) {
            abort(403, 'Désolé !! Vous n\'êtes pas autorisé à voir ce plan.');
        }

        $plan = ProductionPlan::where('prodution_plan_number', $plan_number)->first();
        if (!$plan) {
            session()->flash('error', 'Plan de production introuvable !!');
            return redirect()->route('admin.production-plan.index');
        }

        $rawMaterials = ProductionPlanDetail::with('rawMaterial.rawMaterialMeasurement')
            ->where('production_number', $plan_number)
            ->whereNotNull('r_material_id')
            ->get();

        $finishedGoods = ProductionPlanDetail::with('item')
            ->where('production_number', $plan_number)
            ->whereNotNull('item_id')
            ->get();

        $totalMaterialCost = $rawMaterials->sum('total_material_cost');
        $totalItemCost = $finishedGoods->sum('total_item_cost');
        $totalMaterialQuantity = $rawMaterials->sum('material_quantity');
        $totalItemQuantity = $finishedGoods->sum('item_quantity');

        $requisition = \App\Models\RawMaterialRequisition::where('production_plan_number', $plan_number)->first();

        return view('backend.pages.production_plan.show', compact(
            'plan',
            'rawMaterials',
            'finishedGoods',
            'totalMaterialCost',
            'totalItemCost',
            'totalMaterialQuantity',
            'totalItemQuantity',
            'requisition'
        ));
    }

    public function edit($plan_number)
    {
        if (is_null($this->user) || !$this->user->can('production_plan.edit')) {
            abort(403, 'Désolé !! Vous n\'êtes pas autorisé à modifier ce plan.');
        }

        $plan = ProductionPlan::where('prodution_plan_number', $plan_number)->first();
        if (!$plan) {
            session()->flash('error', 'Plan introuvable !!');
            return redirect()->route('admin.production-plan.index');
        }
        if ($plan->status == '4' || $plan->status == 4) {
            session()->flash('error', 'Impossible de modifier un plan approuvé !!');
            return redirect()->route('admin.production-plan.index');
        }
        if ($plan->flag1 == '1') {
            session()->flash('error', 'Une requête a déjà été créée à partir de ce plan !!');
            return redirect()->route('admin.production-plan.index');
        }

        $raw_materials = RawMaterial::where('status', 'active')->orderBy('name', 'asc')->get();
        $itemDetails = ItemDetail::with(['rawMaterial.rawMaterialMeasurement'])
            ->whereNotNull('r_material_id')->where('r_material_id', '>', 0)->get();

        $rawMaterials = ProductionPlanDetail::with('rawMaterial.rawMaterialMeasurement')
            ->where('production_number', $plan_number)->whereNotNull('r_material_id')->get();
        $finishedGoods = ProductionPlanDetail::with('item')
            ->where('production_number', $plan_number)->whereNotNull('item_id')->get();

        $plannedStartDefault = $plan->planned_start_time
            ? Carbon::parse($plan->planned_start_time)->format('Y-m-d\TH:i')
            : Carbon::now()->format('Y-m-d\TH:i');
        $plannedFinishDefault = $plan->planned_finish_time
            ? Carbon::parse($plan->planned_finish_time)->format('Y-m-d\TH:i')
            : Carbon::now()->addDay()->format('Y-m-d\TH:i');

        return view('backend.pages.production_plan.edit', compact(
            'plan',
            'raw_materials',
            'itemDetails',
            'rawMaterials',
            'finishedGoods',
            'plannedStartDefault',
            'plannedFinishDefault'
        ));
    }

    public function update(Request $request, $plan_number)
    {
        if (is_null($this->user) || !$this->user->can('production_plan.edit')) {
            abort(403, 'Désolé !! Vous n\'êtes pas autorisé à modifier ce plan.');
        }

        $plan = ProductionPlan::where('prodution_plan_number', $plan_number)->first();
        if (!$plan || $plan->status == '4' || $plan->flag1 == '1') {
            session()->flash('error', 'Modification non autorisée.');
            return redirect()->route('admin.production-plan.index');
        }

        $rules = [
            'production_plan_date' => 'required',
            'item_id.*' => 'required',
            'item_quantity.*' => 'required',
            'r_material_id.*' => 'required',
            'material_quantity.*' => 'required',
            'description' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            [$plan_date, $planned_start, $planned_finish] = $this->resolvePlanDates($request);
            $signature = $plan->production_signature;
            $updated_by = $this->user->name;

            $r_material_id = $request->r_material_id ?? [];
            $material_quantity = $request->material_quantity ?? [];
            $material_cost = $request->material_cost ?? [];
            $total_material_cost = 0;
            foreach ($r_material_id as $i => $rid) {
                if (empty($rid)) continue;
                $qty = floatval($material_quantity[$i] ?? 0);
                $cost = floatval($material_cost[$i] ?? 0);
                if ($cost <= 0) {
                    $rm = RawMaterial::find($rid);
                    $cost = $rm ? ($rm->cump ?? 0) : 0;
                }
                $total_material_cost += $qty * $cost;
            }
            $labor = floatval($request->estimated_labor_cost ?? 0);
            $overhead = floatval($request->estimated_overhead_cost ?? 0);
            $total_estimated_cost = $labor + $total_material_cost + $overhead;

            ProductionPlanDetail::where('production_number', $plan_number)->delete();

            for ($i = 0; $i < count($r_material_id); $i++) {
                if (empty($r_material_id[$i])) continue;
                $qty = floatval($material_quantity[$i] ?? 0);
                if ($qty <= 0) continue;
                $cost = floatval($material_cost[$i] ?? 0);
                if ($cost <= 0) {
                    $rm = RawMaterial::find($r_material_id[$i]);
                    $cost = $rm ? ($rm->cump ?? 0) : 0;
                }
                $d = new ProductionPlanDetail();
                $d->production_number = $plan_number;
                $d->production_date = $plan_date;
                $d->start_date = $planned_start->copy()->startOfDay();
                $d->end_date = $planned_finish->copy()->startOfDay();
                $d->production_signature = $signature;
                $d->status = $plan->status;
                $d->r_material_id = $r_material_id[$i];
                $d->material_quantity = $qty;
                $d->material_cost = $cost;
                $d->total_material_cost = $qty * $cost;
                $d->created_by = $plan->created_by;
                $d->updated_by = $updated_by;
                $d->save();
            }

            $item_id = $request->item_id ?? [];
            $item_quantity = $request->item_quantity ?? [];
            $cost_price = $request->cost_price ?? [];
            foreach ($item_id as $idx => $id) {
                if (empty($id)) continue;
                $qty = floatval($item_quantity[$idx] ?? 0);
                if ($qty <= 0) continue;
                $cost = isset($cost_price[$idx]) ? floatval($cost_price[$idx]) : $total_estimated_cost;
                $d = new ProductionPlanDetail();
                $d->production_number = $plan_number;
                $d->production_date = $plan_date;
                $d->start_date = $planned_start->copy()->startOfDay();
                $d->end_date = $planned_finish->copy()->startOfDay();
                $d->production_signature = $signature;
                $d->status = $plan->status;
                $d->item_id = $id;
                $d->item_quantity = $qty;
                $d->cost_price = $cost;
                $d->total_item_cost = $qty * $cost;
                $d->created_by = $plan->created_by;
                $d->updated_by = $updated_by;
                $d->save();
            }

            $plan->production_plan_date = $plan_date;
            $plan->start_date = $planned_start->copy()->startOfDay();
            $plan->end_date = $planned_finish->copy()->startOfDay();
            $plan->description = $request->description;
            $plan->updated_by = $updated_by;
            $plan->work_center = $request->work_center;
            $plan->operation_sequence = $request->operation_sequence;
            $plan->routing_notes = $request->routing_notes;
            $plan->estimated_labor_cost = $labor;
            $plan->estimated_material_cost = $total_material_cost;
            $plan->estimated_overhead_cost = $overhead;
            $plan->total_estimated_cost = $total_estimated_cost;
            $plan->planned_start_time = $planned_start;
            $plan->planned_finish_time = $planned_finish;
            $plan->save();

            DB::commit();
            session()->flash('success', 'Plan de production mis à jour !!');
            return redirect()->route('admin.production-plan.index');
        } catch (\Exception $e) {
            DB::rollback();
            session()->flash('error', 'Erreur: ' . $e->getMessage());
            return back()->withInput();
        }
    }

    public function destroy($plan_number)
    {
        if (is_null($this->user) || !$this->user->can('production_plan.delete')) {
            abort(403, 'Désolé !! Vous n\'êtes pas autorisé à supprimer ce plan.');
        }

        $plan = ProductionPlan::where('prodution_plan_number', $plan_number)->first();
        if (!$plan) {
            session()->flash('error', 'Plan introuvable !!');
            return back();
        }
        if ($plan->status == '4') {
            session()->flash('error', 'Impossible de supprimer un plan approuvé !!');
            return back();
        }
        if ($plan->flag1 == '1') {
            session()->flash('error', 'Impossible de supprimer un plan pour lequel une requête a été créée !!');
            return back();
        }

        try {
            DB::beginTransaction();
            ProductionPlanDetail::where('production_number', $plan_number)->delete();
            $plan->delete();
            DB::commit();
            session()->flash('success', 'Plan de production supprimé !!');
        } catch (\Exception $e) {
            DB::rollback();
            session()->flash('error', 'Erreur: ' . $e->getMessage());
        }
        return back();
    }

    public function validatePlan($plan_number)
    {
        if (is_null($this->user) || !$this->user->can('production_plan.validate')) {
            abort(403, 'Désolé !! Vous n\'êtes pas autorisé à valider ce plan.');
        }

        try {
            DB::beginTransaction();
            ProductionPlan::where('prodution_plan_number', $plan_number)->update(['status' => '2', 'validated_by' => $this->user->name]);
            ProductionPlanDetail::where('production_number', $plan_number)->update(['status' => '2', 'validated_by' => $this->user->name]);
            DB::commit();
            session()->flash('success', 'Plan de production validé !!');
        } catch (\Exception $e) {
            DB::rollback();
            session()->flash('error', $e->getMessage());
        }
        return back();
    }

    public function confirm($plan_number)
    {
        if (is_null($this->user) || !$this->user->can('production_plan.confirm')) {
            abort(403, 'Désolé !! Vous n\'êtes pas autorisé à confirmer ce plan.');
        }

        try {
            DB::beginTransaction();
            ProductionPlan::where('prodution_plan_number', $plan_number)->update(['status' => '3', 'confirmed_by' => $this->user->name]);
            ProductionPlanDetail::where('production_number', $plan_number)->update(['status' => '3', 'confirmed_by' => $this->user->name]);
            DB::commit();
            session()->flash('success', 'Plan de production confirmé !!');
        } catch (\Exception $e) {
            DB::rollback();
            session()->flash('error', $e->getMessage());
        }
        return back();
    }

    public function approve($plan_number)
    {
        if (is_null($this->user) || !$this->user->can('production_plan.approuve')) {
            abort(403, 'Désolé !! Vous n\'êtes pas autorisé à approuver ce plan.');
        }

        try {
            DB::beginTransaction();
            ProductionPlan::where('prodution_plan_number', $plan_number)->update(['status' => '4', 'approuved_by' => $this->user->name]);
            ProductionPlanDetail::where('production_number', $plan_number)->update(['status' => '4', 'approuved_by' => $this->user->name]);
            DB::commit();
            session()->flash('success', 'Plan de production approuvé !!');
        } catch (\Exception $e) {
            DB::rollback();
            session()->flash('error', $e->getMessage());
        }
        return back();
    }

    public function reject($plan_number)
    {
        if (is_null($this->user) || !$this->user->can('production_plan.reject')) {
            abort(403, 'Désolé !! Vous n\'êtes pas autorisé à rejeter ce plan.');
        }

        try {
            DB::beginTransaction();
            ProductionPlan::where('prodution_plan_number', $plan_number)->update(['status' => '-1', 'rejected_by' => $this->user->name]);
            ProductionPlanDetail::where('production_number', $plan_number)->update(['status' => '-1', 'rejected_by' => $this->user->name]);
            DB::commit();
            session()->flash('success', 'Plan de production rejeté !!');
        } catch (\Exception $e) {
            DB::rollback();
            session()->flash('error', $e->getMessage());
        }
        return back();
    }

    public function printPlan($plan_number)
    {
        if (is_null($this->user) || !$this->user->can('production_plan.view')) {
            abort(403, 'Désolé !! Vous n\'êtes pas autorisé.');
        }

        $plan = ProductionPlan::where('prodution_plan_number', $plan_number)->first();
        if (!$plan) {
            session()->flash('error', 'Plan introuvable !!');
            return back();
        }

        $rawMaterials = ProductionPlanDetail::with('rawMaterial.rawMaterialMeasurement')
            ->where('production_number', $plan_number)->whereNotNull('r_material_id')->get();
        $finishedGoods = ProductionPlanDetail::with('item')
            ->where('production_number', $plan_number)->whereNotNull('item_id')->get();

        $setting = DB::table('settings')->orderBy('created_at', 'desc')->first();

        $pdf = PDF::setOptions(['defaultFont' => 'dejavu sans', 'isRemoteEnabled' => true])->loadView('backend.pages.document.production_plan', compact('plan', 'rawMaterials', 'finishedGoods', 'setting'), [], 'UTF-8');
        return $pdf->download('PLAN_PRODUCTION_' . $plan_number . '.pdf');
    }
}
