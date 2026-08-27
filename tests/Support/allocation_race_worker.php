<?php

/**
 * [P1-D] Worker de course allocation — processus INDÉPENDANT.
 *
 * Même pattern que tests/Support/transfer_race_worker.php (P1-F-C) et
 * tests/Support/credit_race_worker.php : chaque worker ouvre sa propre
 * connexion PDO, seule façon de prouver qu'un lockForUpdate() sérialise
 * réellement deux allocateMaterialLot() concurrents sur la même bobine.
 *
 * Appel :
 *   php allocation_race_worker.php <orderId> <lotId> <coilId> <quantity> <userId> <startAtMicrotime>
 *
 * Sortie : une ligne JSON sur STDOUT. Codes de sortie :
 *   0 = allocation réalisée, 2 = refus métier attendu (quantité insuffisante), 3 = erreur inattendue.
 */

use App\Models\StockLot;
use App\Models\User;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\ReservationService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $orderId, $lotId, $coilId, $quantity, $userId, $startAt] = $argv;

while (microtime(true) < (float) $startAt) {
    usleep(1000);
}

try {
    $user = User::findOrFail((int) $userId);
    Auth::login($user);
    app()->instance('current_company', $user->company);

    $order = ProductionOrder::findOrFail((int) $orderId);
    $lot = StockLot::findOrFail((int) $lotId);
    $coil = Coil::findOrFail((int) $coilId);

    app(ReservationService::class)->allocateMaterialLot($order, $lot, (float) $quantity, $coil);

    fwrite(STDOUT, json_encode(['result' => 'allocated', 'order_id' => (int) $orderId], JSON_UNESCAPED_UNICODE));
    exit(0);
} catch (\Illuminate\Validation\ValidationException $exception) {
    fwrite(STDOUT, json_encode([
        'result' => 'blocked',
        'order_id' => (int) $orderId,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE));
    exit(2);
} catch (Throwable $exception) {
    fwrite(STDOUT, json_encode([
        'result' => 'error',
        'order_id' => (int) $orderId,
        'class' => $exception::class,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE));
    exit(3);
}
