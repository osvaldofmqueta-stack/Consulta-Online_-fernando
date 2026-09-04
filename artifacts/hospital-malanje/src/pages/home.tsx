import { ArrowRight, FileHeart, LockKeyhole, MessageCircle, ShieldCheck } from "lucide-react";
import { Link } from "wouter";

const highlights = [
  { icon: LockKeyhole, title: "Área privada", text: "Os seus dados clínicos ficam disponíveis apenas para a sua conta." },
  { icon: FileHeart, title: "Histórico organizado", text: "Consulte as suas consultas e orientações num só lugar." },
  { icon: MessageCircle, title: "Acompanhamento", text: "Prepare a comunicação segura com a equipa que o acompanha." },
];

export default function Home() {
  return (
    <main className="app-noise min-h-[100dvh] bg-background">
      <header className="mx-auto flex max-w-6xl items-center justify-between px-5 py-6 sm:px-8">
        <div className="flex items-center gap-3">
          <div className="flex size-10 items-center justify-center rounded-[12px] bg-primary text-primary-foreground shadow-soft"><ShieldCheck size={21} /></div>
          <div>
            <p className="text-sm font-bold tracking-[-0.02em]">Hospital de Malanje</p>
            <p className="font-mono-ui text-[9px] uppercase tracking-[0.14em] text-muted-foreground">Portal de consultas</p>
          </div>
        </div>
        <Link href="/sign-in" className="rounded-lg border border-border bg-card px-4 py-2 text-sm font-semibold text-foreground transition hover:border-primary hover:text-primary">Entrar</Link>
      </header>

      <section className="mx-auto grid max-w-6xl gap-10 px-5 pb-16 pt-12 sm:px-8 sm:pt-20 lg:grid-cols-[1.1fr_.9fr] lg:items-center">
        <div className="animate-enter">
          <p className="mb-4 font-mono-ui text-[10px] font-bold uppercase tracking-[0.2em] text-primary">Cuidado mais próximo, informação mais clara</p>
          <h1 className="max-w-2xl text-4xl font-bold leading-[1.05] tracking-[-0.045em] text-foreground sm:text-6xl">A sua saúde, acompanhada com confiança.</h1>
          <p className="mt-6 max-w-xl text-base leading-7 text-muted-foreground sm:text-lg">Consulte marcações, histórico de atendimentos e comunique-se com a equipa do Hospital de Malanje numa área segura.</p>
          <div className="mt-8 flex flex-col gap-3 sm:flex-row">
            <Link href="/sign-up" className="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-5 py-3 text-sm font-bold text-primary-foreground transition hover:opacity-90">Criar conta <ArrowRight size={16} /></Link>
            <Link href="/sign-in" className="inline-flex items-center justify-center rounded-lg border border-border bg-card px-5 py-3 text-sm font-bold text-foreground transition hover:border-primary hover:text-primary">Já tenho conta</Link>
          </div>
          <p className="mt-5 text-xs text-muted-foreground">A conta é pessoal e protege o acesso ao seu histórico.</p>
        </div>
        <div className="relative animate-enter-delay">
          <div className="absolute -inset-5 rounded-[32px] bg-primary/10 blur-2xl" />
          <div className="relative rounded-[24px] border border-border bg-card p-5 shadow-soft sm:p-7">
            <div className="flex items-center justify-between border-b border-border pb-5">
              <div><p className="font-mono-ui text-[9px] uppercase tracking-[0.16em] text-muted-foreground">O seu portal</p><p className="mt-1 text-lg font-bold">Tudo num só lugar</p></div>
              <div className="flex size-10 items-center justify-center rounded-full bg-secondary text-primary"><FileHeart size={19} /></div>
            </div>
            <div className="space-y-3 pt-5">
              {["Próxima consulta", "Histórico clínico", "Documentos e receitas"].map((item, index) => (
                <div key={item} className="flex items-center gap-3 rounded-xl border border-border/80 bg-background/70 p-4">
                  <span className="flex size-7 items-center justify-center rounded-full bg-primary/10 font-mono-ui text-[10px] font-bold text-primary">0{index + 1}</span>
                  <span className="text-sm font-semibold">{item}</span>
                  <span className="ml-auto text-muted-foreground">›</span>
                </div>
              ))}
            </div>
            <div className="mt-5 rounded-xl bg-secondary/70 p-4"><p className="text-xs font-semibold text-secondary-foreground">Acesso protegido</p><p className="mt-1 text-xs leading-5 text-muted-foreground">Apenas você e os profissionais autorizados podem consultar os dados.</p></div>
          </div>
        </div>
      </section>

      <section className="mx-auto grid max-w-6xl gap-3 px-5 pb-12 sm:grid-cols-3 sm:px-8">
        {highlights.map(({ icon: Icon, title, text }) => <div key={title} className="rounded-xl border border-border bg-card/70 p-5"><Icon size={18} className="text-primary" /><p className="mt-4 text-sm font-bold">{title}</p><p className="mt-2 text-xs leading-5 text-muted-foreground">{text}</p></div>)}
      </section>
      <footer className="mx-auto max-w-6xl border-t border-border px-5 py-6 text-xs text-muted-foreground sm:px-8">Hospital de Malanje · Central de consultas</footer>
    </main>
  );
}