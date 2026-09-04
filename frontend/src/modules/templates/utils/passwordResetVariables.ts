/**
 * Les variables qu'un modèle de réinitialisation de mot de passe peut nommer.
 *
 * Recopiées de `SendPasswordResetLink::availableVariables()`. Sans cette liste,
 * l'écran proposait celles d'une commande — un modèle qui ne parle d'aucune
 * commande — et les six bonnes n'apparaissaient nulle part. Un nom non déclaré
 * fait échouer le rendu, et l'administrateur n'avait aucun moyen de le deviner.
 *
 * `resetUrl` est la seule indispensable : sans elle, le courriel n'ouvre rien.
 */
export const PASSWORD_RESET_VARIABLES = [
  'resetUrl',
  'user.firstName',
  'user.lastName',
  'user.email',
  'organization.name',
  'expiresInMinutes',
] as const
