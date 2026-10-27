<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Services\Stock\StockoutRegularizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class StockoutRegularizationController extends Controller
{
    public $user;

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::guard('admin')->user();
            return $next($request);
        });
    }

    public function index(Request $request, StockoutRegularizationService $service)
    {
        $allowed = $this->allowedModules($service);
        if ($allowed === []) {
            abort(403, 'Sorry !! You are Unauthorized to edit journal entries !');
        }

        $moduleKey = $request->input('module');
        if (!isset($allowed[$moduleKey])) {
            $moduleKey = array_key_first($allowed);
        }

        $module = $service->module($moduleKey);
        $adjustmentAccountId = $service->adjustmentAccountId($moduleKey);
        $candidates = $service->candidates($moduleKey);

        return view('backend.pages.stock_regularization.index', compact(
            'allowed', 'moduleKey', 'module', 'adjustmentAccountId', 'candidates'
        ));
    }

    public function rectify(Request $request, StockoutRegularizationService $service, $module, $stockout_no)
    {
        $this->authorizeModule($service, $module);

        try {
            $result = $service->rectify($module, $stockout_no, (string) $request->input('force') === '1', $this->user->name);
        } catch (RuntimeException $e) {
            session()->flash('error', $e->getMessage());
            return back();
        }

        session()->flash('success', 'Sortie ' . $stockout_no . ' rectifiée : '
            . number_format($result['amount'], 2, ',', ' ')
            . ' basculés du compte d\'ajustement vers les comptes des fiches (montants, dates et n° de document conservés).');

        return back();
    }

    public function rectifyAll(StockoutRegularizationService $service, $module)
    {
        $this->authorizeModule($service, $module);

        $done = 0;
        $errors = [];

        foreach ($service->candidates($module) as $candidate) {
            if ($candidate['requires_force'] || $candidate['error']) {
                continue;
            }

            try {
                $service->rectify($module, $candidate['stockout_no'], false, $this->user->name);
                $done++;
            } catch (RuntimeException $e) {
                $errors[] = $candidate['stockout_no'] . ' : ' . $e->getMessage();
            }
        }

        session()->flash('success', $done . ' sortie(s) rectifiée(s).');
        if ($errors) {
            session()->flash('error', implode(' | ', $errors));
        }

        return back();
    }

    /**
     * @return array<string, string> clé => libellé des catégories que l'utilisateur peut redresser
     */
    protected function allowedModules(StockoutRegularizationService $service): array
    {
        $allowed = [];
        foreach ($service->modules() as $key => $module) {
            if ($this->user && $this->user->can($module['permission'])) {
                $allowed[$key] = $module['label'];
            }
        }

        return $allowed;
    }

    protected function authorizeModule(StockoutRegularizationService $service, string $module): void
    {
        if (!array_key_exists($module, $this->allowedModules($service))) {
            abort(403, 'Sorry !! You are Unauthorized to edit journal entries !');
        }
    }
}
