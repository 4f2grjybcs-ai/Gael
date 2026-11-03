# Installer Stock Camion (10 minutes, une seule fois)

Il te faut seulement un compte Google (Gmail). Tes collègues n'ont besoin de rien.

## 1. Créer le Google Sheet

1. Va sur **sheets.google.com** et crée une feuille vierge.
2. Nomme-la, par exemple **Stock Camion**.

C'est là que seront stockées toutes les données. Tu pourras les consulter, les trier ou les imprimer directement dans le Sheet.

## 2. Coller le code

1. Dans le Sheet : menu **Extensions → Apps Script**. Un éditeur s'ouvre.
2. Dans le fichier **Code.gs** déjà présent, efface tout et colle le contenu de [`apps-script/Code.gs`](apps-script/Code.gs).
3. Clique sur le **+** à côté de « Fichiers », choisis **HTML** et nomme le fichier exactement **`Index`** (sans `.html`, l'éditeur l'ajoute).
4. Efface son contenu et colle celui de [`apps-script/Index.html`](apps-script/Index.html).
5. Clique sur l'icône **Enregistrer** (disquette).

> Pour copier un fichier depuis GitHub : ouvre-le, puis clique sur le bouton « Copy raw file » (deux carrés) en haut à droite du code.

## 3. Mettre l'appli en ligne

1. En haut à droite : **Déployer → Nouveau déploiement**.
2. Roue dentée à côté de « Sélectionner le type » → **Application Web**.
3. Règle :
   - **Exécuter en tant que** : *Moi*
   - **Qui a accès** : *Tout le monde*
4. **Déployer**. Google demande une autorisation :
   - **Autoriser l'accès** → choisis ton compte.
   - Message « Google n'a pas validé cette application » : clique **Paramètres avancés**, puis **Accéder à Stock Camion (non sécurisé)**, puis **Autoriser**. C'est normal : c'est ton propre script, qui n'a accès qu'à ce Sheet.
5. Copie l'**URL de l'application Web** (elle finit par `/exec`).

## 4. Envoyer le lien à l'équipe

Envoie cette URL par SMS ou WhatsApp. Chacun l'ouvre sur son téléphone, tape son prénom, et c'est parti.

Astuce : dans Chrome ou Safari, **Partager → Ajouter à l'écran d'accueil** crée une icône comme une vraie appli.

Au premier lancement, tape sur **« Charger la liste type »** pour partir de 38 articles de couvreur, puis ajuste les quantités, ou supprime ce qui ne sert pas.

## 5. (Conseillé) Protéger par un code d'équipe

Toute personne qui a le lien peut modifier le stock. Pour éviter les mauvaises surprises si le lien circule :

1. Dans le Sheet, ouvre l'onglet **Réglages** (il apparaît après la première ouverture de l'appli).
2. Sur la ligne `code`, colonne `valeur`, tape un code, par exemple `couv2026`.
3. Donne ce code à l'équipe : l'appli le demandera une fois par téléphone.

Pour changer le code plus tard, modifie simplement la case : chacun devra retaper le nouveau.

## Mettre à jour le code plus tard

Si on modifie l'appli : recolle les fichiers dans Apps Script, puis **Déployer → Gérer les déploiements → ✏️ (modifier) → Version : Nouvelle version → Déployer**. Le lien reste le même.

## Bon à savoir

- Gratuit, dans les limites de Google, très larges pour 4 personnes.
- L'appli se met à jour toute seule toutes les 20 secondes et à chaque fois qu'on la rouvre.
- Sans réseau, elle affiche la dernière version connue, mais les modifications ne passent pas tant que le réseau n'est pas revenu.
- Un bandeau gris de Google (« Cette application a été créée par un utilisateur de Google Apps Script ») s'affiche en haut : c'est normal.
- Sauvegarde : le Sheet garde l'historique des versions (**Fichier → Historique des versions**).
