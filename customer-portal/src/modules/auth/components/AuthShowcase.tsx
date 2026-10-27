import { Boxes, CalendarDays, MapPin, PackageCheck, ShieldCheck } from 'lucide-react'

function Feature({ icon: Icon, title, text }: { icon: typeof MapPin; title: string; text: string }) {
  return (
    <div className="rounded-2xl border border-white/15 bg-white/8 p-4 backdrop-blur-md">
      <Icon className="text-sidebar-primary mb-2 size-5" aria-hidden />
      <h3 className="text-sm font-bold">{title}</h3>
      <p className="text-sidebar-foreground/70 mt-1 text-xs">{text}</p>
    </div>
  )
}

/**
 * Panneau de marque de la connexion — même facture que celui du back-office
 * (jetons `sidebar`, halos, trame), avec un discours tourné vers le client.
 *
 * Pas de chiffres-clés : ils seraient inventés. Le panneau n'apparaît qu'à
 * partir de `lg` et se dépouille de ses tuiles quand la fenêtre raccourcit.
 */
export function AuthShowcase() {
  return (
    <section className="bg-sidebar text-sidebar-foreground relative hidden flex-1 flex-col justify-between overflow-hidden p-10 lg:flex xl:p-16">
      <div
        className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_80%_20%,color-mix(in_oklab,var(--sidebar-primary)_35%,transparent)_0%,transparent_50%),radial-gradient(circle_at_20%_80%,color-mix(in_oklab,var(--sidebar-primary)_30%,transparent)_0%,transparent_60%)]"
        aria-hidden
      />
      <div
        className="pointer-events-none absolute inset-0 bg-[radial-gradient(#ffffff_1px,transparent_1px)] [background-size:24px_24px] opacity-10"
        aria-hidden
      />

      <div className="relative z-10">
        <span className="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-medium tracking-wide uppercase backdrop-blur-md">
          <ShieldCheck className="size-3.5" aria-hidden />
          Espace client sécurisé
        </span>
      </div>

      <div className="relative z-10 my-auto max-w-xl py-8">
        <div className="border-sidebar-primary/30 bg-sidebar-primary/20 mb-6 inline-flex items-center gap-2 rounded-full border px-3.5 py-1.5 text-sm font-semibold">
          <PackageCheck className="text-success size-4" aria-hidden />
          Vos livraisons, en un seul endroit
        </div>

        <h2 className="mb-5 text-3xl leading-tight font-extrabold [@media(min-height:900px)]:text-4xl">
          Suivez vos commandes avec votre transporteur, en temps réel.
        </h2>

        <p className="text-sidebar-foreground/90 mb-8 text-base leading-relaxed">
          Commandes, rendez-vous, suivi des livraisons et stock : votre équipe partage le même espace, chacun avec les
          droits que votre administrateur lui a donnés.
        </p>

        <div className="grid grid-cols-3 gap-4 [@media(max-height:680px)]:hidden">
          <Feature icon={MapPin} title="Suivi" text="L’état de chaque livraison, étape par étape." />
          <Feature icon={CalendarDays} title="Calendrier" text="Vos rendez-vous de livraison planifiés." />
          <Feature icon={Boxes} title="Stock" text="Vos articles et leurs mouvements." />
        </div>
      </div>

      <p className="text-sidebar-foreground/60 relative z-10 border-t border-white/10 pt-6 text-xs [@media(max-height:820px)]:hidden">
        Vos données ne sont visibles que par les membres de votre entreprise.
      </p>
    </section>
  )
}
