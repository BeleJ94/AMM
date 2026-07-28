# MÉMO - PROPOSITION D'AMÉLIORATION DU SYSTÈME AMM-ERP
## Gestion de Projets & Jalons

---

**De :** Équipe projet AMM-ERP  
**À :** Client / Direction  
**Date :** 16 avril 2026  
**Objet :** Recommandations pour optimiser le pilotage stratégique et la coordination

---

## SOMMAIRE EXÉCUTIF

Le système AMM-ERP actuel **centralise efficacement les courriers individuels** mais manque d'une **vue d'ensemble stratégique** pour les initiatives de long terme. 

**Nous recommandons l'ajout d'un module de gestion de projets** permettant de :
- 📌 **Regrouper les initiatives métier** (plusieurs courriers + tâches en un seul projet)
- 📅 **Visualiser les phases clés** (jalons, deadlines critiques)
- 👥 **Équilibrer la charge** par équipe et projet
- 🎯 **Piloter par Direction** avec une roadmap unifiée

**Impact attendu :** Réduction des retards cross-projet, meilleure coordination, visibilité stratégique ++

---

## 1. SITUATION ACTUELLE - POINTS FORTS

✅ **Courrier de qualité :**
- Traçabilité complète (qui, quand, quoi)
- Respect des deadlines individuelles
- Documents gérés avec versionnage
- Notifications et alertes efficaces

✅ **Rôles bien définis :**
- Chaque utilisateur a des responsabilités claires
- Dashboard adapté par profil (Direction, Finance, Juridique)
- Assignations contrôlées et tracées

✅ **Suivi financier et contractuel :**
- Alertes écheances contrats (J-30, J-15, J-7)
- Gestion des paiements
- KPIs robustes

---

## 2. LES DÉFIS NON COUVERTS

### 🔴 **Défi #1 : Fragmentation des initiatives métier**

**Situation :** Une initiative client complexe (ex: "Onboarding Partners 2026") génère 5-10 courriers distincts.

| Courrier | Responsable | Deadline | Statut |
|----------|---|---|---|
| Demande licence | Agent 1 | 20/04 | En cours |
| Contrat commercial | Juridique | 25/04 | Reçu |
| Devis IT | Finance | 22/04 | Assigné |
| Setup technique | Agent 2 | 29/04 | Acausé |
| Intégration système | Agent 3 | 30/04 | Non assigné |

**Problème :** 
- Aucune vue unique = Risque d'oublis
- Chef de projet n'existe pas dans le système
- Dépendances invisibles (ex: ne peut débuter setup avant signature contrat)
- Direction ne voit que des courriers isolés, pas le projet global

### 🔴 **Défi #2 : Pas de calendrier partagé par initiative**

**Situation :** Les agents ne voient que **leurs courriers en deadline**, pas la **charge projet globale**.

**Conséquence :** 
- Surcharge invisible = risque de retard cascadant
- Pas de prévention des goulots (ex: 3 juristes surchargés le 20 avril)
- Planning équipe = impossible à établir

### 🔴 **Défi #3 : Jalons stratégiques flous**

**Situation :** Comment suivre un projet comme *"Migration ERP 2026"* avec phases critiques en 6 mois ?

- Préparation données → fin mai (responsable finance)
- Recette UAT → fin juillet (responsable métier)
- Go-live → 15 août (tous)
- Support post-go → sept-oct

**Aujourd'hui :** Aucun moyen de visualiser ces 4 étapes ensemble ni leurs risques.

### 🔴 **Défi #4 : Direction manque de vue stratégique**

La direction reçoit :
- ✅ KPIs courriers
- ✅ Alertes retard
- ❌ Roadmap initiatives
- ❌ Charge par projet
- ❌ Risques projet-wide (vs. risques courrier)
- ❌ Priorités cross-projet

**Résultat :** Arbitrage subjectif VS objectif basé sur données.

---

## 3. SOLUTION PROPOSÉE : MODULE DE GESTION DE PROJETS

### 📐 **Architecture logique (Version 1)**

