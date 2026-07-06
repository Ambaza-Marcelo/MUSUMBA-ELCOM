
@extends('backend.layouts.master')

@section('title')
@lang('messages.Edit invoice') - @lang('messages.admin_panel')
@endsection

@section('styles')
<style>
    .form-check-label {
        text-transform: capitalize;
    }
    .autocomplete {
        position: relative;
        display: inline-block;
        width: 100%;
    }
    .autocomplete-items {
        position: absolute;
        border: 1px solid #d4d4d4;
        border-bottom: none;
        border-top: none;
        z-index: 99;
        top: 100%;
        left: 0;
        right: 0;
        max-height: 200px;
        overflow-y: auto;
        background: white;
    }
    .autocomplete-items div {
        padding: 10px;
        cursor: pointer;
        background-color: #fff;
        border-bottom: 1px solid #d4d4d4;
    }
    .autocomplete-items div:hover {
        background-color: #e9e9e9;
    }
    .autocomplete-active {
        background-color: DodgerBlue !important;
        color: #ffffff;
    }
</style>
@endsection

@section('admin-content')
<div class="page-title-area">
    <div class="row align-items-center">
        <div class="col-sm-6">
            <div class="breadcrumbs-area clearfix">
                <h4 class="page-title pull-left">@lang('messages.Edit invoice')</h4>
                <ul class="breadcrumbs pull-left">
                    <li><a href="{{ route('admin.dashboard') }}">@lang('messages.dashboard')</a></li>
                    <li><a href="{{ route('admin.invoice.index') }}">@lang('messages.list')</a></li>
                    <li><span>@lang('messages.Edit invoice')</span></li>
                </ul>
            </div>
        </div>
        <div class="col-sm-6 clearfix">
            @include('backend.layouts.partials.logout')
        </div>
    </div>
