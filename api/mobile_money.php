<?php
/**
 * EduSuivi - API Mobile Money
 * 
 * STUB — En attente des clés API des opérateurs.
 * 
 * Opérateurs à intégrer :
 *   - M-Pesa (Vodacom) : https://openapiportal.m-pesa.com/
 *   - Airtel Money     : https://developers.airtel.africa/
 *   - Orange Money     : Orange Money Africa API
 *   - Africell Money   : API Africell (à recevoir)
 * 
 * Auteur : EduSuivi Team
 * Date   : <?= date('Y-m-d') ?>
 */

require_once __DIR__ . '/../includes/config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$action   = $data['action']   ?? 'initiate';
$operator = $data['operator'] ?? '';
$phone    = $data['phone']    ?? '';
$amount   = (int)($data['amount']   ?? 0);
$childId  = (int)($data['child_id'] ?? 0);
$ref      = $data['ref']      ?? '';

// ---- Validation de base ----
if (!$operator || !$phone || $amount <= 0 || !$childId) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit;
}

$allowedOperators = ['mpesa', 'airtel', 'orange', 'africell'];
if (!in_array($operator, $allowedOperators)) {
    echo json_encode(['success' => false, 'message' => 'Opérateur non supporté']);
    exit;
}

// Vérifier que l'enfant appartient au parent connecté
$stmt = $pdo->prepare("SELECT id FROM students WHERE id=? AND parent_id=?");
$stmt->execute([$childId, $currentUser['id']]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Accès refusé']);
    exit;
}

// ---- Routage vers l'opérateur ----
switch ($operator) {
    case 'mpesa':
        $result = initiateMpesa($phone, $amount, $ref, $childId);
        break;
    case 'airtel':
        $result = initiateAirtel($phone, $amount, $ref, $childId);
        break;
    case 'orange':
        $result = initiateOrange($phone, $amount, $ref, $childId);
        break;
    case 'africell':
        $result = initiateAfricell($phone, $amount, $ref, $childId);
        break;
    default:
        $result = ['success' => false, 'message' => 'Opérateur inconnu'];
}

// ---- Logger la tentative ----
try {
    $pdo->prepare("
        INSERT INTO mobile_money_transactions
            (student_id, parent_id, operator, phone, amount, reference, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ")->execute([
        $childId,
        $currentUser['id'],
        $operator,
        $phone,
        $amount,
        $ref,
        $result['success'] ? 'pending' : 'failed'
    ]);
} catch (Exception $e) {
    // Table peut ne pas encore exister — pas bloquant
}

echo json_encode($result);


// =====================================================================
// MPESA (Vodacom Congo)
// Doc: https://openapiportal.m-pesa.com/
// =====================================================================
function initiateMpesa(string $phone, int $amount, string $ref, int $childId): array
{
    /* TODO — Intégrer quand clés API disponibles
    
    $baseUrl     = 'https://openapi.m-pesa.com'; // Prod
    // $baseUrl  = 'https://sandbox.m-pesa.com';  // Sandbox
    $consumerKey = 'VOTRE_CONSUMER_KEY';
    $apiKey      = 'VOTRE_API_KEY';
    
    // 1. Obtenir le token de session
    $sessionUrl = $baseUrl . '/openapi/ipg/v2/vodacomCONGODRC/getSession/';
    // ... appel curl ...
    
    // 2. Initier le paiement C2B
    $c2bUrl = $baseUrl . '/openapi/ipg/v2/vodacomCONGODRC/c2bPayment/singleStage/';
    $payload = [
        'input_Amount'              => $amount,
        'input_Country'             => 'COD',
        'input_Currency'            => 'CDF',
        'input_CustomerMSISDN'      => $phone,
        'input_ServiceProviderCode' => 'VOTRE_SP_CODE',
        'input_ThirdPartyConversationID' => $ref,
        'input_TransactionReference'=> $ref,
        'input_PurchasedItemsDesc'  => 'Frais scolaires EduSuivi'
    ];
    // ... appel curl + retour JSON ...
    
    */

    // STUB temporaire
    return [
        'success'  => true,
        'message'  => 'M-Pesa : demande de paiement envoyée (simulation)',
        'ref'      => $ref,
        'status'   => 'pending',
        'provider' => 'mpesa'
    ];
}

// =====================================================================
// AIRTEL MONEY
// Doc: https://developers.airtel.africa/documentation
// =====================================================================
function initiateAirtel(string $phone, int $amount, string $ref, int $childId): array
{
    /* TODO — Intégrer quand clés API disponibles
    
    $baseUrl      = 'https://openapiuat.airtel.africa'; // UAT
    // $baseUrl   = 'https://openapi.airtel.africa';    // Prod
    $clientId     = 'VOTRE_CLIENT_ID';
    $clientSecret = 'VOTRE_CLIENT_SECRET';

    // 1. OAuth2 token
    // 2. Initier la transaction
    $payload = [
        'reference'   => $ref,
        'subscriber'  => ['country' => 'CD', 'currency' => 'CDF', 'msisdn' => $phone],
        'transaction' => ['amount' => $amount, 'country' => 'CD', 'currency' => 'CDF', 'id' => $ref]
    ];
    
    */

    return [
        'success'  => true,
        'message'  => 'Airtel Money : demande de paiement envoyée (simulation)',
        'ref'      => $ref,
        'status'   => 'pending',
        'provider' => 'airtel'
    ];
}

// =====================================================================
// ORANGE MONEY
// Doc: Orange Money Africa API (obtenir via le portail Orange)
// =====================================================================
function initiateOrange(string $phone, int $amount, string $ref, int $childId): array
{
    /* TODO — Intégrer quand clés API disponibles
    
    $baseUrl      = 'https://api.orange.com/orange-money-webpay/cd/v1';
    $authorization = 'Basic VOTRE_BASE64_KEY';
    
    // 1. Obtenir access_token
    // 2. Initier le paiement
    $payload = [
        'merchant_key' => 'VOTRE_MERCHANT_KEY',
        'currency'     => 'XAF', // ou CDF selon le pays
        'order_id'     => $ref,
        'amount'       => $amount,
        'return_url'   => 'https://votredomaine.com/edusuivi/api/mobile_money_callback.php',
        'cancel_url'   => 'https://votredomaine.com/edusuivi/parent/fees.php',
        'notif_url'    => 'https://votredomaine.com/edusuivi/api/mobile_money_webhook.php',
        'lang'         => 'fr',
        'reference'    => $ref
    ];
    
    */

    return [
        'success'  => true,
        'message'  => 'Orange Money : demande de paiement envoyée (simulation)',
        'ref'      => $ref,
        'status'   => 'pending',
        'provider' => 'orange'
    ];
}

// =====================================================================
// AFRICELL MONEY
// Doc: À recevoir via le portail développeurs Africell
// =====================================================================
function initiateAfricell(string $phone, int $amount, string $ref, int $childId): array
{
    /* TODO — Intégrer quand clés API disponibles
    
    // Contacter le support Africell pour obtenir les credentials API
    // Endpoint et payload à définir selon leur documentation
    
    */

    return [
        'success'  => true,
        'message'  => 'Africell Money : demande de paiement envoyée (simulation)',
        'ref'      => $ref,
        'status'   => 'pending',
        'provider' => 'africell'
    ];
}
