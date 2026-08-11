import { type ClassValue, clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

/**
 * Fusionne des classes Tailwind en resolvant les conflits.
 *
 * `clsx` gere les conditions, `twMerge` tranche entre deux classes de la meme
 * famille : `cn('p-2', 'p-4')` rend `p-4`, pas les deux. Sans lui, une classe
 * passee en prop ne pourrait pas surcharger celle du composant.
 */
export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}