</div>
<div class="main-content-inner">
    <div class="row">
        <!-- data table start -->
         <div class="col-12 mt-5">
            <div class="card">
                <div class="card-body">
                    <h4 class="header-title">Edit Invoice - {{ $invoice->invoice_number }}</h4>
                    <div class="alert alert-info">
                        Lors de la modification, les informations societe sont verifiees et rechargees depuis les Parametres (Setting).
                    </div>
                    <p class="text-muted small mb-2">@lang('messages.Pour les') <strong>@lang('messages.factures d\'avoir')</strong> (notes de crédit créées depuis une facture validée), le type OBR et la numérotation utilisent le code <strong>@lang('messages.FA')</strong> ; la saisie dédiée se fait via le menu facture &rarr; Note de crédit.</p>
                    <p class="text-info">@lang('messages.Les données sont synchronisées depuis le document source:') <strong>{{ $delivery_number }}</strong></p>
                    @if($invoice->etat != '0')
                        <p class="text-warning"><strong>Tous les champs sont en lecture seule car l'état de la facture est {{ $invoice->etat }}. Seules les factures avec état=0 peuvent être modifiées.</strong></p>
                    @else
                        <p class="text-info"><strong>@lang('messages.Vous pouvez modifier cette facture car son état est 0.')</strong></p>
                    @endif
                    @include('backend.layouts.partials.messages')
                    
                    <form action="{{ route('admin.invoice.update', $invoice->invoice_number) }}" method="post" id="dynamic_form">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="delivery_number" value="{{ $delivery_number }}" />
                        <input type="hidden" name="document_no" value="{{ $delivery_number }}" />
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="client_id">@lang('messages.Client')</label>
                                <div class="autocomplete">
                                    <input type="text" id="client_name" name="client_name" class="form-control" placeholder="@lang('messages.choose_client')" autocomplete="off" 
                                        value="{{ ($delivery_note && $delivery_note->client) ? $delivery_note->client->customer_name . '/' . $delivery_note->client->telephone : ($invoice->client ? $invoice->client->customer_name . '/' . $invoice->client->telephone : ($defaultClient ? $defaultClient->customer_name . '/' . $defaultClient->telephone : '')) }}" {{ $invoice->etat != '0' ? 'readonly' : '' }} />
                                    <input type="hidden" name="client_id" id="client_id" value="{{ $delivery_note ? $delivery_note->client_id : ($invoice->client_id ?? ($defaultClient ? $defaultClient->id : '')) }}" />
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="invoice_date">@lang('messages.date')</label>
                                <input type="date" class="form-control" id="invoice_date" name="invoice_date"
                                       value="{{ old('invoice_date', $invoice->invoice_date ? \Carbon\Carbon::parse($invoice->invoice_date)->format('Y-m-d') : now()->format('Y-m-d')) }}" {{ $invoice->etat != '0' ? 'readonly' : '' }}>
                            </div>
                        </div>
                    </div>
                         <table class="table table-bordered" id="dynamicTable">  
                            <tr>
                                <th>@lang('messages.item')</th>
                                <th>@lang('messages.quantity')</th>
                                <th>@lang('messages.Prix Unitaire')</th>
                                <th>@lang('messages.Total')</th>
                            </tr>
                            @php $i = 0; @endphp
                            @foreach($delivery_details as $detail)
                            <tr>  
                                <td>
                                    <div class="autocomplete">
                                        <input type="text" name="item_name[]" id="item_name_{{ $i }}" placeholder="@lang('messages.search_item_placeholder')" class="form-control" autocomplete="off" 
                                            value="@if($detail->item){{ $detail->item->name }}/{{ number_format($detail->item->selling_price,0,',',' ') }}@else{{ $detail->item_designation }}@endif" {{ $invoice->etat != '0' ? 'readonly' : '' }} />
                                        <input type="hidden" name="item_id[]" id="item_id_{{ $i }}" value="{{ $detail->item_id }}" />
                                        <input type="hidden" name="item_price[]" id="item_price_{{ $i }}" value="{{ $detail->item_price }}" />
                                    </div>
                                </td>  
                                <td><input type="number" name="item_quantity[]" id="quantity_{{ $i }}" placeholder="@lang('messages.enter_quantity')" class="form-control quantity-input" value="{{ $detail->item_quantity }}" {{ $invoice->etat != '0' ? 'readonly' : '' }} /></td>
                                <td><input type="text" id="unit_price_{{ $i }}" class="form-control unit-price" value="{{ number_format($detail->item_price, 0, ',', ' ') }} BIF" readonly /></td>
                                <td><input type="text" id="total_price_{{ $i }}" class="form-control total-price" value="{{ number_format($detail->item_total_amount, 0, ',', ' ') }} BIF" readonly /></td>
                                @if($invoice->etat == '0')
                                <td><button type="button" class="btn btn-danger remove-tr">@lang('messages.delete')</button></td>
                                @endif
                            </tr>
                            @php $i++; @endphp
                            @endforeach
                            @if($delivery_details->count() == 0)
                            <tr>  
                                <td colspan="{{ $invoice->etat == '0' ? '5' : '4' }}" class="text-center">@lang('messages.no_items_found')</td>
                            </tr>
                            @endif
                        </table>
                        @if($invoice->etat == '0')
                        <div>
                            <button type="button" name="add" id="add" class="btn btn-success">@lang('messages.addmore')</button>
                        </div>
                        @endif
                        <div class="row mt-3">
                            <div class="col-md-8"></div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title">@lang('messages.Total Général')</h5>
                                        <h3 id="grand_total" class="text-primary">{{ number_format($delivery_details->sum('item_total_amount'), 0, ',', ' ') }} BIF</h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-12">
                            <label for="description">@lang('messages.description')</label>
                            <textarea class="form-control" name="description" id="description" placeholder="@lang('messages.enter_description')" {{ $invoice->etat != '0' ? 'readonly' : '' }}>{{ $delivery_note ? $delivery_note->description : $invoice->description }}</textarea>
                        </div>
                        <div style="margin-top: 15px;margin-left: 15px;">
                            @if($invoice->etat == '0')
                                <button type="submit" onclick="this.style.visibility='hidden';" ondblclick="this.style.visibility='hidden';" class="btn btn-primary" id="save">@lang('messages.update')</button>
                            @endif
                            <a href="{{ route('admin.invoice.index') }}" class="btn btn-secondary">@lang('messages.cancel')</a>
                            @if($invoice->etat != '0')
                                <a href="{{ route('admin.proforma-invoice.index') }}" class="btn btn-info">@lang('messages.Modifier depuis Facture Proforma')</a>
                            @endif
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
</div>

