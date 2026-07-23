# Changelog

## 2026-07-23

- Passage des mises à jour GitHub Actions de Dependabot à un rythme mensuel,
  regroupées dans une seule PR au maximum.

## 2026-06-19 - Publication-ready source

- Resynchronisation du miroir public avec la version live : crédits, paiement, modes d'intelligence, reflet automatique, effet souris et corrections d'état sans secrets embarqués.
- Ajout d'une CI GitHub Actions pour verifier la syntaxe JavaScript et PHP a chaque push.
- Ajout d'une politique de securite, de Dependabot et d'un scan GitHub Actions Gitleaks/TruffleHog hebdomadaire.
- Resynchronisation du depot autonome avec la version vivante de `julienpiron.fr/debaite`.
- Ajout du flux Google OAuth, de la session serveur et de la logique d'essai unique.
- Retrait des anciens scripts locaux de deploiement OVH/SFTP et des assets obsoletes.
- Ajout d'une licence source available avec permission ecrite obligatoire.
- Remplacement de la licence maison par la licence connue PolyForm Strict 1.0.0.
- Remplacement par GNU AGPLv3 pour utiliser une licence reconnue par GitHub et moins permissive que MIT.
- Mise a jour du README pour documenter les variables serveur sans exposer de secret.

## 2026-06-19

- Migration de la source canonique vers le dossier `debaite/` du repo `julienpiron.fr`.
- Remplacement du relais Gemini par DeepSeek (`deepseek-v4-flash` par défaut).
- Ajout du backend PHP de production `api/generate.php`.
- Ajout d'une protection Apache Basic Auth pour l'utilisateur `admin`.
- Utilisation d'un mot de passe admin dédié à Debaite dans NexusSecure.
- Ajout du déploiement SFTP OVH vers le dossier `www/debaite` avec secrets générés hors Git.
- Retrait des clés Gemini du code navigateur.
- Ajout d'un relais serveur local `/api/generate` basé sur `DEEPSEEK_API_KEY`.
- Sécurisation de l'affichage des réponses IA contre l'injection HTML.
- Correction du débordement horizontal sur mobile.
- Remplacement du fond PNG ignoré par une version WebP suivie par Git.
- Correction du libellé du modèle affiché dans l'interface.
- Mise à jour de la documentation de lancement et de déploiement.