```
┌─ PROJET (ex: "Onboarding Partners 2026")
│   ├─ Responsable
│   ├─ Budget
│   ├─ Date début / fin
│   ├─ Statut (Planifié, Actif, En pause, Clos)
│   │
│   ├─ JALON 1 (ex: "Signature contrat")
│   │   ├─ Date : 25/04/2026
│   │   └─ Responsable : Juridique
│   │
│   ├─ JALON 2 (ex: "Setup technique")
│   │   ├─ Date : 29/04/2026
│   │   └─ Responsable : IT
│   │
│   └─ TÂCHES / COURRIERS ASSOCIÉS
│       ├─ Demande licence (Courrier ID 542)
│       ├─ Contrat commercial (Courrier ID 543)
│       ├─ Devis IT (Courrier ID 544)
│       ├─ Setup (Courrier ID 545)
│       └─ Intégration (Courrier ID 546)
```

### 🎯 **Fonctionnalités clés**

#### **1. Créer un projet (5 min)**
- Titre, description, client
- Date début / fin prévue
- Budget (optionnel pour début)
- Responsable projet
- Catégorie (ex: Commercial, IT, Opérationnel)

**Bénéfice :** Une seule source de vérité pour une initiative.

---

#### **2. Définir des jalons (phases)**
Chaque projet a 2-5 jalons clés :
- Date attendue
- Description
- Responsable
- Indicateur de succès (optionnel)

**Exemple - Projet "Migration ERP":**
| Jalon | Date | Responsable | Status |
|---|---|---|---|
| Préparation données | 31/05 | Finance | À 90% |
| Recette UAT | 31/07 | Métier | Planifié |
| Go-live | 15/08 | Direction | Planifié |

**Bénéfice :** Pilotage par étapes, pas par courrier.

---

#### **3. Vue calendrier par projet**
Calendrier mensuel/semaine affichant :
- Les jalons du projet (en gros)
- Les courriers/tâches liées (en normal)
- Les deadlines individuelles (codes couleur : vert, orange, rouge)

**Bénéfice :** Voir d'un coup d'œil si le projet est OK ou en danger.

---

#### **4. Charge par agent / projet**
Table affichant :
- Agent
- Nombre de tâches actives
- Deadline la plus proche
- Nombre de tâches en retard

**Prévu pour un agent :**
| Projet | Tâches actives | Deadline | Charge |
|---|---|---|---|
| Onboarding Partners | 2 | J-4 | 🟢 |
| Migration ERP | 3 | J+2 | 🔴 |
| **Total** | **5** | | **Élevée** |

**Bénéfice :** Détecter surcharges avant qu'elles ne créent des retards.

---

#### **5. Vue directeur / roadmap**
Affichage simplifié pour la direction :
- Tous les projets actifs
- Jalons critiques (ce mois + suivant)
- 🟢 / 🟡 / 🔴 Santé du projet (vs. plan)
- Risques flagrants (ex: "3 projets à retard simultanément")

**Bénéfice :** Direction pilote stratégie, pas courrier par courrier.

---

### 🔗 **Intégration avec AMM-ERP actuel**

**Le système RESTE inchangé :**
- Courriers : enregistrement, assignation, statuts
- Notifications : alertes individuelles
- Contrats : suivi écheances
- Finance : transactions

**Nouveauté : surcharge optionnelle**

Un courrier peut désormais indiquer :
```
Courrier ID 543
├─ Titre : Contrat commercial
├─ Traitement normal (agent → preuve → clos)
│
└─ OPTIONNEL : Lié au Projet "Onboarding Partners 2026"
   └─ Lies au Jalon "Signature contrat"
```

**Impact :** Aucun changement opérationnel pour les réceptionnistes ; nouveaux bénéfices pour pilotage.

---

## 4. BÉNÉFICES ATTENDUS

### 📊 **Quantifiables**

| Bénéfice | Impact estimé | Preuve |
|---|---|---|
| **Réduction retards project** | -20 à 30% | Dépistage précoce surcharges |
| **Temps planification** | -2h/semaine | Roadmap auto-synthétisée |
| **Risques non-détectés** | -50% | Visibilité par projet |
| **Taux cloture SLA** | +5-10% | Meilleur équilibre charge équipe |

### 💡 **Qualitatifs**