<script src="//ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    <script type="text/javascript">
    var i = {{ $delivery_details->count() > 0 ? $delivery_details->count() - 1 : 0 }};

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
    
    // items data for autocomplete
    var items = [
        @foreach($items as $item)
        {
            id: {{ $item->id }},
            name: {{ json_encode($item->name) }},
            price: {{ $item->selling_price }},
            display: {{ json_encode($item->name . '/' . number_format($item->selling_price, 0, ',', ' ')) }}
        },
        @endforeach
    ];
    
    // clients data for autocomplete
    var clients = [
        @foreach($clients as $client)
        {
            id: {{ $client->id }},
            name: {{ json_encode($client->customer_name) }},
            telephone: {{ json_encode($client->telephone) }},
            display: {{ json_encode($client->customer_name . '/' . $client->telephone) }}
        },
        @endforeach
    ];
    
    // Autocomplete function
    function autocomplete(inp, arr, hiddenInput) {
        var currentFocus;
        
        inp.addEventListener("input", function(e) {
            var a, b, i, val = this.value;
            
            closeAllLists();
            if (!val) { return false;}
            currentFocus = -1;
            
            a = document.createElement("DIV");
            a.setAttribute("id", this.id + "autocomplete-list");
            a.setAttribute("class", "autocomplete-items");
            this.parentNode.appendChild(a);
            
            for (i = 0; i < arr.length; i++) {
                // Search by name or telephone for clients
                var matches = arr[i].name.toLowerCase().includes(val.toLowerCase());
                if (arr[i].telephone && arr[i].telephone.toString().includes(val)) {
                    matches = true;
                }
                
                if (matches) {
                    b = document.createElement("DIV");
                    b.innerHTML = "<strong>" + escapeHtml(arr[i].name) + "</strong>";
                    
                    // Display price for items, telephone for clients
                    if (arr[i].price !== undefined) {
                        b.innerHTML += " - " + arr[i].price.toLocaleString() + " BIF";
                    } else if (arr[i].telephone) {
                        b.innerHTML += " - " + escapeHtml(arr[i].telephone);
                    }
                    
                    var hidden = document.createElement("input");
                    hidden.type = "hidden";
                    hidden.value = arr[i].display;
                    b.appendChild(hidden);
                    b.addEventListener("click", function(e) {
                        inp.value = this.getElementsByTagName("input")[0].value;
                        var selectedItem = arr.find(item => item.display === inp.value);
                        hiddenInput.value = selectedItem.id;
                        
                        // Update unit price and calculate total only for items (not clients)
                        var rowIndex = inp.id.split('_')[2];
                        if (rowIndex !== undefined && !isNaN(rowIndex) && selectedItem.price !== undefined) {
                            document.getElementById('unit_price_' + rowIndex).value = selectedItem.price.toLocaleString() + ' BIF';
                            // Update hidden item_price input
                            var itemPriceInput = document.getElementById('item_price_' + rowIndex);
                            if (itemPriceInput) {
                                itemPriceInput.value = selectedItem.price;
                            }
                            calculateRowTotal(rowIndex);
                        }
                        closeAllLists();
                    });
                    a.appendChild(b);
                }
            }
        });
        
        inp.addEventListener("keydown", function(e) {
            var x = document.getElementById(this.id + "autocomplete-list");
            if (x) x = x.getElementsByTagName("div");
            if (e.keyCode == 40) {
                currentFocus++;
                addActive(x);
            } else if (e.keyCode == 38) {
                currentFocus--;
                addActive(x);
            } else if (e.keyCode == 13) {
                e.preventDefault();
                if (currentFocus > -1) {
                    if (x) x[currentFocus].click();
                }
            }
        });
        
        function addActive(x) {
            if (!x) return false;
            removeActive(x);
            if (currentFocus >= x.length) currentFocus = 0;
            if (currentFocus < 0) currentFocus = (x.length - 1);
            x[currentFocus].classList.add("autocomplete-active");
        }
        
        function removeActive(x) {
            for (var i = 0; i < x.length; i++) {
                x[i].classList.remove("autocomplete-active");
            }
        }
        
        function closeAllLists(elmnt) {
            var x = document.getElementsByClassName("autocomplete-items");
            for (var i = 0; i < x.length; i++) {
                if (elmnt != x[i] && elmnt != inp) {
                    x[i].parentNode.removeChild(x[i]);
                }
            }
        }
        
        document.addEventListener("click", function (e) {
            closeAllLists(e.target);
        });
    }
    
    // Calculate row total
    function calculateRowTotal(rowIndex) {
        var quantity = parseFloat(document.getElementById('quantity_' + rowIndex).value) || 0;
        var unitPrice = 0;
        
        // Get unit price from hidden input item_price, otherwise from selected item
        var itemPriceInput = document.getElementById('item_price_' + rowIndex);
        if (itemPriceInput && itemPriceInput.value) {
            unitPrice = parseFloat(itemPriceInput.value) || 0;
        } else {
            // Fallback to selected item price
            var itemId = document.getElementById('item_id_' + rowIndex).value;
            if (itemId) {
                var selectedItem = items.find(item => item.id == itemId);
                if (selectedItem) {
                    unitPrice = selectedItem.price;
                }
            }
        }
        
        var total = quantity * unitPrice;
        document.getElementById('total_price_' + rowIndex).value = total.toLocaleString() + ' BIF';
        
        calculateGrandTotal();
    }
    
    // Calculate grand total
    function calculateGrandTotal() {
        var grandTotal = 0;
        var totalInputs = document.querySelectorAll('.total-price');
        
        totalInputs.forEach(function(input) {
            var value = input.value.replace(/[^\d]/g, ''); // Remove non-numeric characters
            if (value) {
                grandTotal += parseFloat(value);
            }
        });
        
        document.getElementById('grand_total').textContent = grandTotal.toLocaleString() + ' BIF';
    }
    
    // Add event listeners for quantity changes
    $(document).on('input', '.quantity-input', function() {
        var rowIndex = this.id.split('_')[1];
        calculateRowTotal(rowIndex);
    });
    
    // Initialize autocomplete only if invoice can be edited (etat=0)
    @if($invoice->etat == '0')
    // Initialize autocomplete for existing rows
    @foreach($delivery_details as $index => $detail)
    autocomplete(document.getElementById("item_name_{{ $index }}"), items, document.getElementById("item_id_{{ $index }}"));
    @endforeach
    
    // Initialize autocomplete for client input
    autocomplete(document.getElementById("client_name"), clients, document.getElementById("client_id"));
    
    // Add button functionality
    $("#add").click(function(){
        ++i;
        
        var markup = "<tr>"+
                    "<td>"+
                        "<div class='autocomplete'>"+
                            "<input type='text' name='item_name[]' id='item_name_" + i + "' placeholder='Rechercher un item...' class='form-control' autocomplete='off' required />"+
                            "<input type='hidden' name='item_id[]' id='item_id_" + i + "' />"+
                            "<input type='hidden' name='item_price[]' id='item_price_" + i + "' />"+
                        "</div>"+
                    "</td>"+
                    "<td>"+
                        "<input type='number' name='item_quantity[]' id='quantity_" + i + "' placeholder='Enter Quantity' class='form-control quantity-input' required/>"+
                    "</td>"+
                    "<td>"+
                        "<input type='text' id='unit_price_" + i + "' class='form-control unit-price' readonly />"+
                    "</td>"+
                    "<td>"+
                        "<input type='text' id='total_price_" + i + "' class='form-control total-price' readonly />"+
                    "</td>"+
                    "<td>"+
                        "<button type='button' class='btn btn-danger remove-tr'>@lang('messages.delete')</button>"+
                    "</td>"+
                "</tr>";
        
        $("#dynamicTable").append(markup);
        
        // Initialize autocomplete for the new row
        setTimeout(function() {
            autocomplete(document.getElementById("item_name_" + i), items, document.getElementById("item_id_" + i));
        }, 100);
    });
   
    $(document).on('click', '.remove-tr', function(){  
         $(this).parents('tr').remove();
         calculateGrandTotal(); // Recalculate total after removing a row
    });
    @endif 

    function preventBack() {
        window.history.forward();
    }
    setTimeout("preventBack()", 0);
    window.onunload = function () {
        null
    };

</script>
@endsection
