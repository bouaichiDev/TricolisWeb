interface TrackingProgressProps {
  percent: number | null
  label: string
}

/**
 * Barre de progression. `percent` nul — aucune donnée pour le calculer — ne
 * dessine pas une barre vide qui se lirait « 0 % » : la ligne le dit.
 */
export function TrackingProgress({ percent, label }: TrackingProgressProps) {
  if (percent === null) {
    return <p className="text-muted-foreground text-sm">Progression indisponible</p>
  }

  return (
    <div className="flex flex-col gap-1.5">
      <div className="flex items-center justify-between text-sm">
        <span className="text-muted-foreground">{label}</span>
        <span className="font-medium">{percent} %</span>
      </div>
      <div
        className="bg-muted h-2 w-full overflow-hidden rounded-full"
        role="progressbar"
        aria-label={label}
        aria-valuenow={percent}
        aria-valuemin={0}
        aria-valuemax={100}
      >
        <div className="bg-primary h-full rounded-full transition-all" style={{ width: `${percent}%` }} />
      </div>
    </div>
  )
}
