# Data Processing

## 1. Regulatory Baseline
- Data processing is aligned to 152-FZ requirements for personal data handling.
- This document describes storage, retention and deletion process for MVP.

## 2. Data Retention Matrix
- Account profile (`phone`, `email`): retained while account is active.
- Finance records (transactions, plans): retained while account is active.
- Support chat: retained for service quality and dispute resolution.
- Billing and audit records: retained according to financial/legal obligations.

## 3. Deletion Process
- Deletion request is initiated through support chat.
- Admin verifies ownership and legal constraints.
- Data removal is executed through controlled administrative procedures.
- If immediate deletion is not possible due to legal obligations, data is restricted and removed when retention window expires.

## 4. Access Control
- Only authorized roles can access corresponding datasets.
- Admin actions affecting user data are logged in `audit_logs`.

## 5. Technical Controls in Repository
- Secrets: environment variables only.
- API hardening: request validation, rate limits, security headers.
- Payment webhooks: idempotent processing.
