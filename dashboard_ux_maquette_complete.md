# Maquette UX Complete - Dashboard AMM-ERP

## 1. Objectif UX
Le dashboard doit permettre a chaque role de:
- Voir les priorites en moins de 5 secondes.
- Decider et agir en 1 clic.
- Detecter les risques (retards, contrats, qualite de donnees).
- Suivre la performance sur une periode.

Principe directeur: Information critique en haut, analyse au milieu, execution en bas.

## 2. Architecture d'information (Desktop)

### Zone A - Barre de pilotage (sticky)
Contenu:
- Titre dashboard + role (Direction, Reception, Finance, Juridique).
- Filtres globaux: periode, service, statut, urgence, agent.
- Timestamp: derniere synchro des donnees.
- Actions rapides: Nouveau courrier, Assigner, Export CSV/PDF.

Regles UX:
- Toujours visible pendant le scroll.
- Filtres persistants entre pages (session).

### Zone B - Bandeau alertes critiques
Contenu:
- Overdue a traiter aujourd'hui.
- SLA proches d'echeance (J-2, J-1, J0).
- Contrats a risque (J-30, J-15, J-7).
- Dossiers sans preuve alors que statut termine/clos.

Regles UX:
- Trier par impact (Critique > Eleve > Moyen).
- Chaque alerte a un CTA direct: Voir, Assigner, Escalader.

### Zone C - KPI principaux (4 a 6 cartes)
Cartes recommandees:
- Dossiers actifs.
- Overdue.
- Assignation sous 24h (%).
- Delai moyen Recu -> Clos.
- Taux de cloture avec preuve (%).
- Evolution vs periode precedente (%).

Regles UX:
- Afficher variation (hausse/baisse) avec contexte periode.
- Couleurs semantiques coherentes (danger, warning, success).

### Zone D - Bloc analyse (2 colonnes)
Colonne gauche:
- Courbe volume entrant/sortant (hebdo/mensuel).
- Repartition par statut (barres ou donut).

Colonne droite:
- Charge par service/agent.
- Top 5 causes de retard.

Regles UX:
- Graphiques lisibles en 10 secondes max.
- Legendes courtes, labels explicites, pas de surcharge visuelle.

### Zone E - Liste operationnelle "A traiter maintenant"
Table priorisee:
- Colonnes: Reference, Objet, Service, Responsable, Echeance, Statut, Temps restant.
- Tri par criticite par defaut.
- Recherche instantanee + filtres rapides (chips).

Actions ligne:
- Ouvrir dossier.
- Assigner/reassigner.
- Marquer en cours.
- Escalader.

Regles UX:
- Max 7 colonnes visibles pour garder la lisibilite.
- Actions frequentes accessibles sans ouvrir le detail.

### Zone F - Qualite des donnees
Widgets:
- % dossiers sans reference_document.
- % dossiers sans piece jointe.
- % dossiers clos sans preuve.
- Champs obligatoires manquants.

Regles UX:
- Afficher impact concret: "X dossiers a corriger".
- Lien direct vers la liste des enregistrements incomplets.

## 3. Architecture Mobile (priorisation)
Ordre d'affichage:
1. Alertes critiques.
2. KPI essentiels (2 lignes max).
3. Liste "A traiter maintenant" (top 10).
4. Graphique principal unique.
5. Actions rapides en bas (bottom action bar).

Regles mobile:
- Aucun tableau large non scrollable.
- Cards compactes, texte court, CTA pleine largeur.
- Filtres en panneau coulissant (drawer).

## 4. Variantes par role

### Direction
Focus:
- Risques, SLA, tendances, arbitrage.
KPI prioritaires:
- Overdue, delai moyen, taux de resolution, contrats a risque.
Bloc special:
- "Decisions en attente" avec escalades.

### Reception
Focus:
- Saisie, assignation, zero non-assignes.
KPI prioritaires:
- Receptions du jour, non assignes, urgents.
Bloc special:
- "Controle qualite encodage" (reference + scan obligatoires).

### Finance
Focus:
- Valeurs contrats, paiements, expirations.
KPI prioritaires:
- Montant total, montant paye, expirant 30j, expirant 7j.
Bloc special:
- "Contrats a risque financier".

### Juridique
Focus:
- Conformite, pieces justificatives, echeances.
KPI prioritaires:
- Contrats a surveiller, courriers juridiques en attente, documents expires.
Bloc special:
- "Conformite documentaire".

## 5. Composants UX indispensables
- Empty states utiles: expliquer pourquoi la liste est vide et quoi faire.
- Skeleton loaders: eviter le vide pendant chargement.
- Etat erreur comprehensible: message + action de reprise.
- Toast de confirmation pour actions critiques.
- Historique court (audit recent) visible depuis le dashboard.

## 6. Microcopy recommande (FR)
- "Priorites du jour"
- "A traiter dans les 24h"
- "Risque eleve"
- "Action requise"
- "Donnees incompletes"
- "Derniere mise a jour: HH:MM"

## 7. Regles visuelles
- Une seule couleur d'accent primaire pour la marque.
- Couleurs d'etat reservees aux statuts (danger/warning/success/info).
- Taille mini texte: 14px mobile, 15-16px desktop.
- Contraste WCAG AA minimum.
- Icônes seulement en support du texte, jamais seules sur informations critiques.

## 8. Definition de succes (UX + metier)
Le dashboard est valide si:
- Un utilisateur identifie ses 3 priorites en moins de 10 secondes.
- Une alerte critique est actionnable en 1 clic.
- Le taux d'assignation <24h augmente semaine apres semaine.
- Les dossiers clos sans preuve diminuent de facon continue.

## 9. Backlog implementation priorise
Sprint 1:
- Barre de pilotage sticky + filtres globaux persistants.
- Bandeau alertes critiques.
- KPI harmonises avec variation periode.

Sprint 2:
- Liste "A traiter maintenant" avec actions inline.
- Bloc qualite de donnees.
- Version mobile priorisee.

Sprint 3:
- Personnalisation avancee par role.
- Export intelligent selon filtres actifs.
- Optimisation performance et telemetrie UX.

## 10. Wireframe textuel rapide
Desktop:
- Ligne 1: [Pilotage]
- Ligne 2: [Alertes critiques]
- Ligne 3: [KPI x 4-6]
- Ligne 4: [Graphiques 2/3] [Charge/causes 1/3]
- Ligne 5: [Table priorites]
- Ligne 6: [Qualite donnees]

Mobile:
- [Alertes]
- [KPI compact]
- [Top priorites]
- [Graphique principal]
- [Actions rapides bas ecran]

---

Document de reference UX pour implementation AMM-ERP.
