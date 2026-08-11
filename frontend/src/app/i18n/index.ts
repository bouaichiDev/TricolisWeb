import i18n from 'i18next'
import LanguageDetector from 'i18next-browser-languagedetector'
import { initReactI18next } from 'react-i18next'

import fr from './locales/fr.json'

/**
 * Internationalisation.
 *
 * Le français est la langue de référence et la seule livrée en Phase 1 :
 * ajouter une traduction vide serait un mensonge, l'utilisateur verrait des
 * clés brutes. La structure est en place, une seconde langue s'ajoute par un
 * fichier et une ligne.
 *
 * `escapeValue: false` est correct ici : React échappe déjà ce qu'il rend.
 */
export const SUPPORTED_LANGUAGES = ['fr'] as const

export type SupportedLanguage = (typeof SUPPORTED_LANGUAGES)[number]

void i18n
  .use(LanguageDetector)
  .use(initReactI18next)
  .init({
    resources: { fr: { translation: fr } },
    fallbackLng: 'fr',
    supportedLngs: SUPPORTED_LANGUAGES,
    interpolation: { escapeValue: false },
    detection: {
      order: ['localStorage', 'navigator'],
      lookupLocalStorage: 'tricolis.language',
      caches: ['localStorage'],
    },
  })

export default i18n
