# Inscriptions Vol & Ski – extension WordPress

Inscription en ligne à une compétition de vol et ski, avec un espace réservé au comité.

## Installer

1. Télécharge **[volski-inscriptions.zip](volski-inscriptions.zip)** (bouton « Download raw file » sur GitHub).
2. Dans WordPress : **Extensions → Ajouter → Téléverser une extension**, choisis le fichier, puis **Installer** et **Activer**.
3. Menu **Vol & Ski → Infos compétition** : dates, lieu, photo, présentation, programme, infos pratiques, **tarifs**, **options**, e-mail de l'organisation.
4. Crée une page et colle dans un bloc « Code court » :
   - `[volski_evenement]` : la page complète de l'événement (bannière, programme, infos pratiques, tarifs, formulaire)
   - ou `[volski_inscription]` : le formulaire seul
   - `[volski_inscrits]` (sur une autre page) : la liste publique des inscrits

## Ce que fait l'extension

**Page de l'événement** : bannière (photo ou couleur) avec dates, lieu et bouton « S'inscrire », présentation, programme heure par heure, infos pratiques avec lien vers la carte, tarifs par catégorie et options.

**Catégories, tarifs et options** (tout se modifie dans Infos compétition : renommer, ajouter, retirer) : des catégories avec leur prix en CHF (par défaut Solo normal, Parakite, Biplace ; chaque pilote en choisit une seule ; « Passager » demande le nom du passager, ex. Biplace) et des options à ajouter, par exemple **Abonnement télécabine**, **Souper**, **+1 au souper**. Une option peut dépendre d'une autre : « +1 au souper » n'est proposé que si « Souper » est coché. Le total s'affiche en direct dans le formulaire et figure dans les e-mails.

**Formulaire** : prénom, nom, e-mail, téléphone, sexe (Homme / Femme), catégorie Solo normal, Parakite ou Biplace (une seule par pilote ; nom du passager si Biplace), contact d'urgence, commentaire. Une case pour apparaître dans la liste publique, une case obligatoire d'accord sur l'utilisation des données. Protection anti-robots et refus des doublons.

**E-mails automatiques** : confirmation au participant (avec récapitulatif et infos pratiques) et alerte à l'organisation à chaque inscription.

**Liste publique** : nombre d'inscrits par catégorie et noms des personnes qui ont accepté d'y figurer, sans coordonnées.

**Tableau de suivi (Vol & Ski → Tableau de suivi)** : tous les participants dans un tableau modifiable comme une feuille Excel : case **Payé**, **moyen de paiement** (virement, espèces, chèque, carte, Twint, autre), une case par option (télécabine, souper, +1…), total et note du comité. On modifie plusieurs lignes puis on enregistre d'un coup. Totaux en bas (nombre de soupers, payés, montant encaissé). Seules les lignes modifiées sont enregistrées, pour ne pas écraser le travail d'un autre membre.

**Back-office (Vol & Ski → Inscriptions)** :
- compteurs : inscrits, Solo / Biplace / Parakite, hommes / femmes, payés ;
- tableau avec recherche et filtres (catégorie, sexe, paiement) ;
- fiche de chaque inscrit : modification, **statut de paiement**, **note du comité** ;
- ajout manuel (inscription par téléphone…), actions groupées (marquer payé, supprimer) ;
- **export pour Excel** (respecte les filtres en cours) : une colonne par catégorie et par option, total, payé, moyen de paiement.

Le paiement ne se fait pas en ligne : l'organisation recontacte les participants, puis marque « Payé ».

## Espace comité

À l'installation, l'extension crée une page **Espace comité** (adresse affichée dans **Vol & Ski → Comité**). Elle a sa propre connexion (identifiant + mot de passe) et contient :
- les compteurs (inscrits par catégorie, hommes / femmes, options, payés) ;
- le **tableau de suivi** (payé, moyen de paiement, options, total, note) et l'**export Excel** ;
- les **infos de la compétition** (page, catégories, tarifs, options).

Elle ne dépend pas de l'administration WordPress : elle marche même si une autre extension (WooCommerce, sécurité, adhésion…) bloque l'administration pour les comptes non-administrateurs.

Créer les comptes : **Vol & Ski → Comité** (réservé aux administrateurs).
- **un compte partagé** par tout le comité : « Prénom ou nom du compte », « Identifiant » et « Mot de passe » ; le compte est utilisable tout de suite ;
- **un compte par personne** : prénom + e-mail ; la personne reçoit un e-mail pour choisir son mot de passe.

Les comptes du comité ne voient rien d'autre du site. S'ils passent par l'administration WordPress, ils n'y voient que l'extension et leur profil.

## Désinstaller

Désactiver l'extension ne supprime rien. **Supprimer** l'extension efface les inscriptions et les réglages, et retire le rôle « Comité Vol & Ski » (les comptes deviennent de simples abonnés).
