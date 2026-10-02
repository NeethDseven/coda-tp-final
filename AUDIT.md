# Audit initial

## 1. Comportement observable

au lancement de index.php : 4 lignes observable
Le paiement Stripe est simulé pour 143,82 €.
La sauvegarde affiche la réservation 1001 avec le statut confirmed.
L’envoi d’un email à <lea@example.com> est simulé.
Le total final est 143,82 €, soit deux billets à 79,90 € avec 10 % de remise VIP.
Aucune erreur apparente dans cette sortie.

au lancement de characterization.php : Passed: 4, Failed: 0
Les quatre vérifications passent : total d’un client standard, passage au statut confirmed, remise VIP actuelle de 10 % et réduction actuelle de 10 € pour le Pass 3 jours.

## 2. Problèmes identifiés

| Problème | Catégorie | Constat |
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
| Couverture des tests limitée | Tests | Les quatre vérifications couvrent le total standard, le statut confirmé et les deux remises séparément ; les erreurs de validation, le cumul des remises, les moyens de paiement et la confirmation répétée ne sont pas testés. |

## 3. Nos trois priorités

1/Compléter les tests de caractérisation : protéger les comportements actuels avant les refactorings. Sans ces tests, une modification pourrait introduire une régression non détectée.

2/Sécuriser les montants et la confirmation : contrôler les prix, empêcher les montants négatifs et la confirmation répétée d’une réservation. Ces problèmes peuvent entraîner des totaux incorrects ou des paiements répétés.

3/Séparer les responsabilités de BookingService : isoler la tarification, le paiement et les actions après confirmation pour faciliter les évolutions. Actuellement, modifier une règle risque d’affecter les autres traitements.

## 4. Risques avant refactoring

- Régression sur la tarification : déplacer le calcul du total pourrait  modifier les remises actuelles ou leur ordre d’application. Les tests  doivent protéger le comportement initial avant de faire évoluer les règles.

- Modification du déroulement de la confirmation : séparer les responsabilités pourrait changer l’ordre paiement, changement de statut, sauvegarde et email. Une réservation ne doit pas être confirmée si le paiement échoue.

- Perte ou duplication d’actions : réorganiser la confirmation pourrait supprimer l’envoi de l’email ou déclencher plusieurs fois le paiement et les notifications.

- Erreurs sur les montants : modifier les calculs ou convertir les euros en centimes pour PayFast pourrait introduire des erreurs d’arrondi ou de conversion.

- Régressions non détectées : les quatre tests actuels ne couvrent pas les erreurs de validation, le cumul des remises ou la confirmation répétée. Il faut compléter ces tests avant les modifications importantes.
