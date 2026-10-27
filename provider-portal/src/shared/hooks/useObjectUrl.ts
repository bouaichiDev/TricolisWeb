import { useEffect, useState } from 'react'

/**
 * Une URL locale (`blob:`) pour un fichier téléchargé, libérée quand le
 * fichier change ou que le composant disparaît.
 *
 * Créée **et** libérée dans le même effet, jamais dans un `useMemo` : en mode
 * strict, React monte, démonte puis remonte chaque composant ; une URL
 * mémorisée survivait au démontage mais pas à sa libération, et l'image
 * s'affichait cassée dès que le fichier était déjà en cache.
 */
export function useObjectUrl(blob: Blob | undefined): string | null {
  const [url, setUrl] = useState<string | null>(null)

  useEffect(() => {
    if (blob === undefined) return

    const objectUrl = URL.createObjectURL(blob)
    // L'URL doit naître dans l'effet qui la libère : c'est une synchronisation
    // avec une ressource du navigateur, le cas prévu pour un état posé ici.
    // oxlint-disable-next-line react/set-state-in-effect
    setUrl(objectUrl)

    return () => URL.revokeObjectURL(objectUrl)
  }, [blob])

  return blob === undefined ? null : url
}
