<?php

/**
 * [P1-D QA gate — §8] Worker de course CROISÉE — processus INDÉPENDANT.
 *
 * Même pattern que allocation_race_worker.php (P1-D) / transfer_race_worker.php
 * (P1-F-C) / credit_race_worker.php (Ventes) : connexion PDO propre, seule façon
 * de prouver qu'un ordre de verrous cohérent sérialise réellement deux SERVICES
 * DIFFÉRENTS (allocation production ET transfert stock) sur le même produit/lot,
 * sans jamais s'attendre mutuellement (deadlock).
 *
 * Appel :
 *   php cross_flow_race_worker.php allocate <orderId> <lotId> <quantity> <userId> <startAt>
 *   php cross_flow_race_worker.php ship <transferId> <userId> <startAt>
 *
 * Sortie : une ligne JSON sur STDOUT. Codes de sortie :
 *   0 = opération réalisée, 2 = refus métier attendu, 3 = erreur inattendue
 *   (SQLSTATE / deadlock brut — ce que ce test doit précisément prouver absent).
 */

use App\Models\StockLot;
use App\Models\StockTransfer;
use App\Models\User;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\ReservationService;
use App\Services\StockTransferService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$action = $argv[1];

try {
    if ($action === 'allocate') {
        [, , $orderId, $lotId, $quantity, $userId, $startAt] = $argv;
        $user = User::findOrFail((int) $userId);
        Auth::login($user);
        app()->instance('current_company', $user->company);

        while (microtime(true) < (float) $startAt) {
            usleep(1000);
        }

        $order = ProductionOrder::findOrFail((int) $orderId);
        $lot = StockLot::findOrFail((int) $lotId);
        app(ReservationService::class)->allocateMaterialLot($order, $lot, (float) $quantity);

        fwrite(STDOUT, json_encode(['result' => 'allocated'], JSON_UNESCAPED_UNICODE));
        exit(0);
    }

    if ($action === 'ship') {
        [, , $transferId, $userId, $startAt] = $argv;
        $user = User::findOrFail((int) $userId);
        Auth::login($user);
        app()->instance('current_company', $user->company);

        while (microtime(true) < (float) $startAt) {
            usleep(1000);
        }

        $transfer = StockTransfer::findOrFail((int) $transferId);
        app(StockTransferService::class)->ship($transfer);

        fwrite(STDOUT, json_encode(['result' => 'shipped'], JSON_UNESCAPED_UNICODE));
        exit(0);
    }

    throw new \InvalidArgumentException("Action inconnue : {$action}");
} catch (\Illuminate\Validation\ValidationException|\RuntimeException $exception) {
    fwrite(STDOUT, json_encode([
        'result' => 'blocked',
        'action' => $action,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE));
    exit(2);
} catch (Throwable $exception) {
    fwrite(STDOUT, json_encode([
        'result' => 'error',
        'action' => $action,
        'class' => $exception::class,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE));
    exit(3);
}
