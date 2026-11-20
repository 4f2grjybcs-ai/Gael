# Inscriptions Vol & Ski – extension WordPress

Inscription en ligne à une compétition de vol et ski, avec un espace réservé au comité.

## Installer

1. Télécharge **[volski-inscriptions.zip](volski-inscriptions.zip)** (bouton « Download raw file » sur GitHub).
2. Dans WordPress : **Extensions → Ajouter → Téléverser une extension**, choisis le fichier, puis **Installer** et **Activer**.
3. Menu **Vol & Ski → Infos compétition** : nom de la compétition, infos pratiques, e-mail de l'organisation.
4. Crée deux pages et colle dans chacune un bloc « Code court » :
   - `[volski_inscription]` : le formulaire d'inscription
   - `[volski_inscrits]` : la liste publique des inscrits

## Ce que fait l'extension

**Formulaire** : prénom, nom, e-mail, téléphone, sexe (Homme / Femme), catégories Solo, Biplace et Parakite (plusieurs possibles ; nom du passager si Biplace), contact d'urgence, commentaire. Une case pour apparaître dans la liste publique, une case obligatoire d'accord sur l'utilisation des données. Protection anti-robots et refus des doublons.

**E-mails automatiques** : confirmation au participant (avec récapitulatif et infos pratiques) et alerte à l'organisation à chaque inscription.

**Liste publique** : nombre d'inscrits par catégorie et noms des personnes qui ont accepté d'y figurer, sans coordonnées.

**Back-office (Vol & Ski → Inscriptions)** :
- compteurs : inscrits, Solo / Biplace / Parakite, hommes / femmes, payés ;
- tableau avec recherche et filtres (catégorie, sexe, paiement) ;
- fiche de chaque inscrit : modification, **statut de paiement**, **note du comité** ;
- ajout manuel (inscription par téléphone…), actions groupées (marquer payé, supprimer) ;
- **export pour Excel** (respecte les filtres en cours).

Le paiement ne se fait pas en ligne : l'organisation recontacte les participants, puis marque « Payé ».

## Espace comité

Menu **Vol & Ski → Comité** (réservé aux administrateurs). Deux façons de faire :
- **un compte partagé** par tout le comité : remplis « Prénom ou nom du compte », « Identifiant » et « Mot de passe » (e-mail facultatif) ; le compte est utilisable tout de suite ;
- **un compte par personne** : prénom + e-mail ; la personne reçoit un e-mail pour choisir son mot de passe.

Les membres se connectent sur `tonsite.fr/wp-login.php` et arrivent directement sur les inscriptions. Ils voient **uniquement** l'extension (inscriptions, infos compétition) et leur profil : aucune page, aucun article, aucun réglage du site. Toute autre adresse de l'admin les renvoie sur les inscriptions. Ils ne peuvent pas ajouter d'autres membres.

## Désinstaller

Désactiver l'extension ne supprime rien. **Supprimer** l'extension efface les inscriptions et les réglages, et retire le rôle « Comité Vol & Ski » (les comptes deviennent de simples abonnés).
