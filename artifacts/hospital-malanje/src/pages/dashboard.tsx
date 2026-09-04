import { ArrowRight, CalendarClock, CheckCircle2, Clock3, Plus, RefreshCw, Stethoscope, UsersRound } from 'lucide-react';
import { Link, useLocation } from 'wouter';
import { getGetDashboardSummaryQueryKey, getListActivityQueryKey, useGetDashboardSummary, useListActivity } from '@workspace/api-client-react';
import { Button, EmptyState, ErrorState, LoadingRows, MetricCard, PageHeading, StatusBadge, statusLabels } from '@/components/hospital-ui';

function formatTime(value?: string | null) {
  if (!value) return '—';
  return value.slice(0, 5);
}

function formatDate(date?: string | null) {
  if (!date) return 'Hoje';
  const parsed = date.includes('T') ? new Date(date) : new Date(`${date}T12:00:00`);
  if (Number.isNaN(parsed.getTime())) return 'Data indisponível';
  return new Intl.DateTimeFormat('pt-PT', { day: '2-digit', month: 'short' }).format(parsed);
}

export default function Dashboard() {
  const [, setLocation] = useLocation();
  const summaryQuery = useGetDashboardSummary({ query: { queryKey: getGetDashboardSummaryQueryKey(), staleTime: 30_000 } });
  const activityQuery = useListActivity({ limit: 6 }, { query: { queryKey: getListActivityQueryKey({ limit: 6 }), staleTime: 30_000 } });
  const summary = summaryQuery.data;
  const activity = activityQuery.data ?? [];
  const completion = summary && summary.totalAppointments ? Math.round((summary.completedAppointments / summary.totalAppointments) * 100) : 0;
  const dateLabel = new Intl.DateTimeFormat('pt-PT', { weekday: 'long', day: 'numeric', month: 'long' }).format(new Date());

  return <div className="animate-enter">
    <PageHeading eyebrow={dateLabel} title="Bom dia, Ana." description="Aqui está o pulso operacional do Hospital de Malanje para hoje.">
      <Button testId="button-new-appointment" onClick={() => setLocation('/consultas?novo=1')}><Plus size={16} /> Nova consulta</Button>
    </PageHeading>
    {summaryQuery.isLoading ? <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"><LoadingRows rows={4} /></div> : summaryQuery.isError ? <div className="rounded-[14px] border border-border bg-card"><ErrorState onRetry={() => summaryQuery.refetch()} /></div> : summary ? <>
      <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <MetricCard label="Consultas de hoje" value={summary.totalAppointments} detail={`${completion}% concluídas`} icon={CalendarClock} tone="teal" />
        <MetricCard label="A aguardar" value={summary.waitingPatients} detail={summary.waitingPatients === 1 ? 'paciente na fila' : 'pacientes na fila'} icon={UsersRound} tone="amber" />
        <MetricCard label="Em atendimento" value={summary.inProgressAppointments} detail="salas em consulta" icon={Stethoscope} tone="blue" />
        <MetricCard label="Espera média" value={`${summary.averageWaitMinutes ?? 0} min`} detail={`${summary.cancelledAppointments} canceladas hoje`} icon={Clock3} tone="coral" />
      </section>
      <section className="mt-5 grid gap-5 xl:grid-cols-[1.35fr_0.9fr]">
        <div className="rounded-[14px] border border-border bg-card shadow-soft">
          <div className="flex items-center justify-between border-b border-border px-5 py-4"><div><h2 className="text-sm font-bold">Percurso de hoje</h2><p className="mt-0.5 text-xs text-muted-foreground">Distribuição por departamento</p></div><Link href="/relatorios" data-testid="link-view-reports" className="flex items-center gap-1 text-xs font-semibold text-primary hover:underline">Ver relatório <ArrowRight size={14} /></Link></div>
          <div className="space-y-4 px-5 py-5">{summary.departments.length ? summary.departments.map((dept, index) => { const percent = dept.total ? Math.round((dept.completed / dept.total) * 100) : 0; return <div key={dept.departmentId} data-testid={`department-summary-${dept.departmentId}`} className="group"><div className="mb-1.5 flex items-center justify-between gap-3 text-xs"><span className="font-semibold">{dept.departmentName}</span><span className="font-mono-ui text-[10px] text-muted-foreground">{dept.completed}/{dept.total} concluídas</span></div><div className="flex h-2 overflow-hidden rounded-full bg-muted"><div className={`rounded-full transition-all duration-700 ${index % 3 === 0 ? 'bg-primary' : index % 3 === 1 ? 'bg-[#d88968]' : 'bg-[#6e9ead]'}`} style={{ width: `${percent}%` }} /></div><p className="mt-1.5 text-[10px] text-muted-foreground">{dept.waiting} na fila · {percent}% do percurso encerrado</p></div>; }) : <EmptyState title="Sem departamentos activos" description="Os departamentos com consultas aparecerão aqui." icon={Stethoscope} />}</div>
        </div>
        <div className="rounded-[14px] border border-border bg-card shadow-soft">
          <div className="flex items-center justify-between border-b border-border px-5 py-4"><div><h2 className="text-sm font-bold">Próxima consulta</h2><p className="mt-0.5 text-xs text-muted-foreground">O próximo momento da agenda</p></div><CalendarClock size={17} className="text-primary" /></div>
          {summary.nextAppointment ? <div className="p-5"><div className="flex items-start justify-between"><div><p className="font-mono-ui text-3xl font-bold tracking-[-0.06em] text-primary">{formatTime(summary.nextAppointment.time)}</p><p className="mt-1 text-xs text-muted-foreground">{formatDate(summary.nextAppointment.date)} · {summary.nextAppointment.departmentName}</p></div><StatusBadge status={summary.nextAppointment.status} /></div><div className="mt-6 border-t border-border pt-4"><p className="text-sm font-bold">{summary.nextAppointment.patientName}</p><p className="mt-1 text-xs text-muted-foreground">{summary.nextAppointment.medicalRecordNumber} · {summary.nextAppointment.doctorName}</p><Link href="/consultas" data-testid="link-next-appointment" className="mt-4 flex h-9 items-center justify-center gap-2 rounded-lg border border-border text-xs font-semibold transition hover:bg-muted">Abrir agenda <ArrowRight size={14} /></Link></div></div> : <EmptyState title="Agenda livre por agora" description="Não há uma próxima consulta a destacar. A agenda está actualizada." icon={CalendarClock} />}
        </div>
      </section>
      <section className="mt-5 rounded-[14px] border border-border bg-card shadow-soft">
        <div className="flex items-center justify-between border-b border-border px-5 py-4"><div><h2 className="text-sm font-bold">Actividade recente</h2><p className="mt-0.5 text-xs text-muted-foreground">Registo de alterações na central</p></div><RefreshCw size={16} className={`text-muted-foreground ${activityQuery.isFetching ? 'animate-spin' : ''}`} /></div>
        {activityQuery.isLoading ? <LoadingRows rows={4} /> : activity.length ? <div className="divide-y divide-border">{activity.map(item => <div key={item.id} data-testid={`activity-item-${item.id}`} className="flex items-center gap-3 px-5 py-3.5"><div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-secondary text-primary"><CheckCircle2 size={15} /></div><div className="min-w-0 flex-1"><p className="truncate text-xs font-medium">{item.message}</p><p className="mt-0.5 text-[11px] text-muted-foreground">{item.actor ?? 'Sistema'} · {new Intl.DateTimeFormat('pt-PT', { hour: '2-digit', minute: '2-digit' }).format(new Date(item.timestamp))}</p></div><span className="hidden rounded-full bg-muted px-2 py-1 text-[10px] font-medium text-muted-foreground sm:inline">{statusLabels[item.type] ?? 'Registo'}</span></div>)}</div> : <EmptyState title="Sem actividade recente" description="Novos registos de consultas e pacientes aparecerão nesta linha do tempo." icon={RefreshCw} />}
      </section>
    </> : null}
  </div>;
}