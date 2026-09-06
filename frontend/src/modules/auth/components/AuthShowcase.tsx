import { Leaf, LineChart, Network, Route } from 'lucide-react'
import { useTranslation } from 'react-i18next'

/** Une promesse du produit, posée sur une tuile de verre. */
function Feature({
  icon: Icon,
  title,
  text,
}: {
  icon: typeof Route
  title: string
  text: string
}) {
  return (
    <div className="rounded-2xl border border-white/15 bg-white/8 p-4 backdrop-blur-md">
      <Icon className="mb-2 size-5 text-sidebar-primary" aria-hidden />
      <h3 className="text-sm font-bold">{title}</h3>
      <p className="mt-1 text-xs text-sidebar-foreground/70">{text}</p>
    </div>
  )
}

/** Un chiffre-clé du bandeau bas. */
function Metric({ value, label }: { value: string; label: string }) {
  return (
    <div>
      <div className="text-2xl font-bold tracking-tight">{value}</div>
      <div className="mt-0.5 text-xs text-sidebar-foreground/70">{label}</div>
    </div>
  )
}

/**
 * Le panneau de marque qui accompagne la connexion.
 *
 * Il ne s'affiche qu'à partir de `lg` : sur un téléphone, il repousserait le
 * formulaire sous la ligne de flottaison pour ne montrer que du décor.
 *
 * **Il se dépouille quand la fenêtre raccourcit**, et dans cet ordre : les
 * chiffres d'abord, les tuiles ensuite. Le panneau ne défile pas — ce serait
 * demander de faire glisser du décor pour lire du décor —, donc ce qui ne tient
 * pas doit partir franchement plutôt que d'être coupé en deux par le bord.
 *
 * Le bleu nuit vient des jetons `sidebar`, ceux-là mêmes que porte la barre de
 * navigation une fois la session ouverte — la page de connexion annonce ainsi
 * l'application, au lieu d'introduire une deuxième identité qui dériverait de
 * son côté au premier changement de thème.
 */
export function AuthShowcase() {
  const { t } = useTranslation()

  return (
    <section className="relative hidden flex-1 flex-col justify-between overflow-hidden bg-sidebar p-10 text-sidebar-foreground lg:flex xl:p-16">
      {/* Halos et trame : purement décoratifs, donc hors du flux et sans texte. */}
      <div
        className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_80%_20%,color-mix(in_oklab,var(--sidebar-primary)_35%,transparent)_0%,transparent_50%),radial-gradient(circle_at_20%_80%,color-mix(in_oklab,var(--sidebar-primary)_30%,transparent)_0%,transparent_60%)]"
        aria-hidden
      />
      <div
        className="pointer-events-none absolute inset-0 bg-[radial-gradient(#ffffff_1px,transparent_1px)] opacity-10 [background-size:24px_24px]"
        aria-hidden
      />

      <div className="relative z-10">
        <span className="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-medium tracking-wide uppercase backdrop-blur-md">
          <Network className="size-3.5" aria-hidden />
          {t('auth.showcase.network')}
        </span>
      </div>

      <div className="relative z-10 my-auto max-w-xl py-8">
        <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-sidebar-primary/30 bg-sidebar-primary/20 px-3.5 py-1.5 text-sm font-semibold">
          <Leaf className="size-4 text-success" aria-hidden />
          {t('auth.showcase.tagline')}
        </div>

        <h2 className="mb-5 text-3xl leading-tight font-extrabold [@media(min-height:900px)]:text-4xl">
          {t('auth.showcase.title')}
        </h2>

        <p className="mb-8 text-base leading-relaxed text-sidebar-foreground/90">
          {t('auth.showcase.body')}
        </p>

        <div className="grid grid-cols-2 gap-4 [@media(max-height:680px)]:hidden">
          <Feature
            icon={Route}
            title={t('auth.showcase.tours.title')}
            text={t('auth.showcase.tours.body')}
          />
          <Feature
            icon={LineChart}
            title={t('auth.showcase.carbon.title')}
            text={t('auth.showcase.carbon.body')}
          />
        </div>
      </div>

      <div className="relative z-10 grid grid-cols-3 gap-6 border-t border-white/10 pt-6 [@media(max-height:820px)]:hidden">
        <Metric
          value={t('auth.showcase.metrics.punctuality.value')}
          label={t('auth.showcase.metrics.punctuality.label')}
        />
        <Metric
          value={t('auth.showcase.metrics.fleets.value')}
          label={t('auth.showcase.metrics.fleets.label')}
        />
        <Metric
          value={t('auth.showcase.metrics.emissions.value')}
          label={t('auth.showcase.metrics.emissions.label')}
        />
      </div>
    </section>
  )
}
