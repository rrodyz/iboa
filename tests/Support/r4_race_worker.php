<?php

/**
 * [R4] Worker de course sur les points de bascule métier — processus INDÉPENDANT.
 *
 * Trois moments où une double exécution simultanée aurait un coût réel :
 *   - deux encaissements atteignent le seuil en même temps → deux bons de
 *     préparation, donc deux autorisations de chargement pour une commande ;
 *   - deux responsables approuvent la même demande → deux bons, et deux
 *     événements ProductionAuthorized, donc potentiellement deux OF ;
 *   - deux validations du même bon de livraison → deux factures, donc deux
 *     écritures comptables sur une seule sortie de stock.
 *
 * Chaque worker ouvre sa propre connexion PDO : un test mono-processus ne
 * prouverait que la logique applicative, jamais le verrouillage.
 *
 * Appel :
 *   php r4_race_worker.php <action> <id> <userId> <startAtMicrotime> [montant]
 *
 * Sortie : une ligne JSON sur STDOUT. Codes de sortie :
 *   0 = action réalisée, 2 = refus métier attendu, 3 = erreur inattendue.
 */

use App\Models\DeliveryNote;
use App\Models\Order;
use App\Models\User;
use App\Services\BonPreparationService;
use App\Services\CommercialWorkflowService;
use App\Services\DeliveryNoteService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$action = $argv[1];
$id = (int) $argv[2];
$userId = (int) $argv[3];
$startAt = (float) $argv[4];
$montant = isset($argv[5]) ? (int) $argv[5] : 0;

// Départ synchronisé : les workers entrent en section critique ensemble.
while (microtime(true) < $startAt) {
    usleep(1000);
}

try {
    $user = User::findOrFail($userId);
    Auth::login($user);
    app()->instance('current_company', $user->company);

    switch ($action) {
        case 'cash_payment_bp':
            $bp = app(BonPreparationService::class)
                ->createForCashOrder(Order::findOrFail($id), $montant, 'RACE-'.getmypid());
            $payload = ['result' => 'bp_created', 'bp_id' => $bp->id, 'order_id' => $id];
            break;

        case 'decide_preparation':
            $ordre = app(CommercialWorkflowService::class)
                ->decidePreparationApproval(Order::findOrFail($id), true, 'approbation concurrente');
            $payload = ['result' => 'approved', 'order_id' => $ordre->id];
            break;

        case 'validate_delivery_note':
            $bl = app(DeliveryNoteService::class)->validate(DeliveryNote::findOrFail($id));
            $payload = ['result' => 'validated', 'delivery_note_id' => $bl->id];
            break;

        default:
            throw new InvalidArgumentException("Action inconnue : {$action}");
    }

    fwrite(STDOUT, json_encode($payload, JSON_UNESCAPED_UNICODE));
    exit(0);
} catch (RuntimeException $exception) {
    fwrite(STDOUT, json_encode([
        'result' => 'blocked',
        'action' => $action,
        'id' => $id,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE));
    exit(2);
} catch (Throwable $exception) {
    fwrite(STDOUT, json_encode([
        'result' => 'error',
        'action' => $action,
        'id' => $id,
        'class' => $exception::class,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE));
    exit(3);
}
