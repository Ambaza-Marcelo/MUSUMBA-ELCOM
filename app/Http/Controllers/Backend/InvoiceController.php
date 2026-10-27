<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use Carbon\Carbon;
use PDF;
use Excel;
use Mail;
use Validator;
use GuzzleHttp\Client;
use App\Models\ProformaInvoice;
use App\Models\ProformaInvoiceDetail;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteDetail;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Item;
use App\Models\ItemDetail;
use App\Models\RawMaterialBigStore;
use App\Models\RawMaterialBigStoreDetail;
use App\Models\RawMaterialSmallStoreDetail;
use App\Models\EGRClient;
use App\Models\Setting;
use App\Models\NoteCredit;
use App\Models\NoteCreditDetail;
use App\Models\Accounting\AccountCostCenter;
use App\Models\Accounting\AccountJournalEntry;
use App\Models\Accounting\AccountJournalEntryDetail;
use App\Models\Accounting\AccountLedger;
use App\Models\Accounting\AccountAccount;
use App\Models\Accounting\AccountCurrency;
use App\Models\Accounting\AccountSetting;
use App\Models\FinishedGoodsStore;
use App\Models\FinishedGoodsStoreDetail;
use App\Models\FinishedGoodsSmStoreDetail;
use App\Models\GoodsStoreDetail;
use App\Models\FinishedGoodReport;
use App\Mail\DeleteFactureMail;
use App\Mail\InvoiceResetedMail;
use App\Http\Controllers\Backend\Concerns\GuardsAccountingPeriod;

class InvoiceController extends Controller
{
    use GuardsAccountingPeriod;
    //
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
        if (is_null($this->user) || !$this->user->can('invoice.view')) {
            abort(403, 'Sorry !! You are Unauthorized to view any invoice !');
        }

