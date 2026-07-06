<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class WrapBladeTranslations extends Command
{
    protected $signature = 'translations:wrap-blades {--dry-run : Show changes without writing files}';

    protected $description = 'Replace common hardcoded French UI strings in blade views with @lang() calls';

    protected array $replacements = [
        'title="Télécharger d\'abord le document et puis imprimer"' => 'title="@lang(\'messages.download_then_print\')"',
        "title=\"Télécharger d'abord le document et puis imprimer\"" => "title=\"@lang('messages.download_then_print')\"",
        'placeholder="Rechercher un compte (numéro ou nom)..."' => 'placeholder="@lang(\'messages.search_account_placeholder\')"',
        'placeholder="Entrer description"' => 'placeholder="@lang(\'messages.enter_description\')"',
        'placeholder="Entrer quantite"' => 'placeholder="@lang(\'messages.enter_quantity\')"',
        'placeholder="Entrer le nom du Receptionniste"' => 'placeholder="@lang(\'messages.enter_receptionist_name\')"',
        'placeholder="Entrer le nom du Remettant"' => 'placeholder="@lang(\'messages.enter_handingover_name\')"',
        'placeholder="Entrer No facture"' => 'placeholder="@lang(\'messages.enter_invoice_no\')"',
        'placeholder="Merci de choisir Client"' => 'placeholder="@lang(\'messages.choose_client\')"',
        'placeholder="Entrer Nom "' => 'placeholder="@lang(\'messages.enter_firstname\')"',
        'placeholder="Entrer Prenom"' => 'placeholder="@lang(\'messages.enter_lastname\')"',
        'placeholder="Entrer Date Entrée"' => 'placeholder="@lang(\'messages.enter_start_date\')"',
        'placeholder="Entrer Somme Salaire Base"' => 'placeholder="@lang(\'messages.enter_base_salary\')"',
        'placeholder="Entrer prime de fonction"' => 'placeholder="@lang(\'messages.enter_function_bonus\')"',
        'placeholder="Entrer Numéro de compte"' => 'placeholder="@lang(\'messages.enter_account_number\')"',
        'placeholder="Entrer Téléphone "' => 'placeholder="@lang(\'messages.enter_phone\')"',
        'placeholder="Entrer Le Pays "' => 'placeholder="@lang(\'messages.enter_country\')"',
        'placeholder="Entrer Province "' => 'placeholder="@lang(\'messages.enter_province\')"',
        'placeholder="Entrer Commune"' => 'placeholder="@lang(\'messages.enter_commune\')"',
        'placeholder="Entrer Zone"' => 'placeholder="@lang(\'messages.enter_zone\')"',
        'placeholder="Entrer Quartier"' => 'placeholder="@lang(\'messages.enter_quarter\')"',
        'placeholder="Entrer E-mail"' => 'placeholder="@lang(\'messages.enter_email\')"',
        'placeholder="Entrer le telephone"' => 'placeholder="@lang(\'messages.enter_phone\')"',
        'placeholder="Entrer le mail"' => 'placeholder="@lang(\'messages.enter_email\')"',
        'title="Imprimer le document"' => 'title="@lang(\'messages.print_document\')"',
        'title="Cliquer pour telecharger la reference de l\'inventaire"' => 'title="@lang(\'messages.click_download_inventory_ref\')"',
        ">Non assujetti</option>" => ">@lang('messages.Non assujetti')</option>",
        ">Assujetti</option>" => ">@lang('messages.Assujetti')</option>",
        ">Non Assujetti</option>" => ">@lang('messages.Non assujetti')</option>",
        ">EN - Entrée Normale</option>" => ">@lang('messages.movement_en_normal')</option>",
        ">SN - Sortie Normale</option>" => ">@lang('messages.movement_sn_normal')</option>",
        ">SP - Sortie Perte</option>" => ">@lang('messages.movement_sp_loss')</option>",
        ">SV - Sortie Vol</option>" => ">@lang('messages.movement_sv_theft')</option>",
        ">SC - Sortie Casse</option>" => ">@lang('messages.movement_sc_breakage')</option>",
        ">SAJ - Sortie Ajustement</option>" => ">@lang('messages.movement_saj_adjustment')</option>",
        ">SAU - Sortie Autre</option>" => ">@lang('messages.movement_sau_other')</option>",
        ">ER - Entrée Retour Marchandises</option>" => ">@lang('messages.movement_er_return')</option>",
        ">EAJ - Entrée Ajustement</option>" => ">@lang('messages.movement_eaj_adjustment')</option>",
        ">EAU - Entrée Autre</option>" => ">@lang('messages.movement_eau_other')</option>",
        "? 'Oui' : 'Non'" => "? __('messages.Oui') : __('messages.Non')",

        // Phase 2 — HR
        'placeholder="Rechercher un compte..."' => 'placeholder="@lang(\'messages.search_account_short\')"',
        'placeholder="Rechercher un employé..."' => 'placeholder="@lang(\'messages.search_employee_placeholder\')"',
        'placeholder="Tapez pour rechercher un employé..."' => 'placeholder="@lang(\'messages.type_to_search_employee\')"',
        'placeholder="Tapez pour rechercher un compte..."' => 'placeholder="@lang(\'messages.type_to_search_account\')"',
        'placeholder="Entrer Telephone "' => 'placeholder="@lang(\'messages.enter_phone\')"',
        'placeholder="Entrer Numero CNI"' => 'placeholder="@lang(\'messages.enter_id_number\')"',
        'placeholder="Entrer Residence Actuel"' => 'placeholder="@lang(\'messages.enter_current_residence\')"',
        'placeholder="Entrer NIF"' => 'placeholder="@lang(\'messages.enter_nif\')"',
        'placeholder="Entrer RC"' => 'placeholder="@lang(\'messages.enter_rc\')"',
        'placeholder="Entrer Commune "' => 'placeholder="@lang(\'messages.enter_commune\')"',
        'placeholder="Entrer Avenue "' => 'placeholder="@lang(\'messages.enter_avenue\')"',
        'placeholder="Entrer Rue"' => 'placeholder="@lang(\'messages.enter_street\')"',
        'placeholder="Entrer telephone 1"' => 'placeholder="@lang(\'messages.enter_phone_1\')"',
        'placeholder="Entrer Telephone 2 "' => 'placeholder="@lang(\'messages.enter_phone_2\')"',
        'placeholder="Entrer Email"' => 'placeholder="@lang(\'messages.enter_email\')"',
        'placeholder="Entrer Developpeur"' => 'placeholder="@lang(\'messages.enter_developer\')"',
        'placeholder="Entrer Plafond Impôt"' => 'placeholder="@lang(\'messages.hr_enter_tax_ceiling\')"',
        'placeholder="Entrer Plafond Cotisation"' => 'placeholder="@lang(\'messages.hr_enter_contribution_ceiling\')"',
        'placeholder="Entrer Nbre Jrs Ouvrable"' => 'placeholder="@lang(\'messages.hr_enter_working_days\')"',
        'placeholder="Entrer % prime"' => 'placeholder="@lang(\'messages.hr_enter_bonus_percent\')"',
        'placeholder="Saisir Autre Retenue "' => 'placeholder="@lang(\'messages.hr_enter_other_deduction\')"',
        'placeholder="Saisir Soins Medicaux"' => 'placeholder="@lang(\'messages.hr_enter_medical_care\')"',
        'placeholder="Saisir Allocations Familiales"' => 'placeholder="@lang(\'messages.hr_enter_family_allowance\')"',
        'placeholder="Entrer Nom Filière"' => 'placeholder="@lang(\'messages.enter_field_name\')"',
        'placeholder="Entrer Nom Ecole"' => 'placeholder="@lang(\'messages.enter_school_name\')"',
        'placeholder="Entrer Adresse"' => 'placeholder="@lang(\'messages.enter_address\')"',
        'title="valider le congé payé"' => 'title="@lang(\'messages.hr_take_conge_paye_action_validate\')"',
        'title="confirmer le congé payé"' => 'title="@lang(\'messages.hr_take_conge_paye_action_confirm\')"',
        'title="approuver le congé payé"' => 'title="@lang(\'messages.hr_take_conge_paye_action_approve\')"',
        'title="rejeter le congé payé"' => 'title="@lang(\'messages.hr_take_conge_paye_action_reject\')"',
        'title="annuler le congé payé"' => 'title="@lang(\'messages.hr_take_conge_paye_action_cancel\')"',
        'title="Gérer Congé Payé"' => 'title="@lang(\'messages.hr_manage_paid_leave\')"',
        'title="Nouveau journal"' => 'title="@lang(\'messages.hr_new_payroll_journal\')"',
        'title="Nouvelle Fiche de Paie"' => 'title="@lang(\'messages.hr_new_payslip\')"',
        'title="clôturer le journal de paie"' => 'title="@lang(\'messages.hr_close_payroll_journal\')"',
        'title="Cliquer pour exporter en Excel"' => 'title="@lang(\'messages.click_export_excel\')"',
        'title="Créer un horaire"' => 'title="@lang(\'messages.hr_create_schedule\')"',
        'title="Supprimer l\'image"' => 'title="@lang(\'messages.delete_image\')"',
        'title="Supprimer ce document"' => 'title="@lang(\'messages.delete_document\')"',
        'title="Voir le document"' => 'title="@lang(\'messages.view_document\')"',
        'title="Télécharger le document"' => 'title="@lang(\'messages.download_document\')"',
        ' Informations Générales' => " @lang('messages.general_information')",
        'Informations Fiscales' => "@lang('messages.fiscal_information')",
        '>Rémunération<' => ">@lang('messages.remuneration')<",
        'Aucun document disponible pour cet employé.' => "@lang('messages.hr_no_employee_documents')",
        'Veuillez sélectionner un département pour afficher les horaires.' => "@lang('messages.hr_select_department_for_schedules')",
        'title="Ajouter"' => 'title="@lang(\'messages.Ajouter\')"',
        'title="Modifier"' => 'title="@lang(\'messages.edit\')"',
        'title="Supprimer"' => 'title="@lang(\'messages.delete\')"',
        'title="Voir"' => 'title="@lang(\'messages.view_action\')"',
        'title="Créer Facture"' => 'title="@lang(\'messages.create_invoice\')"',

        // Phase 2 — Comptabilité
        'placeholder="Rechercher le fournisseur"' => 'placeholder="@lang(\'messages.search_supplier\')"',
        'placeholder="Saisir le demandeur"' => 'placeholder="@lang(\'messages.enter_requester\')"',
        'placeholder="Tapez pour rechercher un article..."' => 'placeholder="@lang(\'messages.type_to_search_item\')"',
        'placeholder="Rechercher par numéro ou nom de compte..."' => 'placeholder="@lang(\'messages.search_account_by_number_name\')"',
        'placeholder="Rechercher un compte"' => 'placeholder="@lang(\'messages.search_account_short\')"',
        'placeholder="Montant sorti"' => 'placeholder="@lang(\'messages.amount_picked\')"',
        'placeholder="Entrer le montant retourné"' => 'placeholder="@lang(\'messages.enter_returned_amount\')"',
        '>Retour à la liste<' => ">@lang('messages.back_to_list')<",
        'Aucun fournisseur ou employé associé à cette sortie de fonds.' => "@lang('messages.no_supplier_employee_cash_outflow')",
        '>Modifier la sortie de fonds<' => ">@lang('messages.edit_cash_outflow')<",
        'Sans période sélectionnée, seules les 100 écritures les plus récentes sont affichées.' => "@lang('messages.journal_default_limit_notice')",

        // Phase 2 — Ventes
        'placeholder="Rechercher un item..."' => 'placeholder="@lang(\'messages.search_item_placeholder\')"',
        'placeholder="Rechercher un article..."' => 'placeholder="@lang(\'messages.search_item_placeholder\')"',
        'placeholder="Rechercher un article (PF, marchandise)..."' => 'placeholder="@lang(\'messages.search_finished_goods_item\')"',
        'placeholder="Tapez pour rechercher un client..."' => 'placeholder="@lang(\'messages.type_to_search_client\')"',
        'placeholder="Choisir un client"' => 'placeholder="@lang(\'messages.choose_client\')"',
        'Détails Complets' => "@lang('messages.full_details')",
        'Statut & Historique' => "@lang('messages.status_and_history')",
        'title="Valider Cash"' => 'title="@lang(\'messages.validate_cash\')"',
        'title="Valider Credit"' => 'title="@lang(\'messages.validate_credit\')"',
        'title="Imprimer (PDF) - transmet à l\'OBR si nécessaire"' => 'title="@lang(\'messages.print_pdf_obr_hint\')"',
        'title="Créer Bon de livraison"' => 'title="@lang(\'messages.create_delivery_note\')"',
        'title="Créer Bon de commande (données de la facture proforma auto-complétées)"' => 'title="@lang(\'messages.create_so_from_proforma_hint\')"',
        'title="Déjà livré"' => 'title="@lang(\'messages.already_delivered\')"',
        'Bon de Livraison:' => "@lang('messages.delivery_note_label')",
        'Aucun article trouvé' => "@lang('messages.no_items_found')",
        "confirm('Supprimer ce bon de commande ?')" => "confirm({{ json_encode(__('messages.confirm_delete_sales_order')) }})",
        "confirm('Êtes-vous sûr de vouloir supprimer ce bon de livraison? Cette action est irréversible.')" => "confirm({{ json_encode(__('messages.confirm_delete_delivery_note')) }})",
        "alert('Veuillez sélectionner un client dans la liste.')" => "alert({{ json_encode(__('messages.select_client_from_list')) }})",
        "alert('Veuillez sélectionner chaque article dans la liste.')" => "alert({{ json_encode(__('messages.select_each_item')) }})",
        "alert('Veuillez sélectionner chaque article dans la liste (produit fini ou marchandise).')" => "alert({{ json_encode(__('messages.select_each_fg_or_goods_item')) }})",
        'Ce bon de livraison ne peut plus être modifié car une facture validée existe.' => "@lang('messages.dn_locked_validated_invoice')",
        'Montant total à déduire :' => "@lang('messages.total_amount_to_deduct')",
        "confirm('Confirmer la validation ? Cette opération est définitive.')" => "confirm({{ json_encode(__('messages.confirm_credit_note_validation')) }})",

        // Phase 3 — Achats
        'placeholder="Rechercher l\'article"' => 'placeholder="@lang(\'messages.search_article_short\')"',
        'placeholder="Rechercher l\'article (produit fini, marchandise ou matériel)"' => 'placeholder="@lang(\'messages.search_article_all_types\')"',
        'placeholder="Rechercher la matière première"' => 'placeholder="@lang(\'messages.search_raw_material\')"',
        '>Modifier la commande<' => ">@lang('messages.edit_supplier_order')<",
        '>Modifier la reception<' => ">@lang('messages.edit_reception_btn')<",
        'placeholder="Enter Desccription"' => 'placeholder="@lang(\'messages.enter_description\')"',
        "confirm('Are you sure you want to delete this item? This action is irreversible.')" => "confirm({{ json_encode(__('messages.confirm_delete_irreversible')) }})",

        // Phase 3 — Stock
        'placeholder="Enter handingover"' => 'placeholder="@lang(\'messages.enter_handingover_stock\')"',
        'placeholder="Enter origin"' => 'placeholder="@lang(\'messages.enter_origin\')"',
        'placeholder="Enter receptionist"' => 'placeholder="@lang(\'messages.enter_receptionist_stock\')"',
        'placeholder="Enter purchase price"' => 'placeholder="@lang(\'messages.enter_purchase_price\')"',
        'placeholder="Enter quantity"' => 'placeholder="@lang(\'messages.enter_quantity\')"',
        'placeholder="Enter Price"' => 'placeholder="@lang(\'messages.enter_price\')"',
        'placeholder="Enter Description"' => 'placeholder="@lang(\'messages.enter_description\')"',

        // Phase 3 — Production
        "confirm('Valider ce plan ?')" => "confirm({{ json_encode(__('messages.confirm_validate_plan')) }})",
        "confirm('Confirmer ce plan ?')" => "confirm({{ json_encode(__('messages.confirm_confirm_plan')) }})",
        "confirm('Approuver ce plan ?')" => "confirm({{ json_encode(__('messages.confirm_approve_plan')) }})",
        "confirm('Supprimer ce plan ?')" => "confirm({{ json_encode(__('messages.confirm_delete_plan')) }})",
        'title="Créer requête matières premières"' => 'title="@lang(\'messages.create_rm_requisition_title\')"',
        '>Requête<' => ">@lang('messages.requisition_short_label')<",
        'placeholder="Rechercher un produit fini..."' => 'placeholder="@lang(\'messages.search_finished_product\')"',
        'placeholder="Type to search item..."' => 'placeholder="@lang(\'messages.type_to_search_item\')"',
        'placeholder="Type to search raw material..."' => 'placeholder="@lang(\'messages.type_to_search_raw_material\')"',
        'placeholder="Quantity"' => 'placeholder="@lang(\'messages.quantity_placeholder\')"',
        'placeholder="Cost Price"' => 'placeholder="@lang(\'messages.cost_price_placeholder\')"',
        'placeholder="Unit"' => 'placeholder="@lang(\'messages.unit_placeholder\')"',
        'placeholder="Cost"' => 'placeholder="@lang(\'messages.cost_placeholder\')"',
        "confirm('Enregistrer ce plan de production ?')" => "confirm({{ json_encode(__('messages.save_production_plan_confirm')) }})",
        "confirm('Enregistrer les modifications du plan ?')" => "confirm({{ json_encode(__('messages.save_production_plan_changes_confirm')) }})",
        "confirm('Are you sure you want to validate this production order?')" => "confirm({{ json_encode(__('messages.confirm_validate_production_order')) }})",
        "confirm('Are you sure you want to confirm this production order?')" => "confirm({{ json_encode(__('messages.confirm_confirm_production_order')) }})",
        "confirm('Are you sure you want to approve this production order? This will update stocks!')" => "confirm({{ json_encode(__('messages.confirm_approve_production_order_stock')) }})",
        "confirm('Are you sure you want to delete this production order?')" => "confirm({{ json_encode(__('messages.confirm_delete_production_order')) }})",
        "confirm('Are you sure you want to update this production order?')" => "confirm({{ json_encode(__('messages.confirm_update_production_order')) }})",
        "confirm('Are you sure you want to create this production order?')" => "confirm({{ json_encode(__('messages.confirm_create_production_order')) }})",
        "confirm('Are you sure you want to delete this requisition? This action is irreversible.')" => "confirm({{ json_encode(__('messages.confirm_delete_requisition')) }})",
        'title="Valider"' => 'title="@lang(\'messages.Valider\')"',
        'title="Confirmer"' => 'title="@lang(\'messages.Confirmer\')"',
        'title="Approuver"' => 'title="@lang(\'messages.Approuver\')"',
        'title="Validate"' => 'title="@lang(\'messages.Valider\')"',
        'title="Confirm"' => 'title="@lang(\'messages.Confirmer\')"',
        'title="Approve"' => 'title="@lang(\'messages.Approuver\')"',
        'title="Edit"' => 'title="@lang(\'messages.edit\')"',
        'title="View"' => 'title="@lang(\'messages.view_action\')"',
        'title="Delete"' => 'title="@lang(\'messages.delete\')"',
    ];

    public function handle(): int
    {
        $viewsPath = resource_path('views/backend');
        $dryRun = (bool) $this->option('dry-run');
        $changedFiles = 0;
        $totalReplacements = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($viewsPath)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || substr($file->getFilename(), -10) !== '.blade.php') {
                continue;
            }

            $path = $file->getPathname();
            $original = file_get_contents($path);
            $updated = $original;
            $fileCount = 0;

            foreach ($this->replacements as $search => $replace) {
                if (strpos($updated, $search) !== false) {
                    $occurrences = substr_count($updated, $search);
                    $updated = str_replace($search, $replace, $updated);
                    $fileCount += $occurrences;
                }
            }

            if ($fileCount > 0 && $updated !== $original) {
                $changedFiles++;
                $totalReplacements += $fileCount;
                $relative = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path);
                $this->line("{$relative}: {$fileCount} replacement(s)");

                if (!$dryRun) {
                    file_put_contents($path, $updated);
                }
            }
        }

        $mode = $dryRun ? 'would be updated' : 'updated';
        $this->info("Done. {$changedFiles} file(s) {$mode}, {$totalReplacements} replacement(s).");

        return 0;
    }
}
