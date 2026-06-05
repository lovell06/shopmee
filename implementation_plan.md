# Implementation Plan - MoMo Simulated Payment Flow

This plan outlines the steps to complete the simulated e-wallet payment flow using MoMo. It integrates the MoMo Sandbox API, handles IPN callbacks for production/staging environments, and implements a local verification fallback to support seamless testing on `localhost`.

## User Review Required

> [!NOTE]
> 1. **MoMo Credentials**: The project currently contains placeholder MoMo Sandbox credentials in `.env`. The integration will use these credentials.
> 2. **Local Simulation Helper**: Because MoMo's IPN cannot call back to a `localhost` URL, we will introduce a `momo-verify` API endpoint. The frontend (on redirect to `localhost:3000/payment-success`) can call this endpoint to verify the transaction details (with MoMo signature validation) and mark the order as paid locally.

## Proposed Changes

---

### Configuration

#### [MODIFY] [config/services.php](file:///d:/Hoc_Tap/2025-2026_HK2/PHP/CK/shopmee/config/services.php)
Add MoMo configuration variables to read from the `.env` file:
- `momo.partner_code`
- `momo.access_key`
- `momo.secret_key`
- `momo.endpoint`
- `momo.redirect_url`
- `momo.ipn_url`

---

### Services

#### [NEW] [app/Services/MomoService.php](file:///d:/Hoc_Tap/2025-2026_HK2/PHP/CK/shopmee/app/Services/MomoService.php)
Create a new service dedicated to MoMo operations:
- `createPaymentUrl(Order $order)`: Builds request body, signs it, and sends it to the MoMo Sandbox API. Returns the `payUrl` or throws an error.
- `verifyCallbackSignature(array $data)`: Re-generates signature from callback/IPN parameters and compares it to the incoming signature.
- `processPaymentResult(array $data)`: Updates order status to `Paid` if `resultCode` is `0`, otherwise marks payment as failed.

---

### Controllers & Routing

#### [MODIFY] [app/Http/Controllers/Api/V1/OrderController.php](file:///d:/Hoc_Tap/2025-2026_HK2/PHP/CK/shopmee/app/Http/Controllers/Api/V1/OrderController.php)
- Update `checkout()`: Add check for `PaymentMethod::Momo`. Call `MomoService::createPaymentUrl()` and return the `payUrl`.
- Add `momoIpn(Request $request)`: Verifies MoMo IPN signature and processes payment status updates.
- Add `momoVerify(Request $request)`: Verifies return query parameters sent from frontend and updates payment status.

#### [MODIFY] [routes/api.php](file:///d:/Hoc_Tap/2025-2026_HK2/PHP/CK/shopmee/routes/api.php)
- Add route for IPN: `Route::post('payments/momo-ipn', [OrderController::class, 'momoIpn']);` (outside auth middleware).
- Add route for client verification: `Route::post('payments/momo-verify', [OrderController::class, 'momoVerify']);` (inside auth middleware).

---

## Verification Plan

### Automated Tests
We will execute existing unit/feature tests to ensure no regressions. We will also write standard manual integration verification.

### Manual Verification
1. **Checkout Flow**: Initiate checkout with `payment_method = 'momo'`. Verify that the API returns a response containing the MoMo `payUrl`.
2. **Sandbox Redirection**: Navigate to the returned `payUrl` and complete/fail the test payment in MoMo Sandbox.
3. **Redirection Callback / Verification**: Simulate redirect to the frontend with parameters, then trigger `/payments/momo-verify` to ensure the backend validates the signature and updates order status.
4. **IPN Handler**: Send a mock HTTP POST payload matching the MoMo IPN format to `/api/v1/payments/momo-ipn` and check if order status is updated.