        $query = Invoice::with(['client', 'details'])->orderBy('id','desc');
        if ($request->filled('date_debut') && $request->filled('date_fin')) {
            $dateDebut = \Carbon\Carbon::parse($request->date_debut)->startOfDay();
            $dateFin = \Carbon\Carbon::parse($request->date_fin)->endOfDay();
            $query->whereBetween('invoice_date', [$dateDebut, $dateFin]);
        } else {
            $query->take(100);
        }
        $invoices = $query->get();
        return view('backend.pages.invoice.index',compact('invoices'));
    }

    public function exportToExcel(Request $request)
    {
        if (is_null($this->user) || !$this->user->can('invoice.view')) {
            abort(403, 'Sorry !! You are Unauthorized to export invoices !');
        }
        $query = Invoice::with(['client', 'details'])->orderBy('id','desc');
        if ($request->filled('date_debut') && $request->filled('date_fin')) {
            $dateDebut = \Carbon\Carbon::parse($request->date_debut)->startOfDay();
            $dateFin = \Carbon\Carbon::parse($request->date_fin)->endOfDay();
            $query->whereBetween('invoice_date', [$dateDebut, $dateFin]);
        }
        $invoiceNumbers = $query->pluck('invoice_number');
        $details = \App\Models\InvoiceDetail::with(['invoice.client', 'item'])
            ->whereIn('invoice_number', $invoiceNumbers)
            ->orderBy('invoice_number')->orderBy('id')
            ->get();
        $rows = [];
        $i = 1;
        foreach ($details as $d) {
            $doc = $d->invoice;
            $etat = $doc && $doc->etat == '0' ? 'Brouillon' : ($doc && $doc->etat == '1' ? 'Validée' : ($doc && $doc->etat == '2' ? 'Confirmée' : ($doc && $doc->etat == '3' ? 'Approuvée' : ($doc->etat ?? ''))));
            $rows[] = [
                $i++,
                $d->invoice_number,
                $doc && $doc->invoice_date ? \Carbon\Carbon::parse($doc->invoice_date)->format('d/m/Y') : '',
                $doc->invoice_signature ?? '',
                $doc && $doc->client ? $doc->client->customer_name : 'N/A',
                $doc->document_no ?? '',
                $etat,
                $d->item_designation ?? ($d->item ? $d->item->name : ''),
                (float) ($d->item_quantity ?? 0),
                (float) ($d->item_price_nvat ?? $d->item_price ?? 0),
                (float) ($d->vat ?? 0),
                (float) ($d->item_total_amount ?? 0),
                $doc->auteur ?? '',
            ];
        }
        $headings = ['#', 'Numéro Facture', 'Date', 'Signature', 'Customer', 'Bon de Livraison', 'Etat', 'Désignation', 'Quantité', 'PU HTVA', 'TVA', 'Total ligne', 'Auteur'];
        return \Excel::download(new \App\Exports\GenericIndexExport($rows, $headings), 'invoices.xlsx');
    }

    public function create($delivery_number)
    {
        if (is_null($this->user) || !$this->user->can('invoice.create')) {
            abort(403, 'Sorry !! You are Unauthorized to create any invoice !');
        }

        // Facturation uniquement depuis le bon de commande (évite les doublons avec le BL).
        session()->flash(
            'error',
            'La facturation depuis le bon de livraison est désactivée. Créez la facture à partir du bon de commande.'
        );

        return redirect()->route('admin.sales-order.index');
    }

    public function createFromSalesOrder($sales_order_number)
    {
        if (is_null($this->user) || !$this->user->can('invoice.create')) {
            abort(403, 'Sorry !! You are Unauthorized to create any invoice !');
        }
        $sales_order = \App\Models\SalesOrder::where('sales_order_number', $sales_order_number)->with(['client'])->firstOrFail();
        if ($sales_order->etat != '3') {
            session()->flash('error', 'Le bon de commande doit être approuvé (etat=3) pour créer une facture.');
            return redirect()->route('admin.sales-order.index');
        }
        $existing = Invoice::where('document_no', $sales_order_number)->first();
        if ($existing) {
            session()->flash('info', 'Une facture existe déjà pour ce bon de commande.');
            return redirect()->route('admin.invoice.show', $existing->invoice_number);
        }
        $datas = \App\Models\SalesOrderDetail::where('sales_order_number', $sales_order_number)->with(['item'])->get();
        if ($datas->isEmpty()) {
            session()->flash('error', 'Aucun détail trouvé pour ce bon de commande.');
            return redirect()->route('admin.sales-order.index');
        }
        $clients = EGRClient::orderBy('customer_name')->get();
        return view('backend.pages.invoice.create_from_sales_order', compact('clients', 'datas', 'sales_order', 'sales_order_number'));
    }

    public function store(Request $request)
    {
        if (is_null($this->user) || !$this->user->can('invoice.create')) {
            abort(403, 'Sorry !! You are Unauthorized to create any invoice !');
        }

        $rules = array(
            'client_id' => 'required',
            'item_id.*' => 'required',
            'item_quantity.*' => 'required|numeric|min:1',
            'invoice_type' => 'required',
            'payment_type' => 'required',
            'invoice_currency' => 'required'
        );

        $error = Validator::make($request->all(), $rules);

        if($error->fails()){
            return redirect()->back()->withErrors($error)->withInput();
        }

        $company = $this->resolveCompanyDataFromSettings();
        if (!$company['ok']) {
            session()->flash('error', $company['message']);
            return redirect()->back()->withInput();
        }

        try {
            DB::beginTransaction();

            $item_ids = $request->item_id ?? [];
            $quantities = $request->item_quantity ?? [];
            $client_id = $request->client_id;
            $delivery_number = $request->delivery_number ?? null;
            $document_no = $request->document_no ?? $delivery_number;
            $invoiceType = $this->normalizeSingleChoice($request->input('invoice_type'), ['FN', 'RC', 'RHF'], 'FN');
            $paymentType = $this->normalizeSingleChoice($request->input('payment_type'), ['1', '2', '3', '4'], '1');
            $invoiceCurrency = $this->normalizeSingleChoice($request->input('invoice_currency'), ['BIF', 'USD', 'EUR'], 'BIF');

            // Bloquer la facturation rattachée à un bon de livraison (uniquement BC).
            if (!empty($document_no) && DeliveryNote::where('delivery_number', $document_no)->exists()) {
                DB::rollBack();
                session()->flash(
                    'error',
                    'La facturation depuis le bon de livraison est désactivée. Créez la facture à partir du bon de commande.'
                );
                return redirect()->route('admin.sales-order.index');
            }
            
            if (empty($item_ids) || empty($quantities)) {
                return redirect()->back()->withErrors(['error' => 'Veuillez ajouter au moins un article.'])->withInput();
            }
            
            $latest = Invoice::orderBy('id','desc')->first();

            $invoice_date = Carbon::now();

            $dateFormatted = Carbon::parse($invoice_date)->format('Ymd');

            if ($latest) {
                $invoice_number = 'FN' . str_pad((int)$latest->id + 1, 4, '0', STR_PAD_LEFT); 
            } else {
                $invoice_number = 'FN' . str_pad(1, 4, '0', STR_PAD_LEFT);  
            }

            $invoice_signature = $company['data']['tp_TIN']."/".config('app.obr_test_username')."/".Carbon::parse($invoice_date)->format('YmdHis') . "/" . $invoice_number;

            $client = EGRClient::where('id', $client_id)->first();
            
            // Get Setting to check vat_taxpayer
            $setting = Setting::first();
            $is_vat_taxpayer = $company['data']['vat_taxpayer'] == 1;

            // Create main invoice
            $invoice = new Invoice();
            $invoice->invoice_number = $invoice_number;
            $invoice->invoice_date = $invoice_date;
            $invoice->invoice_signature = $invoice_signature;
            $invoice->invoice_signature_date = $invoice_date;
            $invoice->client_id = $client_id;
            $invoice->document_no = $document_no; // delivery_number or sales_order_number
            $invoice->tp_type = $company['data']['tp_type'];
            $invoice->tp_name = $company['data']['tp_name'];
            $invoice->tp_TIN = $company['data']['tp_TIN'];
            $invoice->tp_trade_number = $company['data']['tp_trade_number'];
            $invoice->tp_phone_number = $company['data']['tp_phone_number'];
            $invoice->tp_address_province = $company['data']['tp_address_province'];
            $invoice->tp_address_commune = $company['data']['tp_address_commune'];
            $invoice->tp_address_quartier = $company['data']['tp_address_quartier'];
            $invoice->tp_address_avenue = $company['data']['tp_address_avenue'];
            $invoice->tp_address_rue = $company['data']['tp_address_rue'];
            $invoice->vat_taxpayer = $company['data']['vat_taxpayer'];
            $invoice->ct_taxpayer = $company['data']['ct_taxpayer'];
            $invoice->tl_taxpayer = $company['data']['tl_taxpayer'];
            $invoice->tp_fiscal_center = $company['data']['tp_fiscal_center'];
            $invoice->tp_activity_sector = $company['data']['tp_activity_sector'];
            $invoice->tp_legal_form = $company['data']['tp_legal_form'];
            $invoice->invoice_currency = $invoiceCurrency;
            $invoice->payment_type = $paymentType;
            $invoice->invoice_type = $invoiceType;
            $invoice->client_id = $request->client_id;
            $invoice->invoice_signature = $invoice_signature;
            $invoice->cancelled_invoice = 0;
            $invoice->description = $request->description ?? '';
            $invoice->auteur = $this->user->name;
            $invoice->etat = '0';
            if ($request->hasFile('document')) {
                $file = $request->file('document');
                if (in_array(strtolower($file->getClientOriginalExtension()), ['pdf', 'jpg', 'jpeg', 'png'])) {
                    $invoice->document = $file->store('documents/invoice', 'public');
                }
            }
            $invoice->save();

            // Create details
            $data1 = [];
            for($count = 0; $count < count($item_ids); $count++) {
                $item = Item::where('id', $item_ids[$count])->first();
                if (!$item) continue;

                $item_price_input = $request->item_price[$count] ?? $item->selling_price ?? 0;
                $unitPriceHt = round((float) $item_price_input, 2);
                $quantity = (float) ($quantities[$count] ?? 0);
                
                // Tax calculations based on vat_taxpayer
                // Convention (stockage interne): item_price = PU HT ; item_price_nvat = total HT ligne ; item_total_amount = TTC ligne.
                // Envoi OBR (addInvoice_confirm) : item_price = PU TTC (voir obrItemUnitPriceTaxIncluded()).
                // VAT rate depends on the item (items.vat).
                if ($is_vat_taxpayer) {
                    $vatRate = (float) ($item->vat_rate ?? $item->vat ?? 0); // %
                    $item_price_nvat = (float) $quantity * (float) $unitPriceHt;
                    $vat = $item_price_nvat * $vatRate / 100;
                    $item_total_amount = $item_price_nvat + $vat;
                    $item_price_wvat = $item_total_amount;
                } else {
                    $vat = 0;
                    $item_price_nvat = (float) $quantity * (float) $unitPriceHt;
                    $item_price_wvat = $item_total_amount = $item_price_nvat;
                }

                $data1[] = [
                    'invoice_number' => $invoice_number,
                    'invoice_date' => Carbon::now(), // Save current date and time
                    'invoice_signature' => $invoice_signature,
                    'invoice_signature_date' => $invoice_date,
                    'client_id' => $client_id,
                    'document_no' => $document_no, // Save delivery_number value in document_no
                    'tp_type'=>$company['data']['tp_type'],
                    'tp_name'=>$company['data']['tp_name'],
                    'tp_TIN'=>$company['data']['tp_TIN'],
                    'tp_trade_number'=>$company['data']['tp_trade_number'],
                    'tp_phone_number'=>$company['data']['tp_phone_number'],
                    'tp_address_province'=>$company['data']['tp_address_province'],
                    'tp_address_commune'=>$company['data']['tp_address_commune'],
                    'tp_address_quartier'=>$company['data']['tp_address_quartier'],
                    'tp_address_avenue'=>$company['data']['tp_address_avenue'],
                    'tp_address_rue'=>$company['data']['tp_address_rue'],
                    'vat_taxpayer'=>$company['data']['vat_taxpayer'],
                    'ct_taxpayer'=>$company['data']['ct_taxpayer'],
                    'tl_taxpayer'=>$company['data']['tl_taxpayer'],
                    'tp_fiscal_center'=>$company['data']['tp_fiscal_center'],
                    'tp_activity_sector'=>$company['data']['tp_activity_sector'],
                    'tp_legal_form'=>$company['data']['tp_legal_form'],
                    'payment_type'=>$paymentType,
                    'invoice_type'=>$invoiceType,
                    'client_id'=>$request->client_id,
                    'invoice_signature'=> $invoice_signature,
                    'cancelled_invoice'=> 0,
                    'invoice_currency'=>$invoiceCurrency,
                    'item_id' => $item_ids[$count],
                    'item_designation' => $item->name, // Save item name from items table
                    'item_quantity' => $quantity,
                    'item_price' => $unitPriceHt,
                    'item_price_nvat' => $item_price_nvat,
                    'vat' => $vat,
                    'item_price_wvat' => $item_price_wvat,
                    'item_total_amount' => $item_total_amount,
                    'auteur' => $this->user->name,
                    'etat' => '0',
                ];
            }

            if (!empty($data1)) {
                InvoiceDetail::insert($data1);
            }

            // Update DeliveryNote flag1 = 1 to indicate it has been invoiced
            if ($delivery_number) {
                DeliveryNote::where('delivery_number', $delivery_number)->update(['flag1' => '1']);
                DeliveryNoteDetail::where('delivery_number', $delivery_number)->update(['flag1' => '1']);
            }

            DB::commit();
            session()->flash('success', 'La facture est créée avec succès!!');
            return redirect()->route('admin.invoice.index');
        } catch (\Exception $e) {
            DB::rollback();
            session()->flash('error', __('messages.error_creation') . $e->getMessage());
            return redirect()->back()->withInput();
        }
    }

    public function show($invoice_number)
    {
        if (is_null($this->user) || !$this->user->can('invoice.view')) {
            abort(403, 'Sorry !! You are Unauthorized to view any invoice !');
        }

        $invoice = Invoice::where('invoice_number', $invoice_number)
            ->with(['client', 'details.item', 'deliveryNote'])
            ->firstOrFail();

        return view('backend.pages.invoice.show', compact('invoice'));
    }

    public function edit($invoice_number)
    {
        if (is_null($this->user) || !$this->user->can('invoice.edit')) {
            abort(403, 'Sorry !! You are Unauthorized to edit any invoice !');
        }

        $invoice = Invoice::where('invoice_number', $invoice_number)
            ->with(['client'])
            ->firstOrFail();

        // Check if invoice can be edited (only etat = 0)
        if ($invoice->etat != '0') {
            session()->flash('error', 'Cette facture ne peut pas être modifiée car son état est ' . $invoice->etat . '. Seules les factures avec état=0 peuvent être modifiées.');
            return redirect()->route('admin.invoice.index');
        }

        if (!$invoice->document_no) {
            session()->flash('error', 'Aucun document source associé trouvé.');
            return redirect()->route('admin.invoice.index');
        }

        $sync = \App\Support\BillingChain::invoiceEditFromPrevious($invoice);
        $delivery_details = $sync['lines'];

        if ($delivery_details->isEmpty()) {
            session()->flash('error', 'Aucun détail trouvé pour le document source associé.');
            return redirect()->route('admin.invoice.index');
        }

        $delivery_number = $sync['source_number'];
        $delivery_note = $sync['delivery_note'];
        $sales_order = $sync['sales_order'];
        $formClient = $sync['client'];
        $formClientId = $sync['client_id'];
        $formDescription = $sync['description'];
        $syncedFromPrevious = $sync['synced_from_previous'];
        $sourceDocumentType = $sync['source_type'];

        $items = Item::orderBy('name')->get();
        $clients = EGRClient::orderBy('customer_name')->get();
        $defaultClient = EGRClient::where('mail', 'clientordinaire@gmail.com')->first();

        $setting = Setting::first();
        return view('backend.pages.invoice.edit', compact(
            'invoice',
            'delivery_details',
            'delivery_note',
            'sales_order',
            'items',
            'clients',
            'delivery_number',
            'defaultClient',
            'setting',
            'formClient',
            'formClientId',
            'formDescription',
            'syncedFromPrevious',
            'sourceDocumentType'
        ));
    }

    public function update(Request $request, $invoice_number)
    {
        if (is_null($this->user) || !$this->user->can('invoice.edit')) {
            abort(403, 'Sorry !! You are Unauthorized to edit any invoice !');
        }

        $invoice = Invoice::where('invoice_number', $invoice_number)->firstOrFail();

        // Check if invoice can be edited (only etat = 0)
        if ($invoice->etat != '0') {
            session()->flash('error', 'Cette facture ne peut pas être modifiée car son état est ' . $invoice->etat . '. Seules les factures avec état=0 peuvent être modifiées.');
            return redirect()->route('admin.invoice.index');
        }

        $rules = array(
            'client_id' => 'required',
            'item_id.*' => 'required',
            'item_quantity.*' => 'required|numeric|min:1'
        );

        $error = Validator::make($request->all(), $rules);

        if($error->fails()){
            return redirect()->back()->withErrors($error)->withInput();
        }

        $company = $this->resolveCompanyDataFromSettings();
        if (!$company['ok']) {
            session()->flash('error', $company['message']);
            return redirect()->back()->withInput();
        }

        try {
            DB::beginTransaction();

            $item_ids = $request->item_id ?? [];
            $quantities = $request->item_quantity ?? [];
            $client_id = $request->client_id;
            $delivery_number = $request->delivery_number ?? $invoice->document_no;
            $document_no = $request->document_no ?? $delivery_number;

            if (empty($item_ids) || empty($quantities)) {
                return redirect()->back()->withErrors(['error' => 'Veuillez ajouter au moins un article.'])->withInput();
            }

            // Get Setting to check vat_taxpayer
            $setting = Setting::first();
            $is_vat_taxpayer = $company['data']['vat_taxpayer'] == 1;

            // Update main invoice
            $invoice->client_id = $client_id;
            $invoice->document_no = $document_no;
            $invoice->tp_type = $company['data']['tp_type'];
            $invoice->tp_name = $company['data']['tp_name'];
            $invoice->tp_TIN = $company['data']['tp_TIN'];
            $invoice->tp_trade_number = $company['data']['tp_trade_number'];
            $invoice->tp_phone_number = $company['data']['tp_phone_number'];
            $invoice->tp_address_province = $company['data']['tp_address_province'];
            $invoice->tp_address_commune = $company['data']['tp_address_commune'];
            $invoice->tp_address_quartier = $company['data']['tp_address_quartier'];
            $invoice->tp_address_avenue = $company['data']['tp_address_avenue'];
            $invoice->tp_address_rue = $company['data']['tp_address_rue'];
            $invoice->vat_taxpayer = $company['data']['vat_taxpayer'];
            $invoice->ct_taxpayer = $company['data']['ct_taxpayer'];
            $invoice->tl_taxpayer = $company['data']['tl_taxpayer'];
            $invoice->tp_fiscal_center = $company['data']['tp_fiscal_center'];
            $invoice->tp_activity_sector = $company['data']['tp_activity_sector'];
            $invoice->tp_legal_form = $company['data']['tp_legal_form'];
            $invoice->description = $request->description ?? '';
            $invoice->save();

            // Delete old details
            InvoiceDetail::where('invoice_number', $invoice_number)->delete();

            // Create new details
            $data1 = [];
            for($count = 0; $count < count($item_ids); $count++) {
                $item = Item::where('id', $item_ids[$count])->first();
                if (!$item) continue;

                $item_price = round((float) ($request->item_price[$count] ?? $item->selling_price ?? 0), 2);
                $quantity = $quantities[$count] ?? 0;
                
                // Tax calculations based on vat_taxpayer
                if ($is_vat_taxpayer) {
                    $vatRate = (float) ($item->vat_rate ?? $item->vat ?? 0); // %
                    $item_price_nvat = (float) $quantity * (float) $item_price;
                    $vat = $item_price_nvat * $vatRate / 100;
                    $item_total_amount = $item_price_nvat + $vat;
                    $item_price_wvat = $item_total_amount;
                } else {
                    // Not VAT taxpayer
                    $vat = 0;
                    $item_price_nvat = (float) $quantity * (float) $item_price;
                    $item_price_wvat = $item_total_amount = $item_price_nvat;
                }

                $data1[] = [
                    'invoice_number' => $invoice_number,
                    'invoice_date' => Carbon::now(),
                    'invoice_signature' => $invoice->invoice_signature,
                    'invoice_signature_date' => $invoice->invoice_signature_date,
                    'client_id' => $client_id,
                    'document_no' => $document_no,
                    'tp_type' => $company['data']['tp_type'],
                    'tp_name' => $company['data']['tp_name'],
                    'tp_TIN' => $company['data']['tp_TIN'],
                    'tp_trade_number' => $company['data']['tp_trade_number'],
                    'tp_phone_number' => $company['data']['tp_phone_number'],
                    'tp_address_province' => $company['data']['tp_address_province'],
                    'tp_address_commune' => $company['data']['tp_address_commune'],
                    'tp_address_quartier' => $company['data']['tp_address_quartier'],
                    'tp_address_avenue' => $company['data']['tp_address_avenue'],
                    'tp_address_rue' => $company['data']['tp_address_rue'],
                    'vat_taxpayer' => $company['data']['vat_taxpayer'],
                    'ct_taxpayer' => $company['data']['ct_taxpayer'],
                    'tl_taxpayer' => $company['data']['tl_taxpayer'],
                    'tp_fiscal_center' => $company['data']['tp_fiscal_center'],
                    'tp_activity_sector' => $company['data']['tp_activity_sector'],
                    'tp_legal_form' => $company['data']['tp_legal_form'],
                    'item_id' => $item_ids[$count],
                    'item_designation' => $item->name,
                    'item_quantity' => $quantity,
                    'item_price' => $item_price,
                    'item_price_nvat' => $item_price_nvat,
                    'vat' => $vat,
                    'item_price_wvat' => $item_price_wvat,
                    'item_total_amount' => $item_total_amount,
                    'auteur' => $this->user->name,
                    'etat' => $invoice->etat,
                ];
            }

            if (!empty($data1)) {
                InvoiceDetail::insert($data1);
            }

            DB::commit();
            session()->flash('success', 'La facture est mise à jour avec succès!!');
            return redirect()->route('admin.invoice.index');
        } catch (\Exception $e) {
            DB::rollback();
            session()->flash('error', __('messages.error_update') . $e->getMessage());
            return redirect()->back()->withInput();
        }
    }

    public function destroy($invoice_number)
    {
        // Suppression des documents d'achat / facturation désactivée (anti-perte de données).
        session()->flash('error', 'La suppression des documents d\'achat et de facturation est désactivée.');
        return redirect()->route('admin.invoice.index');
    }

    public function print($invoice_number)
    {
        if (is_null($this->user) || !$this->user->can('invoice.view')) {
            abort(403, 'Sorry !! You are Unauthorized to print this invoice !');
        }

        try {

        $invoice = Invoice::where('invoice_number', $invoice_number)->with(['client', 'details.item'])->firstOrFail();
        $setting = Setting::first();
        $pdf = $this->makeInvoicePdf('backend.pages.document.invoice', $invoice, $setting);

        $datas = InvoiceDetail::where('invoice_number', $invoice_number)->with('item')->get();
        $InvoiceDetail = $this->buildObrInvoiceItems($datas);

        $baseUrl = rtrim((string) config('app.guzzle_test_url'), '/');
        if ($baseUrl === '') {
            return $pdf->download('FACTURE '.$invoice_number.'.pdf');
        }

        $loginUrl = $baseUrl.'/ebms_api/login/';

        try {
            $response = Http::timeout(15)->post($loginUrl, [
                'username'=> config('app.obr_test_username'),
                'password'=> config('app.obr_test_pwd')
            ]);
        } catch (ConnectionException $e) {
            return $pdf->download('FACTURE '.$invoice_number.'.pdf');
        }

        $dataObr = $response->json() ?? [];
        $token = data_get($dataObr, 'result.token');

        if (empty($token)) {
            return $pdf->download('FACTURE '.$invoice_number.'.pdf');
        }

        $client = $invoice->client;
        $theUrl = $baseUrl.'/ebms_api/addInvoice_confirm';
        try {
            $response = Http::timeout(30)->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json'])->post($theUrl, [
            'invoice_number'=>$invoice->invoice_number,
            'invoice_date'=> $invoice->invoice_date,
            'tp_type'=>$invoice->tp_type,
            'tp_name'=>$invoice->tp_name,
            'tp_TIN'=>$invoice->tp_TIN,
            'tp_trade_number'=>$invoice->tp_trade_number,
            'tp_phone_number'=>$invoice->tp_phone_number,
            'tp_address_province'=>$invoice->tp_address_province,
            'tp_address_commune'=>$invoice->tp_address_commune,
            'tp_address_quartier'=>$invoice->tp_address_quartier,
            'tp_address_avenue'=>$invoice->tp_address_rue,
            'tp_address_rue'=>$invoice->tp_address_rue,
            'vat_taxpayer'=>$invoice->vat_taxpayer,
            'ct_taxpayer'=>$invoice->ct_taxpayer,
            'tl_taxpayer'=>$invoice->tl_taxpayer,
            'tp_fiscal_center'=>optional($setting)->tp_fiscal_center,
            'tp_activity_sector'=>$invoice->tp_activity_sector,
            'tp_legal_form'=>$invoice->tp_legal_form,
            'payment_type'=>$invoice->payment_type,
            'invoice_type'=>$invoice->invoice_type,
            'customer_name'=>optional($client)->customer_name ?? $invoice->customer_name ?? '',
            'customer_TIN'=>optional($client)->customer_TIN ?? '',
            'vat_customer_payer'=>optional($client)->vat_customer_payer ?? '0',
            'customer_address'=>optional($client)->customer_address ?? '',
            'invoice_signature'=> $invoice->invoice_signature,
            'invoice_identifier'=> $invoice->invoice_signature,
            'invoice_currency'=> $invoice->invoice_currency,
            'cancelled_invoice_ref'=> $invoice->cancelled_invoice_ref,
            'cancelled_invoice'=> $invoice->cancelled_invoice,
            'invoice_ref'=> $invoice->invoice_ref,
            'invoice_signature_date'=> $invoice->invoice_signature_date,
            'invoice_items' => $InvoiceDetail,

            ]);
        } catch (ConnectionException $e) {
            return $pdf->download('FACTURE '.$invoice_number.'.pdf');
        }

        $dataObr = $response->json() ?? [];
        $done = (bool) ($dataObr['success'] ?? false);

        if ($done) {
            $electronic_signature = $dataObr['electronic_signature'] ?? null;

            DB::beginTransaction();
            try {
                Invoice::where('invoice_number', '=', $invoice_number)
                    ->update(['statut' => 1,'electronic_signature' => $electronic_signature]);
                InvoiceDetail::where('invoice_number', '=', $invoice_number)
                    ->update(['statut' => 1,'electronic_signature' => $electronic_signature]);

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        } else {
            $msg = $dataObr['msg'] ?? 'Transmission de la facture a l\'OBR refusee.';
            session()->flash('warning', $msg);
        }

        return $pdf->download('FACTURE '.$invoice_number.'.pdf');
        
        } catch (\Throwable $e) {
            // Restore DB state if we started a transaction.
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            session()->flash('error', 'Erreur lors de l\'impression : ' . $e->getMessage());
            return redirect()->route('admin.invoice.show', $invoice_number);
        }

    }

    public function reprint($invoice_number)
    {
        if (is_null($this->user) || !$this->user->can('facture.reimprimer')) {
            abort(403, 'Sorry !! You are Unauthorized to reprint this invoice !');
        }

        $invoice = Invoice::where('invoice_number', $invoice_number)->with(['client', 'details.item'])->firstOrFail();
        $setting = Setting::first();
        $pdf = $this->makeInvoicePdf('backend.pages.document.invoice', $invoice, $setting);

        return $pdf->download('FACTURE '.$invoice_number.'.pdf');
    }

    /**
     * PDF facture en USD (conversion BIF → USD selon le taux à la date de la facture, sans transmission OBR).
     */
    public function printUsd($invoice_number)
    {
        if (is_null($this->user) || !$this->user->can('invoice.view')) {
            abort(403, 'Sorry !! You are Unauthorized to print this invoice !');
        }

        $invoice = Invoice::where('invoice_number', $invoice_number)
            ->with(['client', 'details.item'])
            ->firstOrFail();
        $setting = Setting::first();
        $pdf = $this->makeInvoicePdf('backend.pages.document.invoice_usd', $invoice, $setting);

        return $pdf->download('FACTURE_USD_'.$invoice_number.'.pdf');
    }

    public function cash($invoice_number)
    {
        if (is_null($this->user) || !$this->user->can('invoice.view')) {
            abort(403, 'Sorry !! You are Unauthorized to view any invoice !');
        }

        $invoice = Invoice::where('invoice_number', $invoice_number)
            ->with(['client', 'details.item'])
            ->firstOrFail();

        // Get invoice details for the form
        $datas = InvoiceDetail::where('invoice_number', $invoice_number)
            ->with(['item'])
            ->get();

        $setting = Setting::first();

        // Pass invoice as facture for compatibility with cash.blade.php
        $facture = $invoice;

        return view('backend.pages.invoice.cash', compact('facture', 'datas', 'setting'));
    }

    public function credit($invoice_number)
    {
        if (is_null($this->user) || !$this->user->can('invoice.view')) {
            abort(403, 'Sorry !! You are Unauthorized to view any invoice !');
        }

        $invoice = Invoice::where('invoice_number', $invoice_number)
            ->with(['client', 'details.item'])
            ->firstOrFail();

        // Get invoice details for the form
        $datas = InvoiceDetail::where('invoice_number', $invoice_number)
            ->with(['item'])
            ->get();

        $setting = Setting::first();

        // Pass invoice as facture for compatibility with credit.blade.php
        $facture = $invoice;

        return view('backend.pages.invoice.credit', compact('facture', 'datas', 'setting'));
    }

    public function validateAsCash(Request $request, $invoice_number)
    {
        if (is_null($this->user) || !$this->user->can('invoice.edit')) {
            abort(403, 'Sorry !! You are Unauthorized to validate any invoice !');
        }

        $invoice = Invoice::where('invoice_number', $invoice_number)->firstOrFail();

        $rules = array(
            'client_id' => 'required',
            'account_id' => 'required'
        );

        $error = Validator::make($request->all(), $rules);

        if($error->fails()){
            return redirect()->back()->withErrors($error)->withInput();
        }

        if ($this->abortIfAccountingPeriodLocked($invoice->date ?? $invoice->invoice_date ?? now())) {
            return redirect()->back();
        }

        try {
            DB::beginTransaction();

            // Update invoice client_id if changed
            $invoice->client_id = $request->client_id;
            if (Schema::hasColumn('invoices', 'account_id')) {
                $invoice->account_id = $request->account_id;
            }
            $invoice->etat = '1'; // Set etat to 1 for cash validation
            $invoice->validated_by = $this->user->name;
            $invoice->save();

            // Update all invoice details etat to 1
            InvoiceDetail::where('invoice_number', $invoice_number)->update([
                'etat' => '1',
                'client_id' => $request->client_id
            ]);

            // Update DeliveryNote flag1 = 1 to block editing of proforma invoice and delivery note
            if ($invoice->document_no) {
                $delivery_note = DeliveryNote::where('delivery_number', $invoice->document_no)->first();
                if ($delivery_note) {
                    $delivery_note->flag1 = '1';
                    $delivery_note->flag2 = '1';
                    $delivery_note->save();
                    
                    // Update ProformaInvoice flag1 = 1 to block editing (via document_no which contains proforma_number)
                    if ($delivery_note->document_no) {
                        ProformaInvoice::where('proforma_number', $delivery_note->document_no)->update(['flag1' => '1','flag2' => '1']);
                    }
                }
            }

            // Start accounting and stock management
            $item_total_amount = DB::table('invoice_details')
                    ->where('invoice_number', '=', $invoice_number)
                    ->sum('item_total_amount');
            $item_price_nvat = DB::table('invoice_details')
                    ->where('invoice_number', '=', $invoice_number)
                    ->sum('item_price_nvat');
            $vat = DB::table('invoice_details')
                    ->where('invoice_number', '=', $invoice_number)
                    ->sum('vat');

            // Get cash account ID from request
            $cash_account_id = $request->account_id;
            if (!$cash_account_id) {
                DB::rollback();
                session()->flash('error', 'Cash account not selected. Please select a cash account !!');
                return redirect()->back()->withInput();
            }

            // Start debit operations - Cash account (facture ne diminue pas le stock; stock et rapports au bon de livraison)
            $cashLedgerState = $this->updateLedgerTotals($cash_account_id, 'debit', $item_total_amount);

            $journalEntryDebitdata = array(
                    'date' => $invoice->invoice_date,
                    'document_no' => $invoice->invoice_number,
                    'description' => "VENTES DES PRODUITS FINIS",
                    'account_id' => $cash_account_id,
                    'libelle' => "VENTES DES PRODUITS FINIS",
                    'debit' => $item_total_amount,
                    'balance' => $cashLedgerState['balance'],
                    'created_at' => Carbon::now()
                );
                
            AccountJournalEntryDetail::insert([$journalEntryDebitdata]);
            
            // End debit operation

            // Start credit operations - Sales accounts (depends on item store / service)
            $activeSetting = AccountSetting::where('active', 1)->first();
            if (!$activeSetting) {
                DB::rollback();
                session()->flash('error', 'Account setting not found (active=1). Please configure account settings first !!');
                return redirect()->back()->withInput();
            }

            $invoiceDetails = InvoiceDetail::where('invoice_number', $invoice_number)->get();
            $salesByAccount = []; // account_id => amount
            $salesLabelByAccount = []; // account_id => label

            foreach ($invoiceDetails as $d) {
                $lineAmount = (float) ($d->item_price_nvat ?? 0);
                if ($lineAmount <= 0) continue;

                $itemId = (int) ($d->item_id ?? 0);
                if (!$itemId) continue;

                $type = $this->resolveSalesTypeForItem($itemId);
                if ($type === 'FG_SM') {
                    $acc = (int) ($activeSetting->f_sm_selling_account_id ?? 0);
                    $label = "VENTES PRODUITS FINIS (SM)";
                } elseif ($type === 'FG_MAIN') {
                    $acc = (int) ($activeSetting->f_selling_account_id ?? 0);
                    $label = "VENTES PRODUITS FINIS (MAIN)";
                } elseif ($type === 'GOODS') {
                    $acc = (int) ($activeSetting->goods_selling_account_id ?? 0);
                    $label = "VENTES MARCHANDISES";
                } else { // SERVICE
                    $acc = (int) ($activeSetting->service_selling_account_id ?? 0);
                    $label = "VENTES SERVICES";
                }

                if (!$acc) {
                    DB::rollback();
                    session()->flash('error', "Sales account not configured for type {$type}. Please configure account settings first !!");
                    return redirect()->back()->withInput();
                }

                $salesByAccount[$acc] = ($salesByAccount[$acc] ?? 0) + $lineAmount;
                $salesLabelByAccount[$acc] = $label;
            }

            foreach ($salesByAccount as $accId => $amount) {
                if ($amount <= 0) continue;
                $sellingLedgerState = $this->updateLedgerTotals((int)$accId, 'credit', (float)$amount);

                AccountJournalEntryDetail::insert([[
                    'date' => $invoice->invoice_date,
                    'document_no' => $invoice->invoice_number,
                    'description' => $salesLabelByAccount[$accId] ?? 'VENTES',
                    'account_id' => (int)$accId,
                    'libelle' => $salesLabelByAccount[$accId] ?? 'VENTES',
                    'credit' => (float)$amount,
                    'balance' => $sellingLedgerState['balance'],
                    'created_at' => Carbon::now()
                ]]);
            }

            // VAT collected account
            $vat_collec_account_id = AccountSetting::where('active', 1)->value('vat_collec_account_id');
            if ($vat_collec_account_id && $vat > 0) {
                $vatLedgerState = $this->updateLedgerTotals($vat_collec_account_id, 'credit', $vat);
                
                $journalEntryCreditdataAcc2 = array(
                        'date' => $invoice->invoice_date,
                        'document_no' => $invoice->invoice_number,
                        'description' => "TVA COLLECTEE",
                        'account_id' => $vat_collec_account_id,
                        'libelle' => "TVA COLLECTEE",
                        'credit' => $vat,
                        'balance' => $vatLedgerState['balance'],
                        'created_at' => Carbon::now()
                    );

                AccountJournalEntryDetail::insert([$journalEntryCreditdataAcc2]);
            }

            // End credit operations (pas de sortie stock ni rapports sur la facture; faits au bon de livraison)

            // Check accounting balance before commit
            $this->assertBalancedJournalForDocument($invoice->invoice_number);

            DB::commit();
            session()->flash('success', 'La facture est validée en cash avec succès!!');
            return redirect()->route('admin.invoice.show', $invoice_number);
        } catch (\Exception $e) {
            DB::rollback();
            session()->flash('error', __('messages.error_validation') . $e->getMessage());
            return redirect()->back()->withInput();
        }
    }

    public function validateAsCredit(Request $request, $invoice_number)
    {
        if (is_null($this->user) || !$this->user->can('invoice.edit')) {
            abort(403, 'Sorry !! You are Unauthorized to validate any invoice !');
        }

        $invoice = Invoice::with('client')->where('invoice_number', $invoice_number)->firstOrFail();

        $rules = array(
            'client_id' => 'required'
        );

        $error = Validator::make($request->all(), $rules);

        if($error->fails()){
            return redirect()->back()->withErrors($error)->withInput();
        }

        if ($this->abortIfAccountingPeriodLocked($invoice->date ?? $invoice->invoice_date ?? now())) {
            return redirect()->back();
        }

        try {
            DB::beginTransaction();

            // Update invoice client_id if changed
            $invoice->client_id = $request->client_id;
            $invoice->etat = '2'; // Set etat to 2 for credit validation
            $invoice->validated_by = $this->user->name;
            $invoice->save();

            // Update all invoice details etat to 2
            InvoiceDetail::where('invoice_number', $invoice_number)->update([
                'etat' => '2',
                'client_id' => $request->client_id
            ]);

            // Update DeliveryNote flag1 = 1 to block editing of proforma invoice and delivery note
            if ($invoice->document_no) {
                $delivery_note = DeliveryNote::where('delivery_number', $invoice->document_no)->first();
                if ($delivery_note) {
                    $delivery_note->flag1 = '1';
                    $delivery_note->flag2 = '1';
                    $delivery_note->save();
                    
                    // Update ProformaInvoice flag1 = 1 to block editing (via document_no which contains proforma_number)
                    if ($delivery_note->document_no) {
                        ProformaInvoice::where('proforma_number', $delivery_note->document_no)->update(['flag1' => '1','flag2' => '1']);
                    }
                }
            }

            // Start accounting and stock management
            $item_total_amount = DB::table('invoice_details')
                    ->where('invoice_number', '=', $invoice_number)
                    ->sum('item_total_amount');
            $item_price_nvat = DB::table('invoice_details')
                    ->where('invoice_number', '=', $invoice_number)
                    ->sum('item_price_nvat');
            $vat = DB::table('invoice_details')
                    ->where('invoice_number', '=', $invoice_number)
                    ->sum('vat');

            // Start debit operations - Client account (receivable) (pas de sortie stock; faite au bon de livraison)
            if (!$invoice->client) {
                DB::rollback();
                session()->flash('error', 'Client not found for this invoice. Please select a valid client !!');
                return redirect()->back()->withInput();
            }
            
            if (!$invoice->client->account_id) {
                DB::rollback();
                session()->flash('error', 'Client account not configured. Please configure the client account first !!');
                return redirect()->back()->withInput();
            }
            
            $clientLedgerState = $this->updateLedgerTotals($invoice->client->account_id, 'debit', $item_total_amount);

                $journalEntryDebitdataClient = array(
                    'date' => $invoice->invoice_date,
                    'document_no' => $invoice->invoice_number,
                    'description' => "CREANCE CONSOMMATION",
                    'account_id' => $invoice->client->account_id,
                    'libelle' => "CREANCE CONSOMMATION",
                    'debit' => $item_total_amount,
                    'balance' => $clientLedgerState['balance'],
                    'created_at' => Carbon::now()
                );
                
            AccountJournalEntryDetail::insert([$journalEntryDebitdataClient]);

            // Start credit operations - Sales accounts (depends on item store / service)
            $activeSetting = AccountSetting::where('active', 1)->first();
            if (!$activeSetting) {
                DB::rollback();
                session()->flash('error', 'Account setting not found (active=1). Please configure account settings first !!');
                return redirect()->back()->withInput();
            }

            $invoiceDetails = InvoiceDetail::where('invoice_number', $invoice_number)->get();
            $salesByAccount = []; // account_id => amount
            $salesLabelByAccount = []; // account_id => label

            foreach ($invoiceDetails as $d) {
                $lineAmount = (float) ($d->item_price_nvat ?? 0);
                if ($lineAmount <= 0) continue;

                $itemId = (int) ($d->item_id ?? 0);
                if (!$itemId) continue;

                $type = $this->resolveSalesTypeForItem($itemId);
                if ($type === 'FG_SM') {
                    $acc = (int) ($activeSetting->f_sm_selling_account_id ?? 0);
                    $label = "VENTES PRODUITS FINIS (SM)";
                } elseif ($type === 'FG_MAIN') {
                    $acc = (int) ($activeSetting->f_selling_account_id ?? 0);
                    $label = "VENTES PRODUITS FINIS (MAIN)";
                } elseif ($type === 'GOODS') {
                    $acc = (int) ($activeSetting->goods_selling_account_id ?? 0);
                    $label = "VENTES MARCHANDISES";
                } else { // SERVICE
                    $acc = (int) ($activeSetting->service_selling_account_id ?? 0);
                    $label = "VENTES SERVICES";
                }

                if (!$acc) {
                    DB::rollback();
                    session()->flash('error', "Sales account not configured for type {$type}. Please configure account settings first !!");
                    return redirect()->back()->withInput();
                }

                $salesByAccount[$acc] = ($salesByAccount[$acc] ?? 0) + $lineAmount;
                $salesLabelByAccount[$acc] = $label;
            }

            foreach ($salesByAccount as $accId => $amount) {
                if ($amount <= 0) continue;
                $sellingLedgerState = $this->updateLedgerTotals((int)$accId, 'credit', (float)$amount);

                AccountJournalEntryDetail::insert([[
                    'date' => $invoice->invoice_date,
                    'document_no' => $invoice->invoice_number,
                    'description' => $salesLabelByAccount[$accId] ?? 'VENTES',
                    'account_id' => (int)$accId,
                    'libelle' => $salesLabelByAccount[$accId] ?? 'VENTES',
                    'credit' => (float)$amount,
                    'balance' => $sellingLedgerState['balance'],
                    'created_at' => Carbon::now()
                ]]);
            }

            // VAT collected account
            $vat_collec_account_id = AccountSetting::where('active', 1)->value('vat_collec_account_id');
            if ($vat_collec_account_id && $vat > 0) {
                $vatLedgerState = $this->updateLedgerTotals($vat_collec_account_id, 'credit', $vat);
                
                $journalEntryCreditdataAcc2 = array(
                    'date' => $invoice->invoice_date,
                    'document_no' => $invoice->invoice_number,
                    'description' => "TVA COLLECTEE",
                    'account_id' => $vat_collec_account_id,
                    'libelle' => "TVA COLLECTEE",
                    'credit' => $vat,
                    'balance' => $vatLedgerState['balance'],
                    'created_at' => Carbon::now()
                );

                AccountJournalEntryDetail::insert([$journalEntryCreditdataAcc2]);
            }

            // Pas de sortie stock ni rapports sur la facture; faits au bon de livraison

            // Check accounting balance before commit
            $this->assertBalancedJournalForDocument($invoice->invoice_number);

            DB::commit();
            session()->flash('success', 'La facture est validée en crédit avec succès!!');
            return redirect()->route('admin.invoice.show', $invoice_number);
        } catch (\Exception $e) {
            DB::rollback();
            session()->flash('error', __('messages.error_validation') . $e->getMessage());
            return redirect()->back()->withInput();
        }
    }

    public function listCreditReceivables(Request $request)
    {
        if (is_null($this->user) || !$this->user->can('recouvrement.view')) {
            abort(403, 'Sorry !! You are Unauthorized to view credit receivables !');
        }

        $query = Invoice::with(['client', 'details'])
            ->where('etat', '2')
            ->orderBy('id', 'desc');

        if ($request->filled('date_debut') && $request->filled('date_fin')) {
            $query->whereBetween('invoice_date', [
                Carbon::parse($request->date_debut)->startOfDay(),
                Carbon::parse($request->date_fin)->endOfDay(),
            ]);
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        $query->take(100);
        $factures = $query->get();
        $clients = EGRClient::orderBy('customer_name')->get();

        return view('backend.pages.invoice_all.credit', compact('factures', 'clients'));
    }

    public function listRecoveredCredits(Request $request)
    {
        return redirect()->route('admin.credits.receivables.index');
    }

    public function exportRecoveryReportExcel(Request $request)
    {
        if (is_null($this->user) || !$this->user->can('recouvrement.view')) {
            abort(403, 'Sorry !! You are Unauthorized to export recovery reports !');
        }

        $invoices = $this->buildCreditRecoveryQuery($request)->get();
        if ($invoices->isEmpty()) {
            return back()->with('error', 'Aucune facture de recouvrement trouvée pour la période sélectionnée.');
        }

        $rows = [];
        $i = 1;
        foreach ($invoices as $invoice) {
            $amounts = $this->computeCreditInvoiceAmounts($invoice);
            $rows[] = [
                $i++,
                $invoice->invoice_number,
                $invoice->invoice_date ? Carbon::parse($invoice->invoice_date)->format('d/m/Y') : '',
                $invoice->client ? $invoice->client->customer_name : ($invoice->customer_name ?? ''),
                $amounts['total'],
                $amounts['paid'],
                $amounts['remaining'],
                $amounts['status'],
                $invoice->nom_recouvrement ?? '',
            ];
        }

        $headings = ['#', 'Numero Facture', 'Date', 'Client', 'Montant Credit', 'Montant Recouvre', 'Reste a payer', 'Statut', 'Charge de recouvrement'];

        return Excel::download(
            new \App\Exports\GenericIndexExport($rows, $headings),
            'rapport_recouvrement_' . now()->format('Ymd_His') . '.xlsx'
        );
    }

    public function exportRecoveryReportPdf(Request $request)
    {
        if (is_null($this->user) || !$this->user->can('recouvrement.view')) {
            abort(403, 'Sorry !! You are Unauthorized to export recovery reports !');
        }

        $invoices = $this->buildCreditRecoveryQuery($request)->get();
        if ($invoices->isEmpty()) {
            return back()->with('error', 'Aucune facture de recouvrement trouvée pour la période sélectionnée.');
        }

        $rows = [];
        $totalCredit = 0;
        $totalPaid = 0;
        $totalRemaining = 0;

        foreach ($invoices as $invoice) {
            $amounts = $this->computeCreditInvoiceAmounts($invoice);
            $rows[] = [
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => $invoice->invoice_date ? Carbon::parse($invoice->invoice_date)->format('d/m/Y') : '',
                'client_name' => $invoice->client ? $invoice->client->customer_name : ($invoice->customer_name ?? ''),
                'total' => $amounts['total'],
                'paid' => $amounts['paid'],
                'remaining' => $amounts['remaining'],
                'status' => $amounts['status'],
                'collector' => $invoice->nom_recouvrement ?? '',
            ];
            $totalCredit += $amounts['total'];
            $totalPaid += $amounts['paid'];
            $totalRemaining += $amounts['remaining'];
        }

        $setting = Setting::first();
        $pdf = PDF::loadView('backend.pages.invoice_all.documents.recovery-report', [
            'rows' => $rows,
            'setting' => $setting,
            'periodStart' => $request->date_debut,
            'periodEnd' => $request->date_fin,
            'totalCredit' => $totalCredit,
            'totalPaid' => $totalPaid,
            'totalRemaining' => $totalRemaining,
        ]);

        return $pdf->download('rapport_recouvrement_' . now()->format('Ymd_His') . '.pdf');
    }

    public function exportClientGlobalInvoiceExcel(Request $request)
    {
        if (is_null($this->user) || !$this->user->can('recouvrement.view')) {
            abort(403, 'Sorry !! You are Unauthorized to export client credit reports !');
        }

        $request->validate([
            'client_id' => 'required',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date',
        ]);

        $client = EGRClient::findOrFail($request->client_id);
        $invoices = $this->buildCreditRecoveryQuery($request)->where('client_id', $request->client_id)->get();

        if ($invoices->isEmpty()) {
            return back()->with('error', 'Aucune facture de crédit trouvée pour ce client sur la période sélectionnée.');
        }

        $rows = [];
        $i = 1;
        foreach ($invoices as $invoice) {
            $amounts = $this->computeCreditInvoiceAmounts($invoice);
            $rows[] = [
                $i++,
                $invoice->invoice_number,
                $invoice->invoice_date ? Carbon::parse($invoice->invoice_date)->format('d/m/Y') : '',
                $amounts['total'],
                $amounts['paid'],
                $amounts['remaining'],
                $amounts['status'],
                $invoice->invoice_signature ?? '',
            ];
        }

        $headings = ['#', 'Numero Facture', 'Date', 'Montant Credit', 'Montant Recouvre', 'Reste a payer', 'Statut', 'Signature'];
        $filename = 'facture_globale_' . preg_replace('/[^A-Za-z0-9_]/', '_', $client->customer_name ?? 'client') . '_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new \App\Exports\GenericIndexExport($rows, $headings), $filename);
    }

    public function exportClientGlobalInvoicePdf(Request $request)
    {
        if (is_null($this->user) || !$this->user->can('recouvrement.view')) {
            abort(403, 'Sorry !! You are Unauthorized to export client credit reports !');
        }

        $request->validate([
            'client_id' => 'required',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date',
        ]);

        $client = EGRClient::findOrFail($request->client_id);
        $invoices = $this->buildCreditRecoveryQuery($request)->where('client_id', $request->client_id)->get();

        if ($invoices->isEmpty()) {
            return back()->with('error', 'Aucune facture de crédit trouvée pour ce client sur la période sélectionnée.');
        }

        $rows = [];
        $totalCredit = 0;
        $totalPaid = 0;
        $totalRemaining = 0;

        foreach ($invoices as $invoice) {
            $amounts = $this->computeCreditInvoiceAmounts($invoice);
            $rows[] = [
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => $invoice->invoice_date ? Carbon::parse($invoice->invoice_date)->format('d/m/Y') : '',
                'total' => $amounts['total'],
                'paid' => $amounts['paid'],
                'remaining' => $amounts['remaining'],
                'status' => $amounts['status'],
                'signature' => $invoice->invoice_signature ?? '',
            ];
            $totalCredit += $amounts['total'];
            $totalPaid += $amounts['paid'];
            $totalRemaining += $amounts['remaining'];
        }

        $setting = Setting::first();
        $pdf = PDF::loadView('backend.pages.invoice_all.documents.client-global-credit-report', [
            'rows' => $rows,
            'client' => $client,
            'setting' => $setting,
            'periodStart' => $request->date_debut,
            'periodEnd' => $request->date_fin,
            'totalCredit' => $totalCredit,
            'totalPaid' => $totalPaid,
            'totalRemaining' => $totalRemaining,
        ]);

        $fileName = 'facture_globale_' . preg_replace('/[^A-Za-z0-9_]/', '_', $client->customer_name ?? 'client') . '_' . now()->format('Ymd_His') . '.pdf';

        return $pdf->download($fileName);
    }

    public function showCreditPaymentForm($invoice_number)
    {
        if (is_null($this->user) || !$this->user->can('recouvrement.create')) {
            abort(403, 'Sorry !! You are Unauthorized to pay any credit invoice !');
        }

        $facture = Invoice::with(['client'])->where('invoice_number', $invoice_number)->firstOrFail();
        $datas = InvoiceDetail::where('invoice_number', $invoice_number)->get();
        $clients = EGRClient::orderBy('customer_name')->get();

        $totalAmount = (float) ($facture->montant_total_credit ?? 0);
        if ($totalAmount <= 0) {
            $totalAmount = (float) InvoiceDetail::where('invoice_number', $invoice_number)->sum('item_total_amount');
        }

        $montantRecouvre = (float) ($facture->montant_recouvre ?? 0);
        $resteCredit = $facture->reste_credit !== null && $facture->reste_credit !== ''
            ? (float) $facture->reste_credit
            : max(0, $totalAmount - $montantRecouvre);
        $existingNotes = trim((string) ($facture->note_recouvrement ?? ''));
        $nextTrancheNumber = substr_count($existingNotes, '[Tranche ') + 1;

        return view('backend.pages.invoice_all.payer-credit', [
            'facture' => $facture,
            'datas' => $datas,
            'clients' => $clients,
            'total_amount' => $totalAmount,
            'reste_credit' => $resteCredit,
            'next_tranche_number' => $nextTrancheNumber,
        ]);
    }

    public function payCredit(Request $request, $invoice_number)
    {
        if (is_null($this->user) || !$this->user->can('recouvrement.create')) {
            abort(403, 'Sorry !! You are Unauthorized to validate any invoice !');
        }

        $request->validate([
            'client_id' => 'required',
            'statut_paied' => 'required',
            'account_id' => 'required',
            'etat_recouvrement' => 'required',
            'date_recouvrement' => 'required',
            'nom_recouvrement' => 'required',
            'note_recouvrement' => 'required',
            'montant_total_credit' => 'required|numeric|min:0',
            'montant_recouvre' => 'required|numeric|min:0',
        ]);

        if ($this->abortIfAccountingPeriodLocked($request->date_recouvrement)) {
            return redirect()->back();
        }

        try {
            DB::beginTransaction();

            $facture = Invoice::with('client')
                ->where('invoice_number', $invoice_number)
                ->where('etat', '2')
                ->first();

            if (!$facture) {
                DB::rollBack();
                session()->flash('error', 'Facture de crédit introuvable ou non validée.');
                return back();
            }

            $client = EGRClient::find($request->client_id);
            if (!$client) {
                DB::rollBack();
                session()->flash('error', 'Le client sélectionné est introuvable.');
                return back();
            }

            if (!$client->account_id) {
                DB::rollBack();
                session()->flash('error', 'Le client n\'a pas de compte comptable associé.');
                return back();
            }

            $account = AccountAccount::find($request->account_id);
            if (!$account) {
                DB::rollBack();
                session()->flash('error', 'Le compte sélectionné est introuvable.');
                return back();
            }

            $montantTotalCredit = (float) $request->montant_total_credit;
            if ((float) ($facture->montant_total_credit ?? 0) > 0) {
                $montantTotalCredit = (float) $facture->montant_total_credit;
            }
            if ($montantTotalCredit <= 0) {
                $montantTotalCredit = (float) InvoiceDetail::where('invoice_number', $invoice_number)->sum('item_total_amount');
            }

            $montantPaiementCourant = (float) $request->montant_recouvre;
            $montantRecouvreExistant = (float) ($facture->montant_recouvre ?? 0);
            $resteCreditActuel = $facture->reste_credit !== null && $facture->reste_credit !== ''
                ? (float) $facture->reste_credit
                : max(0, $montantTotalCredit - $montantRecouvreExistant);

            if ($montantPaiementCourant <= 0) {
                DB::rollBack();
                session()->flash('error', 'Le montant de ce paiement doit être supérieur à zéro.');
                return back();
            }

            if ($montantPaiementCourant > $resteCreditActuel) {
                DB::rollBack();
                session()->flash('error', 'Le montant de ce paiement ne peut pas dépasser le reste à payer.');
                return back();
            }

            $etatRecouvrement = (int) $request->etat_recouvrement;

            if ($etatRecouvrement === 2 && abs($montantPaiementCourant - $resteCreditActuel) > 0.01) {
                DB::rollBack();
                session()->flash('error', 'Pour un paiement total, le montant doit être égal au reste à payer.');
                return back();
            }

            if ($etatRecouvrement === 1 && $montantPaiementCourant >= $resteCreditActuel) {
                DB::rollBack();
                session()->flash('error', 'Pour un paiement partiel, le montant doit être inférieur au reste à payer.');
                return back();
            }

            $montantTotalRecouvre = $montantRecouvreExistant + $montantPaiementCourant;
            $resteCredit = max(0, $montantTotalCredit - $montantTotalRecouvre);
            $etatRecouvrementFinal = $resteCredit <= 0 ? '2' : '1';
            $modePaiementLabel = (int) $request->statut_paied === 2 ? 'Banque' : 'CASH';
            $accountLabel = trim(($account->account_number ?? '') . ' - ' . ($account->account_name ?? ''));

            $noteRecouvrement = trim((string) $request->note_recouvrement);
            $existingNotes = trim((string) ($facture->note_recouvrement ?? ''));
            $numeroTranche = substr_count($existingNotes, '[Tranche ') + 1;
            $paymentNote = '[Tranche ' . $numeroTranche . ' - ' . $request->date_recouvrement . '] '
                . $noteRecouvrement
                . ' | Mode: ' . $modePaiementLabel
                . ' | Compte: ' . $accountLabel
                . ' | Réf: ' . ($request->cheque_no ?: '-')
                . ' | Montant: ' . number_format($montantPaiementCourant, 0, ',', ' ') . ' BIF'
                . ' | Reste: ' . number_format($resteCredit, 0, ',', ' ') . ' BIF';

            $noteRecouvrementFinale = $existingNotes !== ''
                ? $existingNotes . "\n\n" . $paymentNote
                : $paymentNote;

            Invoice::where('invoice_number', $invoice_number)->update([
                'client_id' => $request->client_id,
                'statut_paied' => $request->statut_paied,
                'etat_recouvrement' => $etatRecouvrementFinal,
                'date_recouvrement' => $request->date_recouvrement,
                'nom_recouvrement' => $request->nom_recouvrement,
                'note_recouvrement' => $noteRecouvrementFinale,
                'bank_name' => $account->account_name,
                'cheque_no' => $request->cheque_no,
                'montant_total_credit' => $montantTotalCredit,
                'montant_recouvre' => $montantTotalRecouvre,
                'reste_credit' => $resteCredit,
                'confirmed_by' => $this->user->name,
            ]);

            InvoiceDetail::where('invoice_number', $invoice_number)->update([
                'statut_paied' => $request->statut_paied,
                'etat_recouvrement' => $etatRecouvrementFinal,
                'date_recouvrement' => $request->date_recouvrement,
                'nom_recouvrement' => $request->nom_recouvrement,
                'note_recouvrement' => $noteRecouvrementFinale,
                'bank_name' => $account->account_name,
                'cheque_no' => $request->cheque_no,
                'montant_total_credit' => $montantTotalCredit,
                'montant_recouvre' => $montantTotalRecouvre,
                'reste_credit' => $resteCredit,
                'confirmed_by' => $this->user->name,
            ]);

            $accountLedgerState = $this->updateLedgerTotals((int) $request->account_id, 'debit', $montantPaiementCourant);
            $journalDescription = 'RECOUVREMENT CREDIT - ' . $noteRecouvrement;

            AccountJournalEntryDetail::insert([[
                'date' => $facture->invoice_date,
                'document_no' => $facture->invoice_number,
                'description' => $journalDescription,
                'account_id' => (int) $request->account_id,
                'libelle' => $journalDescription,
                'debit' => $montantPaiementCourant,
                'balance' => $accountLedgerState['balance'],
                'created_at' => Carbon::now(),
            ]]);

            $clientLedgerState = $this->updateLedgerTotals((int) $client->account_id, 'credit', $montantPaiementCourant);
            AccountJournalEntryDetail::insert([[
                'date' => $facture->invoice_date,
                'document_no' => $facture->invoice_number,
                'description' => $journalDescription,
                'account_id' => (int) $client->account_id,
                'libelle' => $journalDescription,
                'credit' => $montantPaiementCourant,
                'balance' => $clientLedgerState['balance'],
                'created_at' => Carbon::now(),
            ]]);

            DB::commit();
            session()->flash('success', 'Paiement enregistré avec succès (tranche ' . $numeroTranche . '). Reste à payer: ' . number_format($resteCredit, 0, ',', ' ') . ' BIF.');

            return redirect()->route('admin.credits.receivables.index');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Erreur lors du recouvrement de crédit: ' . $e->getMessage(), [
                'invoice_number' => $invoice_number,
                'user' => $this->user->name ?? 'Unknown',
                'trace' => $e->getTraceAsString(),
            ]);

            session()->flash('error', 'Une erreur est survenue lors du recouvrement: ' . $e->getMessage());
            return back()->withInput();
        }
    }

    // Old delivery note update method - should be removed or kept for backward compatibility
    public function updateDeliveryNote(Request $request, $delivery_number)
    {
        if (is_null($this->user) || !$this->user->can('delivery_note.edit')) {
            abort(403, 'Sorry !! You are Unauthorized to edit any delivery note !');
        }

        $delivery_note = DeliveryNote::where('delivery_number', $delivery_number)->firstOrFail();

        $rules = array(
            'client_id' => 'required',
            'item_id.*' => 'required',
            'item_quantity.*' => 'required|numeric|min:1'
        );

        $error = Validator::make($request->all(), $rules);

        if($error->fails()){
            return redirect()->back()->withErrors($error)->withInput();
        }

        try {
            DB::beginTransaction();

            $item_ids = $request->item_id ?? [];
            $quantities = $request->item_quantity ?? [];
            $client_id = $request->client_id;
            $document_no = $request->document_no ?? $delivery_note->document_no;

            if (empty($item_ids) || empty($quantities)) {
                return redirect()->back()->withErrors(['error' => 'Veuillez ajouter au moins un article.'])->withInput();
            }

            // Update main delivery note
            $delivery_note->client_id = $client_id;
            $delivery_note->document_no = $document_no;
            $delivery_note->description = $request->description ?? '';
            $delivery_note->save();

            // Delete old details
            DeliveryNoteDetail::where('delivery_number', $delivery_number)->delete();

            // Create new details
            $data1 = [];
            for($count = 0; $count < count($item_ids); $count++) {
                $item = Item::where('id', $item_ids[$count])->first();
                if (!$item) continue;

                $item_price = $request->item_price[$count] ?? $item->selling_price ?? 0;
                $quantity = $quantities[$count] ?? 0;
                $item_total = $item_price * $quantity;

                $data1[] = [
                    'delivery_number' => $delivery_number,
                    'delivery_date' => $delivery_note->delivery_date,
                    'delivery_signature' => $delivery_note->delivery_signature,
                    'delivery_signature_date' => $delivery_note->delivery_signature_date,
                    'client_id' => $client_id,
                    'document_no' => $document_no, // Save proforma_number value in document_no
                    'item_id' => $item_ids[$count],
                    'item_designation' => $item->name,
                    'item_quantity' => $quantity,
                    'item_price' => $item_price,
                    'item_total_amount' => $item_total,
                    'auteur' => $this->user->name,
                    'etat' => $delivery_note->etat,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];
            }

            if (!empty($data1)) {
                DeliveryNoteDetail::insert($data1);
            }

            DB::commit();
            session()->flash('success', 'Le bon de livraison est mis à jour avec succès!!');
            return redirect()->route('admin.delivery-note.index');
        } catch (\Exception $e) {
            DB::rollback();
            session()->flash('error', __('messages.error_update') . $e->getMessage());
            return redirect()->back()->withInput();
        }
    }

    public function validateInvoice($delivery_number)
    {
        if (is_null($this->user) || !$this->user->can('delivery_note.validate')) {
            abort(403, 'Sorry !! You are Unauthorized to validate any delivery note !');
        }

        try {
            DB::beginTransaction();

            $delivery_note = DeliveryNote::where('delivery_number', $delivery_number)->firstOrFail();
            
            if ($delivery_note->etat == '0') {
                $delivery_note->etat = '1';
                $delivery_note->validated_by = $this->user->name;
                $delivery_note->save();

                // Update details
                DeliveryNoteDetail::where('delivery_number', $delivery_number)->update(['etat' => '1']);

                DB::commit();
                session()->flash('success', 'Le bon de livraison est validé avec succès!!');
            } else {
                session()->flash('error', 'Ce bon de livraison ne peut pas être validé dans son état actuel.');
            }
        } catch (\Exception $e) {
            DB::rollback();
            session()->flash('error', __('messages.error_validation') . $e->getMessage());
        }

        return redirect()->back();
    }

    public function confirm($delivery_number)
    {
        if (is_null($this->user) || !$this->user->can('delivery_note.confirm')) {
            abort(403, 'Sorry !! You are Unauthorized to confirm any delivery note !');
        }

        try {
            DB::beginTransaction();

            $delivery_note = DeliveryNote::where('delivery_number', $delivery_number)->firstOrFail();
            
            if ($delivery_note->etat == '1') {
                $delivery_note->etat = '2';
                $delivery_note->confirmed_by = $this->user->name;
                $delivery_note->save();

                // Update details
                DeliveryNoteDetail::where('delivery_number', $delivery_number)->update(['etat' => '2']);

                DB::commit();
                session()->flash('success', 'Le bon de livraison est confirmé avec succès!!');
            } else {
                session()->flash('error', 'Ce bon de livraison doit être validé (etat=1) avant d\'être confirmé.');
            }
        } catch (\Exception $e) {
            DB::rollback();
            session()->flash('error', __('messages.error_confirmation') . $e->getMessage());
        }

        return redirect()->back();
    }

    public function approve($delivery_number)
    {
        if (is_null($this->user) || !$this->user->can('delivery_note.approuve')) {
            abort(403, 'Sorry !! You are Unauthorized to approve any delivery note !');
        }

        try {
            DB::beginTransaction();

            $delivery_note = DeliveryNote::where('delivery_number', $delivery_number)->firstOrFail();
            
            if (in_array($delivery_note->etat, ['1', '2', '3'])) {
                $delivery_note->etat = '3';
                $delivery_note->approuved_by = $this->user->name;
                $delivery_note->save();

                // Update details
                DeliveryNoteDetail::where('delivery_number', $delivery_number)->update(['etat' => '3']);

                DB::commit();
                session()->flash('success', 'Le bon de livraison est approuvé avec succès!!');
            } else {
                session()->flash('error', 'Ce bon de livraison ne peut pas être approuvé dans son état actuel.');
            }
        } catch (\Exception $e) {
            DB::rollback();
            session()->flash('error', __('messages.error_approval') . $e->getMessage());
        }

        return redirect()->back();
    }


    public function cancelInvoice(Request $request,$invoice_number)
    {
        if (is_null($this->user) || !$this->user->can('invoice.reset')) {
            abort(403, 'Sorry !! You are Unauthorized to reset any invoice !');
        }

        $invoice = Invoice::where('invoice_number', $invoice_number)->firstOrFail();

        // Only allow cancellation while the invoice is in draft mode
        if ((string) $invoice->etat !== '0') {
            session()->flash('error', 'Cette facture ne peut pas etre annulee (etat doit etre 0).');
            return back();
        }

        $request->validate([
            'cn_motif' => 'required|min:10|max:500'
        ]);

        $invoice_signature = $invoice->invoice_signature;
        if (empty($invoice_signature)) {
            session()->flash('error', 'Signature de la facture introuvable (OBR).');
            return back();
        }

        try {DB::beginTransaction();

        $cn_motif = $request->cn_motif;
        
            $theUrl = config('app.guzzle_test_url').'/ebms_api/login/';
                $response = Http::post($theUrl, [
                    'username'=> config('app.obr_test_username'),
                    'password'=> config('app.obr_test_pwd')

                ]);

            $loginJson = $response->json() ?? [];
            $token = data_get($loginJson, 'result.token');

            if (empty($token)) {
                throw new \RuntimeException('Connexion OBR impossible (token manquant).');
            }

            $theUrl = config('app.guzzle_test_url').'/ebms_api/cancelInvoice';      

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/json'])->post($theUrl, [
                'invoice_signature'=>$invoice_signature,
                'cn_motif'=>$cn_motif 
            ]); 

            $cancelJson = $response->json() ?? [];
            $done = (bool) ($cancelJson['success'] ?? false);
            $msg = $cancelJson['msg'] ?? null;


        if ($done == true) {

            Invoice::where('invoice_number', '=', $invoice_number)
                ->update(['etat' => -1,'statut' => -1,'cn_motif' => $cn_motif,'reseted_by' => $this->user->name]);
            InvoiceDetail::where('invoice_number', '=', $invoice_number)
                ->update(['etat' => -1,'statut' => -1,'cn_motif' => $cn_motif,'reseted_by' => $this->user->name]);
            
            DB::commit();
            
            session()->flash('success', 'La Facture  est annulée');
            return back();

            }else{
                DB::rollBack();
                session()->flash('error', $msg ?: 'Transmission de l\'annulation a l\'OBR refusee.');
                return back();
            } 

        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            session()->flash('error', 'Erreur lors de l\'annulation : ' . $e->getMessage());
            return back();
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

    protected function buildCreditRecoveryQuery(Request $request)
    {
        $query = Invoice::with(['client', 'details'])
            ->where('etat', '2')
            ->orderBy('invoice_date', 'asc')
            ->orderBy('id', 'asc');

        if ($request->filled('date_debut') && $request->filled('date_fin')) {
            $query->whereBetween('invoice_date', [
                Carbon::parse($request->date_debut)->startOfDay(),
                Carbon::parse($request->date_fin)->endOfDay(),
            ]);
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        return $query;
    }

    protected function computeCreditInvoiceAmounts(Invoice $invoice): array
    {
        $total = (float) ($invoice->montant_total_credit ?? 0);
        if ($total <= 0) {
            $total = (float) $invoice->details->sum('item_total_amount');
        }

        $paid = (float) ($invoice->montant_recouvre ?? 0);
        $remaining = $invoice->reste_credit !== null && $invoice->reste_credit !== ''
            ? (float) $invoice->reste_credit
            : max(0, $total - $paid);

        if ((string) $invoice->etat_recouvrement === '2' || $remaining <= 0) {
            $status = 'Paiement total';
        } elseif ((string) $invoice->etat_recouvrement === '1' || $paid > 0) {
            $status = 'Paiement partiel';
        } else {
            $status = 'Encours';
        }

        return [
            'total' => $total,
            'paid' => $paid,
            'remaining' => $remaining,
            'status' => $status,
        ];
    }

    /**
     * Determine sales type based on where the item is stored.
     * Priority:
     * - FinishedGoodsSmStoreDetail -> FG_SM
     * - FinishedGoodsStoreDetail -> FG_MAIN
     * - GoodsStoreDetail -> GOODS
     * - else -> SERVICE (non-stockable)
     */
    protected function resolveSalesTypeForItem(int $itemId): string
    {
        if (FinishedGoodsSmStoreDetail::where('item_id', $itemId)->exists()) {
            return 'FG_SM';
        }
        if (FinishedGoodsStoreDetail::where('item_id', $itemId)->exists()) {
            return 'FG_MAIN';
        }
        if (GoodsStoreDetail::where('item_id', $itemId)->exists()) {
            return 'GOODS';
        }
        return 'SERVICE';
    }

    /**
     * Ensure journal entry details for a document are balanced (sum debit == sum credit).
     * Throws exception when imbalance is above tolerance.
     */
    protected function assertBalancedJournalForDocument(string $documentNo, float $tolerance = 0.01): void
    {
        $row = DB::table('account_journal_entry_details')
            ->where('document_no', $documentNo)
            ->selectRaw('COALESCE(SUM(debit),0) as debit_sum, COALESCE(SUM(credit),0) as credit_sum')
            ->first();

        $debit = (float)($row->debit_sum ?? 0);
        $credit = (float)($row->credit_sum ?? 0);
        $diff = $debit - $credit;

        if (abs($diff) > $tolerance) {
            throw new \RuntimeException("Ecritures non équilibrées pour {$documentNo}: Débit={$debit}, Crédit={$credit}, Diff={$diff}");
        }
    }

    /**
     * Read and validate company information from settings.
     */
    protected function resolveCompanyDataFromSettings(): array
    {
        $setting = Setting::first();
        if (!$setting) {
            return [
                'ok' => false,
                'message' => 'Paramètres société introuvables. Veuillez configurer la société avant de créer/modifier une facture.',
            ];
        }

        $company = [
            'tp_type' => $setting->tp_type,
            'tp_name' => $setting->name,
            'tp_TIN' => $setting->nif,
            'tp_trade_number' => $setting->rc,
            'tp_phone_number' => $setting->telephone1,
            'tp_address_province' => $setting->province,
            'tp_address_commune' => $setting->commune,
            'tp_address_quartier' => $setting->quartier,
            'tp_address_avenue' => $setting->rue,
            'tp_address_rue' => $setting->rue,
            'vat_taxpayer' => $setting->vat_taxpayer,
            'ct_taxpayer' => $setting->ct_taxpayer,
            'tl_taxpayer' => $setting->tl_taxpayer,
            'tp_fiscal_center' => $setting->tp_fiscal_center,
            'tp_activity_sector' => $setting->tp_activity_sector,
            'tp_legal_form' => $setting->tp_legal_form,
        ];

        $required = [
            'tp_type' => 'type contribuable',
            'tp_name' => 'nom société',
            'tp_TIN' => 'NIF société',
            'tp_trade_number' => 'registre de commerce (RC)',
            'tp_phone_number' => 'téléphone société',
            'tp_address_province' => 'province société',
            'tp_address_commune' => 'commune société',
            'tp_address_quartier' => 'quartier société',
            'tp_address_rue' => 'rue société',
        ];

        $missing = [];
        foreach ($required as $key => $label) {
            if (is_null($company[$key]) || trim((string)$company[$key]) === '') {
                $missing[] = $label;
            }
        }

        if (!empty($missing)) {
            return [
                'ok' => false,
                'message' => 'Informations société incomplètes dans les paramètres: '.implode(', ', $missing).'.',
            ];
        }

        return [
            'ok' => true,
            'data' => $company,
        ];
    }

    /**
     * Normalize checkbox/radio value to one allowed choice.
     */
    protected function normalizeSingleChoice($value, array $allowed, string $default): string
    {
        if (is_array($value)) {
            foreach ($value as $candidate) {
                $candidate = (string) $candidate;
                if (in_array($candidate, $allowed, true)) {
                    return $candidate;
                }
            }
            return $default;
        }

        $value = (string) $value;
        if (in_array($value, $allowed, true)) {
            return $value;
        }

        return $default;
    }

    /**
     * Génère le PDF facture en mode portrait.
     */
    protected function makeInvoicePdf(string $view, Invoice $invoice, $setting)
    {
        return PDF::setOptions([
            'defaultFont' => 'dejavu sans',
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
        ])
            ->loadView($view, compact('invoice', 'setting'), [], 'UTF-8')
            ->setPaper('a4', 'portrait');
    }

    /**
     * Lignes facture pour l'API OBR (toutes les lignes, désignation sécurisée).
     */
    protected function buildObrInvoiceItems($details): array
    {
        $items = [];

        foreach ($details as $data) {
            $designation = $data->item_designation
                ?? (optional($data->item)->name)
                ?? 'N/A';

            $items[] = [
                'item_designation' => $designation,
                'item_quantity' => $data->item_quantity,
                'item_price' => $this->obrItemUnitPriceTaxIncluded($data),
                'item_ct' => $data->item_ct,
                'item_tl' => $data->item_tl,
                'item_price_nvat' => $data->item_price_nvat,
                'vat' => $data->vat,
                'item_price_wvat' => $data->item_price_wvat,
                'item_total_amount' => $data->item_total_amount,
            ];
        }

        return $items;
    }

    /**
     * Prix unitaire TTC pour l'API OBR (item_price = PU TVA inclus).
     */
    protected function obrItemUnitPriceTaxIncluded($detail): float
    {
        $qty = (float) ($detail->item_quantity ?? 0);
        if ($qty > 0) {
            return round((float) ($detail->item_total_amount ?? 0) / $qty, 2);
        }

        return round((float) ($detail->item_price ?? 0), 2);
    }
}
