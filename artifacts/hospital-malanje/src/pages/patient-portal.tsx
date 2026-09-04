import { useState } from "react";
import { CalendarDays, ClipboardList, FileText, Link2, LogOut, MessageCircle, Send, ShieldCheck, UserRound } from "lucide-react";
import { useClerk, useUser } from "@clerk/react";
import { getGetPatientPortalQueryKey, getListPatientMessagesQueryKey, useGetAuthMe, useGetPatientPortal, useLinkPatient, useListPatientMessages, useSendPatientMessage } from "@workspace/api-client-react";
import { useQueryClient } from "@tanstack/react-query";
import { Link } from "wouter";

function formatDate(value: string) {
  return new Intl.DateTimeFormat("pt-PT", { day: "numeric", month: "long", year: "numeric" }).format(new Date(`${value}T12:00:00`));
}

export default function PatientPortal() {
  const { user } = useUser();
  const { signOut } = useClerk();
  const queryClient = useQueryClient();
  const portal = useGetPatientPortal();
  const profile = useGetAuthMe();
  const messages = useListPatientMessages({ query: { queryKey: getListPatientMessagesQueryKey(), enabled: Boolean(portal.data?.patient) } });
  const linkPatient = useLinkPatient();
  const sendMessage = useSendPatientMessage();
  const [medicalRecordNumber, setMedicalRecordNumber] = useState("");
  const [phone, setPhone] = useState("");
  const [message, setMessage] = useState("");
  const [formError, setFormError] = useState("");
  const patient = portal.data?.patient;
  const appointments = portal.data?.appointments ?? [];
  const displayName = user?.firstName || portal.data?.profile.displayName || "Utilizador";

  function submitLink(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setFormError("");
    linkPatient.mutate({ data: { medicalRecordNumber, phone } }, {
      onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: getGetPatientPortalQueryKey() });
        queryClient.invalidateQueries({ queryKey: ["/api/auth/me"] });
        setMedicalRecordNumber("");
        setPhone("");
      },
      onError: (error) => setFormError(error instanceof Error ? error.message : "Não foi possível ligar o registo."),
    });
  }

  function submitMessage(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const body = message.trim();
    if (!body) return;
    sendMessage.mutate({ data: { body } }, {
      onSuccess: () => {
        setMessage("");
        queryClient.invalidateQueries({ queryKey: getListPatientMessagesQueryKey() });
      },
    });
  }

  if (portal.isLoading || profile.isLoading) return <div className="flex min-h-[100dvh] items-center justify-center bg-background text-sm text-muted-foreground">A carregar o seu portal…</div>;

  return (
    <main className="app-noise min-h-[100dvh] bg-background">
      <header className="border-b border-border bg-card/80 backdrop-blur-sm">
        <div className="mx-auto flex max-w-6xl items-center justify-between px-5 py-5 sm:px-8">
          <div className="flex items-center gap-3"><div className="flex size-9 items-center justify-center rounded-[11px] bg-primary text-primary-foreground"><ShieldCheck size={18} /></div><div><p className="text-sm font-bold">Hospital de Malanje</p><p className="font-mono-ui text-[9px] uppercase tracking-[0.14em] text-muted-foreground">Área do paciente</p></div></div>
          <div className="flex items-center gap-3"><span className="hidden text-xs text-muted-foreground sm:block">Olá, {displayName}</span><button onClick={() => signOut({ redirectUrl: import.meta.env.BASE_URL || "/" })} className="inline-flex items-center gap-2 rounded-lg border border-border bg-background px-3 py-2 text-xs font-semibold text-muted-foreground hover:text-foreground"><LogOut size={14} /> Sair</button></div>
        </div>
      </header>
      <div className="mx-auto max-w-6xl px-5 py-8 sm:px-8 sm:py-10">
        <div className="mb-8"><p className="font-mono-ui text-[10px] uppercase tracking-[0.18em] text-primary">Portal privado</p><h1 className="mt-2 text-3xl font-bold tracking-[-0.04em] sm:text-4xl">O seu acompanhamento</h1><p className="mt-2 text-sm text-muted-foreground">Consulte os seus dados e mantenha o contacto com a equipa de saúde.</p></div>
        {!patient ? (
          <section className="grid gap-5 lg:grid-cols-[1fr_.9fr]">
            <div className="rounded-2xl border border-border bg-card p-6 shadow-soft sm:p-8"><div className="flex size-11 items-center justify-center rounded-xl bg-secondary text-primary"><Link2 size={21} /></div><h2 className="mt-5 text-xl font-bold">Ligue o seu registo</h2><p className="mt-2 max-w-lg text-sm leading-6 text-muted-foreground">Para ver as suas consultas, confirme o número do processo e o telefone que estão registados no hospital.</p><form onSubmit={submitLink} className="mt-6 space-y-4"><label className="block text-xs font-bold uppercase tracking-[0.08em] text-muted-foreground">Número do processo<input value={medicalRecordNumber} onChange={(event) => setMedicalRecordNumber(event.target.value)} placeholder="Ex.: HM-2026-123456" required className="mt-2 w-full rounded-lg border border-input bg-background px-3 py-3 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/15" /></label><label className="block text-xs font-bold uppercase tracking-[0.08em] text-muted-foreground">Telefone registado<input value={phone} onChange={(event) => setPhone(event.target.value)} placeholder="Ex.: 923 000 000" required className="mt-2 w-full rounded-lg border border-input bg-background px-3 py-3 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/15" /></label>{formError && <p className="rounded-lg bg-destructive/10 px-3 py-2 text-xs text-destructive">{formError}</p>}<button disabled={linkPatient.isPending} className="w-full rounded-lg bg-primary px-4 py-3 text-sm font-bold text-primary-foreground disabled:opacity-60">{linkPatient.isPending ? "A confirmar…" : "Ligar o meu registo"}</button></form></div>
            <div className="rounded-2xl border border-border bg-secondary/45 p-6 sm:p-8"><ShieldCheck className="text-primary" size={22} /><h2 className="mt-5 text-lg font-bold">Os seus dados ficam protegidos</h2><p className="mt-2 text-sm leading-6 text-muted-foreground">A ligação exige duas informações já confirmadas pelo hospital. Depois disso, apenas a sua conta terá acesso ao histórico associado.</p><div className="mt-6 space-y-3 text-xs text-muted-foreground"><p className="flex gap-2"><ClipboardList size={15} className="shrink-0 text-primary" /> Consultas e estados de atendimento</p><p className="flex gap-2"><FileText size={15} className="shrink-0 text-primary" /> Documentos clínicos, receitas e orientações</p><p className="flex gap-2"><MessageCircle size={15} className="shrink-0 text-primary" /> Comunicação com a equipa que o acompanha</p></div></div>
          </section>
        ) : (
          <>
            <section className="grid gap-4 sm:grid-cols-3"><div className="rounded-xl border border-border bg-card p-5"><UserRound size={18} className="text-primary" /><p className="mt-4 text-xs text-muted-foreground">Paciente</p><p className="mt-1 text-base font-bold">{patient.name}</p><p className="mt-1 font-mono-ui text-[10px] text-muted-foreground">{patient.medicalRecordNumber}</p></div><div className="rounded-xl border border-border bg-card p-5"><CalendarDays size={18} className="text-primary" /><p className="mt-4 text-xs text-muted-foreground">Consultas registadas</p><p className="mt-1 text-2xl font-bold">{appointments.length}</p></div><div className="rounded-xl border border-border bg-card p-5"><FileText size={18} className="text-primary" /><p className="mt-4 text-xs text-muted-foreground">Documentos</p><p className="mt-1 text-base font-bold">Em breve</p><p className="mt-1 text-xs text-muted-foreground">Área segura a preparar</p></div></section>
            <section className="mt-6 rounded-xl border border-border bg-card"><div className="flex items-center justify-between border-b border-border px-5 py-4"><div><h2 className="text-sm font-bold">As suas consultas</h2><p className="mt-1 text-xs text-muted-foreground">Histórico de marcações no Hospital de Malanje</p></div><ClipboardList size={18} className="text-muted-foreground" /></div>{appointments.length ? <div className="divide-y divide-border">{appointments.map((appointment) => <div key={appointment.id} className="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"><div><p className="text-sm font-bold">{appointment.departmentName}</p><p className="mt-1 text-xs text-muted-foreground">{formatDate(appointment.date)} às {appointment.time} · {appointment.doctorName}</p></div><span className="w-fit rounded-full bg-secondary px-3 py-1 text-[10px] font-bold uppercase tracking-[0.08em] text-secondary-foreground">{appointment.status === "completed" ? "Concluída" : appointment.status === "cancelled" ? "Cancelada" : "Agendada"}</span></div>)}</div> : <div className="px-5 py-10 text-center text-sm text-muted-foreground">Ainda não existem consultas associadas à sua conta.</div>}</section>
            <section className="mt-6 rounded-xl border border-border bg-card">
              <div className="flex items-center justify-between border-b border-border px-5 py-4"><div><h2 className="text-sm font-bold">Falar com a equipa</h2><p className="mt-1 text-xs text-muted-foreground">Envie uma mensagem segura sobre o seu acompanhamento.</p></div><MessageCircle size={18} className="text-primary" /></div>
              <div className="max-h-[360px] space-y-3 overflow-y-auto px-5 py-5 scrollbar-thin">
                {messages.isLoading ? <p className="py-6 text-center text-xs text-muted-foreground">A carregar a conversa…</p> : messages.data?.length ? messages.data.map((item) => <div key={item.id} className={`flex ${item.senderRole === "patient" ? "justify-end" : "justify-start"}`}><div className={`max-w-[85%] rounded-2xl px-4 py-3 text-sm ${item.senderRole === "patient" ? "rounded-br-sm bg-primary text-primary-foreground" : "rounded-bl-sm bg-secondary text-secondary-foreground"}`}><p className="whitespace-pre-wrap leading-5">{item.body}</p><p className={`mt-2 text-[10px] ${item.senderRole === "patient" ? "text-primary-foreground/70" : "text-muted-foreground"}`}>{new Intl.DateTimeFormat("pt-PT", { day: "numeric", month: "short", hour: "2-digit", minute: "2-digit" }).format(new Date(item.createdAt))}</p></div></div>) : <div className="py-6 text-center"><MessageCircle size={22} className="mx-auto text-muted-foreground/50" /><p className="mt-3 text-sm font-semibold">Ainda não há mensagens</p><p className="mt-1 text-xs text-muted-foreground">Escreva abaixo para iniciar o contacto com a equipa.</p></div>}
              </div>
              <form onSubmit={submitMessage} className="flex gap-2 border-t border-border p-4"><input value={message} onChange={(event) => setMessage(event.target.value)} maxLength={2000} placeholder="Escreva a sua mensagem…" className="min-w-0 flex-1 rounded-lg border border-input bg-background px-3 py-3 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/15" /><button disabled={sendMessage.isPending || !message.trim()} aria-label="Enviar mensagem" className="flex size-11 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground disabled:opacity-50"><Send size={16} /></button></form>
              {sendMessage.isError && <p className="px-5 pb-4 text-xs text-destructive">Não foi possível enviar a mensagem. Tente novamente.</p>}
            </section>
            <section className="mt-6 grid gap-4 sm:grid-cols-2"><div className="rounded-xl border border-dashed border-border bg-card/60 p-5"><FileText size={18} className="text-primary" /><p className="mt-4 text-sm font-bold">Documentos e receitas</p><p className="mt-1 text-xs leading-5 text-muted-foreground">A equipa poderá disponibilizar aqui receitas, resultados e outros documentos da sua consulta.</p></div><div className="rounded-xl border border-dashed border-border bg-card/60 p-5"><ShieldCheck size={18} className="text-primary" /><p className="mt-4 text-sm font-bold">Contacto protegido</p><p className="mt-1 text-xs leading-5 text-muted-foreground">As suas mensagens ficam associadas ao registo clínico e visíveis apenas aos profissionais autorizados.</p></div></section>
          </>
        )}
        {profile.data?.role !== "patient" && <Link href="/" className="mt-8 inline-flex items-center gap-2 text-xs font-bold text-primary hover:underline">Abrir consola operacional →</Link>}
      </div>
    </main>
  );
}