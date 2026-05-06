# Lineforge Execution Layer

Lineforge execution is designed as a compliant, provider-aware control plane. Paper mode is the default. Demo or live execution must pass provider health, account status, risk limits, stale-data checks, duplicate-order checks, and explicit user authorization.

## Provider Rules

- Kalshi uses only the official Kalshi API. API keys and private keys are accepted only by the backend and must be encrypted at rest before signed calls are enabled.
- FanDuel is data-only unless FanDuel provides an approved execution API. Lineforge does not automate login, wager placement, MFA, geolocation, anti-bot flows, or browser wagering.
- PaperProvider simulates execution locally and is the first supported route for rule testing.

## ProviderInterface

Every provider implements:

- `connectAccount()`
- `getAccountStatus()`
- `getBalance()`
- `getMarkets()`
- `getMarketPrice()`
- `getOrderBook()`
- `placeOrder()`
- `cancelOrder()`
- `sellPosition()`
- `getOpenOrders()`
- `getPositions()`
- `getTradeHistory()`
- `healthCheck()`

## Data Models

- `UserAccount`
- `ProviderConnection`
- `Market`
- `Watchlist`
- `ExecutionRule`
- `RuleCondition`
- `RuleAction`
- `Order`
- `Position`
- `Trade`
- `AuditLog`
- `RiskLimit`
- `ProviderHealth`

The current standalone site stores these in `Website/storage/execution-state.json`. Production should move the same schema into a transactional database with encrypted secret storage and append-only audit retention.

## Safeguards

- Live mode requires explicit opt-in.
- Rules start paused.
- Manual confirmation is the default.
- Idempotency/client order IDs are generated for rule actions.
- Duplicate orders are blocked unless the rule explicitly allows them.
- Stale data, degraded provider health, insufficient balance, location/legal eligibility uncertainty, self-exclusion, emergency stop, cooldowns, and risk-limit breaches block execution.
- Every evaluation, skip, block, error, and provider response is written to the audit log.

## Kalshi Notes

The Kalshi provider is wired for the official demo and production API roots:

- Demo: `https://external-api.demo.kalshi.co/trade-api/v2`
- Live: `https://external-api.kalshi.com/trade-api/v2`

Signed requests use Kalshi's RSA-PSS requirement through the backend Node signer at `Website/tools/kalshi-sign.js`. Credentials are still blocked unless PHP OpenSSL and `LINEFORGE_CREDENTIAL_KEY` or `AEGIS_CREDENTIAL_KEY` are configured so private keys can be encrypted at rest.
