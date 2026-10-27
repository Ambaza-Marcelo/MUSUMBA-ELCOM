<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddDepreciationFieldsToMaterialsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('materials', function (Blueprint $table) {
            // Flag d'actif amortissable (ex: logiciels internes, immobilisations)
            $table->boolean('is_amortizable')->default(false)->after('default_account_id');

            // Base d'amortissement HT (coût d'achat / coût de développement)
            $table->decimal('depreciation_base', 15, 2)->nullable()->after('is_amortizable');

            // Valeur résiduelle (0 pour un logiciel créé en interne)
            $table->decimal('residual_value', 15, 2)->default(0)->after('depreciation_base');

            // Date de mise en service (déclenche le prorata temporis)
            $table->date('service_start_date')->nullable()->after('residual_value');

            // Durée d'amortissement en années (3 ans typique pour un logiciel)
            $table->unsignedInteger('duration_years')->nullable()->default(3)->after('service_start_date');

            // Compte de charge d'amortissement (Débit, ex: 6811)
            $table->unsignedBigInteger('depreciation_expense_account_id')->nullable()->after('duration_years');
            $table->foreign('depreciation_expense_account_id')
                ->references('id')
                ->on('account_accounts')
                ->onUpdate('cascade')
                ->onDelete('set null');

            // Compte d'amortissements cumulés (Crédit, ex: 2805)
            $table->unsignedBigInteger('depreciation_asset_account_id')->nullable()->after('depreciation_expense_account_id');
            $table->foreign('depreciation_asset_account_id')
                ->references('id')
                ->on('account_accounts')
                ->onUpdate('cascade')
                ->onDelete('set null');
        });

        // Conversion stricte des montants / quantités string -> decimal(15,2)
        // (évite les erreurs d'arrondis monétaires sur les calculs d'amortissement)
        $this->convertColumnToDecimal('purchase_price');
        $this->convertColumnToDecimal('cost_price');
        $this->convertColumnToDecimal('selling_price');
        $this->convertColumnToDecimal('quantity');
        $this->convertColumnToDecimal('threshold_quantity');
        $this->convertColumnToDecimal('cump');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropForeign(['depreciation_expense_account_id']);
            $table->dropForeign(['depreciation_asset_account_id']);
            $table->dropColumn([
                'is_amortizable',
                'depreciation_base',
                'residual_value',
                'service_start_date',
                'duration_years',
                'depreciation_expense_account_id',
                'depreciation_asset_account_id',
            ]);
        });

        // Revenir aux types string d'origine
        DB::statement('ALTER TABLE materials MODIFY purchase_price VARCHAR(255) NULL');
        DB::statement('ALTER TABLE materials MODIFY cost_price VARCHAR(255) NULL');
        DB::statement('ALTER TABLE materials MODIFY selling_price VARCHAR(255) NULL');
        DB::statement('ALTER TABLE materials MODIFY quantity VARCHAR(255) NULL');
        DB::statement('ALTER TABLE materials MODIFY threshold_quantity VARCHAR(255) NULL');
        DB::statement('ALTER TABLE materials MODIFY cump VARCHAR(255) NULL');
    }

    /**
     * Nettoie les valeurs non numériques puis convertit la colonne en DECIMAL(15,2).
     */
    protected function convertColumnToDecimal(string $column): void
    {
        DB::table('materials')
            ->whereNotNull($column)
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($column) {
                foreach ($rows as $row) {
                    $raw = $row->{$column};
                    if ($raw === null || $raw === '') {
                        continue;
                    }

                    // Supprime espaces / séparateurs de milliers usuels
                    $normalized = str_replace([' ', ','], ['', '.'], (string) $raw);
                    $value = is_numeric($normalized) ? round((float) $normalized, 2) : 0;

                    DB::table('materials')->where('id', $row->id)->update([
                        $column => $value,
                    ]);
                }
            });

        DB::statement("ALTER TABLE materials MODIFY {$column} DECIMAL(15,2) NULL");
    }
}
