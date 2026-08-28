# Guide pilote — A3 ERP

Guide court pour l'équipe pilote. Décrit uniquement les écrans réellement présents dans
l'application (vérifié via `php artisan route:list` le 2026-08-28) — aucun bouton ou menu
inventé.

## Commercial : Devis → Commande

1. **Ventes > Devis** (`ventes.devis.index`) — liste des devis. Bouton « Nouveau devis »
   (`ventes.devis.create`).
2. Saisir client, lignes (article, quantité, prix, remise). Le système bloque à la validation
   si un article a été désactivé au référentiel, ou si le prix net après remise tombe sous le
   prix plancher de l'article.
3. Soumettre le devis → workflow de validation interne selon les seuils configurés.
4. Une fois accepté par le client : bouton **Convertir en commande** (`ventes.devis.convert`)
   depuis la fiche devis.
5. Sur la commande (`ventes.commandes.show`) : le statut financier (comptant/crédit/acompte)
   détermine si l'Ordre de Fabrication peut être lancé — voir contrôle financier ci-dessous.

## Caisse : Paiement

1. **Trésorerie > Encaissements** (`tresorerie.encaissements.index`) — bouton **Nouvel
   encaissement** (`tresorerie.encaissements.create`).
2. Choisir le client, le montant, le moyen de paiement.
3. **Imputer** (`tresorerie.encaissements.imputer`) le paiement sur une ou plusieurs factures
   ouvertes du client — un paiement peut aussi rester en acompte non imputé (avant facturation).
4. Le système plafonne automatiquement l'imputation au solde réel de chaque facture ; un
   trop-perçu reste en crédit client, jamais perdu ni rejeté.
5. Reçu imprimable (`tresorerie.encaissements.recu`).

## Magasin : Stock / sortie / livraison

1. **Stock > Tableau de bord** (`stocks.dashboard`) — vue d'ensemble par dépôt.
2. **Stock > Inventaires** (`stocks.inventaires.index`) — pour un comptage physique contrôlé
   (bouton **Nouvel inventaire**, `stocks.inventaires.create`, puis **Compter**
   `stocks.inventaires.count`) : ne jamais corriger une quantité directement en base, toujours
   passer par un inventaire pour garder une piste d'audit.
3. **Ventes > Bons de livraison** (`ventes.bons-livraison.index`) : une fois l'OF terminé et le
   produit libéré qualité, le BL peut être validé (`ventes.bons-livraison.validate`) — la
   livraison est bloquée si l'article n'a pas encore reçu sa libération qualité, et redevient
   bloquée si la qualité refuse après une libération antérieure.

## Production : OF → consommation → production

1. **Production > Ordres de fabrication** (`production.orders.index`).
2. Lancer l'OF (`production.orders.launch`) — bloqué si le contrôle financier n'est pas au vert
   pour une commande MTO (paiement comptant 100 % encaissé, ou crédit sous plafond avec
   échéances respectées, sauf dérogation explicite).
3. **Consommer** les matières (`production.orders.consume`) — le système empêche la
   consommation d'une quantité supérieure au stock réellement disponible.
4. **Terminer** l'OF (`production.orders.finish`) — enregistre la quantité produite ; toute
   déclaration qui dépasserait la quantité commandée (cumul compris) est bloquée sauf
   dérogation explicite (« autoriser dépassement qté »).

## Qualité : Contrôle → libération/refus

1. **Qualité > Libérations** (`qualite.releases.index`).
2. Décision (`qualite.releases.decide`) : **Libéré** (transfère le produit fini vers le dépôt de
   libération, le rend livrable), **Refusé** (bloque la livraison — y compris si le lot avait
   déjà été libéré puis fait l'objet d'un revirement qualité), ou **Dérogation** (libère avec
   référence de dérogation tracée).
3. Un contrôle qualité conforme (**Qualité > Plans de contrôle**,
   `qualite.control-plans.index`) est requis avant toute libération, si le paramétrage de l'OF
   l'exige.

## Comptabilité : Facture → écritures

1. **Ventes > Factures** (`ventes.factures.index`) — création depuis une commande/un BL
   (`ventes.factures.create`) ou conversion directe.
2. La validation d'une facture non-brouillon génère automatiquement les écritures comptables
   correspondantes (journal ventes, TVA, compte client) — aucune saisie manuelle d'écriture
   n'est nécessaire pour ce flux standard.
3. PDF facture téléchargeable (`ventes.factures.pdf`) — mentions légales, TVA, montant en
   lettres, QR code de vérification, numérotation de page fiable (corrigé en P6).

---
Ce guide couvre le strict nécessaire pour le pilote. Pour toute question sur un écran non
listé ici, contacter l'administrateur technique — ne pas improviser une procédure non décrite.