- ✅ Direction a une vraie **roadmap stratégique** (pas juste alertes)
- ✅ Chef de projet peut **piloter sans Excel** (tableau de bord natif)
- ✅ Agents voient **contexte de leur travail** (pourquoi J-4 c'est critique)
- ✅ Métier gère **dépendances** ("Ne peut débuter avant signatures")
- ✅ Client reçoit **preuve visuelle** que son projet est suivi

---

## 5. FRÉQUENCE D'UTILISATION (Estimée)

### Par rôle

| Rôle | Fréquence | Usage |
|---|---|---|
| **Direction** | 2x/semaine | Survoler roadmap, identifier risques |
| **Chef de projet** | 5-7x/semaine | Planifier, mettre à jour jalons, charge |
| **Chef de service** | 3x/semaine | Voir charge équipe sur les projets |
| **Agent** | 2x/semaine | Voir calendrier personnel + projets liés |
| **Reception** | Rarement | Impact nul |
| **Finance** | 1x/semaine | Voir projets + budget (si budgétisé) |

---

## 6. PLAN DE DÉPLOIEMENT

### **Phase 1 : FONDATION (3-4 semaines)**
**Objectif :** Prototype fonctionnel, usage par 1-2 projets pilotes

**Livrables :**
- ✅ Base de données : tables projets, jalons, tâches
- ✅ Interface : créer projet, lister projets, calendrier basique
- ✅ Lien courrier ↔ projet
- ✅ Formation 1 chef de projet pilote

**Coût estimé :** 1 dev × 3 semaines = effort contenu  
**Utilisateurs impactés :** 3-5 pilotes  
**Risque :** Faible (surcharge progressive du système existant)

---

### **Phase 2 : PILOTAGE (2-3 semaines) - SI succès Phase 1**
**Objectif :** Mise à jour par tous les chefs de projet

**Livrables :**
- ✅ Gantt simplifié (chronologie visuelle)
- ✅ Dépendances (tâche A bloque B)
- ✅ Charge par agent (heatmap)
- ✅ Alertes proches jalons
- ✅ Formation équipe

**Go-live :** Déploiement progressif (1 projet/semaine)

---

### **Phase 3 : AVANCÉ (Optional)**
**Contenu :**
- Budgets par projet
- Modèles réutilisables
- Time-tracking
- Rapports exécutifs mensuels

**Décision :** Post-bilan Phase 2

---

## 7. INVESTISSEMENT & ROI

### 💰 **Coûts estimés**

| Élément | Coût | Durée |
|---|---|---|
| Développement Phase 1 | 1 dev | 3-4 sem |
| Développement Phase 2 | 1 dev | 2-3 sem |
| Formation utilisateurs | 2-3 demi-jours | |
| Support initial | 1 sem support | |
| **TOTAL Phase 1+2** | **~2 mois dev** | |

### 📈 **ROI estimé**

| Métrique | Bénéfice / an |
|---|---|
| Temps économisé (planification) | ~100 heures |
| Retards évités | -20-30% × coût d'un retard |
| Satisfaction client (projet visible) | Fidélisation |
| Réductions impacts (charge détectée tôt) | 5-10% |
| **Payback estimé** | **2-3 mois** |

---

## 8. RISQUES & MITIGATION

| Risque | Probabilité | Impact | Mitigation |
|---|---|---|---|
| Surcharge au-delà courrier | Moyenne | Moyen | Phase 1 sur projets seulement (optionnel) |
| Faible adoption (chef projet oublie update) | Moyenne | Moyen | Notifications de rappel + responsable désigné |
| Données incohérentes (projet vs courrier) | Faible | Haut | Validation croisée en cron + audit |
| Complexité interface | Basse | Moyen | UX simple en Phase 1, pas Gantt compliqué |

---

## 9. DÉCISIONS REQUISES

### ✋ **À valider avec le client**

1. **Souhaitez-vous piloter par projets ?**
   - OUI → Procédons Phase 1
   - NON → Arrêtons ici

2. **Projets critiques à couvrir en Phase 1 ?**
   - (Ex: Migration ERP, Partenariat client X)

3. **Qui sera responsable mise-à-jour projets ?**
   - Ex: Chef de projet, Direction, Chef de service

4. **Deadlines ? (Go-live souhaité)**
   - Maintenance actuelle : quand intervalle libre ?

5. **Budget approuvé ?**
   - Phase 1 seule OU Phase 1+2 ?

---

## ANNEXE A : INVENTAIRE DES FONCTIONNALITÉS ACTUELLES

### 📦 **Module Courriers** ✅
**Que peut faire l'utilisateur :**
- ✅ Créer un courrier via formulaire (expediteur, objet, type, urgence)
- ✅ Assigner un courrier à un ou plusieurs agents
- ✅ Scanner et joindre le document (PDF, JPG, PNG)
- ✅ Ajouter une référence unique évitant les doublons
- ✅ Marquer l'urgence (basse, moyenne, haute)
- ✅ Définir une deadline et un service destinataire
- ✅ Consulter la liste des courriers avec filtrage par:
  - Statut (Reçu, Assigné, Accusé, En cours, Terminé, Clos, Archivé, Overdue)
  - Urgence
  - Responsable
  - Service
- ✅ Recherche instantanée par référence/objet
- ✅ Voir le détail d'un courrier (historique, preuves jointes, assignations)

---

### 👤 **Module Assignations & Notifications** ✅
**Que peut faire l'utilisateur :**
- ✅ Recevoir une notification lors d'une assignation
- ✅ Accuser réception d'un courrier assigné (date/heure tracée)
- ✅ Marquer le courrier "accepté", "refusé", ou "terminé"
- ✅ Voir ses courriers personnels en attente
- ✅ Recevoir des alertes sur les courriers proches de deadline
- ✅ Consulter historique d'assignation (qui a assigné, quand, instructions)
- ✅ Notifications accessibles depuis le dashboard
- ✅ Marquer notification comme lue

---

### 📄 **Module Documents** ✅
**Que peut faire l'utilisateur :**
- ✅ Uploader des documents (contrats, rapports, licences, etc.)
- ✅ Catégoriser les documents (type: contrat, licence, rapport)
- ✅ Gérer le versionnage automatique (chaque nouvelle version enregistrée)
- ✅ Ajouter une description et métadonnées
- ✅ Consulter l'historique des versions
- ✅ Télécharger n'importe quelle version passée
- ✅ Voir qui a téléchargé / modifié chaque document
- ✅ List documents by category and search

---

### 💼 **Module Contrats** ✅
**Que peut faire l'utilisateur :**
- ✅ Créer un contrat avec:
  - Partie contractante
  - Montant
  - Date de début / fin
  - Conditions d'alerte (J-7, J-15, J-30)
  - Statut (actif, renouvelable, expiré)
- ✅ Consulter liste de tous les contrats
- ✅ Recevoir alertes automatiques avant expiration:
  - Alerte 30 jours avant expiration
  - Alerte 15 jours avant expiration
  - Alerte 7 jours avant expiration (critique)
- ✅ Dashboard "Contrats à risque" pour Direction & Juridique
- ✅ Joindre document contrat (PDF)
- ✅ Voir montant total en portefeuille

---

### 💰 **Module Finance & Suivi Financier** ✅
**Que peut faire l'utilisateur :**
- ✅ Enregistrer les transactions (paiements entrants/sortants)
- ✅ Créer des factures
- ✅ Consulter détail factures (statut payée, date, client)
- ✅ Voir budget par type (contrats, achats, services)
- ✅ Dashboard Finance avec:
  - Montant total en contrats
  - Montant payé ce mois
  - Contrats expirant prochainement
  - Factures impayées
- ✅ Export données finance pour comptabilité

---

### 📊 **Module Tableaux de Bord (Dashboards)** ✅
**Que peut faire l'utilisateur :**

**Dashboard Direction :**
- ✅ Vue KPIs clés: courriers overdue, délai moyen, retards
- ✅ Risques & alertes critiques
- ✅ Décomposition par statut
- ✅ Charge par service
- ✅ Courriers à arbitrer (escalades)

**Dashboard Réception :**
- ✅ Courriers du jour
- ✅ Courriers non assignés (action requise)
- ✅ Contrôle qualité (champs obligatoires manquants)
- ✅ Taux assignation sous 24h

**Dashboard Finance :**
- ✅ Contrats actifs & paiements
- ✅ Expirations (J-30, J-15, J-7)
- ✅ Montants totaux
- ✅ Factures impayées

**Dashboard Juridique :**
- ✅ Conformité documentaire
- ✅ Contrats à surveiller
- ✅ Documents expirés
- ✅ Courriers juridiques en attente

**Filtres appliquables (tous dashboards) :**
- ✅ Période (jour, semaine, mois)
- ✅ Service
- ✅ Statut
- ✅ Urgence
- ✅ Agent responsable

---

### 👥 **Module Gestion Utilisateurs & Rôles** ✅
**Que peut faire l'administrateur :**
- ✅ Créer des comptes utilisateurs
- ✅ Assigner les rôles:
  - Réceptionniste
  - Agent
  - Chef de service
  - Direction
  - Finance
  - Juridique
  - Administrateur
- ✅ Attribuer un service (Juridique, Finance, Opérationnel, etc.)
- ✅ Activer / désactiver les comptes
- ✅ Consulter liste des utilisateurs actifs/inactifs
- ✅ Chaque rôle = permissions + dashboard spécifique

**Sécurité :**
- ✅ Authentification par email/password
- ✅ Contrôle d'accès par rôle (RBAC)
- ✅ Audit: traçabilité de qui a fait quoi et quand

---

### 🔍 **Module Preuves & Justificatifs** ✅
**Que peut faire l'utilisateur :**
- ✅ Ajouter une preuve (document, fichier, commentaire)
- ✅ Typer la preuve: réponse, paiement, transmission, etc.
- ✅ Plusieurs preuves par courrier (historique complet)
- ✅ Voir qui a uplodé et quand
- ✅ Obligation: Un dossier "Clos" nécessite au moins une preuve
- ✅ Ctrl qualité: Dashboard résumé des dossiers sans preuve

---

### 🔔 **Module Alertes & Risques** ✅
**Que peut faire l'utilisateur :**
- ✅ Alertes Overdue: courriers en retard
- ✅ Alertes SLA: courriers proches de deadline (J-1, J-2, J-3)
- ✅ Alertes Contrats: expiration imminente
- ✅ Alertes Qualité: données incomplètes (ex: scan manquant)
- ✅ Alertes Dossiers: courriers clos sans preuve
- ✅ Notification push immédiate pour alertes critiques
- ✅ Tri par criticité (Rouge > Orange > Jaune)
- ✅ CTA directs: cliquer sur alerte → voir le courrier

---

### 📈 **Module Indicateurs & Rapports** ✅
**Que peut faire l'utilisateur :**
- ✅ KPI Real-time:
  - Nombre courriers actifs
  - Nombre overdue
  - Taux assignation sous 24h (%) 
  - Délai moyen Reçu → Clos (jours)
  - Taux cloture avec preuve (%)
- ✅ Evolution vs période précédente (%)
- ✅ Graphiques: volume entrant/sortant (courbe), répartition par statut (donut)
- ✅ Charge par service/agent (heatmap)
- ✅ Top causes de retard (analyse)
- ✅ Export CSV/PDF des rapports

---

### 🔐 **Module Sécurité & Intégrité** ✅
**Que fait le système :**
- ✅ Changelog d'audit: chaque action tracée (utilisateur, date, action)
- ✅ Contrôle accès fichiers (fichiers uploadés sécurisés)
- ✅ Base de données chiffrée
- ✅ Sessions utilisateur sécurisées
- ✅ Passwords hachés (TODO: migrer vers bcrypt recommandé)
- ✅ Logs disponibles pour administrateur

---

### 🗄️ **Gestion des Données** ✅
**Que peut faire l'utilisateur :**
- ✅ Recherche multi-critères (courrier, document, contrat)
- ✅ Filtres avancés sauvegardables
- ✅ Export listes (CSV, PDF)
- ✅ Archive automatique (courriers fermés disparaissent de vue par défaut)
- ✅ Gestion uploads (scans, documents, justificatifs)
- ✅ Nettoyage automatique des imports échoués

---

## 10. PROCHAINES ÉTAPES

### 📋 **Si accord du client**

1. **Semaine 1 :** Kickoff + recueil besoins détaillés (jalons, champs métier)
2. **Semaine 2:** Conception BD + maquettes UI
3. **Semaine 3-4 :** Développement Phase 1 + tests
4. **Semaine 5 :** Déploiement test + formation pilote
5. **Semaine 6 :** Ajustements, déploiement production

**Livrables :**
- ✅ Module fonctionnel
- ✅ Documentation utilisateur
- ✅ 2 projets pilotes en production

---

## 📞 CONTACT & SUPPORT

**Pour questions :**
- Rémi Lambert (Chef projet) - [email]
- Support technique - [support@amm-erp.com]

**Ressources :**
- Démo module (vidéo 10 min) - [lien]
- Cas d'usage détaillés - [lien]
- FAQ clients - [lien]

---

**Fin du mémo**

*Document confidentiel - Réservé au client et à l'équipe interne*
