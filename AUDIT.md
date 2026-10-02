# Audit initial

## 1. Comportement observable

au lancement de index.php : 4 lignes observable
Le paiement Stripe est simulé pour 143,82 €.
La sauvegarde affiche la réservation 1001 avec le statut confirmed.
L’envoi d’un email à <lea@example.com> est simulé.
Le total final est 143,82 €, soit deux billets à 79,90 € avec 10 % de remise VIP.
Aucune erreur apparente dans cette sortie.

Vérification du 2 octobre 2026 : `php index.php` produit les quatre lignes décrites ci-dessus.

Au lancement de `php tests/characterization.php` : `Passed: 39, Failed: 0`.
Les 39 assertions sont réparties sur 16 scénarios : client standard, VIP, Pass 3 jours, cumul des remises (100 € × 90 % − 10 € = 80 €), plusieurs lignes de billets, confirmation répétée et dix cas d’erreur.
Les cas d’erreur couvrent une réservation vide, un email invalide, les quantités nulles et négatives, les prix nuls et négatifs, les totaux nuls et négatifs après remise, PayFast et un moyen de paiement inconnu. Chaque cas vérifie le type et le message de l’exception ainsi que le maintien du statut pending.
La confirmation répétée est actuellement autorisée : le scénario produit deux paiements et deux emails simulés. Ce comportement est caractérisé avant sa correction future.
`TestRunner::run()` relève les exceptions inattendues dans le bilan et permet aux scénarios suivants de continuer. `throws()` vérifie les exceptions attendues. Aucun tampon de sortie n’est utilisé.

## 2. Problèmes identifiés

| Problème | Catégorie | Constat |
| --- | --- | --- |
| Responsabilités regroupées dans BookingService | Conception | La méthode confirm() valide la réservation, calcule le total, applique les remises, déclenche le paiement, change le statut, simule la sauvegarde et envoie l’email. |
| Dépendances créées directement | Couplage | BookingService instancie StripeClient et EmailService dans confirm(), sans injection de dépendances. |
| Règles de remise codées en dur | Règles métier | La remise VIP de 10 % et la réduction de 10 € du Pass 3 jours sont définies directement dans confirm(). |
| Prix des billets non validés | Validation | Ticket accepte un prix de type float sans contrôle ; confirm() vérifie la quantité, mais ne vérifie pas le prix. |
| Total négatif possible | Calcul du montant | La réduction de 10 € du Pass 3 jours est soustraite sans contrôle du montant final ; un total initial inférieur à 10 € devient négatif. |
| Montants calculés en nombres flottants | Précision monétaire | Les prix et le total utilisent le type float ; aucun arrondi monétaire explicite n’est appliqué avant le paiement. |
| Confirmation répétée possible | Gestion du statut | confirm() ne vérifie pas le statut initial de la réservation ; une réservation déjà confirmed peut déclencher à nouveau le paiement et l’email. |
| Paiement PayFast non implémenté | Fonctionnalité | La branche payfast lève systématiquement l’exception « PayFast not implemented ». |
| Sauvegarde simulée | Persistance | La sauvegarde de la réservation se limite à un echo de « SQL INSERT » ; aucune requête SQL n’est exécutée dans confirm(). |
| État de la réservation modifiable directement | Encapsulation | Les propriétés items, status et passType de Booking sont publiques et peuvent être modifiées directement. |
| Couverture des effets de bord limitée | Testabilité | Les calculs, les validations, les erreurs de paiement et la confirmation répétée sont caractérisés. L’ordre et le nombre des traces paiement, sauvegarde et email ne sont pas vérifiés automatiquement ; les dépendances créées directement rendent leur isolation difficile. |

## 3. Nos trois priorités

1/Compléter les tests de caractérisation : protéger les comportements actuels avant les refactorings. Sans ces tests, une modification pourrait introduire une régression non détectée. Cette première étape est réalisée pour les règles principales avec 39 assertions ; les effets de bord restent à renforcer lors de l’isolation des dépendances.

2/Sécuriser les montants et la confirmation : contrôler les prix, empêcher les montants négatifs et la confirmation répétée d’une réservation. Ces problèmes peuvent entraîner des totaux incorrects ou des paiements répétés.

3/Séparer les responsabilités de BookingService : isoler la tarification, le paiement et les actions après confirmation pour faciliter les évolutions. Actuellement, modifier une règle risque d’affecter les autres traitements.

## 4. Risques avant refactoring

- Régression sur la tarification : déplacer le calcul du total pourrait  modifier les remises actuelles ou leur ordre d’application. Les tests  doivent protéger le comportement initial avant de faire évoluer les règles.

- Modification du déroulement de la confirmation : séparer les responsabilités pourrait changer l’ordre paiement, changement de statut, sauvegarde et email. Une réservation ne doit pas être confirmée si le paiement échoue.

- Perte ou duplication d’actions : réorganiser la confirmation pourrait supprimer l’envoi de l’email ou déclencher plusieurs fois le paiement et les notifications.

- Erreurs sur les montants : modifier les calculs ou convertir les euros en centimes pour PayFast pourrait introduire des erreurs d’arrondi ou de conversion.

- Régressions non détectées : les 39 assertions protègent les règles principales et les erreurs actuelles, mais ne vérifient pas automatiquement les sorties ni leur ordre. Le paiement est simulé ; aucune panne réseau, base de données ou messagerie n’est exercée.

## 5. Bilan du ticket #101

L’application a été exécutée, onze signaux d’alerte sont classés, trois priorités sont justifiées et leurs risques sont documentés. Les tests de caractérisation des règles principales ont été complétés et passent. Les attendus du ticket d’audit sont couverts ; les corrections métier et la séparation des responsabilités relèvent des évolutions suivantes.
