# Audit initial

## 1. Comportement observable

`BookingService::confirm()` fait tout, dans cet ordre : validation, calcul du total, remises, paiement, statut `confirmed`, « insertion SQL » (un `echo`), e-mail (un `echo`).

- Remise VIP : −10 %. Remise pass 3 jours : −10 €.
- Paiement : Stripe fonctionne, PayFast lève « not implemented ».
- `php index.php` affiche `TOTAL FINAL: 143.82`.
- Les 3 tests actuels figent : standard = 100 €, VIP = 90 €, pass 3 jours = 110 €.
- `SmsClient`, `LoyaltyService`, `AnalyticsClient` et `PayFastSdk` ne sont jamais appelés.

## 2. Problèmes identifiés

| # | Problème | Catégorie | Impact |
|---|---|---|---|
| 1 | `confirm()` fait tout : validation, prix, paiement, persistance, notification. | SRP | Toute évolution touche cette méthode, et elle est difficile à tester. |
| 2 | `StripeClient` et `EmailService` sont créés avec `new` dans la méthode. | DIP | Impossible de les remplacer ou de les simuler. |
| 3 | Le moyen de paiement est choisi par un `if/elseif` sur une chaîne, et PayFast n'est pas branché. | OCP | Chaque nouveau paiement oblige à modifier `confirm()`. |
| 4 | Les remises sont codées en dur, cumulables, sans plancher. | OCP | Le total peut être ≤ 0, et Stripe lève alors `Invalid amount`. |
| 5 | Chaînes magiques (`'vip'`, `'3days'`) et montants en `float`. | Modélisation | Une faute de frappe passe sans erreur, les arrondis sont imprécis, et PayFast attend des centimes. |
| 6 | Les effets de bord sont des `echo`, 3 services sont inutilisés et les tests ne couvrent que le cas nominal. | Testabilité | Impossible de vérifier la persistance ou les notifications, et les cas d'erreur ne sont pas testés. |

## 3. Nos trois priorités

1. **Abstractions et injection de dépendances** : une interface de paiement (Stripe et un adaptateur PayFast), injectée dans `BookingService`. Corrige les problèmes 1, 2 et 3.
2. **Remises en règles séparées** : une règle par remise, avec un total jamais négatif. Corrige le problème 4.
3. **Modèle et tests** : enums, montants en centimes, notifications branchées, tests complétés. Corrige les problèmes 5 et 6.

## 4. Risques avant refactoring

- **Tests à adapter :** ils figent les anciennes règles de remise, qui doivent évoluer.
- **Arrondis :** passer aux centimes peut décaler les totaux de quelques centimes.
- **`PayFastSdk` non modifiable :** l'adaptateur doit tout absorber (centimes, devise, référence, échec).
- **Paiement avant confirmation :** si l'insertion ou l'e-mail échoue après le paiement, le client est débité sans réservation.
- **Tests manquants :** validation, PayFast, cumul des remises, total ≤ 0. À écrire avant de toucher au code.
