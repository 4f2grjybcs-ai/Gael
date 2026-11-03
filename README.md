# Stock Camion

Appli pour gérer le stock de matériel et l'outillage d'un véhicule de couvreur, à partager avec l'équipe (3-4 personnes).

- Fonctionne sur téléphone, dans le navigateur. Pas d'appli à installer.
- **Aucun compte nécessaire pour l'équipe** : on envoie un lien, c'est tout.
- **Gratuit** : hébergé par Google Apps Script, données dans un Google Sheet qui t'appartient.

Fonctions : outils (dans le camion / sur chantier / en réparation / manquant, avec qui ou où), consommables avec boutons − / + et seuil mini, liste « À racheter » à copier pour SMS ou WhatsApp, historique de qui a fait quoi, liste type de couvreur (38 articles) pour démarrer.

👉 **Installation : voir [INSTALLATION.md](INSTALLATION.md)** (10 minutes, une seule fois).

Fichiers :
- `apps-script/Code.gs` : la partie serveur (lit et écrit dans le Google Sheet)
- `apps-script/Index.html` : l'appli elle-même
- `apps-script/appsscript.json` : réglages du projet (facultatif)
