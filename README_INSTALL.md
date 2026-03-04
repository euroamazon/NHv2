# NH - Notes d'honoraires (v2)

## Nouveautés v2
- Paramètres cabinet (ICE/IF/RC/CNSS, RIB, logo, ville).
- Gestion des décomptes (base suivi = somme des décomptes HT).
- Export "PDF / Aperçu" :
  - si Dompdf est installé via Composer → PDF A4.
  - sinon → HTML imprimable (fallback).
- Montant en lettres (FR) sur la note.
- Snapshot (freeze) stocké en base lors de la génération (et validation).

## Installation (Infomaniak)
1) Uploadez le contenu du ZIP sur votre hébergement.
2) Idéal: configurer le document root vers `public/`.
3) Ouvrez : `https://votre-domaine/install/` puis renseignez DB + admin.
4) Connectez-vous : `/?r=login`
5) Supprimez le dossier `/install` (sécurité).

## Activer un vrai PDF (Dompdf)
Sur l’hébergement (si Composer est disponible) :
- `composer install --no-dev`
Cela créera `vendor/` et l’export sortira un PDF.
Sinon, utilisez le fallback HTML et imprimez en PDF depuis le navigateur.

## Sécurité
- Ne laissez pas `/install` en ligne.
- Utilisez un mot de passe DB fort et ne le partagez pas en clair.
