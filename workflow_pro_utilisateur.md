# AMM-ERP - Workflow Utilisateur Professionnel

Version: 1.0  
Date: 09/04/2026  
Public cible: Reception, Agent, Chef de service, Direction, Finance, Juridique, Admin

---

## 1. Objectif du logiciel
AMM-ERP centralise le traitement des courriers, la gestion documentaire et le suivi des contrats afin de:
- reduire les pertes d'information
- tracer les actions par utilisateur
- respecter les delais et echeances
- piloter l'activite via des tableaux de bord

---

## 2. Parcours global (vue simple)
1. Connexion au systeme
2. Reception et enregistrement du courrier
3. Assignation a un service/agent
4. Accuse de reception par l'agent
5. Traitement du dossier
6. Ajout de preuves
7. Validation, cloture et archivage
8. Suivi des KPIs et des alertes

---

## 3. Workflow detaille par role

### 3.1 Reception
1. Ouvrir Courriers > Nouveau courrier.
2. Encoder les donnees obligatoires:
   - Expediteur
   - Reference du document
   - Objet
   - Date de reception
3. Completer les metadonnees:
   - Type de document
   - Urgence
   - Service destinataire
   - Deadline
4. Joindre le scan du document (PDF/JPG/PNG).
5. Enregistrer le courrier.
6. Assigner le courrier a un ou plusieurs agents.

Resultat attendu: le courrier est trace et visible dans les dashboards.

### 3.2 Agent / Chef de service
1. Consulter les notifications recues.
2. Ouvrir le courrier assigne.
3. Accuser reception.
4. Traiter la demande metier.
5. Ajouter une ou plusieurs preuves (fichier + commentaire).
6. Mettre a jour le statut selon l'avancement.

Resultat attendu: dossier traite avec preuves et statut fiable.

### 3.3 Direction
1. Consulter le dashboard Direction.
2. Surveiller:
   - courriers en retard
   - urgences actives
   - charges de traitement
   - contrats proches d'echeance
3. Arbitrer les courriers escalades.
4. Valider les clotures importantes.

Resultat attendu: pilotage decisionnel et respect des delais.

### 3.4 Finance / Juridique
- Finance:
  1. Suivre contrats actifs et paiements.
  2. Traiter les alertes d'echeance (7/15/30 jours).
- Juridique:
  1. Suivre les courriers juridiques.
  2. Verifier les documents expirants et clauses contractuelles.

Resultat attendu: maitrise des risques financiers et juridiques.

### 3.5 Administrateur
1. Creer et gerer les comptes utilisateurs.
2. Attribuer les roles adequats.
3. Activer/desactiver les comptes.
4. Controler l'hygiene des donnees et la discipline de statut.

Resultat attendu: gouvernance des acces et fiabilite du systeme.

---

## 4. Regles de gestion a communiquer aux utilisateurs
1. La reference du document doit toujours etre encodee a la reception.
2. Un courrier assigne doit etre accuse reception rapidement.
3. Aucun dossier ne doit etre cloture sans preuve.
4. Les statuts doivent refleter la realite terrain.
5. Les echeances critiques doivent etre traitees en priorite.

---

## 5. Statuts standards (courriers)
- Recu: courrier enregistre, pas encore pris en charge
- Assigne: courrier envoye a un agent/service
- Accuse: reception confirmee par l'agent
- En_cours: traitement actif
- Termine: traitement termine
- Clos: dossier cloture avec preuves
- Archive: dossier historise
- Overdue: delai depasse

---

## 6. Procedure standard (SOP) - Reception d'un courrier
1. Verifier la lisibilite du document physique.
2. Scanner le document (qualite lisible, format autorise).
3. Enregistrer le courrier avec reference document.
4. Definir urgence, service, deadline.
5. Assigner au responsable operationnel.
6. Verifier la presence du courrier dans la liste.

Checklist qualite:
- Champ expediteur renseigne
- Champ reference document renseigne
- Objet clair et complet
- Piece jointe associee
- Destinataire/agent correctement defini

---

## 7. Script de demonstration (10 minutes)
1. Connexion avec profil Reception.
2. Creation d'un courrier avec reference + scan.
3. Assignation a un agent.
4. Connexion Agent: accuse reception.
5. Ajout d'une preuve.
6. Passage du statut a Clos.
7. Verification dashboard + notifications.

Message cle: toute action est tracee, mesurable et presentable.

---

## 8. Incidents frequents et conduite a tenir
- Fichier ne s'affiche pas:
  - verifier upload effectif
  - verifier droits dossier uploads
  - verifier acces URL
- Statut incoherent:
  - corriger via ecran detail courrier
  - documenter en commentaire/preuve
- Non assignes en hausse:
  - filtrer dashboard Reception
  - appliquer assignation quotidienne

---

## 9. Indicateurs de performance recommandes
- Taux de courriers assignes sous 24h
- Taux de courriers clos avec preuve
- Nombre de dossiers Overdue
- Delai moyen Recu -> Clos
- Nombre de contrats a risque (J-30, J-15, J-7)

---

## 10. Plan de formation recommande
1. Session 1 (45 min): prise en main + workflow global
2. Session 2 (45 min): exercices par role
3. Session 3 (30 min): tableaux de bord et qualite des donnees
4. Evaluation: cas pratique de bout en bout

---

## 11. Message de cadrage pour les utilisateurs
AMM-ERP n'est pas seulement un outil de saisie. C'est un systeme de traçabilite et de decision. La qualite des donnees saisies conditionne directement la qualite du pilotage managérial.

Fin du document.
