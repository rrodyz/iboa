<?php

/**
 * [P1-F-C] Worker de course transfert — processus INDÉPENDANT.
 *
 * Même pattern que tests/Support/credit_race_worker.php : chaque worker ouvre
 * sa propre connexion PDO, c'est la seule façon de prouver qu'un lockForUpdate()
 * sérialise réellement deux ship() concurrents sur le même ProductStock/StockLot.
 *
 * Appel :
 *   php transfer_race_worker.php <transferId> <userId> <startAtMicrotime>
 *
 * Sortie : une ligne JSON sur STDOUT. Codes de sortie :
 *   0 = expédition réalisée, 2 = refus métier attendu (stock insuffisant), 3 = erreur inattendue.
 */

use App\Models\StockTransfer;
use App\Models\User;
use App\Services\StockTransferService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $transferId, $userId, $startAt] = $argv;
$transferId = (int) $transferId;

// Départ synchronisé : les deux workers entrent en section critique ensemble.
while (microtime(true) < (float) $startAt) {
    usleep(1000);
}

try {
    $user = User::findOrFail((int) $userId);
    Auth::login($user);
    app()->instance('current_company', $user->company);

    $transfer = StockTransfer::findOrFail($transferId);
    app(StockTransferService::class)->ship($transfer);

    fwrite(STDOUT, json_encode(['result' => 'shipped', 'transfer_id' => $transferId], JSON_UNESCAPED_UNICODE));
    exit(0);
} catch (RuntimeException $exception) {
    fwrite(STDOUT, json_encode([
        'result' => 'blocked',
        'transfer_id' => $transferId,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE));
    exit(2);
} catch (Throwable $exception) {
    fwrite(STDOUT, json_encode([
        'result' => 'error',
        'transfer_id' => $transferId,
        'class' => $exception::class,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE));
    exit(3);
}
